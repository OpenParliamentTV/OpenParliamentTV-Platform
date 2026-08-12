<?php

/**
 * Statistics index writer.
 *
 * One document per (speech x segment class x word), plus one totals document per
 * (speech x segment class):
 *
 *   speech_word    _id = md5("w|<speechId>|<procedural>|<word>")
 *   speech_totals  _id = md5("t|<speechId>|<procedural>")
 *
 * The point of the deterministic _id is idempotency. The previous model wrote
 * pre-aggregated documents keyed on (date, word, faction) and incremented them
 * with a scripted upsert, so re-indexing a speech ADDED its counts a second
 * time -- and speeches are demonstrably re-indexed long after first import, when
 * NER or alignment is re-run. Here a re-index overwrites, and the numbers hold.
 *
 * The old query shapes all survive as query-time aggregations:
 *   word trends        term(stem|word) -> date_histogram -> sum(count)
 *   speaker vocabulary term(speaker_id) -> terms(word) ordered by sum(count)
 *   first mention      term(stem) -> min(date)
 *   normalization      sum(token_count) over speech_totals
 *
 * @see segments.php for the main/procedural split, stemming.php for `stem`.
 */

require_once(__DIR__ . '/text-processing.php');
require_once(__DIR__ . '/extraction.php');
require_once(__DIR__ . '/stemming.php');
require_once(__DIR__ . '/../search/opensearch.php');

/** Speeches per bulk request when writing a batch. */
const STATS_BULK_SPEECHES = 200;

/**
 * Build the statistics documents for a batch of speeches.
 *
 * Pure: no OpenSearch writes, so bin/verify-statistics.php can recompute the
 * expected index contents from a session file and diff them against reality.
 * The one round-trip it does make is the shared stem lookup, which is batched
 * across the whole input rather than per speech.
 *
 * @param array       $speechNodes Media nodes, each with an `id`.
 * @param string|null $parliament  Parliament config key.
 * @param array|null  $config      Platform config; the global one when omitted.
 * @return array List of ['_id' => string, 'doc' => array].
 */
function statsIndexBuildBatchDocs(array $speechNodes, $parliament = null, $config = null)
{
    if ($config === null) {
        $config = $GLOBALS['config'] ?? [];
    }

    // Pass 1: word frequencies and metadata, collecting the batch's vocabulary.
    $prepared = [];
    $vocabulary = [];

    foreach ($speechNodes as $speechData) {
        $speechId = $speechData['id'] ?? null;
        $date     = statsIndexExtractDate($speechData);

        if ($speechId === null || $date === null) {
            continue;
        }

        $frequencies = statsIndexGetWordFrequencies($speechData, $config, $parliament);
        if (empty($frequencies)) {
            continue;
        }

        $prepared[] = [
            'speechId'    => $speechId,
            'date'        => $date,
            'dateString'  => statsIndexExtractDateString($speechData),
            'speakerId'   => statsIndexExtractSpeakerId($speechData),
            'factionId'   => statsIndexExtractFactionId($speechData),
            'sessionId'   => statsIndexExtractSessionId($speechData),
            'agendaItemId' => statsIndexExtractAgendaItemId($speechData),
            'periodId'    => statsIndexExtractElectoralPeriodId($speechData),
            'frequencies' => $frequencies,
        ];

        foreach ($frequencies as $classData) {
            foreach (array_keys($classData['words']) as $word) {
                $vocabulary[$word] = true;
            }
        }
    }

    // Pass 2: one stem lookup for the whole batch.
    $stems = empty($vocabulary)
        ? []
        : statsIndexStemWords(array_keys($vocabulary), $parliament, $config);

    // Pass 3: documents.
    $docs = [];

    foreach ($prepared as $speech) {
        foreach ($speech['frequencies'] as $class => $classData) {
            $procedural = ($class === STATS_SEGMENT_PROCEDURAL);

            $scope = [
                'speech_id'           => $speech['speechId'],
                'procedural'          => $procedural,
                'date'                => $speech['date'],
                'date_string'         => $speech['dateString'],
                'faction_id'          => $speech['factionId'],
                'speaker_id'          => $speech['speakerId'],
                'session_id'          => $speech['sessionId'],
                'agenda_item_id'      => $speech['agendaItemId'],
                'electoral_period_id' => $speech['periodId'],
            ];

            foreach ($classData['words'] as $word => $count) {
                $docs[] = [
                    '_id' => statsIndexWordDocId($speech['speechId'], $procedural, $word),
                    'doc' => $scope + [
                        'aggregation_type' => 'speech_word',
                        'word'             => $word,
                        'stem'             => $stems[$word] ?? $word,
                        'count'            => $count,
                    ],
                ];
            }

            $docs[] = [
                '_id' => statsIndexTotalsDocId($speech['speechId'], $procedural),
                'doc' => $scope + [
                    'aggregation_type'   => 'speech_totals',
                    'token_count'        => $classData['tokenCount'],
                    'counted_word_count' => $classData['countedWordCount'],
                ],
            ];
        }
    }

    return $docs;
}

/**
 * Documents for a single speech.
 *
 * @param array       $speechData A media node with an `id`.
 * @param string|null $parliament Parliament config key.
 * @param array|null  $config     Platform config; the global one when omitted.
 * @return array List of ['_id' => string, 'doc' => array].
 */
function statsIndexBuildSpeechDocs($speechData, $parliament = null, $config = null)
{
    return statsIndexBuildBatchDocs([$speechData], $parliament, $config);
}

/** Deterministic id of one word document. */
function statsIndexWordDocId($speechId, $procedural, $word)
{
    return md5('w|' . $speechId . '|' . ($procedural ? '1' : '0') . '|' . $word);
}

/** Deterministic id of one totals document. */
function statsIndexTotalsDocId($speechId, $procedural)
{
    return md5('t|' . $speechId . '|' . ($procedural ? '1' : '0'));
}

/**
 * Remove every statistics document belonging to the given speeches.
 *
 * Used by the incremental path over the ids it was ASKED to refresh, not the
 * ids it ends up writing: a speech whose text was removed, or that was made
 * non-public, produces no documents, and its old ones have to go regardless.
 *
 * @param array       $speechIds  Media ids.
 * @param string|null $parliament Parliament config key.
 * @return array ['success' => bool, ...]
 */
function statsIndexDeleteSpeeches(array $speechIds, $parliament = null)
{
    $speechIds = array_values(array_filter($speechIds));
    if (empty($speechIds)) {
        return ['success' => true, 'deleted' => 0];
    }

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }

    try {
        $response = $client->deleteByQuery([
            'index'     => getStatisticsIndexName($parliament),
            'conflicts' => 'proceed',
            'refresh'   => true,
            'body'      => ['query' => ['terms' => ['speech_id' => $speechIds]]],
        ]);
    } catch (Exception $e) {
        // A missing index is the normal case before the first rebuild.
        if (strpos($e->getMessage(), 'index_not_found_exception') !== false) {
            return ['success' => true, 'deleted' => 0];
        }
        error_log('statsIndexDeleteSpeeches: delete_by_query failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }

    return ['success' => true, 'deleted' => $response['deleted'] ?? 0];
}

/**
 * Write the statistics documents for a batch of speeches.
 *
 * Deterministic ids overwrite, but a speech's word set can SHRINK between
 * versions when a transcript is corrected, which would strand the documents for
 * words that are no longer there. On the incremental path we therefore clear the
 * batch's speeches first -- one delete_by_query for the whole batch, not one per
 * speech. A full rebuild recreates the index from scratch, so it passes
 * `deleteFirst => false` and skips the cost entirely.
 *
 * @param array       $speechNodes Media nodes, each with an `id`.
 * @param string|null $parliament  Parliament config key.
 * @param array       $options     ['deleteFirst' => bool] -- default true.
 * @return array ['success' => bool, 'documents' => int, 'speeches' => int, ...]
 */
function statsIndexWriteSpeeches(array $speechNodes, $parliament = null, array $options = [])
{
    if (empty($speechNodes)) {
        return ['success' => true, 'documents' => 0, 'speeches' => 0];
    }

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }

    $indexName   = getStatisticsIndexName($parliament);
    $deleteFirst = $options['deleteFirst'] ?? true;

    $docs = statsIndexBuildBatchDocs($speechNodes, $parliament);

    if ($deleteFirst) {
        $speechIds = [];
        foreach ($speechNodes as $speechData) {
            if (isset($speechData['id'])) {
                $speechIds[] = $speechData['id'];
            }
        }

        if (!empty($speechIds)) {
            try {
                $client->deleteByQuery([
                    'index'     => $indexName,
                    'conflicts' => 'proceed',
                    'refresh'   => true,
                    'body'      => ['query' => ['terms' => ['speech_id' => $speechIds]]],
                ]);
            } catch (Exception $e) {
                // A missing index is the normal case on a first incremental run.
                if (strpos($e->getMessage(), 'index_not_found_exception') === false) {
                    error_log('statsIndexWriteSpeeches: delete_by_query failed: ' . $e->getMessage());
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            }
        }
    }

    if (empty($docs)) {
        return ['success' => true, 'documents' => 0, 'speeches' => count($speechNodes)];
    }

    $body = [];
    foreach ($docs as $entry) {
        $body[] = ['index' => ['_index' => $indexName, '_id' => $entry['_id']]];
        $body[] = $entry['doc'];
    }

    try {
        $response = $client->bulk(['body' => $body]);
    } catch (Exception $e) {
        error_log('statsIndexWriteSpeeches: bulk write failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }

    if (!empty($response['errors'])) {
        $firstError = null;
        foreach ($response['items'] ?? [] as $item) {
            if (isset($item['index']['error'])) {
                $firstError = json_encode($item['index']['error']);
                break;
            }
        }
        error_log('statsIndexWriteSpeeches: bulk write had errors: ' . $firstError);
        return ['success' => false, 'error' => 'Bulk write had errors', 'details' => $firstError];
    }

    return [
        'success'   => true,
        'documents' => count($docs),
        'speeches'  => count($speechNodes),
    ];
}

/**
 * Write the statistics documents for one speech.
 *
 * @param array       $speechData A media node with an `id`.
 * @param string|null $parliament Parliament config key.
 * @return array
 */
function updateSpeechStatistics($speechData, $parliament = null)
{
    return statsIndexWriteSpeeches([$speechData], $parliament);
}
