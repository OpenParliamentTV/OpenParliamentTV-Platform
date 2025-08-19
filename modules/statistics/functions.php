<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/search/functions.php');
require_once(__DIR__.'/../utilities/functions.api.php');

// Debug flag - set to 1 to enable debug logging
$DEBUG_MODE = 0;

// Initialize OpenSearch client using centralized method
$ESClient = getApiOpenSearchClient();
if (is_array($ESClient) && isset($ESClient["errors"])) {
    // Handle error case - log and set to null
    error_log("Failed to initialize OpenSearch client in statistics functions: " . json_encode($ESClient));
    $ESClient = null;
}

/**
 * Get general statistics about the dataset
 * 
 * @return array Statistics about the dataset
 */
function getGeneralStatistics() {
    global $ESClient, $DEBUG_MODE, $config;
    
    // --- Use enhanced statistics index for word frequency ---
    $statisticsIndex = 'optv_statistics_de'; // TODO: Support other parliaments
    
    // Get total word count and top 20 words by frequency from enhanced statistics
    $totalUniqueWords = 0;
    $topWords = [];
    $contextBasedStats = [];
    
    try {
        // Enhanced query to leverage speaker context groupings from planning docs
        $enhancedWordsQuery = [
            'size' => 0,
            'query' => [
                'term' => ['aggregation_type' => 'word_frequency_daily_faction']
            ],
            'aggs' => [
                // Top words overall
                'top_words' => [
                    'terms' => [
                        'field' => 'word',
                        'size' => 20,
                        'order' => ['total_frequency' => 'desc']
                    ],
                    'aggs' => [
                        'total_frequency' => ['sum' => ['field' => 'count']]
                    ]
                ],
                // Enhanced: Aggregation type groupings (actual field from real data)
                'by_aggregation_type' => [
                    'terms' => ['field' => 'aggregation_type'],
                    'aggs' => [
                        'top_words' => [
                            'terms' => [
                                'field' => 'word',
                                'size' => 10,
                                'order' => ['total_frequency' => 'desc']
                            ],
                            'aggs' => [
                                'total_frequency' => ['sum' => ['field' => 'count']]
                            ]
                        ]
                    ]
                ],
                // Enhanced: Faction-specific analysis as recommended  
                'by_faction' => [
                    'terms' => ['field' => 'faction_id.keyword', 'size' => 10],
                    'aggs' => [
                        'top_words' => [
                            'terms' => [
                                'field' => 'word',
                                'size' => 20,
                                'order' => ['total_frequency' => 'desc']
                            ],
                            'aggs' => [
                                'total_frequency' => ['sum' => ['field' => 'count']]
                            ]
                        ]
                    ]
                ],
                'total_unique_words' => [
                    'cardinality' => ['field' => 'word']
                ]
            ]
        ];
        
        $wordsResults = $ESClient->search(['index' => $statisticsIndex, 'body' => $enhancedWordsQuery]);
        
        // Process overall top words
        if (isset($wordsResults['aggregations']['top_words']['buckets'])) {
            foreach ($wordsResults['aggregations']['top_words']['buckets'] as $bucket) {
                $topWords[] = [
                    'key' => $bucket['key'],
                    'doc_count' => $bucket['total_frequency']['value']
                ];
            }
        }
        
        // Process enhanced aggregation-type-based statistics from real data  
        if (isset($wordsResults['aggregations']['by_aggregation_type']['buckets'])) {
            foreach ($wordsResults['aggregations']['by_aggregation_type']['buckets'] as $aggBucket) {
                $aggregationType = $aggBucket['key'];
                $aggStats = ['aggregationType' => $aggregationType, 'topWords' => []];
                
                if (isset($aggBucket['top_words']['buckets'])) {
                    foreach ($aggBucket['top_words']['buckets'] as $wordBucket) {
                        $aggStats['topWords'][] = [
                            'word' => $wordBucket['key'],
                            'count' => $wordBucket['total_frequency']['value']
                        ];
                    }
                }
                $contextBasedStats[] = $aggStats;
            }
        }
        
        // Process faction-based statistics from real data
        if (isset($wordsResults['aggregations']['by_faction']['buckets'])) {
            $factionStats = ['type' => 'by_faction', 'factions' => []];
            foreach ($wordsResults['aggregations']['by_faction']['buckets'] as $factionBucket) {
                $factionID = $factionBucket['key'];
                $factionInfo = ['factionID' => $factionID, 'topWords' => []];
                
                if (isset($factionBucket['top_words']['buckets'])) {
                    foreach ($factionBucket['top_words']['buckets'] as $wordBucket) {
                        $factionInfo['topWords'][] = [
                            'word' => $wordBucket['key'],
                            'count' => $wordBucket['total_frequency']['value']
                        ];
                    }
                }
                $factionStats['factions'][] = $factionInfo;
            }
            $contextBasedStats[] = $factionStats;
        }
        
        if (isset($wordsResults['aggregations']['total_unique_words']['value'])) {
            $totalUniqueWords = $wordsResults['aggregations']['total_unique_words']['value'];
        }
        
    } catch (Exception $e) {
        error_log('Error fetching words from enhanced statistics index: ' . $e->getMessage());
        
        // No fallback available - enhanced indexing is required
        error_log('Enhanced statistics index is required for word frequency data');
    }
    // --- End enhanced statistics index ---

    // Main aggregation query for all statistics
    $query = [
        'size' => 0,
        'query' => ['match_all' => (object)[]],
        'aggs' => [
            'speakers' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'filtered_speakers' => [
                        'filter' => [ 
                            'bool' => [
                                'must' => [
                                    ['term' => [ 'annotations.data.attributes.context' => 'main-speaker' ]],
                                    ['term' => [ 'annotations.data.type' => 'person' ]]
                                ]
                            ]
                        ],
                        'aggs' => [
                            'unique_speakers' => [ 'cardinality' => [ 'field' => 'annotations.data.id' ] ],
                            'top_speakers' => [ 
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                'aggs' => [
                                    'unique_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speech_count' => [ 'cardinality' => [ 'field' => '_id' ] ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'speakers_by_faction' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'faction_filter' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [
                            'factions' => [
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                'aggs' => [
                                    'back_to_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speakers_in_faction' => [
                                                'nested' => [ 'path' => 'annotations.data' ],
                                                'aggs' => [
                                                    'main_speakers' => [
                                                        'filter' => [ 
                                                            'bool' => [
                                                                'must' => [
                                                                    ['term' => [ 'annotations.data.attributes.context' => 'main-speaker' ]],
                                                                    ['term' => [ 'annotations.data.type' => 'person' ]]
                                                                ]
                                                            ]
                                                        ],
                                                        'aggs' => [
                                                            'unique_speakers' => [ 'cardinality' => [ 'field' => 'annotations.data.id' ] ],
                                                            'top_speakers' => [
                                                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                                                'aggs' => [
                                                                    'speech_count' => [
                                                                        'reverse_nested' => new stdClass(),
                                                                        'aggs' => [
                                                                            'speeches' => [ 'cardinality' => [ 'field' => '_id' ] ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'speakingTime' => [ 'stats' => [ 'field' => 'attributes.duration' ] ],
            'speaking_time_by_faction' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'faction_filter' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [
                            'factions' => [
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                'aggs' => [
                                    'back_to_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speaking_time' => [ 'stats' => [ 'field' => 'attributes.duration' ] ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'speeches' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'total_speeches' => [
                        'reverse_nested' => new stdClass(),
                        'aggs' => [
                            'speech_count' => [ 'cardinality' => [ 'field' => '_id' ] ]
                        ]
                    ],
                    'factions' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [ 
                            'by_faction' => [ 
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['speech_count' => 'desc'] ],
                                'aggs' => [
                                    'speech_count' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speeches' => [ 'cardinality' => [ 'field' => '_id' ] ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    try {
        $results = $ESClient->search([
            'index' => 'openparliamenttv_*',
            'body' => $query
        ]);
        if (!isset($results['aggregations'])) {
            throw new Exception('Invalid OpenSearch response: missing required aggregations');
        }
        $aggregations = $results['aggregations'];
        // Inject the enhanced word frequency data from statistics index
        $aggregations['wordFrequency'] = [
            'buckets' => $topWords,
            'total_unique_words' => $totalUniqueWords,
            // Enhanced: Context-based word analysis as recommended in planning docs
            'contextBasedStats' => $contextBasedStats
        ];
        return $aggregations;
    } catch (Exception $e) {
        error_log('General statistics error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get entity-specific statistics
 * 
 * @param string $entityType The type of entity (person, organisation)
 * @param string $entityID The ID of the entity
 * @return array Statistics about the entity
 */
function getEntityStatistics($entityType, $entityID) {
    global $ESClient, $DEBUG_MODE;
    
    // Determine appropriate context based on entity type
    $entitySpecificContext = ($entityType === 'person') ? 'main-speaker' : 'main-speaker-faction';
    
    $query = [
        "size" => 0,
        "query" => [
            "nested" => [
                "path" => "annotations.data",
                "query" => [
                    "bool" => [
                        "must" => [
                            ["term" => ["annotations.data.id" => $entityID]],
                            ["term" => ["annotations.data.type" => $entityType]]
                        ]
                    ]
                ]
            ]
        ],
        "aggs" => [
            "associations" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "unique_speeches" => [
                        "reverse_nested" => new stdClass()
                    ],
                    "top_detected_entities" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.attributes.context" => "NER"]]
                                ],
                                "must_not" => [
                                    ["term" => ["annotations.data.id" => $entityID]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            "ner_entities" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 50
                                ],
                                "aggs" => [
                                    "entity_type" => [
                                        "terms" => [
                                            "field" => "annotations.data.type.keyword",
                                            "size" => 1
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "mentioned_by_speakers" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.type" => "person"]],
                                    ["term" => ["annotations.data.attributes.context" => "main-speaker"]]
                                ],
                                "must_not" => [
                                    ["term" => ["annotations.data.id" => $entityID]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            "top_mentioned_by" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "speeches_as_main_speaker" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "filter_this_person_as_main_speaker" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.id" => $entityID]],
                                    ["term" => ["annotations.data.type" => $entityType]],
                                    ["term" => ["annotations.data.attributes.context" => $entitySpecificContext]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            "unique_speeches_as_speaker" => [
                                "reverse_nested" => new stdClass()
                            ]
                        ]
                    ]
                ]
            ],
            "trends" => [
                "date_histogram" => [
                    "field" => "attributes.dateStart",
                    "calendar_interval" => "day"
                ]
            ]
        ]
    ];
    
    try {
        if ($DEBUG_MODE) {
            error_log("Entity Statistics Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
        if ($DEBUG_MODE) {
            error_log("Entity Statistics Response: " . json_encode($results, JSON_PRETTY_PRINT));
        }
        
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Entity statistics error: " . $e->getMessage());
        if ($DEBUG_MODE) {
            error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        return null;
    }
}




?>