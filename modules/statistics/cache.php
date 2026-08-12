<?php

/**
 * Response cache for the statistics endpoints.
 *
 * Validity is not a TTL but a fingerprint of the two indices: if their document
 * counts still match what they were when an entry was written, the entry still
 * describes them. That means a cache entry can live indefinitely while nothing
 * is imported, and stops being used the moment anything is — without anyone
 * having to guess a expiry.
 *
 * The indexing jobs also clear the namespace explicitly (statsCacheInvalidate),
 * so the fingerprint is a backstop for the cases they miss rather than the
 * primary mechanism.
 *
 * Why this matters more than it looks: the corpus-wide aggregations behind
 * `statistics/general` take ~16 seconds cold on the production-sized corpus.
 * That is fine as index-time work and unacceptable as request-time work, which
 * is the whole reason statsCacheWarm() exists.
 */

require_once(__DIR__ . '/../search/opensearch.php');
require_once(__DIR__ . '/../utilities/functions.php'); // optvVarPath()
require_once(__DIR__ . '/../../api/v1/utilities.php');

/**
 * Cache file base path for one entry.
 *
 * @param string $endpoint   Endpoint name, e.g. 'general'.
 * @param array  $params     Parameters that vary the response.
 * @param string $parliament Resolved parliament key.
 * @return string Path without extension.
 */
function statsCachePath($endpoint, array $params, $parliament)
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== []);
    ksort($params);

    $name = 'statistics_' . $parliament . '_' . $endpoint;

    if (!empty($params)) {
        $name .= '_' . substr(sha1(json_encode($params)), 0, 12);
    }

    return optvVarPath('cache/api') . '/' . $name;
}

/**
 * Fingerprint of the indices a statistics response was derived from.
 *
 * Document counts only. Store size was part of this and should not have been:
 * a segment merge changes the byte count without changing a single answer, and
 * that threw away the cache for no reason.
 *
 * @return array
 */
function statsCacheFingerprint($parliament)
{
    static $cache = [];

    if (isset($cache[$parliament])) {
        return $cache[$parliament];
    }

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        // Never matches a stored fingerprint, so entries are simply not used
        // while OpenSearch is unreachable.
        return ['unavailable' => microtime(true)];
    }

    try {
        $mainIndex  = getParliamentIndexName($parliament);
        $statsIndex = getStatisticsIndexName($parliament);

        $mainStats  = $client->indices()->stats(['index' => $mainIndex]);
        $indexStats = $client->indices()->stats(['index' => $statsIndex]);

        $cache[$parliament] = [
            'main_docs'  => $mainStats['indices'][$mainIndex]['total']['docs']['count'] ?? 0,
            'stats_docs' => $indexStats['indices'][$statsIndex]['total']['docs']['count'] ?? 0,
        ];
    } catch (Exception $e) {
        return ['unavailable' => microtime(true)];
    }

    return $cache[$parliament];
}

/**
 * Read a cached response.
 *
 * @return array|null The payload, or null when absent or stale.
 */
function statsCacheGet($endpoint, array $params = [], $parliament = null)
{
    $parliament = resolveParliamentKey($parliament);
    $base = statsCachePath($endpoint, $params, $parliament);

    if (!file_exists($base . '.meta') || !file_exists($base . '.json')) {
        return null;
    }

    $meta = json_decode(@file_get_contents($base . '.meta'), true);
    if (!is_array($meta) || !isset($meta['fingerprint'])) {
        return null;
    }

    if ($meta['fingerprint'] !== statsCacheFingerprint($parliament)) {
        @unlink($base . '.json');
        @unlink($base . '.meta');
        return null;
    }

    $payload = json_decode((string) @file_get_contents($base . '.json'), true);

    return is_array($payload) ? $payload : null;
}

/**
 * Store a response.
 *
 * @param mixed $payload Anything json_encode can round-trip.
 */
function statsCachePut($endpoint, array $params, $parliament, $payload)
{
    $parliament = resolveParliamentKey($parliament);
    $base = statsCachePath($endpoint, $params, $parliament);

    @file_put_contents($base . '.json', json_encode($payload));
    @file_put_contents($base . '.meta', json_encode([
        'created_at'  => time(),
        'parliament'  => $parliament,
        'endpoint'    => $endpoint,
        'fingerprint' => statsCacheFingerprint($parliament),
    ]));
}

/**
 * Drop every cached statistics response for a parliament.
 *
 * Called by the indexing jobs when they finish writing.
 *
 * @return int Number of entries removed.
 */
function statsCacheInvalidate($parliament = null)
{
    $parliament = resolveParliamentKey($parliament);
    $pattern = optvVarPath('cache/api') . '/statistics_' . $parliament . '_*';

    $removed = 0;
    foreach (glob($pattern) ?: [] as $file) {
        if (@unlink($file)) {
            $removed++;
        }
    }

    // Entry written by the pre-1.0 cache, which used its own file name.
    foreach (glob(optvVarPath('cache/api') . '/general_statistics_' . $parliament . '.*') ?: [] as $file) {
        @unlink($file);
        $removed++;
    }

    return $removed;
}

/**
 * Recompute the responses no user request should ever have to wait for.
 *
 * The split this maintains: corpus-wide aggregations are index-time work, and
 * filter-narrow queries are request-time work. Anything added here should be
 * something whose cost scales with the whole corpus rather than with one
 * person's question.
 *
 * @param string|null   $parliament Parliament config key.
 * @param callable|null $onProgress Receives a status string per step.
 * @return array ['warmed' => string[], 'seconds' => float]
 */
function statsCacheWarm($parliament = null, callable $onProgress = null)
{
    require_once(__DIR__ . '/../../api/v1/modules/statistics.php');

    $parliament = resolveParliamentKey($parliament);
    $started = microtime(true);
    $warmed = [];

    $report = function ($message) use ($onProgress) {
        if ($onProgress) {
            $onProgress($message);
        }
    };

    // The platform dashboard: corpus-wide term and cardinality aggregations over
    // both indices, and by far the most expensive thing here.
    $report('warming general statistics');
    statisticsGetGeneral(['parliament' => $parliament]);
    $warmed[] = 'general';

    // The denominators every relative-mode word trend divides by. Identical
    // across all of them, so computing it once here spares each request one of
    // its two aggregations.
    $report('warming trend denominators');
    $totals = statsQueryTotalsHistogram([
        'parliament' => $parliament,
        'dateFrom'   => statisticsParliamentDataStart($parliament),
    ]);
    if ($totals !== null) {
        statsCachePut('totals', ['scope' => 'all'], $parliament, $totals);
        $warmed[] = 'totals';
    }

    return ['warmed' => $warmed, 'seconds' => round(microtime(true) - $started, 1)];
}
