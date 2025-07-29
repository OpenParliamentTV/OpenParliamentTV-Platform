<?php

/**
 * Enhanced autocomplete using statistics index
 */
function searchAutocompleteEnhanced($query, $maxResults = 10, $partyFilter = null, $parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return [];
    }
    
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    
    $mustClauses = [
        ['term' => ['aggregation_type' => 'word_frequency_daily_faction']]
    ];
    
    // Add word matching
    $shouldClauses = [
        ['prefix' => ['word' => ['value' => strtolower($query), 'boost' => 3]]],
        ['wildcard' => ['word' => ['value' => '*' . strtolower($query) . '*', 'boost' => 1]]]
    ];
    
    if ($partyFilter) {
        $mustClauses[] = ['term' => ['faction_id' => $partyFilter]];
    }
    
    $searchQuery = [
        'size' => 0,
        'query' => [
            'bool' => [
                'must' => $mustClauses,
                'should' => $shouldClauses,
                'minimum_should_match' => 1
            ]
        ],
        'aggs' => [
            'autocomplete_words' => [
                'terms' => [
                    'field' => 'word',
                    'size' => $maxResults,
                    'order' => ['total_frequency' => 'desc']
                ],
                'aggs' => [
                    'total_frequency' => ['sum' => ['field' => 'count']],
                    'speech_count' => ['sum' => ['field' => 'speech_count']],
                    'last_used' => ['max' => ['field' => 'date']],
                    'last_used_string' => ['max' => ['field' => 'date_string']]
                ]
            ]
        ]
    ];
    
    try {
        $results = $ESClient->search(['index' => $indexName, 'body' => $searchQuery]);
        
        $autocompleteResults = [];
        if (isset($results['aggregations']['autocomplete_words']['buckets'])) {
            foreach ($results['aggregations']['autocomplete_words']['buckets'] as $bucket) {
                $autocompleteResults[] = [
                    'text' => $bucket['key'],
                    'frequency' => $bucket['total_frequency']['value'],
                    'speech_count' => $bucket['speech_count']['value'],
                    'last_used' => $bucket['last_used']['value'],
                    'last_used_string' => $bucket['last_used_string']['value_as_string'] ?? null,
                    'type' => strpos($bucket['key'], strtolower($query)) === 0 ? 'prefix' : 'substring'
                ];
            }
        }
        
        return $autocompleteResults;
    } catch (Exception $e) {
        error_log("Enhanced autocomplete error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get word trends over time
 */
function getWordTrendsEnhanced($words, $startDate, $endDate, $parliamentCode = 'de', $factions = []) {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    
    $mustClauses = [
        ['terms' => ['word' => $words]],
        ['term' => ['aggregation_type' => 'word_frequency_daily_faction']],
        ['range' => ['date' => ['gte' => strtotime($startDate), 'lte' => strtotime($endDate)]]]
    ];
    
    // Add faction filter if specified
    if (!empty($factions)) {
        $mustClauses[] = ['terms' => ['faction_id' => $factions]];
    }
    
    $query = [
        'size' => 0,
        'query' => [
            'bool' => [
                'must' => $mustClauses
            ]
        ],
        'aggs' => [
            'words_over_time' => [
                'terms' => ['field' => 'word', 'size' => count($words)],
                'aggs' => [
                    'time_series' => [
                        'date_histogram' => [
                            'field' => 'date',
                            'calendar_interval' => '1M',
                            'format' => 'yyyy-MM'
                        ],
                        'aggs' => [
                            'total_count' => ['sum' => ['field' => 'count']],
                            'speech_count' => ['sum' => ['field' => 'speech_count']]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    try {
        $results = $ESClient->search(['index' => $indexName, 'body' => $query]);
        return ['success' => true, 'data' => $results['aggregations']];
    } catch (Exception $e) {
        error_log("Word trends error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get speaker vocabulary analysis
 */
function getSpeakerVocabularyEnhanced($speakerId, $limit = 50, $parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    
    $query = [
        'size' => 0,
        'query' => [
            'bool' => [
                'must' => [
                    ['term' => ['aggregation_type' => 'speaker_word_frequency']],
                    ['term' => ['speaker_id' => $speakerId]]
                ]
            ]
        ],
        'aggs' => [
            'top_words' => [
                'terms' => [
                    'field' => 'word',
                    'size' => $limit,
                    'order' => ['frequency' => 'desc']
                ],
                'aggs' => [
                    'frequency' => ['max' => ['field' => 'count']],
                    'speech_count' => ['max' => ['field' => 'speech_count']],
                    'first_used' => ['min' => ['field' => 'first_used']],
                    'last_used' => ['max' => ['field' => 'last_used']]
                ]
            ],
            'total_words' => ['sum' => ['field' => 'count']],
            'unique_words' => ['cardinality' => ['field' => 'word']]
        ]
    ];
    
    try {
        $results = $ESClient->search(['index' => $indexName, 'body' => $query]);
        return ['success' => true, 'data' => $results['aggregations']];
    } catch (Exception $e) {
        error_log("Speaker vocabulary error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


/**
 * Test enhanced query functions
 */
function testEnhancedQueries($parliamentCode = 'de') {
    $results = [];
    
    // Test autocomplete
    $autocompleteResult = searchAutocompleteEnhanced('test', 5, null, $parliamentCode);
    $results['autocomplete'] = [
        'success' => !empty($autocompleteResult),
        'count' => count($autocompleteResult),
        'sample' => array_slice($autocompleteResult, 0, 3)
    ];
    
    // Test word trends
    $trendsResult = getWordTrendsEnhanced(['test'], '2024-01-01', '2024-12-31', $parliamentCode);
    $results['trends'] = $trendsResult;
    
    // Test speaker vocabulary
    $speakerResult = getSpeakerVocabularyEnhanced('test_speaker', 10, $parliamentCode);
    $results['speaker_vocab'] = $speakerResult;
    
    
    return $results;
}