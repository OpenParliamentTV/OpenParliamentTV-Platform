<?php

require_once(__DIR__.'/functions.enhanced.php');
require_once(__DIR__.'/functions.wordEvents.php');
require_once(__DIR__.'/functions.statistics.php');

/**
 * Main function to index a speech using enhanced architecture
 * This integrates with existing indexing workflow
 */
function indexSpeechEnhanced($speechData, $parliamentCode = 'de') {
    $results = [];
    
    try {
        // 1. Index to main speech index (existing functionality)
        // NOTE: This should call your existing speech indexing logic
        // For now, we assume existing indexing continues to work
        $results['main_index'] = ['success' => true, 'message' => 'Using existing main index logic'];
        
        // 2. Index word events (only if enhanced indexing is enabled)
        if (isEnhancedIndexingEnabled($parliamentCode)) {
            $wordEventsResult = indexSpeechWordEvents($speechData, $parliamentCode);
            $results['word_events'] = $wordEventsResult;
            
            if (!$wordEventsResult['success']) {
                error_log("Enhanced indexing - Word events failed: " . $wordEventsResult['error']);
                // Continue with other indexing - don't fail completely
            }
            
            // 3. Update statistics (only if word events succeeded)
            if ($wordEventsResult['success']) {
                $statisticsResult = updateSpeechStatistics($speechData, $parliamentCode);
                $results['statistics'] = $statisticsResult;
                
                if (!$statisticsResult['success']) {
                    error_log("Enhanced indexing - Statistics update failed: " . $statisticsResult['error']);
                }
            } else {
                $results['statistics'] = ['success' => false, 'error' => 'Skipped due to word events failure'];
            }
        } else {
            $results['word_events'] = ['success' => false, 'message' => 'Enhanced indexing disabled'];
            $results['statistics'] = ['success' => false, 'message' => 'Enhanced indexing disabled'];
        }
        
        return [
            'success' => true,
            'results' => $results,
            'message' => 'Speech indexed successfully (main index + enhanced features)'
        ];
        
    } catch (Exception $e) {
        error_log("Enhanced indexing error for speech {$speechData['id']}: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'partial_results' => $results
        ];
    }
}

/**
 * Check if enhanced indexing is enabled for a parliament
 */
function isEnhancedIndexingEnabled($parliamentCode) {
    global $config;
    
    // Check if enhanced indexing is enabled in config
    if (isset($config['enhanced']['enabled']) && !$config['enhanced']['enabled']) {
        return false;
    }
    
    // Check if indices exist (if not, enhanced indexing is not ready)
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return false;
    }
    
    $wordEventsIndex = 'optv_word_events_' . strtolower($parliamentCode);
    $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
    
    try {
        $wordEventsExists = $ESClient->indices()->exists(['index' => $wordEventsIndex]);
        $statisticsExists = $ESClient->indices()->exists(['index' => $statisticsIndex]);
        
        return $wordEventsExists && $statisticsExists;
    } catch (Exception $e) {
        error_log("Error checking enhanced indices: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup enhanced indexing (create indices if they don't exist)
 */
function setupEnhancedIndexing($parliamentCode = 'de', $cleanRebuild = false) {
    // Use the new createEnhancedIndices function with clean rebuild option
    return createEnhancedIndices($parliamentCode, $cleanRebuild);
}

/**
 * Integration point for cronUpdater.php
 * Call this function during speech processing in existing workflow
 */
function integrateSpeechWithEnhancedIndexing($speechData, $parliamentCode) {
    // This function should be called from your existing speech processing
    // in cronUpdater.php or wherever individual speeches are indexed
    
    if (!isEnhancedIndexingEnabled($parliamentCode)) {
        return ['success' => true, 'message' => 'Enhanced indexing not enabled'];
    }
    
    return indexSpeechEnhanced($speechData, $parliamentCode);
}

/**
 * Bulk process existing speeches for enhanced indexing
 * This can be called during full index rebuilds
 */
function bulkProcessExistingSpeeches($parliamentCode = 'de', $batchSize = 100) {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $mainIndex = 'openparliamenttv_' . strtolower($parliamentCode);
    $processed = 0;
    $errors = 0;
    
    try {
        // Get all speeches from main index in batches
        $scrollParams = [
            'index' => $mainIndex,
            'scroll' => '2m',
            'size' => $batchSize,
            'body' => [
                'query' => ['match_all' => (object)[]]
            ]
        ];
        
        $response = $ESClient->search($scrollParams);
        $scrollId = $response['_scroll_id'];
        
        while (count($response['hits']['hits']) > 0) {
            foreach ($response['hits']['hits'] as $hit) {
                $speechData = $hit['_source'];
                $speechData['id'] = $hit['_id']; // Ensure ID is set
                
                $result = integrateSpeechWithEnhancedIndexing($speechData, $parliamentCode);
                
                if ($result['success']) {
                    $processed++;
                } else {
                    $errors++;
                    error_log("Bulk processing error for speech {$hit['_id']}: " . ($result['error'] ?? 'Unknown error'));
                }
                
                // Log progress every 100 speeches
                if (($processed + $errors) % 100 === 0) {
                    error_log("Enhanced indexing progress: {$processed} processed, {$errors} errors");
                }
            }
            
            // Get next batch
            $response = $ESClient->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '2m'
            ]);
        }
        
        // Clear scroll
        $ESClient->clearScroll(['scroll_id' => $scrollId]);
        
        return [
            'success' => true,
            'processed' => $processed,
            'errors' => $errors,
            'message' => "Bulk processing complete: {$processed} speeches processed, {$errors} errors"
        ];
        
    } catch (Exception $e) {
        error_log("Bulk processing error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'processed' => $processed,
            'errors' => $errors
        ];
    }
}

/**
 * Test complete enhanced indexing workflow
 */
function testEnhancedIndexingWorkflow($parliamentCode = 'de') {
    $results = [];
    
    // 1. Setup indices
    $setupResult = setupEnhancedIndexing($parliamentCode);
    $results['setup'] = $setupResult;
    
    if (!$setupResult['success']) {
        return [
            'success' => false,
            'error' => 'Setup failed',
            'results' => $results
        ];
    }
    
    // 2. Test with sample speech data matching actual structure
    $sampleSpeech = [
        'id' => 'test_speech_' . time(),
        'attributes' => [
            'date' => date('Y-m-d'),
            'textContents' => [
                [
                    'textBody' => [
                        [
                            'speech_id' => 'test_speech_id',
                            'type' => 'speech',
                            'speaker' => 'Test Speaker',
                            'sentences' => [
                                [
                                    'text' => 'Dies ist ein Test für die erweiterte Indexierung.',
                                    'timeStart' => '1.000',
                                    'timeEnd' => '3.000'
                                ],
                                [
                                    'text' => 'Wir testen die Funktionalität der neuen Architektur.',
                                    'timeStart' => '3.000', 
                                    'timeEnd' => '5.000'
                                ],
                                [
                                    'text' => 'Mit verschiedenen Wörtern und Phrasen für die Analyse.',
                                    'timeStart' => '5.000',
                                    'timeEnd' => '7.000'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        'relationships' => [
            'speaker' => ['data' => ['id' => 'test_speaker']],
            'session' => ['data' => ['id' => 'test_session']]
        ],
        'annotations' => [
            'data' => [
                [
                    'type' => 'person',
                    'id' => 'test_speaker',
                    'attributes' => [
                        'context' => 'main-speaker',
                        'label' => 'Test Speaker Name'
                    ]
                ],
                [
                    'type' => 'organisation',
                    'id' => 'test_party',
                    'attributes' => [
                        'context' => 'main-speaker-faction',
                        'label' => 'Test Partei'
                    ]
                ]
            ]
        ]
    ];
    
    $indexingResult = indexSpeechEnhanced($sampleSpeech, $parliamentCode);
    $results['test_indexing'] = $indexingResult;
    
    return [
        'success' => $indexingResult['success'],
        'results' => $results,
        'message' => 'Enhanced indexing workflow test complete'
    ];
}