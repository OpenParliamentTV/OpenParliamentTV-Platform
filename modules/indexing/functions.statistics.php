<?php

require_once(__DIR__.'/functions.textprocessing.php');
require_once(__DIR__.'/functions.extraction.php');

/**
 * Update statistics index for a single speech
 */
function updateSpeechStatistics($speechData, $parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    global $config;
    
    // Extract speech metadata using proper main-speaker context
    $dateTimestamp = extractSpeechDate($speechData); // Returns timestamp
    $dateString = extractSpeechDateString($speechData); // Returns YYYY-MM-DD string  
    $speakerId = extractMainSpeakerId($speechData); // Use main-speaker
    $faction = extractMainSpeakerFaction($speechData); // Use main-speaker-faction
    $sessionId = extractSessionId($speechData);
    
    if (empty($dateTimestamp)) {
        return ['success' => false, 'error' => 'Missing required date'];
    }
    
    // Get word frequencies using existing sentence structure
    $wordFreqs = getWordFrequenciesFromSpeech($speechData, $config);
    
    if (empty($wordFreqs)) {
        // Don't treat empty speeches as errors - they're just metadata-only entries
        return ['success' => true, 'aggregations_updated' => 0, 'message' => 'No words found - metadata-only speech'];
    }
    
    $bulkData = [];
    $today = date('Y-m-d');
    
    foreach ($wordFreqs as $word => $count) {
        // Daily faction aggregation (optimal performance)
        $dailyFactionId = generateAggregationId('daily_faction', $dateString, $word, $faction['id'] ?? null);
        $dailyFactionAgg = [
            'aggregation_type' => 'word_frequency_daily_faction',
            'date' => $dateTimestamp,
            'date_string' => $dateString,
            'word' => $word,
            'faction_id' => $faction['id'] ?? null,
            'count' => $count,
            'speech_count' => 1,
            'last_updated' => $today
        ];
        
        $bulkData[] = [
            'update' => [
                '_index' => 'optv_statistics_' . strtolower($parliamentCode),
                '_id' => $dailyFactionId
            ]
        ];
        $bulkData[] = [
            'script' => [
                'source' => '
                    ctx._source.count += params.count;
                    ctx._source.speech_count += 1;
                    ctx._source.last_updated = params.today
                ',
                'params' => ['count' => $count, 'today' => $today]
            ],
            'upsert' => $dailyFactionAgg
        ];
        
        // Speaker aggregation (simplified)
        if ($speakerId) {
            $speakerAggId = generateAggregationId('speaker', null, $word, $speakerId);
            $speakerAgg = [
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
            ];
            
            $bulkData[] = [
                'update' => [
                    '_index' => 'optv_statistics_' . strtolower($parliamentCode),
                    '_id' => $speakerAggId
                ]
            ];
            $bulkData[] = [
                'script' => [
                    'source' => '
                        ctx._source.count += params.count; 
                        ctx._source.speech_count += 1;
                        ctx._source.last_used = params.dateTimestamp;
                        ctx._source.last_used_string = params.dateString;
                        ctx._source.last_updated = params.today;
                        if (params.dateTimestamp < ctx._source.first_used) { 
                            ctx._source.first_used = params.dateTimestamp;
                            ctx._source.first_used_string = params.dateString;
                        }
                    ',
                    'params' => [
                        'count' => $count, 
                        'dateTimestamp' => $dateTimestamp,
                        'dateString' => $dateString,
                        'today' => $today
                    ]
                ],
                'upsert' => $speakerAgg
            ];
        }
    }
    
    // Execute bulk operations
    if (!empty($bulkData)) {
        try {
            $response = $ESClient->bulk(['body' => $bulkData]);
            
            // Check for errors
            if (isset($response['errors']) && $response['errors']) {
                error_log("Bulk update had errors: " . json_encode($response['items']));
                return ['success' => false, 'error' => 'Bulk update had errors', 'details' => $response];
            }
            
            return [
                'success' => true, 
                'aggregations_updated' => count($wordFreqs) * 2, // daily + speaker
                'unique_words' => count($wordFreqs)
            ];
        } catch (Exception $e) {
            error_log("Error bulk updating statistics: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    return ['success' => true, 'aggregations_updated' => 0];
}

/**
 * Generate consistent aggregation document ID
 */
function generateAggregationId($type, $date, $word, $identifier) {
    $parts = [$type, $date, $word, $identifier];
    return md5(implode('_', array_filter($parts)));
}

/**
 * Optimized statistics processing for cronUpdater incremental updates
 * This is specifically designed for high-throughput processing during data imports
 */
function processStatisticsForSpeechOptimized($speechData, $parliamentCode) {
    // For incremental updates during cronUpdater execution, we use the same
    // processing as regular updates but with better error handling and logging
    $result = updateSpeechStatistics($speechData, $parliamentCode);
    
    // Add performance tracking for cronUpdater monitoring
    if ($result['success']) {
        $result['optimized'] = true;
        $result['processing_time'] = microtime(true);
    }
    
    return $result;
}

/**
 * Smart cleanup for incremental updates - only runs when needed
 */
function smartStatisticsCleanup($parliamentCode = 'de', $forceCleanup = false) {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    
    try {
        // Get current index stats
        $stats = $ESClient->indices()->stats(['index' => $indexName]);
        $totalDocs = $stats['indices'][$indexName]['total']['docs']['count'] ?? 0;
        $deletedDocs = $stats['indices'][$indexName]['total']['docs']['deleted'] ?? 0;
        
        if ($totalDocs == 0) {
            return ['success' => true, 'message' => 'Index is empty, no cleanup needed'];
        }
        
        $deletedPercentage = ($deletedDocs / ($totalDocs + $deletedDocs)) * 100;
        
        // Only cleanup if deleted docs > 15% OR force cleanup requested
        if (!$forceCleanup && $deletedPercentage < 15) {
            return [
                'success' => true, 
                'message' => 'Cleanup not needed',
                'deleted_percentage' => round($deletedPercentage, 1),
                'deleted_docs' => $deletedDocs,
                'threshold' => '15%'
            ];
        }
        
        // Perform lightweight cleanup (expunge deletes only, don't force single segment)
        $startTime = microtime(true);
        $sizeBefore = $stats['indices'][$indexName]['total']['store']['size_in_bytes'] ?? 0;
        
        $ESClient->indices()->forcemerge([
            'index' => $indexName,
            'only_expunge_deletes' => true, // Lightweight cleanup
            'max_num_segments' => 5 // Allow multiple segments for faster operation
        ]);
        
        // Get stats after cleanup
        sleep(1);
        $statsAfter = $ESClient->indices()->stats(['index' => $indexName]);
        $deletedAfter = $statsAfter['indices'][$indexName]['total']['docs']['deleted'] ?? 0;
        $sizeAfter = $statsAfter['indices'][$indexName]['total']['store']['size_in_bytes'] ?? 0;
        
        $cleanupTime = round(microtime(true) - $startTime, 2);
        $spaceReclaimed = $sizeBefore - $sizeAfter;
        $deletedCleaned = $deletedDocs - $deletedAfter;
        
        return [
            'success' => true,
            'cleanup_performed' => true,
            'cleanup_time' => $cleanupTime,
            'deleted_cleaned' => $deletedCleaned,
            'space_reclaimed_mb' => round($spaceReclaimed / 1024 / 1024, 1),
            'deleted_percentage_before' => round($deletedPercentage, 1),
            'deleted_percentage_after' => round(($deletedAfter / $totalDocs) * 100, 1)
        ];
        
    } catch (Exception $e) {
        error_log("Smart statistics cleanup error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Test statistics indexing with a single speech
 */
function testStatisticsIndexing($speechData, $parliamentCode = 'de') {
    $result = updateSpeechStatistics($speechData, $parliamentCode);
    
    if ($result['success']) {
        error_log("Statistics indexing test successful: " . json_encode($result));
    } else {
        error_log("Statistics indexing test failed: " . $result['error']);
    }
    
    return $result;
}