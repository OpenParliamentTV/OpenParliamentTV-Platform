<?php

/**
 * Statistics index lifecycle: the mapping, and creating/deleting the index.
 *
 * This file holds the ONE mapping definition. There used to be two -- a rich one
 * here that nothing reachable ever called, and a thinner one inside
 * bin/index-statistics.php that the live index was actually built from. The
 * consequence was that fields the writer emitted (faction_id) were never mapped
 * and arrived as dynamically-mapped text, forcing the read side to query
 * `faction_id.keyword`, while a field that was mapped (party_id) was never
 * written at all.
 */

require_once(__DIR__ . '/../../api/v1/utilities.php');
require_once(__DIR__ . '/../search/opensearch.php');

/**
 * Mapping and settings for the statistics index.
 *
 * @param bool $bulkSettings Tune for a bulk rebuild (no replicas, no refresh).
 *                           Call statsIndexRestoreSettings() afterwards.
 * @return array Body for indices()->create().
 */
function statsIndexMappingBody($bulkSettings = false)
{
    $settings = [
        'number_of_shards'   => 1,
        'number_of_replicas' => $bulkSettings ? 0 : 1,
        'refresh_interval'   => $bulkSettings ? -1 : '30s',
        'analysis' => [
            'analyzer' => [
                'autocomplete_analyzer' => [
                    'tokenizer' => 'keyword',
                    'filter'    => ['lowercase', 'autocomplete_filter'],
                ],
            ],
            'filter' => [
                'autocomplete_filter' => [
                    'type'     => 'edge_ngram',
                    'min_gram' => 1,
                    'max_gram' => 20,
                ],
            ],
        ],
    ];

    if ($bulkSettings) {
        $settings['index.translog.sync_interval'] = '30s';
        $settings['index.translog.durability']    = 'async';
    }

    return [
        'mappings' => [
            'properties' => [
                'aggregation_type' => ['type' => 'keyword'],

                // Scope. Present on both document types, so one filter clause
                // covers word documents and their totals alike.
                'speech_id'           => ['type' => 'keyword'],
                'session_id'          => ['type' => 'keyword'],
                'agenda_item_id'      => ['type' => 'keyword'],
                'electoral_period_id' => ['type' => 'keyword'],
                'faction_id'          => ['type' => 'keyword'],
                'speaker_id'          => ['type' => 'keyword'],

                // true = session-chair text. Excluded from every default query.
                'procedural' => ['type' => 'boolean'],

                // `date` is a plain epoch-SECOND long, not a date type: min/max
                // aggregations on a date field come back in milliseconds while
                // range queries take seconds, and mixing the two silently yields
                // first-use dates in the year 46398. Date histograms run on
                // `date_string`, which is a real date field.
                'date'        => ['type' => 'long'],
                'date_string' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],

                // Surface form for display and word suggestions; stem for grouping.
                'word' => [
                    'type'   => 'keyword',
                    'fields' => [
                        'text' => ['type' => 'text', 'analyzer' => 'autocomplete_analyzer'],
                    ],
                ],
                'stem'  => ['type' => 'keyword'],
                'count' => ['type' => 'long'],

                // speech_totals only: normalization denominators.
                'token_count'        => ['type' => 'long'],
                'counted_word_count' => ['type' => 'long'],
            ],
        ],
        'settings' => $settings,
    ];
}

/**
 * Create the statistics index if it does not exist.
 *
 * @param string|null $parliament   Parliament config key.
 * @param bool        $bulkSettings Tune for a bulk rebuild.
 * @return array ['success' => bool, ...]
 */
function createStatisticsIndex($parliament = null, $bulkSettings = false)
{
    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }

    $indexName = getStatisticsIndexName($parliament);

    try {
        if ($client->indices()->exists(['index' => $indexName])) {
            return ['success' => true, 'message' => 'Index already exists', 'index' => $indexName];
        }

        $response = $client->indices()->create([
            'index' => $indexName,
            'body'  => statsIndexMappingBody($bulkSettings),
        ]);

        return ['success' => true, 'response' => $response, 'index' => $indexName];
    } catch (Exception $e) {
        error_log('Error creating statistics index: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Drop the statistics index.
 *
 * @param string|null $parliament Parliament config key.
 * @return array ['success' => bool, ...]
 */
function deleteStatisticsIndex($parliament = null)
{
    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }

    $indexName = getStatisticsIndexName($parliament);

    try {
        if (!$client->indices()->exists(['index' => $indexName])) {
            return ['success' => true, 'message' => 'Statistics index did not exist'];
        }

        $response = $client->indices()->delete(['index' => $indexName]);

        return ['success' => true, 'message' => 'Statistics index deleted', 'response' => $response];
    } catch (Exception $e) {
        error_log('Error deleting statistics index: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Restore serving settings after a bulk rebuild.
 *
 * @param string|null $parliament Parliament config key.
 * @return array ['success' => bool, ...]
 */
function statsIndexRestoreSettings($parliament = null)
{
    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }

    $indexName = getStatisticsIndexName($parliament);

    try {
        $client->indices()->putSettings([
            'index' => $indexName,
            'body'  => [
                'index' => [
                    'number_of_replicas'        => 1,
                    'refresh_interval'          => '30s',
                    'translog.sync_interval'    => '5s',
                    'translog.durability'       => 'request',
                ],
            ],
        ]);

        return ['success' => true, 'index' => $indexName];
    } catch (Exception $e) {
        error_log('Error restoring statistics index settings: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create the statistics index, optionally dropping it first.
 *
 * @param string|null $parliament   Parliament config key.
 * @param bool        $cleanRebuild Drop the index before creating it.
 * @param bool        $bulkSettings Tune for a bulk rebuild.
 * @return array ['success' => bool, ...]
 */
function createStatisticsIndexing($parliament = null, $cleanRebuild = false, $bulkSettings = false)
{
    $results = [];

    if ($cleanRebuild) {
        $deleteResult = deleteStatisticsIndex($parliament);
        $results['cleanup'] = $deleteResult;

        if (!$deleteResult['success']) {
            return [
                'success' => false,
                'error'   => 'Failed to clean existing statistics index for rebuild',
                'details' => $deleteResult,
            ];
        }
    }

    $createResult = createStatisticsIndex($parliament, $bulkSettings);
    $results['statistics'] = $createResult;

    return [
        'success' => $createResult['success'],
        'results' => $results,
        'message' => $createResult['success']
            ? 'Statistics index created successfully'
            : 'Statistics index creation failed',
    ];
}
