<?php

/**
 * Entry points for writing speeches into the statistics index.
 *
 * @see aggregation.php for the document model, index-admin.php for the mapping.
 */

require_once(__DIR__ . '/index-admin.php');
require_once(__DIR__ . '/aggregation.php');

/**
 * Index a batch of speeches into the statistics index.
 *
 * @param array       $speechNodes Media nodes, each with an `id`.
 * @param string|null $parliament  Parliament config key.
 * @param array       $options     Passed through to statsIndexWriteSpeeches().
 * @return array ['success' => bool, ...]
 */
function indexSpeechStatisticsBatch(array $speechNodes, $parliament = null, array $options = [])
{
    if (!statsIndexIsReady($parliament)) {
        return ['success' => true, 'skipped' => true, 'message' => 'Statistics index does not exist yet'];
    }

    try {
        return statsIndexWriteSpeeches($speechNodes, $parliament, $options);
    } catch (Exception $e) {
        error_log('Statistics indexing error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Index a single speech into the statistics index.
 *
 * @param array       $speechData A media node with an `id`.
 * @param string|null $parliament Parliament config key.
 * @param array       $options    Passed through to statsIndexWriteSpeeches().
 * @return array ['success' => bool, ...]
 */
function indexSpeechStatistics($speechData, $parliament = null, array $options = [])
{
    return indexSpeechStatisticsBatch([$speechData], $parliament, $options);
}

/**
 * Does this parliament's statistics index exist yet?
 *
 * This is the whole readiness question. The index is created by the rebuild
 * (bin/index-statistics.php), so its absence means "not set up yet" and the
 * writer has nothing to write into. There is deliberately no separate on/off
 * config flag: a boolean an operator has to know to set is just a way to end up
 * with silently missing statistics and no indication why.
 *
 * Memoised per parliament: this used to run once per speech, an extra HTTP
 * round-trip for every one of the tens of thousands of speeches in a rebuild.
 *
 * @param string|null $parliament Parliament config key.
 * @return bool
 */
function statsIndexIsReady($parliament = null)
{
    static $cache = [];

    $indexName = getStatisticsIndexName($parliament);

    if (isset($cache[$indexName])) {
        return $cache[$indexName];
    }

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return false;
    }

    try {
        $cache[$indexName] = (bool) $client->indices()->exists(['index' => $indexName]);
    } catch (Exception $e) {
        error_log('Error checking statistics index: ' . $e->getMessage());
        return false;
    }

    return $cache[$indexName];
}

/**
 * Create the statistics index if it does not exist yet.
 *
 * @param string|null $parliament   Parliament config key.
 * @param bool        $cleanRebuild Drop the index before creating it.
 * @param bool        $bulkSettings Tune for a bulk rebuild.
 * @return array ['success' => bool, ...]
 */
function setupStatisticsIndexing($parliament = null, $cleanRebuild = false, $bulkSettings = false)
{
    return createStatisticsIndexing($parliament, $cleanRebuild, $bulkSettings);
}
