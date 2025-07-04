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
    
    $query = [
        "size" => 0,
        "aggs" => [
            "speakers" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "filtered_speakers" => [
                        "filter" => [
                            "term" => [
                                "annotations.data.attributes.context" => "main-speaker"
                            ]
                        ],
                        "aggs" => [
                            "unique_speakers" => [
                                "cardinality" => [
                                    "field" => "annotations.data.id"
                                ]
                            ],
                            "top_speakers" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "speakingTime" => [
                "stats" => [
                    "field" => "attributes.duration"
                ]
            ],
            "speakerMentions" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "filtered_speakers" => [
                        "filter" => [
                            "term" => [
                                "annotations.data.type" => "person"
                            ]
                        ],
                        "aggs" => [
                            "topSpeakers" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10,
                                    "order" => ["_count" => "desc"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "shareOfVoice" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "parties" => [
                        "filter" => [
                            "term" => [
                                "annotations.data.attributes.context" => "main-speaker-party"
                            ]
                        ],
                        "aggs" => [
                            "topParties" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10,
                                    "order" => ["_count" => "desc"]
                                ]
                            ]
                        ]
                    ],
                    "factions" => [
                        "filter" => [
                            "term" => [
                                "annotations.data.attributes.context" => "main-speaker-faction"
                            ]
                        ],
                        "aggs" => [
                            "topFactions" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10,
                                    "order" => ["_count" => "desc"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "wordFrequency" => [
                "terms" => [
                    "field" => "attributes.textContents.textHTML",
                    "size" => 20,
                    "min_doc_count" => 1,
                    "order" => ["_count" => "desc"],
                    "exclude" => $config["excludedStopwords"]
                ]
            ],
            "entities" => [
                "nested" => [
                    "path" => "annotations.data"
                ],
                "aggs" => [
                    "entityTypes" => [
                        "terms" => [
                            "field" => "annotations.data.type.keyword",
                            "size" => 5
                        ],
                        "aggs" => [
                            "topEntities" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 10,
                                    "order" => ["_count" => "desc"]
                                ],
                                "aggs" => [
                                    "unique_documents" => [
                                        "cardinality" => [
                                            "field" => "_id"
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
        // First check if the index exists
        $indices = $ESClient->cat()->indices(['index' => 'openparliamenttv_*']);
        if (empty($indices)) {
            throw new Exception("No OpenSearch indices found matching 'openparliamenttv_*'");
        }
        
        if ($DEBUG_MODE) {
            error_log("General Statistics Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
        if ($DEBUG_MODE) {
            error_log("General Statistics Response: " . json_encode($results, JSON_PRETTY_PRINT));
            if (isset($results["aggregations"]["wordFrequency"]["buckets"])) {
                error_log("Word Frequency Buckets: " . json_encode($results["aggregations"]["wordFrequency"]["buckets"], JSON_PRETTY_PRINT));
                foreach ($results["aggregations"]["wordFrequency"]["buckets"] as $bucket) {
                    error_log("Word: " . $bucket["key"] . ", Doc Count: " . $bucket["doc_count"]);
                }
            }
        }
        
        // Validate response structure
        if (!isset($results["aggregations"])) {
            throw new Exception("Invalid OpenSearch response: missing aggregations");
        }
        
        $aggregations = $results["aggregations"];
        
        // Validate required aggregations
        if (!isset($aggregations["speakers"]) || 
            !isset($aggregations["speakingTime"]) || 
            !isset($aggregations["wordFrequency"])) {
            throw new Exception("Invalid OpenSearch response: missing required aggregations");
        }
        
        return $aggregations;
    } catch(Exception $e) {
        error_log("Statistics error: " . $e->getMessage());
        if ($DEBUG_MODE) {
            error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
            if (isset($results)) {
                error_log("Response: " . json_encode($results, JSON_PRETTY_PRINT));
            }
        }
        throw $e; // Re-throw to be handled by the API layer
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
    
    $query = [
        "size" => 0,
        "aggs" => [
            "frequency" => [
                "terms" => [
                    "field" => "attributes.textContents.textHTML",
                    "size" => 20,
                    "min_doc_count" => 1,
                    "order" => ["_count" => "desc"],
                    "exclude" => $config["excludedStopwords"]
                ],
                "aggs" => [
                    "total_occurrences" => [
                        "value_count" => [
                            "field" => "attributes.textContents.textHTML"
                        ]
                    ]
                ]
            ],
            "trends" => [
                "date_histogram" => [
                    "field" => "attributes.dateStart",
                    "calendar_interval" => "day",
                    "min_doc_count" => 0
                ],
                "aggs" => [
                    "terms" => [
                        "terms" => [
                            "field" => "attributes.textContents.textHTML",
                            "size" => 10,
                            "min_doc_count" => 1,
                            "order" => ["_count" => "desc"],
                            "exclude" => $config["excludedStopwords"]
                        ]
                    ]
                ]
            ]
        ],
        "query" => [
            "bool" => [
                "must" => [
                    [
                        "exists" => [
                            "field" => "attributes.textContents.textHTML"
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    try {
        // First check if the index exists
        $indices = $ESClient->cat()->indices(['index' => 'openparliamenttv_*']);
        if (empty($indices)) {
            throw new Exception("No OpenSearch indices found matching 'openparliamenttv_*'");
        }
        
        if ($DEBUG_MODE) {
            error_log("Term Statistics Query: " . json_encode($query, JSON_PRETTY_PRINT));
        }
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
        if ($DEBUG_MODE) {
            error_log("Term Statistics Response: " . json_encode($results, JSON_PRETTY_PRINT));
        }
        
        if (!isset($results["aggregations"])) {
            throw new Exception("No aggregations found in search results");
        }
        
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Term statistics error: " . $e->getMessage());
        if ($DEBUG_MODE) {
            error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
            if (isset($results)) {
                error_log("Response: " . json_encode($results, JSON_PRETTY_PRINT));
            }
        }
        throw new Exception("Failed to retrieve term statistics: " . $e->getMessage());
    }
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