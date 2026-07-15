<?php
/**
 * System status module — read-only monitoring of the OpenSearch cluster and the
 * MySQL databases, plus storage usage, for the admin "Settings" page.
 *
 * Backed entirely by facilities the platform already has:
 *   - getApiOpenSearchClient()      (api/v1/utilities.php) for cluster/node/index stats
 *   - getApiDatabaseConnection()    (api/v1/utilities.php) for MySQL status/size
 *   - disk_total_space()/disk_free_space() for the app filesystem
 *
 * Each backend is queried inside its own try/catch so one dead service degrades
 * to an "unavailable" section instead of blanking the whole panel (mirrors the
 * defensive pattern in modules/utilities/health.php).
 *
 * Two non-privileged actions live here too:
 *   - systemClearCaches(): clears OpenSearch fielddata/query/request caches +
 *     flush, for instant (transient) heap relief.
 *   - systemStorageScan(): recomputes the size of the platform data/ directory
 *     (slow on large repos) and caches it, so systemStatusGet() never has to.
 *
 * The heavier "Optimize indices" lever is NOT here — the UI reuses the existing
 * action=index&itemType=optimize endpoint (api/v1/modules/searchIndex.php).
 *
 * All actions are admin-only: the "system" action is not in the apiV1 whitelist
 * in modules/utilities/auth.php, so auth() only lets admins through.
 */

require_once(__DIR__ . '/../../../vendor/autoload.php');
require_once(__DIR__ . '/../utilities.php');

/**
 * Absolute path to the platform data/ directory.
 */
function systemDataDirPath() {
    $path = realpath(__DIR__ . '/../../../data');
    return $path !== false ? $path : (__DIR__ . '/../../../data');
}

/**
 * Path to the cached data/ directory size (written by systemStorageScan()).
 */
function systemDataDirSizeCachePath() {
    return __DIR__ . '/../cache/dataDirSize.json';
}

/**
 * Build the list of OpenSearch indices this instance owns: the main speech
 * index plus the statistics index for every configured parliament. Computed
 * inline (same shape as performIndexOptimization) to avoid pulling in the whole
 * search stack just for two string builders.
 *
 * @return string[]
 */
function systemOwnedIndices() {
    global $config;
    $indices = [];
    foreach (($config["parliament"] ?? []) as $key => $parliamentConfig) {
        $suffix = $parliamentConfig["OpenSearch"]["index"] ?? strtolower($key);
        $indices[] = "openparliamenttv_" . $suffix;
        $indices[] = "optv_statistics_" . $suffix;
    }
    return array_values(array_unique($indices));
}

/**
 * Resolve a usable OpenSearch client or null (logging the init error).
 *
 * @return \OpenSearch\Client|null
 */
function systemOpenSearchClientOrNull() {
    $client = getApiOpenSearchClient();
    if (!$client || (is_array($client) && isset($client["errors"]))) {
        error_log("systemStatus: OpenSearch client unavailable");
        return null;
    }
    return $client;
}

/**
 * Highest JVM heap-used percentage across all nodes (worst-case pressure), or
 * null if it can't be read.
 *
 * @param \OpenSearch\Client $client
 * @return float|null
 */
function systemMaxHeapPercent($client) {
    try {
        $stats = $client->nodes()->stats(['metric' => 'jvm']);
        $max = null;
        foreach (($stats["nodes"] ?? []) as $node) {
            $pct = $node["jvm"]["mem"]["heap_used_percent"] ?? null;
            if ($pct !== null && ($max === null || $pct > $max)) {
                $max = (float)$pct;
            }
        }
        return $max;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * GET action: aggregate OpenSearch + databases + storage into one payload.
 */
function systemStatusGet($api_request) {
    $data = [
        "openSearch" => systemStatusOpenSearch(),
        "databases"  => systemStatusDatabases(),
    ];
    // Storage reuses figures already gathered above (DB + OpenSearch totals).
    $data["storage"] = systemStatusStorage($data["openSearch"], $data["databases"]);

    return createApiSuccessResponse($data);
}

/**
 * OpenSearch section: cluster health, per-node heap/CPU/fs, and per-index docs/size.
 */
function systemStatusOpenSearch() {
    $client = systemOpenSearchClientOrNull();
    if ($client === null) {
        return ["available" => false, "error" => "OpenSearch client unavailable"];
    }

    $out = ["available" => true];

    // Cluster health
    try {
        $h = $client->cluster()->health();
        $out["cluster"] = [
            "status"              => $h["status"] ?? null,
            "nodes"               => $h["number_of_nodes"] ?? null,
            "dataNodes"           => $h["number_of_data_nodes"] ?? null,
            "activeShards"        => $h["active_shards"] ?? null,
            "relocatingShards"    => $h["relocating_shards"] ?? null,
            "initializingShards"  => $h["initializing_shards"] ?? null,
            "unassignedShards"    => $h["unassigned_shards"] ?? null,
            "activeShardsPercent" => $h["active_shards_percent_as_number"] ?? null,
        ];
    } catch (Throwable $e) {
        $out["available"] = false;
        $out["error"] = $e->getMessage();
        return $out;
    }

    // Per-node stats
    $out["nodes"] = [];
    try {
        $ns = $client->nodes()->stats(['metric' => 'jvm,os,process,fs']);
        foreach (($ns["nodes"] ?? []) as $node) {
            $out["nodes"][] = [
                "name"            => $node["name"] ?? null,
                "heapUsedPercent" => $node["jvm"]["mem"]["heap_used_percent"] ?? null,
                "heapUsedBytes"   => $node["jvm"]["mem"]["heap_used_in_bytes"] ?? null,
                "heapMaxBytes"    => $node["jvm"]["mem"]["heap_max_in_bytes"] ?? null,
                "cpuPercent"      => $node["os"]["cpu"]["percent"] ?? ($node["process"]["cpu"]["percent"] ?? null),
                "fsTotalBytes"    => $node["fs"]["total"]["total_in_bytes"] ?? null,
                "fsAvailableBytes"=> $node["fs"]["total"]["available_in_bytes"] ?? null,
            ];
        }
    } catch (Throwable $e) {
        error_log("systemStatus: node stats failed: " . $e->getMessage());
    }

    // Per-index docs + store size (only this instance's indices). Filter to
    // indices that actually exist first — indices()->stats() has no
    // ignore_unavailable option in this client and throws on a missing index.
    $out["indices"] = [];
    $out["totalStoreBytes"] = 0;
    try {
        $existing = [];
        foreach (systemOwnedIndices() as $name) {
            try {
                if ($client->indices()->exists(['index' => $name])) {
                    $existing[] = $name;
                }
            } catch (Throwable $e) {
                // skip an index we can't probe
            }
        }
        if (!empty($existing)) {
            $stats = $client->indices()->stats(['index' => implode(',', $existing)]);
            foreach (($stats["indices"] ?? []) as $name => $idx) {
                $size = $idx["total"]["store"]["size_in_bytes"] ?? 0;
                $out["indices"][] = [
                    "name"      => $name,
                    "docs"      => $idx["total"]["docs"]["count"] ?? 0,
                    "deleted"   => $idx["total"]["docs"]["deleted"] ?? 0,
                    "sizeBytes" => $size,
                ];
                $out["totalStoreBytes"] += $size;
            }
            // Stable, predictable ordering
            usort($out["indices"], function ($a, $b) {
                return strcmp($a["name"], $b["name"]);
            });
        }
    } catch (Throwable $e) {
        error_log("systemStatus: index stats failed: " . $e->getMessage());
    }

    return $out;
}

/**
 * Databases section: MySQL server info (deduped by host) + per-schema size.
 *
 * Covers the platform database and every configured parliament database. If two
 * of them live on the same MySQL host, server-global figures (version, uptime,
 * connections) are reported once for that host.
 */
function systemStatusDatabases() {
    global $config;

    $targets = [];
    // Platform DB
    $targets[] = [
        "label"    => "Platform",
        "host"     => $config["platform"]["sql"]["access"]["host"] ?? "",
        "database" => $config["platform"]["sql"]["db"] ?? "",
        "type"     => "platform",
        "code"     => null,
    ];
    // Parliament DBs
    foreach (($config["parliament"] ?? []) as $code => $parliamentConfig) {
        $targets[] = [
            "label"    => ($parliamentConfig["label"] ?? $code) ?: $code,
            "host"     => $parliamentConfig["sql"]["access"]["host"] ?? "",
            "database" => $parliamentConfig["sql"]["db"] ?? "",
            "type"     => "parliament",
            "code"     => $code,
        ];
    }

    $servers = [];   // host => server info
    $schemas = [];

    foreach ($targets as $t) {
        $db = ($t["type"] === "platform")
            ? getApiDatabaseConnection('platform')
            : getApiDatabaseConnection('parliament', $t["code"]);

        if (is_array($db) || !($db instanceof SafeMySQL)) {
            $schemas[] = [
                "label"     => $t["label"],
                "database"  => $t["database"],
                "host"      => $t["host"],
                "sizeBytes" => null,
                "tables"    => null,
                "reachable" => false,
            ];
            continue;
        }

        // Server-global info once per host
        if (!isset($servers[$t["host"]])) {
            try {
                $uptimeRow  = $db->getRow("SHOW GLOBAL STATUS LIKE ?s", "Uptime");
                $threadsRow = $db->getRow("SHOW GLOBAL STATUS LIKE ?s", "Threads_connected");
                $servers[$t["host"]] = [
                    "host"             => $t["host"],
                    "version"          => $db->getOne("SELECT VERSION()"),
                    "uptimeSeconds"    => isset($uptimeRow["Value"]) ? (int)$uptimeRow["Value"] : null,
                    "threadsConnected" => isset($threadsRow["Value"]) ? (int)$threadsRow["Value"] : null,
                ];
            } catch (Throwable $e) {
                error_log("systemStatus: server info failed for {$t['host']}: " . $e->getMessage());
                $servers[$t["host"]] = [
                    "host" => $t["host"], "version" => null,
                    "uptimeSeconds" => null, "threadsConnected" => null,
                ];
            }
        }

        // Per-schema size + table count
        try {
            $sizeRow = $db->getRow(
                "SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes, COUNT(*) AS tables
                 FROM information_schema.tables WHERE table_schema = ?s",
                $t["database"]
            );
            $schemas[] = [
                "label"     => $t["label"],
                "database"  => $t["database"],
                "host"      => $t["host"],
                "sizeBytes" => isset($sizeRow["bytes"]) ? (int)$sizeRow["bytes"] : null,
                "tables"    => isset($sizeRow["tables"]) ? (int)$sizeRow["tables"] : null,
                "reachable" => true,
            ];
        } catch (Throwable $e) {
            error_log("systemStatus: schema size failed for {$t['database']}: " . $e->getMessage());
            $schemas[] = [
                "label"     => $t["label"],
                "database"  => $t["database"],
                "host"      => $t["host"],
                "sizeBytes" => null,
                "tables"    => null,
                "reachable" => true,
            ];
        }
    }

    return [
        "servers" => array_values($servers),
        "schemas" => $schemas,
    ];
}

/**
 * Storage section: app filesystem usage, cached data/ dir size, and totals
 * carried over from the OpenSearch and databases sections.
 */
function systemStatusStorage($openSearch, $databases) {
    $dataDir = systemDataDirPath();

    // App filesystem (the volume data/ lives on)
    $filesystem = ["path" => $dataDir, "totalBytes" => null, "freeBytes" => null, "usedBytes" => null, "usedPercent" => null];
    $total = @disk_total_space($dataDir);
    $free  = @disk_free_space($dataDir);
    if ($total !== false && $free !== false && $total > 0) {
        $used = $total - $free;
        $filesystem = [
            "path"        => $dataDir,
            "totalBytes"  => (int)$total,
            "freeBytes"   => (int)$free,
            "usedBytes"   => (int)$used,
            "usedPercent" => round($used / $total * 100, 1),
        ];
    }

    // Cached data/ dir size (never computed here — see systemStorageScan())
    $dataDirInfo = ["path" => $dataDir, "sizeBytes" => null, "computedAt" => null];
    $cachePath = systemDataDirSizeCachePath();
    if (is_file($cachePath)) {
        $cached = json_decode(@file_get_contents($cachePath), true);
        if (is_array($cached) && isset($cached["sizeBytes"])) {
            $dataDirInfo["sizeBytes"]  = (int)$cached["sizeBytes"];
            $dataDirInfo["computedAt"] = $cached["computedAt"] ?? null;
        }
    }

    // Databases total (sum of reachable schema sizes)
    $dbTotal = 0;
    foreach (($databases["schemas"] ?? []) as $s) {
        if (is_numeric($s["sizeBytes"] ?? null)) {
            $dbTotal += (int)$s["sizeBytes"];
        }
    }

    // OpenSearch cluster filesystem — first data node is representative (nodes
    // on the live host share one disk; summing would double-count).
    $osFs = ["totalBytes" => null, "availableBytes" => null];
    if (!empty($openSearch["nodes"][0])) {
        $osFs["totalBytes"]     = $openSearch["nodes"][0]["fsTotalBytes"] ?? null;
        $osFs["availableBytes"] = $openSearch["nodes"][0]["fsAvailableBytes"] ?? null;
    }

    return [
        "filesystem"          => $filesystem,
        "dataDir"             => $dataDirInfo,
        "databasesTotalBytes" => $dbTotal,
        "openSearchTotalBytes"=> $openSearch["totalStoreBytes"] ?? null,
        "openSearchFs"        => $osFs,
    ];
}

/**
 * Clear OpenSearch caches (fielddata/query/request) + flush on this instance's
 * indices. Instant, transient heap relief — no docker, no privileges.
 */
function systemClearCaches($api_request) {
    $client = systemOpenSearchClientOrNull();
    if ($client === null) {
        return createApiErrorResponse(503, 'OPENSEARCH_UNAVAILABLE', 'messageErrorOpenSearchClient', 'OpenSearch client unavailable');
    }

    $indices = systemOwnedIndices();
    if (empty($indices)) {
        return createApiErrorResponse(422, 'NO_INDICES', 'messageErrorInvalidParameter', 'No configured indices to clear');
    }
    $indexList = implode(',', $indices);

    $heapBefore = systemMaxHeapPercent($client);

    try {
        $client->indices()->clearCache([
            'index'              => $indexList,
            'fielddata'          => 'true',
            'query'              => 'true',
            'request'            => 'true',
            'ignore_unavailable' => 'true',
        ]);
        $client->indices()->flush([
            'index'              => $indexList,
            'ignore_unavailable' => 'true',
        ]);
    } catch (Throwable $e) {
        error_log("systemStatus: clearCaches failed: " . $e->getMessage());
        return createApiErrorResponse(500, 'CLEAR_CACHE_FAILED', 'messageErrorOpenSearchClient', $e->getMessage());
    }

    $heapAfter = systemMaxHeapPercent($client);

    return createApiSuccessResponse([
        "indices"           => $indices,
        "flushed"           => true,
        "heapBeforePercent" => $heapBefore,
        "heapAfterPercent"  => $heapAfter,
    ]);
}

/**
 * Recompute the size of the platform data/ directory and cache it. Slow on large
 * parliament repos, so it is a deliberate, explicit action (never on auto-poll).
 * Uses `du` when available; falls back to a PHP walk if exec is disabled.
 */
function systemStorageScan($api_request) {
    $dataDir = systemDataDirPath();
    if (!is_dir($dataDir)) {
        return createApiErrorResponse(404, 'DATADIR_MISSING', 'messageErrorInvalidParameter', 'data directory not found');
    }

    $sizeBytes = systemMeasureDirSize($dataDir);
    if ($sizeBytes === null) {
        return createApiErrorResponse(500, 'DU_FAILED', 'messageErrorInvalidParameter', 'Could not measure directory size');
    }

    $payload = [
        "path"       => $dataDir,
        "sizeBytes"  => $sizeBytes,
        "computedAt" => date('c'),
    ];

    // Persist to cache so systemStatusGet() can surface it without recomputing.
    $cachePath = systemDataDirSizeCachePath();
    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    @file_put_contents($cachePath, json_encode($payload));

    return createApiSuccessResponse($payload);
}

/**
 * Measure a directory's size in bytes. Prefers `du -sk` (fast); falls back to a
 * recursive PHP walk when exec is unavailable/disabled. Returns null on failure.
 *
 * @return int|null
 */
function systemMeasureDirSize($dir) {
    // Prefer du when exec is available and not disabled.
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('exec') && !in_array('exec', $disabled, true)) {
        $out = [];
        $rc = null;
        @exec('du -sk ' . escapeshellarg($dir) . ' 2>/dev/null', $out, $rc);
        if ($rc === 0 && !empty($out[0])) {
            $kb = (int)strtok(trim($out[0]), "\t ");
            if ($kb > 0) {
                return $kb * 1024;
            }
        }
    }

    // PHP fallback (slower; used only if du is unavailable).
    try {
        $total = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $total += $file->getSize();
            }
        }
        return $total;
    } catch (Throwable $e) {
        error_log("systemStatus: dir size fallback failed: " . $e->getMessage());
        return null;
    }
}
