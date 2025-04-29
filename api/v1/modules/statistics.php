<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");
require_once (__DIR__."/../../../modules/statistics/functions.php");

// Initialize OpenSearch client if not already initialized
if (!isset($GLOBALS['ESClient'])) {
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

    $GLOBALS['ESClient'] = $ESClientBuilder->build();
}

/**
 * Get general statistics about the dataset
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetGeneral($request) {
    global $config;
    
    $return = [
        "meta" => [
            "requestStatus" => "error"
        ],
        "data" => [
            "type" => "statistics",
            "id" => "general",
            "attributes" => []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/statistics/general"
        ]
    ];
    
    try {
        // Check if OpenSearch client is available
        if (!isset($GLOBALS['ESClient'])) {
            throw new Exception("OpenSearch client not initialized");
        }
        
        $stats = getGeneralStatistics();
        
        if ($stats === null) {
            throw new Exception("Failed to retrieve statistics from OpenSearch");
        }
        
        // Process speaker statistics
        if (!isset($stats["speakers"]["filtered_speakers"])) {
            throw new Exception("Invalid speaker statistics format");
        }
        
        $return["data"]["attributes"]["speakers"] = [
            "total" => $stats["speakers"]["filtered_speakers"]["unique_speakers"]["value"],
            "topSpeakers" => array_map(function($bucket) {
                return [
                    "id" => $bucket["key"],
                    "count" => $bucket["doc_count"]
                ];
            }, $stats["speakers"]["filtered_speakers"]["top_speakers"]["buckets"])
        ];
        
        // Process speaking time statistics
        if (!isset($stats["speakingTime"])) {
            throw new Exception("Invalid speaking time statistics format");
        }
        
        $return["data"]["attributes"]["speakingTime"] = [
            "total" => $stats["speakingTime"]["sum"],
            "average" => $stats["speakingTime"]["avg"],
            "unit" => "seconds"
        ];
        
        // Process term frequency statistics
        if (!isset($stats["termFrequency"])) {
            throw new Exception("Invalid term frequency statistics format");
        }
        
        $return["data"]["attributes"]["termFrequency"] = [
            "total" => $stats["termFrequency"]["sum_other_doc_count"],
            "topTerms" => array_map(function($bucket) {
                return [
                    "term" => $bucket["key"],
                    "documentCount" => $bucket["doc_count"],
                    "totalOccurrences" => $bucket["total_occurrences"]["value"]
                ];
            }, $stats["termFrequency"]["buckets"])
        ];
        
        // Set success status
        $return["meta"]["requestStatus"] = "success";
        
    } catch (Exception $e) {
        unset($return["data"]);
        $return["errors"] = [
            [
                "status" => "500",
                "code" => "1",
                "title" => "Statistics Error",
                "detail" => $e->getMessage()
            ]
        ];
    }
    
    return $return;
}

/**
 * Get entity-specific statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetEntity($request) {
    global $config;
    
    $return = [
        "meta" => [
            "requestStatus" => "error"
        ],
        "data" => [
            "type" => "statistics",
            "id" => "entity",
            "attributes" => []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/statistics/entity"
        ]
    ];
    
    try {
        if (empty($request["entityType"]) || empty($request["entityID"])) {
            throw new Exception("Missing required parameters: entityType and entityID");
        }
        
        $stats = getEntityStatistics($request["entityType"], $request["entityID"]);
        
        if ($stats === null) {
            throw new Exception("Failed to retrieve entity statistics");
        }
        
        // Process entity information
        $return["data"]["attributes"]["entity"] = [
            "id" => $request["entityID"],
            "type" => $request["entityType"]
        ];
        
        // Update self link with entity path
        $return["links"]["self"] .= "/".$request["entityType"]."/".$request["entityID"];
        
        // Process associations
        $return["data"]["attributes"]["associations"] = [
            "total" => $stats["associations"]["doc_count"],
            "topSpeakers" => array_map(function($bucket) {
                return [
                    "id" => $bucket["key"],
                    "count" => $bucket["doc_count"]
                ];
            }, $stats["associations"]["top_speakers"]["buckets"])
        ];
        
        // Process trends
        $return["data"]["attributes"]["trends"] = [
            "total" => $stats["trends"]["buckets"][count($stats["trends"]["buckets"])-1]["doc_count"],
            "timeline" => array_map(function($bucket) {
                return [
                    "date" => $bucket["key_as_string"],
                    "count" => $bucket["doc_count"]
                ];
            }, $stats["trends"]["buckets"])
        ];
        
        // Add request parameters to self link
        $params = [
            "entityType" => $request["entityType"],
            "entityID" => $request["entityID"]
        ];
        if (!empty($request["terms"])) {
            $params["terms"] = $request["terms"];
        }
        if (!empty($request["factions"])) {
            $params["factions"] = $request["factions"];
        }
        $return["links"]["self"] .= "?" . http_build_query($params);
        
        // Set success status
        $return["meta"]["requestStatus"] = "success";
        
    } catch (Exception $e) {
        unset($return["data"]);
        $return["errors"] = [
            [
                "status" => "500",
                "code" => "1",
                "title" => "Entity Statistics Error",
                "detail" => $e->getMessage()
            ]
        ];
    }
    
    return $return;
}

/**
 * Get term statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetTerms($request) {
    global $config;
    
    $return = [
        "meta" => [
            "requestStatus" => "error"
        ],
        "data" => [
            "type" => "statistics",
            "id" => "terms",
            "attributes" => []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/statistics/terms"
        ]
    ];
    
    try {
        $stats = getTermStatistics();
        
        if ($stats === null) {
            throw new Exception("Failed to retrieve term statistics");
        }
        
        // Process frequency statistics
        $return["data"]["attributes"]["frequency"] = [
            "total" => $stats["frequency"]["sum_other_doc_count"],
            "topTerms" => array_map(function($bucket) {
                return [
                    "term" => $bucket["key"],
                    "documentCount" => $bucket["doc_count"],
                    "totalOccurrences" => $bucket["total_occurrences"]["value"]
                ];
            }, $stats["frequency"]["buckets"])
        ];
        
        // Process trends
        $return["data"]["attributes"]["trends"] = [
            "timeline" => array_map(function($bucket) {
                return [
                    "date" => $bucket["key_as_string"],
                    "terms" => array_map(function($term) {
                        return [
                            "term" => $term["key"],
                            "count" => $term["doc_count"]
                        ];
                    }, $bucket["terms"]["buckets"])
                ];
            }, $stats["trends"]["buckets"])
        ];
        
        // Set success status
        $return["meta"]["requestStatus"] = "success";
        
    } catch (Exception $e) {
        unset($return["data"]);
        $return["errors"] = [
            [
                "status" => "500",
                "code" => "1",
                "title" => "Term Statistics Error",
                "detail" => $e->getMessage()
            ]
        ];
    }
    
    return $return;
}

/**
 * Compare terms statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsCompareTerms($request) {
    global $config;
    
    $return = [
        "meta" => [
            "requestStatus" => "error"
        ],
        "data" => [
            "type" => "statistics",
            "id" => "compare-terms",
            "attributes" => []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/statistics/compare-terms"
        ]
    ];
    
    try {
        if (empty($request["terms"]) || !is_array($request["terms"])) {
            throw new Exception("Missing required parameter: terms array");
        }
        
        $factions = isset($request["factions"]) ? $request["factions"] : [];
        $stats = compareTermsStatistics($request["terms"], $factions);
        
        if ($stats === null) {
            throw new Exception("Failed to retrieve term comparison statistics");
        }
        
        // Process terms comparison
        $return["data"]["attributes"]["terms"] = array_map(function($term) use ($stats) {
            return [
                "term" => $term,
                "timeline" => array_map(function($bucket) {
                    return [
                        "date" => $bucket["key_as_string"],
                        "count" => $bucket["doc_count"]
                    ];
                }, $stats["term_comparison"]["buckets"][$term]["over_time"]["buckets"])
            ];
        }, $request["terms"]);
        
        // Process factions if provided
        if (!empty($factions)) {
            $return["data"]["attributes"]["factions"] = [];
            foreach ($factions as $faction) {
                $return["data"]["attributes"]["factions"][$faction] = [];
                foreach ($request["terms"] as $term) {
                    $return["data"]["attributes"]["factions"][$faction][$term] = [];
                    foreach ($stats["term_comparison"]["buckets"][$term]["over_time"]["buckets"] as $bucket) {
                        $return["data"]["attributes"]["factions"][$faction][$term][$bucket["key_as_string"]] = $bucket["doc_count"];
                    }
                }
            }
        }
        
        // Add request parameters to self link
        $params = [
            "terms" => $request["terms"]
        ];
        if (!empty($factions)) {
            $params["factions"] = $factions;
        }
        $return["links"]["self"] .= "?" . http_build_query($params);
        
        // Set success status
        $return["meta"]["requestStatus"] = "success";
        
    } catch (Exception $e) {
        unset($return["data"]);
        $return["errors"] = [
            [
                "status" => "500",
                "code" => "1",
                "title" => "Term Comparison Error",
                "detail" => $e->getMessage()
            ]
        ];
    }
    
    return $return;
}

/**
 * Get network/relationship analysis
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetNetwork($request) {
    global $config;
    
    $return = [
        "meta" => [
            "requestStatus" => "error"
        ],
        "data" => [
            "type" => "statistics",
            "id" => "network",
            "attributes" => []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/statistics/network"
        ]
    ];
    
    try {
        $entityID = isset($request["entityID"]) ? $request["entityID"] : null;
        $entityType = isset($request["entityType"]) ? $request["entityType"] : null;
        
        $stats = getNetworkAnalysis($entityID, $entityType);
        
        if ($stats === null) {
            throw new Exception("Failed to retrieve network analysis");
        }
        
        error_log("Network Analysis Stats: " . json_encode($stats, JSON_PRETTY_PRINT));
        
        // Use the processed data but preserve the self link
        $return["data"] = $stats["data"];
        $return["links"] = [
            "self" => $config["dir"]["api"]."/statistics/network"
        ];
        
        // Set success status
        $return["meta"]["requestStatus"] = "success";
        
        error_log("Processed Network Response: " . json_encode($return, JSON_PRETTY_PRINT));
        
    } catch (Exception $e) {
        unset($return["data"]);
        $return["errors"] = [
            [
                "status" => "500",
                "code" => "1",
                "title" => "Network Analysis Error",
                "detail" => $e->getMessage()
            ]
        ];
    }
    
    return $return;
}

?>
