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
 * @param string $contextFilter Context filter (main-speaker, vice-president, etc.)
 * @param string|null $factionFilter Faction ID filter
 * @param float $confidenceThreshold Minimum confidence threshold for NER annotations
 * @return array Statistics about the dataset
 */
function getGeneralStatistics($contextFilter = 'main-speaker', $factionFilter = null, $confidenceThreshold = 0.7) {
    global $ESClient, $DEBUG_MODE, $config;
    
    // --- Use enhanced statistics index for word frequency ---
    $statisticsIndex = 'optv_statistics_de'; // TODO: Support other parliaments
    
    // Get total word count and top 20 words by frequency from enhanced statistics
    $totalWords = 0;
    $topWords = [];
    $contextBasedStats = [];
    
    try {
        // Enhanced query to leverage speaker context groupings from planning docs
        $enhancedWordsQuery = [
            'size' => 0,
            'query' => [
                'term' => ['aggregation_type' => 'word_frequency_daily_party']
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
                // Enhanced: Party-specific analysis as recommended  
                'by_party' => [
                    'terms' => ['field' => 'party_id', 'size' => 10],
                    'aggs' => [
                        'top_words' => [
                            'terms' => [
                                'field' => 'word',
                                'size' => 5,
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
        
        // Process party-based statistics from real data
        if (isset($wordsResults['aggregations']['by_party']['buckets'])) {
            $partyStats = ['type' => 'by_party', 'parties' => []];
            foreach ($wordsResults['aggregations']['by_party']['buckets'] as $partyBucket) {
                $partyID = $partyBucket['key'];
                $partyInfo = ['partyID' => $partyID, 'topWords' => []];
                
                if (isset($partyBucket['top_words']['buckets'])) {
                    foreach ($partyBucket['top_words']['buckets'] as $wordBucket) {
                        $partyInfo['topWords'][] = [
                            'word' => $wordBucket['key'],
                            'count' => $wordBucket['total_frequency']['value']
                        ];
                    }
                }
                $partyStats['parties'][] = $partyInfo;
            }
            $contextBasedStats[] = $partyStats;
        }
        
        if (isset($wordsResults['aggregations']['total_unique_words']['value'])) {
            $totalWords = $wordsResults['aggregations']['total_unique_words']['value'];
        }
    } catch (Exception $e) {
        error_log('Error fetching words from enhanced statistics index: ' . $e->getMessage());
        
        // No fallback available - enhanced indexing is required
        error_log('Enhanced statistics index is required for word frequency data');
    }
    // --- End enhanced statistics index ---

    // Build query filters based on parameters
    $baseQuery = ['bool' => ['must' => []]];
    
    // Add faction filter to main query if specified
    if ($factionFilter) {
        $baseQuery['bool']['must'][] = [
            'nested' => [
                'path' => 'annotations.data',
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['annotations.data.id' => $factionFilter]],
                            ['term' => ['annotations.data.attributes.context' => 'main-speaker-faction']]
                        ]
                    ]
                ]
            ]
        ];
    }

    // Main aggregation query with dynamic context filtering
    $query = [
        'size' => 0,
        'query' => count($baseQuery['bool']['must']) > 0 ? $baseQuery : ['match_all' => (object)[]],
        'aggs' => [
            'speakers' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'filtered_speakers' => [
                        'filter' => [ 
                            'bool' => [
                                'must' => [
                                    ['term' => [ 'annotations.data.attributes.context' => $contextFilter ]],
                                    ['term' => [ 'annotations.data.type' => 'person' ]]
                                ]
                            ]
                        ],
                        'aggs' => [
                            'unique_speakers' => [ 'cardinality' => [ 'field' => 'annotations.data.id' ] ],
                            'top_speakers' => [ 'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ] ]
                        ]
                    ]
                ]
            ],
            'speakingTime' => [ 'stats' => [ 'field' => 'attributes.duration' ] ],
            'speakerMentions' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'filtered_speakers' => [
                        'filter' => [ 'term' => [ 'annotations.data.type' => 'person' ] ],
                        'aggs' => [
                            'topSpeakers' => [ 'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['_count' => 'desc'] ] ]
                        ]
                    ]
                ]
            ],
            'shareOfVoice' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'parties' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-party' ] ],
                        'aggs' => [ 'topParties' => [ 'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['_count' => 'desc'] ] ] ]
                    ],
                    'factions' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [ 'topFactions' => [ 'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['_count' => 'desc'] ] ] ]
                    ]
                ]
            ],
            'entities' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'entityTypes' => [
                        'terms' => [ 'field' => 'annotations.data.type.keyword', 'size' => 5 ],
                        'aggs' => [
                            'topEntities' => [
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['_count' => 'desc'] ],
                                'aggs' => [ 'unique_documents' => [ 'cardinality' => [ 'field' => '_id' ] ] ]
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
            'sum_other_doc_count' => $totalWords,
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
                    "top_speakers" => [
                        "terms" => [
                            "field" => "annotations.data.id",
                            "size" => 10
                        ]
                    ],
                    "main_speakers_only" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.type" => "person"]],
                                    ["term" => ["annotations.data.attributes.context" => $entitySpecificContext]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            "top_main_speakers" => [
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

/**
 * Get term statistics using enhanced statistics index
 * 
 * @return array Statistics about terms in the dataset
 */
function getTermStatistics() {
    global $ESClient, $DEBUG_MODE, $config;
    
    // Use enhanced statistics index for better performance
    $statisticsIndex = 'optv_statistics_de'; // TODO: Support other parliaments
    
    $totalWords = 0;
    $allWords = [];
    
    try {
        // Get all words from enhanced statistics index
        $wordsQuery = [
            'size' => 0,
            'query' => [
                'term' => ['aggregation_type' => 'word_frequency_daily_party']
            ],
            'aggs' => [
                'all_words' => [
                    'terms' => [
                        'field' => 'word',
                        'size' => 10000,
                        'order' => ['total_frequency' => 'desc']
                    ],
                    'aggs' => [
                        'total_frequency' => ['sum' => ['field' => 'count']]
                    ]
                ],
                'total_unique_words' => [
                    'cardinality' => ['field' => 'word']
                ]
            ]
        ];
        
        $wordsResults = $ESClient->search(['index' => $statisticsIndex, 'body' => $wordsQuery]);
        if (isset($wordsResults['aggregations']['all_words']['buckets'])) {
            foreach ($wordsResults['aggregations']['all_words']['buckets'] as $bucket) {
                $allWords[] = [
                    'key' => $bucket['key'],
                    'doc_count' => $bucket['total_frequency']['value']
                ];
            }
        }
        
        if (isset($wordsResults['aggregations']['total_unique_words']['value'])) {
            $totalWords = $wordsResults['aggregations']['total_unique_words']['value'];
        }
    } catch (Exception $e) {
        error_log('Error fetching words from enhanced statistics index: ' . $e->getMessage());
        
        // No fallback available - enhanced indexing is required
        error_log('Enhanced statistics index is required for term statistics data');
    }
    
    // Return in the same format as before
    return [
        'frequency' => [
            'buckets' => $allWords,
            'sum_other_doc_count' => $totalWords
        ]
    ];
}


/**
 * Get network/relationship analysis
 * 
 * @param string|null $entityID Optional entity ID to analyze
 * @param string|null $entityType Optional entity type
 * @return array Network analysis statistics
 */
function getNetworkAnalysis($entityID = null, $entityType = null) {
    global $ESClient, $DEBUG_MODE;
    
    $query = [
        "size" => 0,
        "aggs" => [
            "co_occurrences" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "entity_pairs" => [
                        "terms" => [
                            "field" => "annotations.data.id",
                            "size" => 100
                        ],
                        "aggs" => [
                            "co_occurring_entities" => [
                                "reverse_nested" => new stdClass(),
                                "aggs" => [
                                    "related_entities" => [
                                        "nested" => [
                                            "path" => "annotations.data"
                                        ],
                                        "aggs" => [
                                            "filtered" => [
                                                "filter" => [
                                                    "bool" => [
                                                        "must_not" => $entityID ? [
                                                            ["term" => ["annotations.data.id" => $entityID]]
                                                        ] : []
                                                    ]
                                                ],
                                                "aggs" => [
                                                    "entity_connections" => [
                                                        "terms" => [
                                                            "field" => "annotations.data.id",
                                                            "size" => 50,
                                                            "min_doc_count" => 1
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
            "speaker_entity_network" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "speakers" => [
                        "filter" => [
                            "term" => [
                                "annotations.data.attributes.context" => "main-speaker"
                            ]
                        ],
                        "aggs" => [
                            "top_speakers" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 50
                                ],
                                "aggs" => [
                                    "mentioned_entities" => [
                                        "reverse_nested" => new stdClass(),
                                        "aggs" => [
                                            "entities" => [
                                                "nested" => [
                                                    "path" => "annotations.data"
                                                ],
                                                "aggs" => [
                                                    "filtered" => [
                                                        "filter" => [
                                                            "bool" => [
                                                                "must_not" => [
                                                                    ["term" => ["annotations.data.attributes.context" => $entitySpecificContext]]
                                                                ]
                                                            ]
                                                        ],
                                                        "aggs" => [
                                                            "entity_mentions" => [
                                                                "terms" => [
                                                                    "field" => "annotations.data.id",
                                                                    "size" => 50,
                                                                    "min_doc_count" => 1
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
    ];
    
    if ($entityID && $entityType) {
        $query["query"] = [
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
        ];
    }
    
    try {
        // First check if the index exists
        $indices = $ESClient->cat()->indices(['index' => 'openparliamenttv_*']);
        if (empty($indices)) {
            throw new Exception("No OpenSearch indices found matching 'openparliamenttv_*'");
        }
        
        if ($DEBUG_MODE) {
            error_log("Network Analysis Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
        if ($DEBUG_MODE) {
            error_log("Network Analysis Response: " . json_encode($results, JSON_PRETTY_PRINT));
        }
        
        // Validate response structure
        if (!isset($results["aggregations"])) {
            throw new Exception("Invalid OpenSearch response: missing aggregations");
        }
        
        $aggregations = $results["aggregations"];
        
        // Process co-occurrence network
        $nodes = [];
        $edges = [];
        
        if (isset($aggregations["co_occurrences"]["entity_pairs"]["buckets"])) {
            foreach ($aggregations["co_occurrences"]["entity_pairs"]["buckets"] as $bucket) {
                $sourceId = $bucket["key"];
                $nodes[] = [
                    "id" => $sourceId,
                    "type" => "entity"
                ];
                
                if (isset($bucket["co_occurring_entities"]["related_entities"]["filtered"]["entity_connections"]["buckets"])) {
                    foreach ($bucket["co_occurring_entities"]["related_entities"]["filtered"]["entity_connections"]["buckets"] as $connection) {
                        $targetId = $connection["key"];
                        $edges[] = [
                            "source" => $sourceId,
                            "target" => $targetId,
                            "weight" => $connection["doc_count"]
                        ];
                    }
                }
            }
        }

        // Process speaker-entity network
        $speakerNodes = [];
        $speakerEdges = [];

        if (isset($aggregations["speaker_entity_network"]["speakers"]["top_speakers"]["buckets"])) {
            foreach ($aggregations["speaker_entity_network"]["speakers"]["top_speakers"]["buckets"] as $speakerBucket) {
                $speakerId = $speakerBucket["key"];
                $speakerNodes[] = [
                    "id" => $speakerId,
                    "type" => "person",
                    "context" => "main-speaker"
                ];
                
                if (isset($speakerBucket["mentioned_entities"]["entities"]["filtered"]["entity_mentions"]["buckets"])) {
                    foreach ($speakerBucket["mentioned_entities"]["entities"]["filtered"]["entity_mentions"]["buckets"] as $entityBucket) {
                        $entityId = $entityBucket["key"];
                        $speakerEdges[] = [
                            "source" => $speakerId,
                            "target" => $entityId,
                            "weight" => $entityBucket["doc_count"]
                        ];
                    }
                }
            }
        }
        
        $response = [
            "data" => [
                "type" => "statistics",
                "id" => "network",
                "attributes" => [
                    "coOccurrence" => [
                        "nodes" => $nodes,
                        "edges" => $edges
                    ],
                    "speakerEntity" => [
                        "nodes" => $speakerNodes,
                        "edges" => $speakerEdges
                    ]
                ]
            ]
        ];
        
        if ($DEBUG_MODE) {
            error_log("Processed Network Response: " . json_encode($response, JSON_PRETTY_PRINT));
        }
        
        return $response;
    } catch(Exception $e) {
        error_log("Network analysis error: " . $e->getMessage());
        if ($DEBUG_MODE) {
            error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
            if (isset($results)) {
                error_log("Response: " . json_encode($results, JSON_PRETTY_PRINT));
            }
        }
        throw new Exception("Failed to retrieve network analysis: " . $e->getMessage());
    }
}

?>