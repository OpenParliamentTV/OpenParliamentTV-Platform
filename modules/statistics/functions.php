<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/search/functions.php');

// Debug flag - set to 1 to enable debug logging
$DEBUG_MODE = 0;

// Initialize OpenSearch client
$ESClientBuilder = Elasticsearch\ClientBuilder::create();

if ($config["ES"]["hosts"]) {
    $ESClientBuilder->setHosts($config["ES"]["hosts"]);
}
if ($config["ES"]["BasicAuthentication"]["user"]) {
    $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"],$config["ES"]["BasicAuthentication"]["passwd"]);
}
if ($config["ES"]["SSL"]["pem"]) {
    $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
}

$ESClient = $ESClientBuilder->build();

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
            "termFrequency" => [
                "terms" => [
                    "field" => "attributes.textContents.textHTML",
                    "size" => 20,
                    "min_doc_count" => 1,
                    "order" => ["_count" => "desc"],
                    "exclude" => $config["excludedStopwords"]
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
        }
        
        // Validate response structure
        if (!isset($results["aggregations"])) {
            throw new Exception("Invalid OpenSearch response: missing aggregations");
        }
        
        $aggregations = $results["aggregations"];
        
        // Validate required aggregations
        if (!isset($aggregations["speakers"]) || 
            !isset($aggregations["speakingTime"]) || 
            !isset($aggregations["termFrequency"])) {
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
        
        // Validate required aggregations
        if (!isset($aggregations["co_occurrences"])) {
            throw new Exception("Invalid OpenSearch response: missing co_occurrences aggregation");
        }
        
        // Process the results into nodes and edges
        $nodes = [];
        $edges = [];
        
        // Process nodes from entity_pairs
        if (isset($aggregations["co_occurrences"]["entity_pairs"]["buckets"])) {
            foreach ($aggregations["co_occurrences"]["entity_pairs"]["buckets"] as $bucket) {
                $sourceId = $bucket["key"];
                $nodes[] = [
                    "id" => $sourceId,
                    "type" => "entity"
                ];
                
                // Process edges from entity_connections
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
        
        $response = [
            "data" => [
                "type" => "statistics",
                "id" => "network",
                "attributes" => [
                    "coOccurrence" => [
                        "nodes" => $nodes,
                        "edges" => $edges
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