<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");
require_once (__DIR__."/../../../modules/statistics/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

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
    
    try {
        // Check if OpenSearch client is available
        if (!isset($GLOBALS['ESClient'])) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorOpenSearchClientInitFailed"
            );
        }
        
        $stats = getGeneralStatistics();
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorOpenSearchQueryFailed"
            );
        }
        
        // Process speaker statistics
        if (!isset($stats["speakers"]["filtered_speakers"])) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorInvalidStatsFormatSpeaker"
            );
        }

        $data = [
            "type" => "statistics",
            "id" => "general",
            "attributes" => [
                "speakers" => [
                    "total" => $stats["speakers"]["filtered_speakers"]["unique_speakers"]["value"],
                    "topSpeakers" => array_map(function($bucket) {
                        return [
                            "id" => $bucket["key"],
                            "speechCount" => $bucket["doc_count"]
                        ];
                    }, $stats["speakers"]["filtered_speakers"]["top_speakers"]["buckets"])
                ]
            ]
        ];
        
        // Process speaking time statistics
        if (isset($stats["speakingTime"])) {
            $data["attributes"]["speakingTime"] = [
                "total" => $stats["speakingTime"]["sum"],
                "average" => $stats["speakingTime"]["avg"],
                "unit" => "seconds"
            ];
        }
        
        // Process word frequency statistics
        if (isset($stats["wordFrequency"])) {
            $data["attributes"]["wordFrequency"] = [
                "total" => $stats["wordFrequency"]["sum_other_doc_count"],
                "topWords" => array_map(function($bucket) {
                    return [
                        "word" => $bucket["key"],
                        "speechCount" => $bucket["doc_count"]
                    ];
                }, $stats["wordFrequency"]["buckets"])
            ];
        }

        // Process speaker mentions
        if (isset($stats["speakerMentions"])) {
            $data["attributes"]["speakerMentions"] = [
                "total" => $stats["speakerMentions"]["filtered_speakers"]["doc_count"],
                "topMentions" => array_map(function($bucket) {
                    return [
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ];
                }, $stats["speakerMentions"]["filtered_speakers"]["topSpeakers"]["buckets"])
            ];
        }

        // Process share of voice
        if (isset($stats["shareOfVoice"])) {
            $data["attributes"]["shareOfVoice"] = [
                "parties" => array_map(function($bucket) {
                    return [
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ];
                }, $stats["shareOfVoice"]["parties"]["topParties"]["buckets"]),
                "factions" => array_map(function($bucket) {
                    return [
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ];
                }, $stats["shareOfVoice"]["factions"]["topFactions"]["buckets"])
            ];
        }

        // Process entities statistics
        if (isset($stats["entities"])) {
            $data["attributes"]["entities"] = [];
            foreach ($stats["entities"]["entityTypes"]["buckets"] as $typeBucket) {
                $entityType = $typeBucket["key"];
                $data["attributes"]["entities"][$entityType] = [
                    "total" => $typeBucket["doc_count"],
                    "topEntities" => array_map(function($bucket) {
                        return [
                            "id" => $bucket["key"],
                            "totalCount" => $bucket["doc_count"],
                            "speechCount" => $bucket["unique_documents"]["value"]
                        ];
                    }, $typeBucket["topEntities"]["buckets"])
                ];
            }
        }

        return createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"]."/statistics/general"
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorStatisticsTitle",
            $e->getMessage()
        );
    }
}

/**
 * Get entity-specific statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetEntity($request) {
    global $config;
    
    try {
        if (empty($request["entityType"]) || empty($request["entityID"])) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "entityType and entityID"]
            );
        }
        
        $stats = getEntityStatistics($request["entityType"], $request["entityID"]);
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorEntityStatsTitle",
                "messageErrorEntityStatsQueryFailed"
            );
        }
        
        // Build data structure
        $data = [
            "type" => "statistics",
            "id" => "entity",
            "attributes" => [
                "entity" => [
                    "id" => $request["entityID"],
                    "type" => $request["entityType"]
                ],
                "associations" => [
                    "total" => $stats["associations"]["doc_count"],
                    "topSpeakers" => array_map(function($bucket) {
                        return [
                            "id" => $bucket["key"],
                            "count" => $bucket["doc_count"]
                        ];
                    }, $stats["associations"]["top_speakers"]["buckets"])
                ],
                "trends" => [
                    "total" => $stats["trends"]["buckets"][count($stats["trends"]["buckets"])-1]["doc_count"],
                    "timeline" => array_map(function($bucket) {
                        return [
                            "date" => $bucket["key_as_string"],
                            "count" => $bucket["doc_count"]
                        ];
                    }, $stats["trends"]["buckets"])
                ]
            ]
        ];
        
        // Build self link with parameters
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
        
        $selfLink = $config["dir"]["api"]."/statistics/entity/".$request["entityType"]."/".$request["entityID"]
                 . "?" . http_build_query($params);
        
        return createApiSuccessResponse($data, [
            "links" => ["self" => $selfLink]
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorEntityStatsTitle",
            $e->getMessage()
        );
    }
}

/**
 * Get term statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetTerms($request) {
    global $config;
    
    try {
        $stats = getTermStatistics();
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorTermStatsTitle",
                "messageErrorTermStatsQueryFailed"
            );
        }
        
        $data = [
            "type" => "statistics",
            "id" => "terms",
            "attributes" => [
                "frequency" => [
                    "total" => $stats["frequency"]["sum_other_doc_count"],
                    "topTerms" => array_map(function($bucket) {
                        return [
                            "term" => $bucket["key"],
                            "speechCount" => $bucket["doc_count"]
                        ];
                    }, $stats["frequency"]["buckets"])
                ],
                "trends" => [
                    "timeline" => array_map(function($bucket) {
                        return [
                            "date" => $bucket["key_as_string"],
                            "terms" => array_map(function($term) {
                                return [
                                    "term" => $term["key"],
                                    "speechCount" => $term["doc_count"]
                                ];
                            }, $bucket["terms"]["buckets"])
                        ];
                    }, $stats["trends"]["buckets"])
                ]
            ]
        ];
        
        return createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"]."/statistics/terms"
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorTermStatsTitle",
            $e->getMessage()
        );
    }
}

/**
 * Compare terms statistics
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsCompareTerms($request) {
    global $config;
    
    try {
        if (empty($request["terms"]) || !is_array($request["terms"])) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "terms array"]
            );
        }
        
        $factions = isset($request["factions"]) ? $request["factions"] : [];
        $stats = compareTermsStatistics($request["terms"], $factions);
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorTermCompareStatsTitle",
                "messageErrorTermCompareStatsQueryFailed"
            );
        }
        
        $data = [
            "type" => "statistics",
            "id" => "compare-terms",
            "attributes" => [
                "terms" => array_map(function($term) use ($stats) {
                    return [
                        "term" => $term,
                        "timeline" => array_map(function($bucket) {
                            return [
                                "date" => $bucket["key_as_string"],
                                "speechCount" => $bucket["doc_count"]
                            ];
                        }, $stats["term_comparison"]["buckets"][$term]["over_time"]["buckets"])
                    ];
                }, $request["terms"])
            ]
        ];
        
        // Process factions if provided
        if (!empty($factions)) {
            $data["attributes"]["factions"] = [];
            foreach ($factions as $faction) {
                $data["attributes"]["factions"][$faction] = [];
                foreach ($request["terms"] as $term) {
                    $data["attributes"]["factions"][$faction][$term] = [];
                    foreach ($stats["term_comparison"]["buckets"][$term]["over_time"]["buckets"] as $bucket) {
                        $data["attributes"]["factions"][$faction][$term][$bucket["key_as_string"]] = $bucket["doc_count"];
                    }
                }
            }
        }
        
        // Build self link with parameters
        $params = ["terms" => $request["terms"]];
        if (!empty($factions)) {
            $params["factions"] = $factions;
        }
        
        return createApiSuccessResponse($data, [
            "links" => [
                "self" => $config["dir"]["api"]."/statistics/compare-terms?" . http_build_query($params)
            ]
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorTermCompareStatsTitle",
            $e->getMessage()
        );
    }
}

/**
 * Get network/relationship analysis
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetNetwork($request) {
    global $config;
    
    try {
        $entityID = isset($request["entityID"]) ? $request["entityID"] : null;
        $entityType = isset($request["entityType"]) ? $request["entityType"] : null;
        
        $stats = getNetworkAnalysis($entityID, $entityType);
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorNetworkStatsTitle",
                "messageErrorNetworkStatsQueryFailed"
            );
        }
        
        // Use the processed data but preserve the self link
        $data = $stats["data"];
        
        return createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"]."/statistics/network"
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorNetworkStatsTitle",
            $e->getMessage()
        );
    }
}

function statisticsGetEntityCounts($request) {
    global $config;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorResponse(503, 1, "Database connection error", "Connecting to platform database failed");
    }

    $counts = [
        'person' => ['total' => 0, 'subtypes' => []],
        'organisation' => ['total' => 0, 'subtypes' => []],
        'document' => ['total' => 0, 'subtypes' => []],
        'term' => ['total' => 0, 'subtypes' => []],
    ];

    try {
        // Person
        $personTable = $config["platform"]["sql"]["tbl"]["Person"];
        $counts['person']['total'] = (int)$db->getOne("SELECT COUNT(*) FROM ?n", $personTable);
        foreach ($config["entityTypes"]["person"] as $subtype) {
            $counts['person']['subtypes'][$subtype] = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE PersonType = ?s", $personTable, $subtype);
        }

        // Organisation
        $orgTable = $config["platform"]["sql"]["tbl"]["Organisation"];
        $counts['organisation']['total'] = (int)$db->getOne("SELECT COUNT(*) FROM ?n", $orgTable);
        foreach ($config["entityTypes"]["organisation"] as $subtype) {
            $counts['organisation']['subtypes'][$subtype] = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE OrganisationType = ?s", $orgTable, $subtype);
        }

        // Document
        $docTable = $config["platform"]["sql"]["tbl"]["Document"];
        $counts['document']['total'] = (int)$db->getOne("SELECT COUNT(*) FROM ?n", $docTable);
        foreach ($config["entityTypes"]["document"] as $subtype) {
            $counts['document']['subtypes'][$subtype] = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE DocumentType = ?s", $docTable, $subtype);
        }
        
        // Term
        $termTable = $config["platform"]["sql"]["tbl"]["Term"];
        $counts['term']['total'] = (int)$db->getOne("SELECT COUNT(*) FROM ?n", $termTable);
        foreach ($config["entityTypes"]["term"] as $subtype) {
            $counts['term']['subtypes'][$subtype] = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE TermType = ?s", $termTable, $subtype);
        }

    } catch (Exception $e) {
        return createApiErrorResponse(500, 1, "Database query error", $e->getMessage());
    }

    return createApiSuccessResponse($counts);
}

?>
