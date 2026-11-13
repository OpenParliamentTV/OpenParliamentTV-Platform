<?php
/**
 * One-time backlog migration for the conflict management overhaul.
 *
 * Parses the legacy per-occurrence conflict rows (free-text ConflictSubject /
 * ConflictDescription) and aggregates them into the grouped format: one
 * `conflict` row per unique issue (ConflictGroupKey) plus one `conflict_media`
 * row per affected media. Legacy rows are identified by ConflictGroupKey IS
 * NULL (rows written by the new code always carry a group key) and are deleted
 * after their aggregate has been flushed, so the script is resumable and
 * re-runnable at any point.
 *
 * Usage:
 *   php bin/migrate-conflicts.php --dry-run              # parse + report only, no writes
 *   php bin/migrate-conflicts.php                        # migrate
 *   php bin/migrate-conflicts.php --batch-size 5000      # rows per batch (default 5000)
 *   php bin/migrate-conflicts.php --drop-legacy-columns  # drop the legacy columns
 *                                                        # (only allowed when 0 legacy rows remain)
 *
 * Apply the migrations (php bin/migrate.php --apply, which includes
 * 0006_conflict_aggregation) and deploy the new code BEFORE running this script.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

require_once(__DIR__ . "/../bootstrap-cli.php");
require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
require_once(__DIR__ . "/../api/v1/modules/conflict.php");

$options = getopt("", ["dry-run", "batch-size:", "drop-legacy-columns"]);
$dryRun = array_key_exists("dry-run", $options);
$batchSize = max(100, (int)($options["batch-size"] ?? 5000));
$dropLegacyColumns = array_key_exists("drop-legacy-columns", $options);

$db = new SafeMySQL([
    'host' => $config["platform"]["sql"]["access"]["host"],
    'user' => $config["platform"]["sql"]["access"]["user"],
    'pass' => $config["platform"]["sql"]["access"]["passwd"],
    'db' => $config["platform"]["sql"]["db"]
]);

$tbl = $config["platform"]["sql"]["tbl"]["Conflict"];
$tblMedia = $config["platform"]["sql"]["tbl"]["ConflictMedia"];

$legacyColumns = ["ConflictEntity", "ConflictIdentifier", "ConflictRival", "ConflictSubject", "ConflictDescription", "ConflictDate", "ConflictTimestamp", "ConflictResolved"];

function migrateLog($message) {
    print(date("Y-m-d H:i:s") . " - " . $message . PHP_EOL);
}

// ---------------------------------------------------------------------
// --drop-legacy-columns mode
// ---------------------------------------------------------------------
if ($dropLegacyColumns) {
    $remaining = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE ConflictGroupKey IS NULL", $tbl);
    if ($remaining > 0) {
        migrateLog("Refusing to drop legacy columns: $remaining unmigrated rows remain. Run the migration first.");
        exit(1);
    }
    foreach ($legacyColumns as $column) {
        $db->query("ALTER TABLE ?n DROP COLUMN IF EXISTS ?n", $tbl, $column);
    }
    migrateLog("Dropped legacy columns: " . implode(", ", $legacyColumns) . ".");
    exit(0);
}

// ---------------------------------------------------------------------
// Parsing helpers
// ---------------------------------------------------------------------

/**
 * Decodes the JSON object embedded at the end of a legacy description
 * (everything from the first "{" on).
 */
function extractTrailingJson($description) {
    $pos = strpos($description, "{");
    if ($pos === false) {
        return null;
    }
    $decoded = json_decode(substr($description, $pos), true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
}

/**
 * Parses one legacy conflict row into the new structured shape.
 *
 * @return array {type, entityType, entityLabel, entityWid, entityKey, parliament, mediaKey, data}
 */
function parseLegacyConflict($row) {
    $subject = $row["ConflictSubject"];
    $description = (string)$row["ConflictDescription"];
    $identifier = trim((string)$row["ConflictIdentifier"]);

    $parliament = "";
    if ($identifier !== "") {
        $itemInfos = optvParseStringId($identifier);
        if ($itemInfos && !empty($itemInfos["parliament"])) {
            $parliament = $itemInfos["parliament"];
        }
    }

    $result = [
        "type" => null,
        "entityType" => null,
        "entityLabel" => null,
        "entityWid" => null,
        "entityKey" => null,
        "parliament" => $parliament,
        "mediaKey" => ($identifier !== "") ? $identifier : null,
        "data" => null
    ];

    switch ($subject) {
        case "mediaAdd person has no WikidataID":
            $person = extractTrailingJson($description);
            if ($person === null) { return null; }
            $result["type"] = "person-missing-wikidata-id";
            $result["entityType"] = "person";
            $result["entityLabel"] = $person["label"] ?? null;
            $result["entityKey"] = mb_strtolower(trim((string)($person["label"] ?? "")));
            $result["data"] = $person;
            return $result;

        case "faction has no WikidataID":
            $person = extractTrailingJson($description);
            if ($person === null) { return null; }
            $factionLabel = trim((string)($person["faction"]["label"] ?? ""));
            // An entirely empty faction object means the person legitimately
            // has no faction (e.g. speaking as minister) — not a conflict;
            // dropped, matching the import behavior.
            if ($factionLabel === "") {
                return ["discard" => "empty-faction"];
            }
            $result["type"] = "faction-missing-wikidata-id";
            $result["entityType"] = "organisation";
            $result["entityLabel"] = $factionLabel;
            $result["entityKey"] = mb_strtolower($factionLabel);
            $result["data"] = $person;
            return $result;

        case "mediaAdd main-proceeding-speaker found":
            // Recording the media-vs-proceedings speaker disagreement as a
            // conflict was a mistake — these rows are dropped, not migrated
            // (the new import code no longer reports them either).
            return ["discard" => "main-proceeding-speaker"];

        case "mediaAdd person without context":
            $person = extractTrailingJson($description);
            if ($person === null) { return null; }
            $wid = trim((string)($person["wid"] ?? ""));
            $result["type"] = "person-missing-context";
            $result["entityType"] = "person";
            $result["entityLabel"] = $person["label"] ?? null;
            $result["entityWid"] = ($wid !== "") ? $wid : null;
            $result["entityKey"] = ($wid !== "") ? $wid : mb_strtolower(trim((string)($person["label"] ?? "")));
            $result["data"] = $person;
            return $result;

        case "mediaAdd failed - required fields missing":
            // "Item: <json> ||| Errors: <json>"
            $parts = explode(" ||| Errors: ", $description, 2);
            $item = extractTrailingJson($parts[0]);
            $errors = isset($parts[1]) ? json_decode($parts[1], true) : null;
            $result["type"] = "media-validation-error";
            $result["entityKey"] = "";
            if (is_array($item)) {
                $result["parliament"] = $item["parliament"] ?? "";
                $originID = $item["media"]["originID"] ?? "";
                $originMediaID = $item["media"]["originMediaID"] ?? "";
                if ($originID !== "" || $originMediaID !== "") {
                    $result["mediaKey"] = "origin:" . $originID . "/" . $originMediaID;
                }
                $result["data"] = [
                    "errors" => $errors,
                    "originID" => $originID ?: null,
                    "originMediaID" => $originMediaID ?: null,
                    "agendaItemID" => $item["agendaItem"]["officialID"] ?? null
                ];
            } else {
                $result["data"] = ["message" => mb_substr($description, 0, 2000)];
            }
            return $result;

        case "mediaAdd failed":
            $result["type"] = "media-import-error";
            $result["entityKey"] = "";
            $result["data"] = ["message" => mb_substr($description, 0, 2000)];
            return $result;

        case "mediaAdd Annotation":
            $result["type"] = "annotation-import-error";
            $result["entityKey"] = "";
            $result["data"] = ["message" => mb_substr($description, 0, 2000)];
            return $result;

        case "mediaAdd TextContent Add failed":
        case "mediaAdd TextContent Update failed":
            $result["type"] = "text-import-error";
            $result["entityKey"] = "";
            $result["data"] = [
                "op" => (strpos($subject, "Update") !== false) ? "update" : "add",
                "message" => mb_substr($description, 0, 2000)
            ];
            return $result;

        case "mediaAdd document no sourceURI":
            $document = extractTrailingJson($description);
            if ($document === null) { return null; }
            $result["type"] = "document-missing-source-uri";
            $result["entityType"] = "document";
            $result["entityLabel"] = $document["label"] ?? null;
            $result["entityKey"] = mb_strtolower(trim((string)($document["label"] ?? "")));
            $result["data"] = $document;
            return $result;

        case "Suggestion had no ID":
            $reportArray = extractTrailingJson($description);
            if ($reportArray === null) { return null; }
            $context = trim((string)($reportArray["context"] ?? ""));
            $result["type"] = "entity-suggestion-missing-id";
            $result["entityType"] = mb_strtolower((string)($reportArray["type"] ?? "")) ?: null;
            $result["entityLabel"] = $reportArray["label"] ?? null;
            $result["entityKey"] = mb_strtolower(trim((string)($reportArray["label"] ?? "")));
            if ($context !== "") {
                $result["mediaKey"] = $context;
                $itemInfos = optvParseStringId($context);
                if ($itemInfos && !empty($itemInfos["parliament"])) {
                    $result["parliament"] = $itemInfos["parliament"];
                }
            }
            $result["data"] = $reportArray;
            return $result;

        default:
            return null;
    }
}

/** Fallback for unknown subjects / unparseable descriptions. */
function fallbackLegacyConflict($row) {
    return [
        "type" => "legacy-unclassified",
        "entityType" => null,
        "entityLabel" => null,
        "entityWid" => null,
        "entityKey" => sha1($row["ConflictSubject"] . $row["ConflictDescription"]),
        "parliament" => "",
        "mediaKey" => (trim((string)$row["ConflictIdentifier"]) !== "") ? trim((string)$row["ConflictIdentifier"]) : null,
        "data" => [
            "subject" => $row["ConflictSubject"],
            "description" => mb_substr((string)$row["ConflictDescription"], 0, 2000)
        ]
    ];
}

// ---------------------------------------------------------------------
// Flush helpers (upsert group + insert media rows)
// ---------------------------------------------------------------------

function flushGroups($groups, $db, $tbl, $tblMedia) {
    $stats = ["created" => 0, "updated" => 0, "mediaRows" => 0];

    foreach ($groups as $groupKey => $group) {
        $existing = $db->getRow("SELECT ConflictID, ConflictEntityLabel, ConflictEntityWid, ConflictData, ConflictFirstSeen, ConflictLastSeen FROM ?n WHERE ConflictGroupKey = ?s", $tbl, $groupKey);

        $dataJson = ($group["data"] !== null) ? json_encode($group["data"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($existing) {
            // Merge into a row the new code (or a previous batch) already
            // created: widen the seen-range, backfill fields, keep status.
            $updateParts = [
                $db->parse("ConflictFirstSeen = LEAST(COALESCE(ConflictFirstSeen, ?i), ?i)", $group["firstSeen"], $group["firstSeen"]),
                $db->parse("ConflictLastSeen = GREATEST(COALESCE(ConflictLastSeen, ?i), ?i)", $group["lastSeen"], $group["lastSeen"])
            ];
            if (empty($existing["ConflictEntityLabel"]) && $group["entityLabel"] !== null) {
                $updateParts[] = $db->parse("ConflictEntityLabel = ?s", $group["entityLabel"]);
            }
            if (empty($existing["ConflictEntityWid"]) && $group["entityWid"] !== null) {
                $updateParts[] = $db->parse("ConflictEntityWid = ?s", $group["entityWid"]);
            }
            if (empty($existing["ConflictData"]) && $dataJson !== null) {
                $updateParts[] = $db->parse("ConflictData = ?s", $dataJson);
            }
            $db->query("UPDATE ?n SET ?p WHERE ConflictID = ?i", $tbl, implode(", ", $updateParts), $existing["ConflictID"]);
            $conflictID = (int)$existing["ConflictID"];
            $stats["updated"]++;
        } else {
            $db->query("INSERT INTO ?n SET
                    ConflictType = ?s,
                    ConflictGroupKey = ?s,
                    ConflictEntityType = ?s,
                    ConflictEntityLabel = ?s,
                    ConflictEntityWid = ?s,
                    ConflictParliament = ?s,
                    ConflictData = ?s,
                    ConflictStatus = 'open',
                    ConflictFirstSeen = ?i,
                    ConflictLastSeen = ?i",
                $tbl, $group["type"], $groupKey, $group["entityType"], $group["entityLabel"], $group["entityWid"],
                $group["parliament"], $dataJson, $group["firstSeen"], $group["lastSeen"]);
            $conflictID = (int)$db->insertId();
            $stats["created"]++;
        }

        if (!empty($group["media"])) {
            foreach (array_chunk(array_keys($group["media"]), 1000) as $chunk) {
                $values = [];
                foreach ($chunk as $mediaKey) {
                    $values[] = $db->parse("(?i, ?s)", $conflictID, $mediaKey);
                }
                $db->query("INSERT IGNORE INTO ?n (ConflictID, MediaID) VALUES " . implode(", ", $values), $tblMedia);
                $stats["mediaRows"] += count($chunk);
            }
        }
    }

    return $stats;
}

// ---------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------

$totalLegacy = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE ConflictGroupKey IS NULL", $tbl);
migrateLog(($dryRun ? "[DRY-RUN] " : "") . "Legacy conflict rows to migrate: $totalLegacy (batch size $batchSize)");

$cursor = 0;
$processed = 0;
$discarded = []; // reason => count
$unparseable = 0;
$unparseableSamples = [];
$typeStats = [];   // type => ["rows" => n, "groups" => set]
$totals = ["created" => 0, "updated" => 0, "mediaRows" => 0];
$dryRunGroups = []; // dry-run only: groupKey => media count

// Skipped entirely when nothing is left: a database created from the current
// dump has no legacy columns, so the SELECT below would fail.
while ($totalLegacy > 0) {
    $rows = $db->getAll(
        "SELECT ConflictID, ConflictSubject, ConflictDescription, ConflictIdentifier, ConflictTimestamp FROM ?n
         WHERE ConflictGroupKey IS NULL AND ConflictID > ?i ORDER BY ConflictID ASC LIMIT ?i",
        $tbl, $cursor, $batchSize
    );
    if (empty($rows)) {
        break;
    }

    $groups = [];
    $rowIDs = [];
    foreach ($rows as $row) {
        $cursor = max($cursor, (int)$row["ConflictID"]);
        $rowIDs[] = (int)$row["ConflictID"];
        $processed++;

        $parsed = parseLegacyConflict($row);
        if (is_array($parsed) && !empty($parsed["discard"])) {
            $discarded[$parsed["discard"]] = ($discarded[$parsed["discard"]] ?? 0) + 1;
            continue; // row is still deleted with its batch below
        }
        if ($parsed === null) {
            $unparseable++;
            if (count($unparseableSamples) < 5) {
                $unparseableSamples[] = "#" . $row["ConflictID"] . " [" . $row["ConflictSubject"] . "] " . mb_substr((string)$row["ConflictDescription"], 0, 120);
            }
            $parsed = fallbackLegacyConflict($row);
        }

        $groupKey = conflictComputeGroupKey($parsed["type"], $parsed["parliament"], (string)$parsed["entityKey"]);
        $timestamp = (int)($row["ConflictTimestamp"] ?: time());

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                "type" => $parsed["type"],
                "entityType" => $parsed["entityType"],
                "entityLabel" => $parsed["entityLabel"],
                "entityWid" => $parsed["entityWid"],
                "parliament" => $parsed["parliament"],
                "data" => $parsed["data"],
                "dataTimestamp" => $timestamp,
                "firstSeen" => $timestamp,
                "lastSeen" => $timestamp,
                "media" => []
            ];
        } else {
            $groups[$groupKey]["firstSeen"] = min($groups[$groupKey]["firstSeen"], $timestamp);
            if ($timestamp >= $groups[$groupKey]["lastSeen"]) {
                $groups[$groupKey]["lastSeen"] = $timestamp;
                $groups[$groupKey]["dataTimestamp"] = $timestamp;
                $groups[$groupKey]["data"] = $parsed["data"];
            }
            // Backfill identifying fields from later occurrences if missing
            foreach (["entityLabel", "entityWid", "entityType"] as $field) {
                if ($groups[$groupKey][$field] === null && $parsed[$field] !== null) {
                    $groups[$groupKey][$field] = $parsed[$field];
                }
            }
        }
        if ($parsed["mediaKey"] !== null) {
            $groups[$groupKey]["media"][$parsed["mediaKey"]] = true;
        }

        if (!isset($typeStats[$parsed["type"]])) {
            $typeStats[$parsed["type"]] = ["rows" => 0, "groups" => []];
        }
        $typeStats[$parsed["type"]]["rows"]++;
        $typeStats[$parsed["type"]]["groups"][$groupKey] = true;
    }

    if ($dryRun) {
        foreach ($groups as $groupKey => $group) {
            $dryRunGroups[$groupKey] = ($dryRunGroups[$groupKey] ?? 0) + count($group["media"]);
        }
    } else {
        $stats = flushGroups($groups, $db, $tbl, $tblMedia);
        $totals["created"] += $stats["created"];
        $totals["updated"] += $stats["updated"];
        $totals["mediaRows"] += $stats["mediaRows"];
        $db->query("DELETE FROM ?n WHERE ConflictID IN (?a)", $tbl, $rowIDs);
    }

    migrateLog(($dryRun ? "[DRY-RUN] " : "") . "Processed $processed / $totalLegacy rows...");
}

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------

$discardedSummary = empty($discarded) ? "0" : implode(", ", array_map(function ($reason, $count) { return "$reason: $count"; }, array_keys($discarded), $discarded));
migrateLog(($dryRun ? "[DRY-RUN] " : "") . "Done. Rows processed: $processed, discarded (not conflicts — $discardedSummary), unparseable (kept as legacy-unclassified): $unparseable");
foreach ($typeStats as $type => $stats) {
    migrateLog(sprintf("  %-32s %7d rows -> %5d groups", $type, $stats["rows"], count($stats["groups"])));
}
if ($dryRun) {
    arsort($dryRunGroups);
    migrateLog("[DRY-RUN] Largest groups (media per group): " . implode(", ", array_slice($dryRunGroups, 0, 10)));
    migrateLog("[DRY-RUN] Total distinct groups: " . count($dryRunGroups) . " — no changes written.");
} else {
    migrateLog("Groups created: " . $totals["created"] . ", merged into existing: " . $totals["updated"] . ", media rows inserted: " . $totals["mediaRows"]);
    $remaining = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE ConflictGroupKey IS NULL", $tbl);
    migrateLog("Legacy rows remaining: $remaining" . ($remaining === 0 ? " — you can now run --drop-legacy-columns." : ""));
}
if (!empty($unparseableSamples)) {
    migrateLog("Unparseable samples:");
    foreach ($unparseableSamples as $sample) {
        migrateLog("  " . $sample);
    }
}
