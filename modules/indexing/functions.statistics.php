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
    $party = extractMainSpeakerFaction($speechData); // Use main-speaker-faction
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
        // Daily party aggregation
        $dailyPartyId = generateAggregationId('daily_party', $dateString, $word, $party['id'] ?? null);
        $dailyPartyAgg = [
            'aggregation_type' => 'word_frequency_daily_party',
            'date' => $dateTimestamp,
            'date_string' => $dateString,
            'word' => $word,
            'party_id' => $party['id'] ?? null,
            'party_label' => $party['label'] ?? null,
            'count' => $count,
            'speech_count' => 1,
            'speech_ids' => [$speechData['id'] ?? null => true],
            'last_updated' => $today
        ];
        
        $bulkData[] = [
            'update' => [
                '_index' => 'optv_statistics_' . strtolower($parliamentCode),
                '_id' => $dailyPartyId
            ]
        ];
        $bulkData[] = [
            'script' => [
                'source' => '
                    ctx._source.count += params.count;
                    if (!ctx._source.containsKey("speech_ids")) {
                        ctx._source.speech_ids = [:];
                    }
                    if (params.speechId != null && !ctx._source.speech_ids.containsKey(params.speechId)) {
                        ctx._source.speech_ids[params.speechId] = true;
                        ctx._source.speech_count = ctx._source.speech_ids.size();
                    }
                    ctx._source.last_updated = params.today
                ',
                'params' => ['count' => $count, 'speechId' => $speechData['id'] ?? null, 'today' => $today]
            ],
            'upsert' => $dailyPartyAgg
        ];
        
        // Speaker aggregation
        if ($speakerId) {
            $speakerId_clean = $speakerId;
            $speakerAggId = generateAggregationId('speaker', null, $word, $speakerId_clean);
            $speakerAgg = [
                'aggregation_type' => 'speaker_word_frequency',
                'speaker_id' => $speakerId_clean,
                'word' => $word,
                'count' => $count,
                'speech_count' => 1,
                'speech_ids' => [$speechData['id'] ?? null => true],
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
                        if (!ctx._source.containsKey("speech_ids")) {
                            ctx._source.speech_ids = [:];
                        }
                        if (params.speechId != null && !ctx._source.speech_ids.containsKey(params.speechId)) {
                            ctx._source.speech_ids[params.speechId] = true;
                            ctx._source.speech_count = ctx._source.speech_ids.size();
                        } 
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
                        'speechId' => $speechData['id'] ?? null,
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