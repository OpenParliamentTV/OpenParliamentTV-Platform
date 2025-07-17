<?php

require_once(__DIR__.'/../utilities/functions.api.php');
require_once(__DIR__.'/../search/functions.php');

/**
 * Create word events index mapping
 */
function createWordEventsIndex($parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $indexName = 'optv_word_events_' . strtolower($parliamentCode);
    
    $mapping = [
        'mappings' => [
            'properties' => [
                'word' => [
                    'type' => 'text',
                    'analyzer' => 'german',
                    'fields' => [
                        'keyword' => ['type' => 'keyword']
                    ]
                ],
                'speech_id' => ['type' => 'keyword'],
                'speaker_id' => ['type' => 'keyword'],
                'party_id' => ['type' => 'keyword'],
                'party_label' => ['type' => 'keyword'],
                'date' => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'position_in_speech' => ['type' => 'integer'],
                'time_start' => ['type' => 'float'],
                'time_end' => ['type' => 'float'],
                'sentence_id' => ['type' => 'keyword'],
                'position_in_sentence' => ['type' => 'integer']
            ]
        ],
        'settings' => [
            'number_of_shards' => 2,
            'number_of_replicas' => 0,
            'index.mapping.total_fields.limit' => 2000,
            'refresh_interval' => '30s',
            'codec' => 'best_compression',
            'analysis' => [
                'analyzer' => [
                    'german_word_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'german_stemmer']
                    ]
                ],
                'filter' => [
                    'german_stemmer' => [
                        'type' => 'stemmer',
                        'name' => 'light_german'
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
        error_log("Error creating word events index: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

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
                'party_id' => ['type' => 'keyword'],
                'party_label' => ['type' => 'keyword'],
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
 * Delete enhanced indices for clean rebuild
 */
function deleteEnhancedIndices($parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $results = [];
    $wordEventsIndex = 'optv_word_events_' . strtolower($parliamentCode);
    $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
    
    // Delete word events index
    try {
        if ($ESClient->indices()->exists(['index' => $wordEventsIndex])) {
            $response = $ESClient->indices()->delete(['index' => $wordEventsIndex]);
            $results['word_events'] = ['success' => true, 'message' => 'Word events index deleted', 'response' => $response];
        } else {
            $results['word_events'] = ['success' => true, 'message' => 'Word events index did not exist'];
        }
    } catch (Exception $e) {
        $results['word_events'] = ['success' => false, 'error' => $e->getMessage()];
        error_log("Error deleting word events index: " . $e->getMessage());
    }
    
    // Delete statistics index
    try {
        if ($ESClient->indices()->exists(['index' => $statisticsIndex])) {
            $response = $ESClient->indices()->delete(['index' => $statisticsIndex]);
            $results['statistics'] = ['success' => true, 'message' => 'Statistics index deleted', 'response' => $response];
        } else {
            $results['statistics'] = ['success' => true, 'message' => 'Statistics index did not exist'];
        }
    } catch (Exception $e) {
        $results['statistics'] = ['success' => false, 'error' => $e->getMessage()];
        error_log("Error deleting statistics index: " . $e->getMessage());
    }
    
    // Overall success if both operations succeeded
    $overallSuccess = $results['word_events']['success'] && $results['statistics']['success'];
    
    return [
        'success' => $overallSuccess,
        'results' => $results,
        'message' => $overallSuccess ? 'Enhanced indices cleaned successfully' : 'Some indices failed to delete'
    ];
}

/**
 * Create enhanced indices with clean rebuild option
 */
function createEnhancedIndices($parliamentCode = 'de', $cleanRebuild = false) {
    $results = [];
    
    // Clean rebuild: delete existing indices first
    if ($cleanRebuild) {
        $deleteResult = deleteEnhancedIndices($parliamentCode);
        $results['cleanup'] = $deleteResult;
        
        if (!$deleteResult['success']) {
            return [
                'success' => false,
                'error' => 'Failed to clean existing indices for rebuild',
                'details' => $deleteResult
            ];
        }
    }
    
    // Create word events index
    $wordEventsResult = createWordEventsIndex($parliamentCode);
    $results['word_events'] = $wordEventsResult;
    
    // Create statistics index
    $statisticsResult = createStatisticsIndex($parliamentCode);
    $results['statistics'] = $statisticsResult;
    
    // Overall success
    $overallSuccess = $wordEventsResult['success'] && $statisticsResult['success'];
    
    return [
        'success' => $overallSuccess,
        'results' => $results,
        'message' => $overallSuccess ? 'Enhanced indices created successfully' : 'Some indices failed to create'
    ];
}

/**
 * Test index creation for both new indices
 */
function testEnhancedIndexCreation($parliamentCode = 'de') {
    return createEnhancedIndices($parliamentCode, false);
}

/**
 * Update word events index for a specific speech (incremental)
 */
function updateWordEventsForSpeech($parliamentCode, $speechId, $speechData) {
    try {
        require_once(__DIR__ . '/../utilities/functions.api.php');
        require_once(__DIR__ . '/functions.wordEvents.php');
        
        $ESClient = getApiOpenSearchClient();
        if (is_array($ESClient) && isset($ESClient["errors"])) {
            return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
        }
        
        $indexName = 'optv_word_events_' . strtolower($parliamentCode);
        
        // First, delete existing word events for this speech to avoid duplicates
        $deleteQuery = [
            'query' => [
                'term' => ['speech_id' => $speechId]
            ]
        ];
        
        try {
            $ESClient->deleteByQuery([
                'index' => $indexName,
                'body' => $deleteQuery
            ]);
        } catch (Exception $e) {
            // Index might not exist or no documents to delete - that's fine
        }
        
        // Extract word events from speech data
        $wordEvents = extractWordEventsFromSpeech($speechId, $speechData);
        
        if (empty($wordEvents)) {
            return ['success' => true, 'message' => 'No word events to index - metadata-only speech'];
        }
        
        // Bulk index the word events
        $bulkParams = ['body' => []];
        foreach ($wordEvents as $wordEvent) {
            $bulkParams['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $wordEvent['id'] ?? null
                ]
            ];
            $bulkParams['body'][] = $wordEvent;
        }
        
        $response = $ESClient->bulk($bulkParams);
        
        if (isset($response['errors']) && $response['errors']) {
            return ['success' => false, 'error' => 'Bulk indexing failed for word events'];
        }
        
        return ['success' => true, 'message' => 'Word events updated for speech ' . $speechId];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
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
 * Extract word events from speech data for incremental indexing
 */
function extractWordEventsFromSpeech($speechId, $speechData) {
    try {
        require_once(__DIR__ . '/functions.extraction.php');
        require_once(__DIR__ . '/functions.textprocessing.php');
        
        global $config;
        
        // Extract speech metadata
        $date = extractSpeechDateString($speechData);
        $speakerId = extractMainSpeakerId($speechData);
        $party = extractMainSpeakerFaction($speechData);
        
        // Check if speech has any text content
        if (!isset($speechData['attributes']['textContents']) || 
            !is_array($speechData['attributes']['textContents']) ||
            empty($speechData['attributes']['textContents'])) {
            return [];
        }
        
        // Check textContentsCount if available
        if (isset($speechData['attributes']['textContentsCount']) && $speechData['attributes']['textContentsCount'] == 0) {
            return [];
        }
        
        $wordEvents = [];
        $position = 0;
        
        // Process text contents and extract word events
        foreach ($speechData['attributes']['textContents'] as $textContent) {
            if (!isset($textContent['textBody']) || !is_array($textContent['textBody'])) continue;
            
            foreach ($textContent['textBody'] as $textBodyItem) {
                if (!isset($textBodyItem['sentences']) || !is_array($textBodyItem['sentences'])) continue;
                
                foreach ($textBodyItem['sentences'] as $sentence) {
                    if (!isset($sentence['text']) || empty($sentence['text'])) continue;
                    
                    $sentenceText = $sentence['text'];
                    $timeStart = isset($sentence['timeStart']) ? floatval($sentence['timeStart']) : null;
                    $timeEnd = isset($sentence['timeEnd']) ? floatval($sentence['timeEnd']) : null;
                    $words = tokenizeWords($sentenceText);
                    
                    foreach ($words as $word) {
                        $normalizedWord = normalizeWord($word);
                        
                        // Skip stopwords for events index
                        if (isStopword($normalizedWord, $config)) {
                            $position++;
                            continue;
                        }
                        
                        $wordEvents[] = [
                            'id' => $speechId . '_' . $position,
                            'word' => $normalizedWord,
                            'speech_id' => $speechId,
                            'speaker_id' => $speakerId,
                            'party_id' => $party['id'] ?? null,
                            'party_label' => $party['label'] ?? null,
                            'date' => $date,
                            'position_in_speech' => $position,
                            'time_start' => $timeStart,
                            'time_end' => $timeEnd,
                            'sentence_context' => truncateContext($sentenceText, 200)
                        ];
                        
                        $position++;
                    }
                }
            }
        }
        
        return $wordEvents;
        
    } catch (Exception $e) {
        error_log("Error extracting word events from speech {$speechId}: " . $e->getMessage());
        return [];
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
        $party = extractMainSpeakerFaction($speechData);
        
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
            // Daily party aggregation
            $dailyPartyId = generateAggregationId('daily_party', $dateString, $word, $party['id'] ?? null);
            $statisticsUpdates[] = [
                'id' => $dailyPartyId,
                'data' => [
                    'aggregation_type' => 'word_frequency_daily_party',
                    'date' => $dateTimestamp,
                    'date_string' => $dateString,
                    'word' => $word,
                    'party_id' => $party['id'] ?? null,
                    'party_label' => $party['label'] ?? null,
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

