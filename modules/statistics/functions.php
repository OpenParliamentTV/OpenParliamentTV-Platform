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
    
    // --- Use words index for word frequency ---
    $wordsIndices = array_map(function($index) {
        return strtolower(str_replace('openparliamenttv_', 'openparliamenttv_words_', $index));
    }, getParliamentIndices());
    $wordsIndexPattern = implode(',', $wordsIndices);
    
    // Get total word count and top 20 words by frequency
    $totalWords = 0;
    $topWords = [];
    
    try {
        // Get total count of unique words
        $countResults = $ESClient->count(['index' => $wordsIndexPattern]);
        $totalWords = $countResults['count'] ?? 0;
        
        // Get top 20 words by frequency
        $wordsQuery = [
            'size' => 20,
            'sort' => [
                ['frequency' => ['order' => 'desc']],
                ['word' => ['order' => 'asc']]
            ]
        ];
        $wordsResults = $ESClient->search(['index' => $wordsIndexPattern, 'body' => $wordsQuery]);
        if (isset($wordsResults['hits']['hits'])) {
            foreach ($wordsResults['hits']['hits'] as $hit) {
                $topWords[] = [
                    'key' => $hit['_source']['word'],
                    'doc_count' => $hit['_source']['frequency']
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching words from words index: ' . $e->getMessage());
    }
    // --- End words index ---

    // Main aggregation query for all other stats (as before)
    $query = [
        'size' => 0,
        'aggs' => [
            'speakers' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'filtered_speakers' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker' ] ],
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
        // Inject the top words from the words index
        $aggregations['wordFrequency'] = [
            'buckets' => $topWords,
            'sum_other_doc_count' => $totalWords
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
                    "top_speakers" => [
                        "terms" => [
                            "field" => "annotations.data.id",
                            "size" => 10
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
 * Get term statistics
 * 
 * @return array Statistics about terms in the dataset
 */
function getTermStatistics() {
    global $ESClient, $DEBUG_MODE, $config;
    
    // Use words index for all terms
    $wordsIndices = array_map(function($index) {
        return strtolower(str_replace('openparliamenttv_', 'openparliamenttv_words_', $index));
    }, getParliamentIndices());
    $wordsIndexPattern = implode(',', $wordsIndices);
    $wordsQuery = [
        'size' => 10000, // or as needed
        'sort' => [
            ['frequency' => ['order' => 'desc']],
            ['word' => ['order' => 'asc']]
        ]
    ];
    $totalWords = 0;
    $allWords = [];
    try {
        // Get total count of unique words
        $countResults = $ESClient->count(['index' => $wordsIndexPattern]);
        $totalWords = $countResults['count'] ?? 0;
        
        $wordsResults = $ESClient->search(['index' => $wordsIndexPattern, 'body' => $wordsQuery]);
        if (isset($wordsResults['hits']['hits'])) {
            foreach ($wordsResults['hits']['hits'] as $hit) {
                $allWords[] = [
                    'key' => $hit['_source']['word'],
                    'doc_count' => $hit['_source']['frequency']
                ];
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching words from words index: ' . $e->getMessage());
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
 * Compare terms statistics
 * 
 * @param array $terms Array of terms to compare
 * @param array $factions Optional array of factions to filter by
 * @return array Comparison statistics
 */
function compareTermsStatistics($terms, $factions = []) {
    global $ESClient, $DEBUG_MODE;
    
    $query = [
        "size" => 0,
        "query" => [
            "bool" => [
                "should" => array_map(function($term) {
                    return [
                        "match_phrase" => [
                            "attributes.textContents.textHTML" => $term
                        ]
                    ];
                }, $terms)
            ]
        ],
        "aggs" => [
            "term_comparison" => [
                "filters" => [
                    "filters" => array_reduce($terms, function($acc, $term) {
                        $acc[$term] = [
                            "match_phrase" => [
                                "attributes.textContents.textHTML" => $term
                            ]
                        ];
                        return $acc;
                    }, [])
                ],
                "aggs" => [
                    "over_time" => [
                        "date_histogram" => [
                            "field" => "attributes.dateStart",
                            "calendar_interval" => "day"
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    if (!empty($factions)) {
        $query["query"]["bool"]["filter"] = [
            "terms" => [
                "annotations.data.attributes.context" => $factions
            ]
        ];
    }
    
    try {
        if ($DEBUG_MODE) {
            error_log("Compare Terms Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
        if ($DEBUG_MODE) {
            error_log("Compare Terms Response: " . json_encode($results, JSON_PRETTY_PRINT));
        }
        
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Term comparison error: " . $e->getMessage());
        if ($DEBUG_MODE) {
            error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        return null;
    }
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
                                                                    ["term" => ["annotations.data.attributes.context" => "main-speaker"]]
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