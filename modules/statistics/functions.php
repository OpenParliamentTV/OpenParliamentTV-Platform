<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/search/functions.php');

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
    global $ESClient;
    
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
                    "size" => 20
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
        
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        
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
        error_log("Query: " . json_encode($query, JSON_PRETTY_PRINT));
        if (isset($results)) {
            error_log("Response: " . json_encode($results, JSON_PRETTY_PRINT));
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
    global $ESClient;
    
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
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Entity statistics error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get term statistics
 * 
 * @return array Statistics about terms in the dataset
 */
function getTermStatistics() {
    global $ESClient;
    
    $query = [
        "size" => 0,
        "aggs" => [
            "frequency" => [
                "terms" => [
                    "field" => "attributes.textContents.textHTML",
                    "size" => 20
                ]
            ],
            "trends" => [
                "date_histogram" => [
                    "field" => "attributes.dateStart",
                    "calendar_interval" => "day"
                ],
                "aggs" => [
                    "terms" => [
                        "terms" => [
                            "field" => "attributes.textContents.textHTML",
                            "size" => 10
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    try {
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Term statistics error: " . $e->getMessage());
        return null;
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
    global $ESClient;
    
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
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Term comparison error: " . $e->getMessage());
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
    global $ESClient;
    
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
                                                    "size" => 50
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
        $results = $ESClient->search([
            "index" => "openparliamenttv_*",
            "body" => $query
        ]);
        return $results["aggregations"];
    } catch(Exception $e) {
        error_log("Network analysis error: " . $e->getMessage());
        return null;
    }
}

?>