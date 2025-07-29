<?php

require_once(__DIR__.'/../utilities/functions.api.php');
require_once(__DIR__.'/../search/functions.php');


/**
 * Create statistics index mapping
 */
function createStatisticsIndex($parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    
    $mapping = [
        'mappings' => [
            'properties' => [
                'aggregation_type' => ['type' => 'keyword'],
                'date' => ['type' => 'date', 'format' => 'epoch_second'],
                'date_string' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'word' => [
                    'type' => 'keyword',
                    'fields' => [
                        'text' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete_analyzer'
                        ]
                    ]
                ],
                'faction_id' => ['type' => 'keyword'],
                'speaker_id' => ['type' => 'keyword'],
                'count' => ['type' => 'long'],
                'speech_count' => ['type' => 'long'],
                'first_used' => ['type' => 'date', 'format' => 'epoch_second'],
                'last_used' => ['type' => 'date', 'format' => 'epoch_second'],
                'first_used_string' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'last_used_string' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'last_updated' => ['type' => 'date', 'format' => 'yyyy-MM-dd']
            ]
        ],
        'settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'refresh_interval' => '30s', // Reduce refresh frequency during indexing
            'index.merge.policy.max_merged_segment' => '2gb', // Larger segments
            'index.translog.flush_threshold_size' => '1gb', // Less frequent flushing
            'analysis' => [
                'analyzer' => [
                    'autocomplete_analyzer' => [
                        'tokenizer' => 'keyword',
                        'filter' => ['lowercase', 'autocomplete_filter']
                    ]
                ],
                'filter' => [
                    'autocomplete_filter' => [
                        'type' => 'edge_ngram',
                        'min_gram' => 1,
                        'max_gram' => 20
                    ]
                ]
            ]
        ]
    ];
    
    try {
        if ($ESClient->indices()->exists(['index' => $indexName])) {
            return ['success' => true, 'message' => 'Index already exists'];
        }
        
        $response = $ESClient->indices()->create([
            'index' => $indexName,
            'body' => $mapping
        ]);
        
        return ['success' => true, 'response' => $response];
    } catch (Exception $e) {
        error_log("Error creating statistics index: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete statistics index for clean rebuild
 */
function deleteStatisticsIndex($parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
    
    try {
        if ($ESClient->indices()->exists(['index' => $statisticsIndex])) {
            $response = $ESClient->indices()->delete(['index' => $statisticsIndex]);
            return ['success' => true, 'message' => 'Statistics index deleted', 'response' => $response];
        } else {
            return ['success' => true, 'message' => 'Statistics index did not exist'];
        }
    } catch (Exception $e) {
        error_log("Error deleting statistics index: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create statistics index with clean rebuild option
 */
function createStatisticsIndexing($parliamentCode = 'de', $cleanRebuild = false) {
    $results = [];
    
    // Clean rebuild: delete existing index first
    if ($cleanRebuild) {
        $deleteResult = deleteStatisticsIndex($parliamentCode);
        $results['cleanup'] = $deleteResult;
        
        if (!$deleteResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to clean existing statistics index for rebuild',
                'details' => $deleteResult
            ];
        }
    }
    
    // Create statistics index
    $statisticsResult = createStatisticsIndex($parliamentCode);
    $results['statistics'] = $statisticsResult;
    
    return [
        'success' => $statisticsResult['success'],
        'results' => $results,
        'message' => $statisticsResult['success'] ? 'Statistics index created successfully' : 'Statistics index creation failed'
    ];
}

/**
 * Test statistics index creation
 */
function testStatisticsIndexCreation($parliamentCode = 'de') {
    return createStatisticsIndexing($parliamentCode, false);
}


/**
 * Update statistics index for a specific speech (incremental)
 */
function updateStatisticsForSpeech($parliamentCode, $speechId, $speechData) {
    try {
        require_once(__DIR__ . '/../utilities/functions.api.php');
        require_once(__DIR__ . '/functions.statistics.php');
        
        // Use the existing statistics update function
        $result = updateSpeechStatistics($speechData, $parliamentCode);
        
        if ($result['success']) {
            return [
                'success' => true, 
                'message' => 'Statistics updated for speech ' . $speechId,
                'aggregations_updated' => $result['aggregations_updated'] ?? 0
            ];
        } else {
            return ['success' => false, 'error' => $result['error'] ?? 'Unknown error'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


/**
 * Optimize statistics index by cleaning up deleted documents and merging segments
 */
function optimizeStatisticsIndex($parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
    
    try {
        // Get stats before optimization
        $statsBefore = $ESClient->indices()->stats(['index' => $statisticsIndex]);
        $docsBefore = $statsBefore['indices'][$statisticsIndex]['total']['docs'];
        
        // Force merge to clean up deleted documents and optimize segments
        $mergeParams = [
            'index' => $statisticsIndex,
            'max_num_segments' => 1, // Merge to single segment for maximum optimization
            'only_expunge_deletes' => false // Full optimization, not just deletes
        ];
        
        $startTime = microtime(true);
        $mergeResponse = $ESClient->indices()->forcemerge($mergeParams);
        $endTime = microtime(true);
        
        // Get stats after optimization
        sleep(2); // Give OpenSearch time to update stats
        $statsAfter = $ESClient->indices()->stats(['index' => $statisticsIndex]);
        $docsAfter = $statsAfter['indices'][$statisticsIndex]['total']['docs'];
        
        $deletedCleaned = $docsBefore['deleted'] - $docsAfter['deleted'];
        $sizeBefore = $statsBefore['indices'][$statisticsIndex]['total']['store']['size_in_bytes'];
        $sizeAfter = $statsAfter['indices'][$statisticsIndex]['total']['store']['size_in_bytes'];
        $spaceReclaimed = $sizeBefore - $sizeAfter;
        
        return [
            'success' => true,
            'duration' => round($endTime - $startTime, 2),
            'deleted_cleaned' => $deletedCleaned,
            'space_reclaimed' => $spaceReclaimed,
            'space_savings_percent' => $sizeBefore > 0 ? round(($spaceReclaimed / $sizeBefore) * 100, 2) : 0,
            'before' => $docsBefore,
            'after' => $docsAfter,
            'message' => "Optimization complete: {$deletedCleaned} deleted documents cleaned, " . 
                        round($spaceReclaimed / 1024 / 1024, 2) . " MB reclaimed"
        ];
        
    } catch (Exception $e) {
        error_log("Error optimizing statistics index: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Extract statistics updates from speech data for incremental indexing
 */
function extractStatisticsFromSpeech($speechId, $speechData) {
    try {
        require_once(__DIR__ . '/functions.extraction.php');
        require_once(__DIR__ . '/functions.textprocessing.php');
        
        global $config;
        
        // Extract speech metadata
        $dateTimestamp = extractSpeechDate($speechData);
        $dateString = extractSpeechDateString($speechData);
        $speakerId = extractMainSpeakerId($speechData);
        $faction = extractMainSpeakerFaction($speechData);
        
        if (empty($dateTimestamp)) {
            return [];
        }
        
        // Get word frequencies from speech
        $wordFreqs = getWordFrequenciesFromSpeech($speechData, $config);
        
        if (empty($wordFreqs)) {
            return [];
        }
        
        $statisticsUpdates = [];
        $today = date('Y-m-d');
        
        foreach ($wordFreqs as $word => $count) {
            // Daily faction aggregation (optimal performance)
            $dailyFactionId = generateAggregationId('daily_faction', $dateString, $word, $faction['id'] ?? null);
            $statisticsUpdates[] = [
                'id' => $dailyFactionId,
                'data' => [
                    'aggregation_type' => 'word_frequency_daily_faction',
                    'date' => $dateTimestamp,
                    'date_string' => $dateString,
                    'word' => $word,
                    'faction_id' => $faction['id'] ?? null,
                    'count' => $count,
                    'speech_count' => 1,
                    'last_updated' => $today
                ]
            ];
            
            // Speaker aggregation
            if ($speakerId) {
                $speakerAggId = generateAggregationId('speaker', null, $word, $speakerId);
                $statisticsUpdates[] = [
                    'id' => $speakerAggId,
                    'data' => [
                        'aggregation_type' => 'speaker_word_frequency',
                        'speaker_id' => $speakerId,
                        'word' => $word,
                        'count' => $count,
                        'speech_count' => 1,
                        'first_used' => $dateTimestamp,
                        'last_used' => $dateTimestamp,
                        'first_used_string' => $dateString,
                        'last_used_string' => $dateString,
                        'last_updated' => $today
                    ]
                ];
            }
        }
        
        return $statisticsUpdates;
        
    } catch (Exception $e) {
        error_log("Error extracting statistics from speech {$speechId}: " . $e->getMessage());
        return [];
    }
}

