<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/text-array-converters.php");
require_once (__DIR__."/../../../modules/statistics/statistics.php");
require_once (__DIR__."/../../../modules/statistics/queries.php");
require_once (__DIR__."/../../../modules/statistics/cache.php");
require_once (__DIR__."/../../../modules/database/connections.php");
require_once (__DIR__."/../../../api/v1/utilities.php");

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
        $parliament = resolveParliamentKey($request['parliament'] ?? null);

        // Optional scope. Parameter names mirror media search, so the same
        // filters a user applied to a result list can be handed straight to this
        // endpoint — which is what the per-session, per-agendaItem and
        // per-entity modules do.
        $scope = statisticsScopeFromRequest($request);

        // Scoped calls get their own cache entries; the unscoped one is the
        // corpus-wide response the post-indexing warm step precomputes.
        $cachedResult = statsCacheGet("general", $scope, $parliament);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        // Check if OpenSearch client is available
        if (!isset($GLOBALS['ESClient'])) {
            return apiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorOpenSearchClientInitFailed"
            );
        }

        $stats = getGeneralStatistics($parliament, $scope);

        if ($stats === null) {
            return apiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorOpenSearchQueryFailed"
            );
        }
        
        // Process speaker statistics
        if (!isset($stats["speakers"]["filtered_speakers"])) {
            return apiErrorResponse(
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
                    "id" => $bucket["key"],
                    "label" => getEntityLabel('organisation', $bucket["key"]),
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
                        "id" => $bucket["key"],
                        "label" => getEntityLabel('organisation', $bucket["key"]),
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
                        "speechCount" => $bucket["doc_count"],
                        "frequency" => $bucket["frequency"]
                    ];
                }, $stats["wordFrequency"]["buckets"]),
                "byFaction" => array_map(function($factionInfo) {
                    return [
                        "id" => $factionInfo["factionID"],
                        "label" => getEntityLabel('organisation', $factionInfo["factionID"]),
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

        $result = apiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"]."/statistics/general"
        ]);
        
        // Cache the result
        statsCachePut("general", $scope, $parliament, $result);
        
        return $result;
        
    } catch (Exception $e) {
        return apiErrorResponse(
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
/**
 * Entities worth showing alongside this one.
 *
 * Prefers the weighted ranking, which asks "who appears here MORE than
 * elsewhere" rather than "who appears here most". The unweighted question has a
 * dull answer for anything with a broad footprint: the same globally frequent
 * names show up beside every entity, because they show up beside everything.
 *
 * Weighting only has an answer when the entity's company actually differs from
 * the corpus average, so a very frequent entity can legitimately produce almost
 * nothing — hence the fall back to raw co-occurrence rather than an empty panel.
 *
 * @param array $stats Aggregations from getEntityStatistics().
 * @return array Up to ten entities, richest ranking first.
 */
function statisticsPickCoOccurrences($stats) {
    $associations = $stats["associations"]["top_detected_entities"] ?? [];

    $shape = function ($buckets) {
        return array_slice(array_values(array_filter(array_map(function ($bucket) {
            $entityType = $bucket["entity_type"]["buckets"][0]["key"] ?? 'unknown';
            $entityId = $bucket["key"];

            $entityInfo = getEntityInfo($entityType, $entityId);

            // Factions and members of parliament are dropped: they co-occur with
            // everything by construction and say nothing about this entity.
            if ($entityInfo['shouldFilter']) {
                return null;
            }

            // An entity we cannot name is not worth a node in a graph.
            if ($entityInfo['label'] === $entityId) {
                return null;
            }

            return enrichEntityWithSelfLink([
                "id" => $entityId,
                "type" => $entityType,
                "coOccurrenceCount" => $bucket["doc_count"]
            ], $entityType);
        }, $buckets))), 0, 10);
    };

    $weighted = $shape($associations["ner_entities_significant"]["buckets"] ?? []);

    if (count($weighted) >= STATS_COOCCURRENCE_MIN_RESULTS) {
        return $weighted;
    }

    return $shape($associations["ner_entities"]["buckets"] ?? []);
}

function statisticsGetEntity($request) {
    global $config;
    
    try {
        if (empty($request["entityType"]) || empty($request["entityID"])) {
            return apiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "entityType and entityID"]
            );
        }
        
        $parliament = resolveParliamentKey($request['parliament'] ?? null);
        $stats = getEntityStatistics($request["entityType"], $request["entityID"], $parliament);
        
        // Vocabulary, for the entities that actually speak. People are looked up
        // by speaker; factions and parties by the faction their speakers belong
        // to, which is a scope over the same word documents rather than a
        // different question. Everything else (documents, terms, ministries) is
        // only ever spoken ABOUT and gets no vocabulary block.
        $speakerVocabulary = null;
        if ($request["entityType"] === 'person') {
            $speakerVocabulary = statsQuerySpeakerVocabulary($request["entityID"], STATS_SPEAKER_VOCABULARY_SIZE, $parliament);
        } elseif ($request["entityType"] === 'organisation') {
            $entityInfo = getEntityInfo('organisation', $request["entityID"]);
            if (!empty($entityInfo['shouldFilter'])) {
                $factionWords = statsQueryTopWords([
                    "parliament" => $parliament,
                    "factionID"  => $request["entityID"],
                    "size"       => STATS_SPEAKER_VOCABULARY_SIZE,
                    "byFaction"  => false,
                ]);
                if ($factionWords !== null) {
                    $speakerVocabulary = [
                        "totalWords"  => null,
                        "uniqueWords" => $factionWords["totalUniqueWords"],
                        "topWords"    => array_map(function ($word) {
                            return [
                                "word"        => $word["word"],
                                "frequency"   => $word["frequency"],
                                "speechCount" => $word["speechCount"],
                                "firstUsed"   => null,
                                "lastUsed"    => null,
                            ];
                        }, $factionWords["topWords"]),
                    ];
                }
            }
        }
        
        if ($stats === null) {
            return apiErrorResponse(
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
                    "topDetectedEntities" => statisticsPickCoOccurrences($stats),
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
        
        // Add speaker vocabulary if available. statsQuerySpeakerVocabulary()
        // returns it already shaped, so this is a straight assignment; it used to
        // unpack raw OpenSearch aggregation buckets here.
        if ($speakerVocabulary) {
            $data["attributes"]["speakerVocabulary"] = $speakerVocabulary;
        }

        // When this entity was talked ABOUT, as opposed to when it was speaking.
        // Structural contexts are excluded, so a person's own speeches do not
        // register as mentions of them; documents come back as two series,
        // because being on the agenda and being named in a speech are different
        // events worth labelling differently.
        $mentions = statsQueryEntityMentions(
            $request["entityType"],
            $request["entityID"],
            [
                "parliament" => $parliament,
                "dateFrom"   => $request["dateFrom"] ?? null,
                "dateTo"     => $request["dateTo"] ?? null,
            ]
        );
        if ($mentions !== null) {
            $data["attributes"]["mentionsOverTime"] = $mentions;
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
        
        return apiSuccessResponse($data, [], [
            "self" => $selfLink
        ]);
        
    } catch (Exception $e) {
        return apiErrorResponse(
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
/**
 * Scope parameters a statistics request may carry.
 *
 * Deliberately the same names media search uses, so a caller can hand the same
 * filters to either. Anything not listed here is ignored rather than passed
 * through, which keeps the cache key bounded — an unbounded key would let a
 * caller mint unlimited cache entries by varying a parameter nobody reads.
 */
const STATS_SCOPE_PARAMS = [
    'sessionID', 'agendaItemID', 'electoralPeriodID', 'factionID', 'personID',
    'dateFrom', 'dateTo',
];

/**
 * Extract the scope filters from a request.
 *
 * @return array Only the keys actually supplied, sorted, so two requests that
 *               mean the same scope produce the same cache key.
 */
function statisticsScopeFromRequest($request)
{
    $scope = [];

    foreach (STATS_SCOPE_PARAMS as $param) {
        if (isset($request[$param]) && $request[$param] !== '') {
            $scope[$param] = is_array($request[$param])
                ? reset($request[$param])
                : $request[$param];
        }
    }

    ksort($scope);

    return $scope;
}

/** Fallback when a parliament has no electoral periods recorded yet. */
const STATS_DATA_START_FALLBACK = '2000-01-01';

/** How many of a speaker's words the entity endpoint reports. */
const STATS_SPEAKER_VOCABULARY_SIZE = 50;

/**
 * The date a parliament's data begins.
 *
 * Read from the earliest electoral period, NOT from the earliest indexed speech:
 * an instance that has only imported recent sessions would otherwise report its
 * import coverage as the start of parliamentary history, and the answer would
 * drift every time older material was imported.
 *
 * @param string|null $parliament Parliament config key.
 * @return string YYYY-MM-DD.
 */
function statisticsParliamentDataStart($parliament = null) {
    global $config;

    static $cache = [];

    $parliament = resolveParliamentKey($parliament);

    if (isset($cache[$parliament])) {
        return $cache[$parliament];
    }

    $cache[$parliament] = STATS_DATA_START_FALLBACK;

    try {
        $dbp = optvDbConnection('parliament', $parliament);
        $table = $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"];
        $earliest = $dbp->getOne(
            "SELECT MIN(ElectoralPeriodDateStart) FROM ?n WHERE ElectoralPeriodDateStart IS NOT NULL",
            $table
        );
        if (!empty($earliest)) {
            $cache[$parliament] = substr($earliest, 0, 10);
        }
    } catch (Exception $e) {
        error_log('statisticsParliamentDataStart: ' . $e->getMessage());
    }

    return $cache[$parliament];
}

function statisticsGetWordTrends($request) {
    global $config;
    
    try {
        if (empty($request["words"])) {
            return apiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => "words array"]
            );
        }
        
        $words = is_array($request["words"]) ? $request["words"] : [$request["words"]];
        $parliament = resolveParliamentKey($request["parliament"] ?? null);

        // Default start is where this parliament's data actually begins, taken
        // from its earliest electoral period. It used to be a hardcoded
        // '2020-01-01', which silently cropped a decade off every trend line.
        $startDate = $request["startDate"] ?? statisticsParliamentDataStart($parliament);
        $endDate = $request["endDate"] ?? date('Y-m-d');
        $factions = $request["factions"] ?? []; // Add faction filtering support
        $separateByFaction = isset($request["separateByFaction"]) &&
                           (strtolower($request["separateByFaction"]) === 'true' || $request["separateByFaction"] === '1' || $request["separateByFaction"] === true);

        // Group inflected forms by default: "Flüchtlinge" and "Flüchtling" are
        // one line unless the caller explicitly asks for exact surface forms.
        $matchMode = (isset($request["match"]) && strtolower($request["match"]) === 'exact') ? 'exact' : 'stem';
        $normalize = (isset($request["normalize"]) && strtolower($request["normalize"]) === 'relative') ? 'relative' : 'absolute';

        $trendsResult = statsQueryWordTrends($words, [
            "parliament"        => $parliament,
            "dateFrom"          => $startDate,
            "dateTo"            => $endDate,
            "factions"          => $factions,
            "separateByFaction" => $separateByFaction,
            "match"             => $matchMode,
        ]);

        if ($trendsResult === null) {
            return apiErrorResponse(
                500,
                1,
                "messageErrorWordTrendsTitle",
                "messageErrorOpenSearchQueryFailed"
            );
        }

        $wordTrends = $trendsResult['trends'];

        // Relative mode adds the tokens spoken in the same scope and month, so a
        // word does not look like it is surging simply because the month was a
        // busy one. The denominator is the same for every word in the request,
        // and is returned as a count rather than a rate — see
        // statsQueryAttachTotals().
        if ($normalize === 'relative') {
            $totals = statsQueryTotalsHistogram([
                "parliament" => $parliament,
                "dateFrom"   => $startDate,
                "dateTo"     => $endDate,
                "factions"   => $factions,
            ]);
            if ($totals !== null) {
                statsQueryAttachTotals($wordTrends, $totals);
            }
        }

        // Faction labels are resolved here rather than in the query module, which
        // has no business knowing about the entity database.
        foreach ($wordTrends as &$trend) {
            foreach ($trend['factionBreakdown'] ?? [] as &$faction) {
                $faction['label'] = getEntityLabel('organisation', $faction['id']);
            }
            unset($faction);
        }
        unset($trend);

        $data = [
            "type" => "statistics",
            "id" => "word-trends",
            "attributes" => [
                "words" => $words,
                "startDate" => $startDate,
                "endDate" => $endDate,
                "match" => $matchMode,
                "normalize" => $normalize,
                "trends" => $wordTrends
            ]
        ];

        // Add faction information if filtered
        if (!empty($factions)) {
            $data["attributes"]["factions"] = $factions;
        }

        return apiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"] . "/statistics/word-trends"
        ]);
        
    } catch (Exception $e) {
        return apiErrorResponse(
            500,
            1,
            "messageErrorWordTrendsTitle",
            $e->getMessage()
        );
    }
}


/**
 * Words that characterise a scope, against the rest of the corpus.
 *
 * Unlike a top-words list, which any large scope answers with the same handful
 * of common nouns, this reports what is distinctive about the scope. At least
 * one scope filter is required — "what characterises everything" has no answer.
 *
 * @param array $request
 * @return array The API response
 */
function statisticsGetSignificantWords($request) {
    global $config;

    try {
        $parliament = resolveParliamentKey($request["parliament"] ?? null);
        $scope = statisticsScopeFromRequest($request);

        if (empty($scope)) {
            return apiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorMissingParametersDetail",
                ["parameters" => implode(", ", STATS_SCOPE_PARAMS)]
            );
        }

        $size = isset($request["size"]) ? (int) $request["size"] : 20;
        $cacheParams = $scope + ["size" => $size];

        $cached = statsCacheGet("significant-words", $cacheParams, $parliament);
        if ($cached !== null) {
            return $cached;
        }

        $words = statsQuerySignificantWords(array_merge($scope, [
            "parliament" => $parliament,
            "size"       => $size,
        ]));

        if ($words === null) {
            return apiErrorResponse(
                500,
                1,
                "messageErrorStatisticsTitle",
                "messageErrorOpenSearchQueryFailed"
            );
        }

        $data = [
            "type" => "statistics",
            "id" => "significant-words",
            "attributes" => [
                "scope" => $scope,
                "words" => $words
            ]
        ];

        $result = apiSuccessResponse($data, [], [
            "self" => $config["dir"]["api"] . "/statistics/significant-words?" . http_build_query($scope)
        ]);

        statsCachePut("significant-words", $cacheParams, $parliament, $result);

        return $result;

    } catch (Exception $e) {
        return apiErrorResponse(
            500,
            1,
            "messageErrorStatisticsTitle",
            $e->getMessage()
        );
    }
}


function statisticsGetEntityCounts($request) {
    global $config;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return apiErrorResponse(503, 1, "Database connection error", "Connecting to platform database failed");
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
        return apiErrorResponse(500, 1, "Database query error", $e->getMessage());
    }

    // Wrap in proper JSON:API structure
    $data = [
        "type" => "statistics",
        "id" => "entity-counts",
        "attributes" => [
            "entityCounts" => $counts
        ]
    ];

    return apiSuccessResponse($data, [], [
        "self" => $config["dir"]["api"] . "/statistics/entity-counts"
    ]);
}



?>
