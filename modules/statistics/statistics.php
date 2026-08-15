<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/search/opensearch.php');
require_once(__DIR__.'/../../api/v1/utilities.php');
require_once(__DIR__.'/queries.php');

/**
 * Floor for weighted co-occurrence. The entities that make this ranking useful
 * (a named body, a specific policy term) appear a handful of times, not dozens.
 */
const STATS_COOCCURRENCE_MIN_DOCS = 3;

/** Below this many weighted results, fall back to raw co-occurrence. */
const STATS_COOCCURRENCE_MIN_RESULTS = 5;

/**
 * Most-mentioned entities reported per type by the general endpoint.
 *
 * Higher than the twenty words beside it, because the list is split three ways
 * once the reader picks a type, and because entity cards are cheap to skim.
 */
const STATS_TOP_ENTITIES_SIZE = 24;

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
function getGeneralStatistics($parliamentKey = null, array $scope = []) {
    global $ESClient, $DEBUG_MODE, $config;

    $parliamentKey = resolveParliamentKey($parliamentKey);
    $statisticsIndex = getStatisticsIndexName($parliamentKey);
    $speechIndexPattern = getSearchIndexPattern($parliamentKey);

    // Word-level statistics come from the statistics index, via its read module.
    // This used to be an inline query here, aggregating the pre-aggregated
    // documents the old model wrote; queries.php owns that shape now.
    $vocabulary = statsQueryTopWords(array_merge($scope, [
        "parliament" => $parliamentKey,
        "size"       => 20,
        "byFaction"  => true,
    ]));

    // Which entities were named, from the same index and the same scope. Words
    // and entities are two readings of one question -- what was this scope
    // about -- so they travel together rather than costing a second request.
    $entityMentions = statsQueryTopEntities(array_merge($scope, [
        "parliament" => $parliamentKey,
        "size"       => STATS_TOP_ENTITIES_SIZE,
        "byFaction"  => true,
        // An entity scoped to itself names itself constantly, and saying so
        // tells the reader nothing.
        "excludeIDs" => array_values(array_filter([
            $scope["personID"] ?? null,
            $scope["factionID"] ?? null,
        ])),
    ]));

    $totalUniqueWords = $vocabulary["totalUniqueWords"] ?? 0;
    $topWords = [];
    $contextBasedStats = [];

    foreach ($vocabulary["topWords"] ?? [] as $word) {
        $topWords[] = [
            "key"       => $word["word"],
            "doc_count" => $word["speechCount"],
            "frequency" => $word["frequency"],
        ];
    }

    if (!empty($vocabulary["byFaction"])) {
        $factionStats = ["type" => "by_faction", "factions" => []];
        foreach ($vocabulary["byFaction"] as $faction) {
            $factionStats["factions"][] = [
                "factionID" => $faction["id"],
                "topWords"  => array_map(function ($word) {
                    return [
                        "word"        => $word["word"],
                        "speechCount" => $word["speechCount"],
                        "frequency"   => $word["frequency"],
                    ];
                }, $faction["topWords"]),
            ];
        }
        $contextBasedStats[] = $factionStats;
    }

    // Main aggregation query for all statistics. Scoping narrows the set of
    // speeches every aggregation below runs over, which is what lets one
    // endpoint serve both the platform dashboard and the per-session,
    // per-agendaItem and per-entity modules.
    $speechScopeFilters = statsQuerySpeechScopeFilters($scope);
    $query = [
        'size' => 0,
        'query' => empty($speechScopeFilters)
            ? ['match_all' => (object)[]]
            : ['bool' => ['filter' => $speechScopeFilters]],
        'aggs' => [
            'speakers' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'filtered_speakers' => [
                        'filter' => [ 
                            'bool' => [
                                'must' => [
                                    ['term' => [ 'annotations.data.attributes.context' => 'main-speaker' ]],
                                    ['term' => [ 'annotations.data.type' => 'person' ]]
                                ]
                            ]
                        ],
                        'aggs' => [
                            'unique_speakers' => [ 'cardinality' => [ 'field' => 'annotations.data.id' ] ],
                            'top_speakers' => [ 
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 20 ],
                                'aggs' => [
                                    'unique_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speech_count' => [ 'cardinality' => [ 'field' => '_id' ] ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'speakers_by_faction' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'faction_filter' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [
                            'factions' => [
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                'aggs' => [
                                    'back_to_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speakers_in_faction' => [
                                                'nested' => [ 'path' => 'annotations.data' ],
                                                'aggs' => [
                                                    'main_speakers' => [
                                                        'filter' => [ 
                                                            'bool' => [
                                                                'must' => [
                                                                    ['term' => [ 'annotations.data.attributes.context' => 'main-speaker' ]],
                                                                    ['term' => [ 'annotations.data.type' => 'person' ]]
                                                                ]
                                                            ]
                                                        ],
                                                        'aggs' => [
                                                            'unique_speakers' => [ 'cardinality' => [ 'field' => 'annotations.data.id' ] ],
                                                            'top_speakers' => [
                                                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 20 ],
                                                                'aggs' => [
                                                                    'speech_count' => [
                                                                        'reverse_nested' => new stdClass(),
                                                                        'aggs' => [
                                                                            'speeches' => [ 'cardinality' => [ 'field' => '_id' ] ]
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
            ],
            'speakingTime' => [ 'stats' => [ 'field' => 'attributes.duration' ] ],
            'speaking_time_by_faction' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'faction_filter' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [
                            'factions' => [
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10 ],
                                'aggs' => [
                                    'back_to_speeches' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speaking_time' => [ 'stats' => [ 'field' => 'attributes.duration' ] ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'speeches' => [
                'nested' => [ 'path' => 'annotations.data' ],
                'aggs' => [
                    'total_speeches' => [
                        'reverse_nested' => new stdClass(),
                        'aggs' => [
                            'speech_count' => [ 'cardinality' => [ 'field' => '_id' ] ]
                        ]
                    ],
                    'factions' => [
                        'filter' => [ 'term' => [ 'annotations.data.attributes.context' => 'main-speaker-faction' ] ],
                        'aggs' => [ 
                            'by_faction' => [ 
                                'terms' => [ 'field' => 'annotations.data.id', 'size' => 10, 'order' => ['speech_count' => 'desc'] ],
                                'aggs' => [
                                    'speech_count' => [
                                        'reverse_nested' => new stdClass(),
                                        'aggs' => [
                                            'speeches' => [ 'cardinality' => [ 'field' => '_id' ] ]
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
        $results = $ESClient->search([
            'index' => $speechIndexPattern,
            'body' => $query
        ]);
        if (!isset($results['aggregations'])) {
            throw new Exception('Invalid OpenSearch response: missing required aggregations');
        }
        $aggregations = $results['aggregations'];
        // Inject the enhanced word frequency data from statistics index
        $aggregations['wordFrequency'] = [
            'buckets' => $topWords,
            'total_unique_words' => $totalUniqueWords,
            // Enhanced: Context-based word analysis as recommended in planning docs
            'contextBasedStats' => $contextBasedStats
        ];
        // Entity mentions travel as the query module shaped them; resolving ids
        // to labels and thumbnails is the API module's job.
        $aggregations['entityMentions'] = $entityMentions;
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
function getEntityStatistics($entityType, $entityID, $parliamentKey = null) {
    global $ESClient, $DEBUG_MODE;
    
    $speechIndexPattern = getSearchIndexPattern($parliamentKey);
    
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
                    "top_detected_entities" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.attributes.context" => "NER"]]
                                ],
                                "must_not" => [
                                    ["term" => ["annotations.data.id" => $entityID]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            // Two rankings of the same buckets, both cheap, with the
                            // caller choosing. Raw co-occurrence answers "who appears
                            // here most", which for any entity with a broad footprint
                            // is the same handful of globally frequent names. Weighted
                            // co-occurrence answers "who appears here MORE than
                            // elsewhere", which is the question worth asking — but it
                            // only has an answer when the entity's profile actually
                            // differs from the corpus, so a very frequent entity can
                            // legitimately produce nothing and needs the raw list to
                            // fall back on.
                            "ner_entities" => [
                                "terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 50
                                ],
                                "aggs" => [
                                    "entity_type" => [
                                        "terms" => [
                                            "field" => "annotations.data.type.keyword",
                                            "size" => 1
                                        ]
                                    ]
                                ]
                            ],
                            "ner_entities_significant" => [
                                "significant_terms" => [
                                    "field" => "annotations.data.id",
                                    "size" => 50,
                                    // Low on purpose: the entities worth surfacing here
                                    // are specific and therefore rare. Raising this to
                                    // 20 drops IPCC and Klimaneutralität from "Globale
                                    // Erwärmung" and hands back the generic list.
                                    "min_doc_count" => STATS_COOCCURRENCE_MIN_DOCS
                                ],
                                "aggs" => [
                                    "entity_type" => [
                                        "terms" => [
                                            "field" => "annotations.data.type.keyword",
                                            "size" => 1
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "mentioned_by_speakers" => [
                        "filter" => [
                            "bool" => [
                                "must" => [
                                    ["term" => ["annotations.data.type" => "person"]],
                                    ["term" => ["annotations.data.attributes.context" => "main-speaker"]]
                                ],
                                "must_not" => [
                                    ["term" => ["annotations.data.id" => $entityID]]
                                ]
                            ]
                        ],
                        "aggs" => [
                            "top_mentioned_by" => [
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
            "index" => $speechIndexPattern,
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




?>