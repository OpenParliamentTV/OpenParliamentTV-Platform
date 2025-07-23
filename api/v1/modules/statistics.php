<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");
require_once (__DIR__."/../../../modules/statistics/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * Create self-link for an entity based on type and ID
 * @param string $entityType The entity type (person, organisation, document, etc.)
 * @param string $entityID The entity ID
 * @return string The self-link URL
 */
function createEntitySelfLink($entityType, $entityID) {
    global $config;
    
    $typeMapping = [
        'person' => 'person',
        'organisation' => 'organisation', 
        'organization' => 'organisation', // Handle both spellings
        'document' => 'document',
        'term' => 'term',
        'media' => 'media'
    ];
    
    $type = $typeMapping[$entityType] ?? $entityType;
    return $config["dir"]["api"]."/".$type."/".$entityID;
}

/**
 * Enrich entity with self-link for easy navigation
 * @param array $entity The entity data
 * @param string $entityType The entity type (required - we should always know this from context)
 * @return array The enriched entity with self-link
 */
function enrichEntityWithSelfLink($entity, $entityType) {
    if ($entityType && isset($entity['id'])) {
        $entity['links'] = ['self' => createEntitySelfLink($entityType, $entity['id'])];
    }
    
    return $entity;
}

/**
 * Detect entity type from context or annotations
 * @param string $entityID The entity ID
 * @return string|null The detected entity type
 */
function detectEntityType($entityID) {
    // This could be enhanced to query the database to determine actual type
    // For now, use ID pattern matching
    if (preg_match('/^Q\d+$/', $entityID)) {
        return 'person'; // Most Wikidata IDs in our context are persons
    } elseif (is_numeric($entityID)) {
        return 'document';
    }
    return null;
}

// Initialize OpenSearch client if not already initialized
if (!isset($GLOBALS['ESClient'])) {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        // Handle error case - log and set to null
        error_log("Failed to initialize OpenSearch client in statistics module: " . json_encode($ESClient));
        $GLOBALS['ESClient'] = null;
    } else {
        $GLOBALS['ESClient'] = $ESClient;
    }
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
        
        // CRITICAL: Hardcode context filtering for meaningful statistics
        // Statistics MUST use main-speaker context by default, not user-configurable
        $contextFilter = 'main-speaker'; // Hardcoded to ensure data consistency  
        $factionFilter = $request['factionID'] ?? null;
        $confidenceThreshold = $request['confidenceThreshold'] ?? 0.7;
        
        $stats = getGeneralStatistics($contextFilter, $factionFilter, $confidenceThreshold);
        
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
                "context" => $contextFilter, // Show which context filter is applied
                "factionID" => $factionFilter, // Show faction filter if applied
                "speakers" => [
                    "total" => $stats["speakers"]["filtered_speakers"]["unique_speakers"]["value"],
                    "topSpeakers" => array_map(function($bucket) {
                        return enrichEntityWithSelfLink([
                            "id" => $bucket["key"],
                            "speechCount" => $bucket["doc_count"]
                        ], 'person');
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
        
        // Process word frequency statistics with enhanced context-based analysis
        if (isset($stats["wordFrequency"])) {
            $data["attributes"]["wordFrequency"] = [
                "totalWords" => $stats["wordFrequency"]["sum_other_doc_count"],
                "topWords" => array_map(function($bucket) {
                    return [
                        "word" => $bucket["key"],
                        "speechCount" => $bucket["doc_count"]
                    ];
                }, $stats["wordFrequency"]["buckets"])
            ];
            
            // Enhanced: Include context-based statistics as recommended in planning docs
            if (isset($stats["wordFrequency"]["contextBasedStats"])) {
                $data["attributes"]["wordFrequency"]["byContext"] = $stats["wordFrequency"]["contextBasedStats"];
            }
        }

        // Process speaker mentions with self-links
        if (isset($stats["speakerMentions"])) {
            $data["attributes"]["speakerMentions"] = [
                "total" => $stats["speakerMentions"]["filtered_speakers"]["doc_count"],
                "topMentions" => array_map(function($bucket) {
                    return enrichEntityWithSelfLink([
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ], 'person');
                }, $stats["speakerMentions"]["filtered_speakers"]["topSpeakers"]["buckets"])
            ];
        }

        // Process share of voice with self-links  
        if (isset($stats["shareOfVoice"])) {
            $data["attributes"]["shareOfVoice"] = [
                "parties" => array_map(function($bucket) {
                    return enrichEntityWithSelfLink([
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ], 'organisation');
                }, $stats["shareOfVoice"]["parties"]["topParties"]["buckets"]),
                "factions" => array_map(function($bucket) {
                    return enrichEntityWithSelfLink([
                        "id" => $bucket["key"],
                        "count" => $bucket["doc_count"]
                    ], 'organisation');
                }, $stats["shareOfVoice"]["factions"]["topFactions"]["buckets"])
            ];
        }

        // Process entities statistics with self-links
        if (isset($stats["entities"])) {
            $data["attributes"]["entities"] = [];
            foreach ($stats["entities"]["entityTypes"]["buckets"] as $typeBucket) {
                $entityType = $typeBucket["key"];
                $data["attributes"]["entities"][$entityType] = [
                    "total" => $typeBucket["doc_count"],
                    "topEntities" => array_map(function($bucket) use ($entityType) {
                        return enrichEntityWithSelfLink([
                            "id" => $bucket["key"],
                            "totalCount" => $bucket["doc_count"],
                            "speechCount" => $bucket["unique_documents"]["value"]
                        ], $entityType);
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
        
        // Build data structure with enhanced property names and self-links
        $data = [
            "type" => "statistics",
            "id" => "entity",
            "attributes" => [
                "entity" => enrichEntityWithSelfLink([
                    "id" => $request["entityID"],
                    "type" => $request["entityType"]
                ], $request["entityType"]),
                "speechCounts" => [
                    "totalSpeeches" => $stats["associations"]["unique_speeches"]["doc_count"], // speeches where entity appears in ANY context
                    "speechesInPrimaryContext" => $stats["speeches_as_main_speaker"]["filter_this_person_as_main_speaker"]["unique_speeches_as_speaker"]["doc_count"] ?? 0 // speeches where entity appears in primary context (main-speaker for person, main-speaker-faction for organisation)
                ],
                "entityAssociations" => [ // SEMANTIC CLARIFICATION: other entities appearing in same speeches as this entity
                    "topCoOccurringPersons" => array_map(function($bucket) {
                        return enrichEntityWithSelfLink([
                            "id" => $bucket["key"],
                            "count" => $bucket["doc_count"]
                        ], 'person');
                    }, $stats["associations"]["top_speakers"]["buckets"]),
                    "topMainSpeakers" => array_map(function($bucket) {
                        return enrichEntityWithSelfLink([
                            "id" => $bucket["key"],
                            "count" => $bucket["doc_count"]
                        ], 'person');
                    }, $stats["associations"]["main_speakers_only"]["top_main_speakers"]["buckets"] ?? [])
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
        
        return createApiSuccessResponse($data, [], [
            "self" => $selfLink
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

/**
 * Get word trends over time using enhanced indexing
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetWordTrends($request) {
    global $config;
    
    try {
        if (empty($request["words"])) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "words array"]
            );
        }
        
        $words = is_array($request["words"]) ? $request["words"] : [$request["words"]];
        $startDate = $request["startDate"] ?? '2020-01-01';
        $endDate = $request["endDate"] ?? date('Y-m-d');
        $parliamentCode = $request["parliament"] ?? 'de';
        $factions = $request["factions"] ?? []; // Add faction filtering support
        
        require_once(__DIR__ . "/../../../modules/search/functions.enhanced.php");
        $trendsResult = getWordTrendsEnhanced($words, $startDate, $endDate, $parliamentCode, $factions);
        
        if (!$trendsResult['success']) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorWordTrendsTitle",
                $trendsResult['error']
            );
        }
        
        // Transform raw OpenSearch data to proper JSON:API format
        $rawData = $trendsResult['data'];
        
        // Process word trends with proper naming conventions
        $wordTrends = [];
        if (isset($rawData['words_over_time']['buckets'])) {
            foreach ($rawData['words_over_time']['buckets'] as $wordBucket) {
                $timeSeriesData = [];
                if (isset($wordBucket['time_series']['buckets'])) {
                    foreach ($wordBucket['time_series']['buckets'] as $timeBucket) {
                        $timeSeriesData[] = [
                            'date' => $timeBucket['key_as_string'],
                            'totalCount' => $timeBucket['total_count']['value'] ?? 0,
                            'speechCount' => $timeBucket['speech_count']['value'] ?? 0
                        ];
                    }
                }
                
                $wordTrends[] = [
                    'word' => $wordBucket['key'],
                    'timeline' => $timeSeriesData
                ];
            }
        }
        
        $data = [
            "type" => "statistics",
            "id" => "word-trends", 
            "attributes" => [
                "words" => $words,
                "startDate" => $startDate,
                "endDate" => $endDate,
                "trends" => $wordTrends
            ]
        ];
        
        // Add faction information if filtered
        if (!empty($factions)) {
            $data["attributes"]["factions"] = $factions;
        }
        
        return createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"] . "/statistics/word-trends"
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorWordTrendsTitle",
            $e->getMessage()
        );
    }
}

/**
 * Get speaker vocabulary analysis using enhanced indexing
 * 
 * @param array $request The request parameters
 * @return array The API response
 */
function statisticsGetSpeakerVocabulary($request) {
    global $config;
    
    try {
        $speakerID = $request["speakerID"] ?? null;
        
        if (empty($speakerID)) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "speakerID"]
            );
        }
        
        $limit = $request["limit"] ?? 50;
        $parliamentCode = $request["parliament"] ?? 'de';
        
        require_once(__DIR__ . "/../../../modules/search/functions.enhanced.php");
        $vocabularyResult = getSpeakerVocabularyEnhanced($speakerID, $limit, $parliamentCode);
        
        if (!$vocabularyResult['success']) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorSpeakerVocabularyTitle",
                $vocabularyResult['error']
            );
        }
        
        // Transform raw OpenSearch data to proper JSON:API format
        $rawData = $vocabularyResult['data'];
        
        // Get actual speech count from main index instead of statistics
        $actualSpeechCount = 0;
        try {
            global $ESClient;
            if ($ESClient) {
                $speechCountQuery = [
                    'size' => 0,
                    'query' => [
                        'nested' => [
                            'path' => 'annotations.data',
                            'query' => [
                                'bool' => [
                                    'must' => [
                                        ['term' => ['annotations.data.id' => $speakerID]],
                                        ['term' => ['annotations.data.attributes.context' => 'main-speaker']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                $countResult = $ESClient->search([
                    'index' => 'openparliamenttv_*',
                    'body' => $speechCountQuery
                ]);
                $actualSpeechCount = $countResult['hits']['total']['value'] ?? 0;
            }
        } catch (Exception $e) {
            error_log("Failed to get actual speech count: " . $e->getMessage());
        }
        
        // Process vocabulary words with proper naming conventions
        $vocabularyWords = [];
        if (isset($rawData['top_words']['buckets'])) {
            foreach ($rawData['top_words']['buckets'] as $bucket) {
                $vocabularyWords[] = [
                    'word' => $bucket['key'],
                    'frequency' => $bucket['frequency']['value'] ?? 0,
                    'speechCount' => $bucket['speech_count']['value'] ?? 0,
                    'firstUsed' => isset($bucket['first_used']['value']) ? date('Y-m-d', $bucket['first_used']['value'] / 1000) : null,
                    'lastUsed' => isset($bucket['last_used']['value']) ? date('Y-m-d', $bucket['last_used']['value'] / 1000) : null
                ];
            }
        }
        
        $data = [
            "type" => "statistics",
            "id" => "speaker-vocabulary",
            "attributes" => [
                "speakerID" => $speakerID,
                "speaker" => enrichEntityWithSelfLink([
                    "id" => $speakerID
                ], 'person'),
                "limit" => $limit,
                "vocabulary" => [
                    "totalWords" => $rawData['total_words']['value'] ?? 0,
                    "uniqueWords" => $rawData['unique_words']['value'] ?? 0,
                    "totalSpeeches" => $actualSpeechCount,
                    "topWords" => $vocabularyWords // Renamed from 'words' for clarity
                ]
            ]
        ];
        
        return createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"] . "/statistics/speaker-vocabulary"
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorSpeakerVocabularyTitle",
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

    // Wrap in proper JSON:API structure
    $data = [
        "type" => "statistics",
        "id" => "entity-counts",
        "attributes" => [
            "entityCounts" => $counts
        ]
    ];

    return createApiSuccessResponse($data, [], [
        "self" => $config["dir"]["api"] . "/statistics/entity-counts"
    ]);
}

?>
