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
 * Enrich entity with self-link and label for easy navigation
 * @param array $entity The entity data
 * @param string $entityType The entity type (required - we should always know this from context)
 * @return array The enriched entity with self-link and label
 */
function enrichEntityWithSelfLink($entity, $entityType) {
    if ($entityType && isset($entity['id'])) {
        $entity['label'] = getEntityLabel($entityType, $entity['id']);
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

/**
 * Fetch entity information (label and filtering data) using existing API methods
 * @param string $entityType The entity type
 * @param string $entityID The entity ID
 * @return array Array with 'label' and 'shouldFilter' keys
 */
function getEntityInfo($entityType, $entityID) {
    try {
        // Cache to avoid multiple lookups for the same entity
        static $infoCache = [];
        $cacheKey = $entityType . ':' . $entityID;
        
        if (isset($infoCache[$cacheKey])) {
            return $infoCache[$cacheKey];
        }
        
        // Map entity types to their respective modules and functions
        $functionMap = [
            'person' => ['module' => 'person', 'function' => 'personGetByID'],
            'organisation' => ['module' => 'organisation', 'function' => 'organisationGetByID'],
            'organization' => ['module' => 'organisation', 'function' => 'organisationGetByID'],
            'document' => ['module' => 'document', 'function' => 'documentGetByID'],
            'term' => ['module' => 'term', 'function' => 'termGetByID']
        ];
        
        $result = ['label' => $entityID, 'shouldFilter' => false];
        $mapping = $functionMap[$entityType] ?? null;
        
        if (!$mapping) {
            $infoCache[$cacheKey] = $result;
            return $result;
        }
        
        // Include the appropriate module
        $modulePath = __DIR__ . "/{$mapping['module']}.php";
        if (file_exists($modulePath)) {
            require_once($modulePath);
            
            if (function_exists($mapping['function'])) {
                $apiResult = $mapping['function']($entityID);
                
                if (isset($apiResult['data']['attributes']['label']) && !empty($apiResult['data']['attributes']['label'])) {
                    $result['label'] = $apiResult['data']['attributes']['label'];
                }
                
                // Check for filtering criteria based on entity type
                if ($entityType === 'person' && isset($apiResult['data']['attributes']['type'])) {
                    $result['shouldFilter'] = ($apiResult['data']['attributes']['type'] === 'memberOfParliament');
                } elseif (($entityType === 'organisation' || $entityType === 'organization') && isset($apiResult['data']['attributes']['type'])) {
                    $result['shouldFilter'] = ($apiResult['data']['attributes']['type'] === 'faction');
                }
            }
        }
        
        $infoCache[$cacheKey] = $result;
        return $result;
    } catch (Exception $e) {
        // Log but don't fail - return ID as fallback
        //error_log("Error fetching entity info for {$entityType}/{$entityID}: " . $e->getMessage());
        return ['label' => $entityID, 'shouldFilter' => false];
    }
}

/**
 * Fetch entity label from database using getByID functions
 * @param string $entityType The entity type
 * @param string $entityID The entity ID
 * @return string The entity label or ID if not found
 */
function getEntityLabel($entityType, $entityID) {
    $info = getEntityInfo($entityType, $entityID);
    return $info['label'];
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
        // Check cache first
        $parliament = $request['parliament'] ?? 'de';
        $cachedResult = getGeneralStatisticsFromCache($parliament);
        if ($cachedResult !== null) {
            return $cachedResult;
        }
        
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

        // Build data structure in the exact order requested: speeches, speakers, speakingTime, vocabulary
        $attributes = [];

        // 1. Speeches (first in order)
        if (isset($stats["speeches"])) {
            $attributes["speeches"] = [
                "total" => $stats["speeches"]["total_speeches"]["speech_count"]["value"],
                "byFaction" => array_map(function($bucket) {
                    return enrichEntityWithSelfLink([
                        "id" => $bucket["key"],
                        "total" => $bucket["speech_count"]["speeches"]["value"]
                    ], 'organisation');
                }, $stats["speeches"]["factions"]["by_faction"]["buckets"])
            ];
        }

        // 2. Speakers (second in order)
        $attributes["speakers"] = [
            "total" => $stats["speakers"]["filtered_speakers"]["unique_speakers"]["value"],
            "topSpeakers" => array_map(function($bucket) {
                return enrichEntityWithSelfLink([
                    "id" => $bucket["key"],
                    "speechCount" => $bucket["unique_speeches"]["speech_count"]["value"]
                ], 'person');
            }, $stats["speakers"]["filtered_speakers"]["top_speakers"]["buckets"]),
            "byFaction" => array_map(function($bucket) {
                return [
                    "factionID" => $bucket["key"],
                    "factionLabel" => getEntityLabel('organisation', $bucket["key"]),
                    "total" => $bucket["back_to_speeches"]["speakers_in_faction"]["main_speakers"]["unique_speakers"]["value"],
                    "topSpeakers" => array_map(function($speakerBucket) {
                        return enrichEntityWithSelfLink([
                            "id" => $speakerBucket["key"],
                            "speechCount" => $speakerBucket["speech_count"]["speeches"]["value"]
                        ], 'person');
                    }, $bucket["back_to_speeches"]["speakers_in_faction"]["main_speakers"]["top_speakers"]["buckets"])
                ];
            }, $stats["speakers_by_faction"]["faction_filter"]["factions"]["buckets"])
        ];

        // 3. Speaking time (third in order)
        if (isset($stats["speakingTime"])) {
            $attributes["speakingTime"] = [
                "total" => $stats["speakingTime"]["sum"],
                "average" => $stats["speakingTime"]["avg"],
                "unit" => "seconds",
                "byFaction" => array_map(function($bucket) {
                    return [
                        "factionID" => $bucket["key"],
                        "factionLabel" => getEntityLabel('organisation', $bucket["key"]),
                        "total" => $bucket["back_to_speeches"]["speaking_time"]["sum"],
                        "average" => $bucket["back_to_speeches"]["speaking_time"]["avg"],
                        "unit" => "seconds"
                    ];
                }, $stats["speaking_time_by_faction"]["faction_filter"]["factions"]["buckets"])
            ];
        }

        // 4. Vocabulary (fourth in order, renamed from wordFrequency)
        if (isset($stats["wordFrequency"])) {
            $attributes["vocabulary"] = [
                "totalUniqueWords" => $stats["wordFrequency"]["total_unique_words"],
                "topWords" => array_map(function($bucket) {
                    return [
                        "word" => $bucket["key"],
                        "speechCount" => $bucket["doc_count"]
                    ];
                }, $stats["wordFrequency"]["buckets"]),
                "byFaction" => array_map(function($factionInfo) {
                    return [
                        "factionID" => $factionInfo["factionID"],
                        "factionLabel" => getEntityLabel('organisation', $factionInfo["factionID"]),
                        "topWords" => $factionInfo["topWords"]
                    ];
                }, (function() use ($stats) {
                    // Find the by_faction context in contextBasedStats
                    $contextStats = $stats["wordFrequency"]["contextBasedStats"] ?? [];
                    foreach ($contextStats as $contextItem) {
                        if (isset($contextItem["type"]) && $contextItem["type"] === "by_faction") {
                            return $contextItem["factions"] ?? [];
                        }
                    }
                    return [];
                })())
            ];
        }

        // Assemble final data structure with enforced order
        $data = [
            "type" => "statistics",
            "id" => "general",
            "attributes" => $attributes
        ];

        $result = createApiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"]."/statistics/general"
        ]);
        
        // Cache the result
        cacheGeneralStatistics($result, $parliament);
        
        return $result;
        
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
        
        // Get speaker vocabulary if this is a person entity
        $speakerVocabulary = null;
        if ($request["entityType"] === 'person') {
            require_once(__DIR__ . "/../../../modules/search/functions.enhanced.php");
            $vocabResult = getSpeakerVocabularyEnhanced($request["entityID"], 50);
            if ($vocabResult['success']) {
                $speakerVocabulary = $vocabResult['data'];
            }
        }
        
        if ($stats === null) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorEntityStatsTitle",
                "messageErrorEntityStatsQueryFailed"
            );
        }
        
        // Build data structure with proper property names and self-links
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
                    "topDetectedEntities" => array_slice(array_values(array_filter(array_map(function($bucket) {
                        // Get the entity type from the nested aggregation
                        $entityType = $bucket["entity_type"]["buckets"][0]["key"] ?? 'unknown';
                        $entityId = $bucket["key"];
                        
                        // Get entity info (label and filtering data) in one database call
                        $entityInfo = getEntityInfo($entityType, $entityId);
                        
                        // Filter out entities that should be filtered (political factions, parliament members)
                        if ($entityInfo['shouldFilter']) {
                            return null;
                        }
                        
                        return enrichEntityWithSelfLink([
                            "id" => $entityId,
                            "type" => $entityType,
                            "coOccurrenceCount" => $bucket["doc_count"]
                        ], $entityType);
                    }, $stats["associations"]["top_detected_entities"]["ner_entities"]["buckets"] ?? []))), 0, 10),
                    "topMentionedBy" => array_map(function($bucket) {
                        return enrichEntityWithSelfLink([
                            "id" => $bucket["key"],
                            "coOccurrenceCount" => $bucket["doc_count"]
                        ], 'person');
                    }, $stats["associations"]["mentioned_by_speakers"]["top_mentioned_by"]["buckets"] ?? [])
                ],
                "trends" => [
                    "total" => $stats["trends"]["buckets"][count($stats["trends"]["buckets"])-1]["doc_count"],
                    "timeline" => array_map(function($bucket) {
                        return [
                            "date" => $bucket["key_as_string"],
                            "speechCount" => $bucket["doc_count"]
                        ];
                    }, $stats["trends"]["buckets"])
                ]
            ]
        ];
        
        // Add speaker vocabulary if available
        if ($speakerVocabulary) {
            $data["attributes"]["speakerVocabulary"] = [
                "totalWords" => $speakerVocabulary["total_words"]["value"] ?? 0,
                "uniqueWords" => $speakerVocabulary["unique_words"]["value"] ?? 0,
                "topWords" => array_map(function($bucket) {
                    return [
                        "word" => $bucket["key"],
                        "frequency" => $bucket["frequency"]["value"] ?? 0,
                        "speechCount" => $bucket["speech_count"]["value"] ?? 0,
                        "firstUsed" => $bucket["first_used"]["value"] ?? null,
                        "lastUsed" => $bucket["last_used"]["value"] ?? null
                    ];
                }, $speakerVocabulary["top_words"]["buckets"] ?? [])
            ];
        }
        
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
 * Get word trends over time using statistics indexing
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
        $separateByFaction = isset($request["separateByFaction"]) && 
                           (strtolower($request["separateByFaction"]) === 'true' || $request["separateByFaction"] === '1' || $request["separateByFaction"] === true);
        
        require_once(__DIR__ . "/../../../modules/search/functions.enhanced.php");
        $trendsResult = getWordTrendsEnhanced($words, $startDate, $endDate, $parliamentCode, $factions, $separateByFaction);
        
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
        
        if ($separateByFaction && isset($rawData['words_by_faction']['buckets'])) {
            // Process faction-separated data structure
            foreach ($rawData['words_by_faction']['buckets'] as $wordBucket) {
                $factionBreakdown = [];
                
                if (isset($wordBucket['factions']['buckets'])) {
                    foreach ($wordBucket['factions']['buckets'] as $factionBucket) {
                        $timeSeriesData = [];
                        if (isset($factionBucket['time_series']['buckets'])) {
                            foreach ($factionBucket['time_series']['buckets'] as $timeBucket) {
                                $timeSeriesData[] = [
                                    'date' => $timeBucket['key_as_string'],
                                    'totalCount' => $timeBucket['total_count']['value'] ?? 0,
                                    'speechCount' => $timeBucket['speech_count']['value'] ?? 0
                                ];
                            }
                        }
                        
                        $factionBreakdown[] = [
                            'factionID' => $factionBucket['key'],
                            'factionLabel' => getEntityLabel('organisation', $factionBucket['key']),
                            'timeline' => $timeSeriesData
                        ];
                    }
                }
                
                $wordTrends[] = [
                    'word' => $wordBucket['key'],
                    'factionBreakdown' => $factionBreakdown
                ];
            }
        } else if (isset($rawData['words_over_time']['buckets'])) {
            // Process regular aggregated data structure
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

/**
 * Get general statistics from cache if valid
 * 
 * @param string $parliament Parliament code (e.g., 'de')
 * @return array|null Cached result or null if not valid/missing
 */
function getGeneralStatisticsFromCache($parliament = 'de') {
    $cacheDir = __DIR__ . '/../cache/';
    $cacheKey = "general_statistics_{$parliament}.json";
    $cacheFile = $cacheDir . $cacheKey;
    $cacheMetaFile = $cacheDir . "general_statistics_{$parliament}.meta";
    
    // Check if both cache and metadata files exist
    if (!file_exists($cacheFile) || !file_exists($cacheMetaFile)) {
        return null;
    }
    
    // Check if cache is still valid
    if (!isGeneralStatisticsCacheValid($parliament)) {
        // Clean up invalid cache files
        @unlink($cacheFile);
        @unlink($cacheMetaFile);
        return null;
    }
    
    // Read and return cached data
    $cachedData = @file_get_contents($cacheFile);
    if ($cachedData !== false) {
        $decodedData = json_decode($cachedData, true);
        if (is_array($decodedData)) {
            return $decodedData;
        }
    }
    
    return null;
}

/**
 * Cache general statistics result
 * 
 * @param array $result The API result to cache
 * @param string $parliament Parliament code (e.g., 'de')
 */
function cacheGeneralStatistics($result, $parliament = 'de') {
    $cacheDir = __DIR__ . '/../cache/';
    $cacheKey = "general_statistics_{$parliament}.json";
    $cacheFile = $cacheDir . $cacheKey;
    $cacheMetaFile = $cacheDir . "general_statistics_{$parliament}.meta";
    
    // Create cache directory if it doesn't exist
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    
    // Get current index versions for validation
    $indexVersions = getCurrentIndexVersions($parliament);
    
    // Save cache data and metadata
    @file_put_contents($cacheFile, json_encode($result));
    @file_put_contents($cacheMetaFile, json_encode([
        'created_at' => time(),
        'parliament' => $parliament,
        'index_versions' => $indexVersions
    ]));
}

/**
 * Check if general statistics cache is valid
 * 
 * @param string $parliament Parliament code (e.g., 'de')
 * @return bool True if cache is valid
 */
function isGeneralStatisticsCacheValid($parliament = 'de') {
    $cacheDir = __DIR__ . '/../cache/';
    $cacheMetaFile = $cacheDir . "general_statistics_{$parliament}.meta";
    
    if (!file_exists($cacheMetaFile)) {
        return false;
    }
    
    $meta = json_decode(@file_get_contents($cacheMetaFile), true);
    if (!$meta || !isset($meta['index_versions'])) {
        return false;
    }
    
    // Compare with current index versions
    $currentVersions = getCurrentIndexVersions($parliament);
    return $meta['index_versions'] === $currentVersions;
}

/**
 * Get current index versions for cache validation
 * 
 * @param string $parliament Parliament code (e.g., 'de')
 * @return array Index version information
 */
function getCurrentIndexVersions($parliament = 'de') {
    try {
        if (!isset($GLOBALS['ESClient'])) {
            return ['timestamp' => time()]; // Fallback
        }
        
        $ESClient = $GLOBALS['ESClient'];
        
        $mainIndexName = "openparliamenttv_" . strtolower($parliament);
        $statsIndexName = "optv_statistics_" . strtolower($parliament);
        
        $mainStats = $ESClient->indices()->stats(['index' => $mainIndexName]);
        $statsStats = $ESClient->indices()->stats(['index' => $statsIndexName]);
        
        return [
            'main_docs' => $mainStats['indices'][$mainIndexName]['total']['docs']['count'] ?? 0,
            'stats_docs' => $statsStats['indices'][$statsIndexName]['total']['docs']['count'] ?? 0,
            'main_size' => $mainStats['indices'][$mainIndexName]['total']['store']['size_in_bytes'] ?? 0,
            'stats_size' => $statsStats['indices'][$statsIndexName]['total']['store']['size_in_bytes'] ?? 0
        ];
    } catch (Exception $e) {
        // Fallback to timestamp if OpenSearch is unavailable
        return ['timestamp' => time()];
    }
}

/**
 * Invalidate general statistics cache for a parliament
 * 
 * @param string $parliament Parliament code (e.g., 'de')
 */
function invalidateGeneralStatisticsCache($parliament = 'de') {
    $cacheDir = __DIR__ . '/../cache/';
    $cacheFile = $cacheDir . "general_statistics_{$parliament}.json";
    $cacheMetaFile = $cacheDir . "general_statistics_{$parliament}.meta";
    
    @unlink($cacheFile);
    @unlink($cacheMetaFile);
}

?>
