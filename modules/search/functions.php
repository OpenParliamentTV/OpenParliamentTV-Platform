<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__ .'/../../modules/utilities/functions.entities.php');
require_once(__DIR__ .'/../../modules/utilities/functions.api.php');

// Debug flag - set to 1 to enable debug logging
$DEBUG_MODE = 0;

// Initialize OpenSearch client using centralized method
$ESClient = getApiOpenSearchClient();
if (is_array($ESClient) && isset($ESClient["errors"])) {
    // Handle error case - log and set to null
    error_log("Failed to initialize OpenSearch client: " . json_encode($ESClient));
    $ESClient = null;
}



/**
 * Get the total count of documents in the OpenSearch index
 * 
 * @return int The total count of documents in the index
 */
function getIndexCount() {
	
	global $ESClient;

	try {
		// Only count speech indices, exclude words indices
		$indices = getParliamentIndices();
		// Convert to lowercase to match actual index names
		$indices = array_map('strtolower', $indices);
		$indexPattern = implode(',', $indices);
		$return = $ESClient->count(['index' => $indexPattern]);
		$result = $return["count"];
	} catch(Exception $e) {
		//print_r($e->getMessage());
		$result = 0;
	}
	
	return $result;
}

/**
 * Search for speeches based on request parameters
 * 
 * This function translates request parameters into a search query and returns the results.
 * It handles text search, filtering, and highlighting of search results.
 * 
 * @param array $request The request parameters containing search criteria
 * @param bool $getAllResults Whether to return all results (up to 10000) or paginate
 * @return array The search results
 */
function searchSpeeches($request, $getAllResults = false) {
    require_once(__DIR__.'/../../modules/utilities/functions.api.php');

    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        // Return error response if client initialization failed
        return [
            "hits" => [
                "hits" => [],
                "total" => ["value" => 0],
                "totalHits" => 0
            ],
            "aggregations" => [
                "term_hits" => [
                    "buckets" => []
                ]
            ]
        ];
    }

    $data = getSearchBody($request, $getAllResults);

    // Debug output
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("Full search body: " . json_encode($data, JSON_PRETTY_PRINT));
    }

    // Add fragment settings to get all matches
    if (!isset($data["highlight"])) {
        $data["highlight"] = [];
    }
    $data["highlight"]["number_of_fragments"] = 0;
    $data["highlight"]["fields"]["attributes.textContents.textHTML"] = [
        "number_of_fragments" => 0,
        "pre_tags" => ["<em>"],
        "post_tags" => ["</em>"]
    ];

    $searchParams = array("index" => "openparliamenttv_*", "body" => $data);
    
    try {
        $results = $ESClient->search($searchParams);
        
        // Debug output
        global $DEBUG_MODE;
        if ($DEBUG_MODE) {
            error_log("Search results: " . json_encode($results, JSON_PRETTY_PRINT));
        }
    } catch(Exception $e) {
        // Debug output
        global $DEBUG_MODE;
        if ($DEBUG_MODE) {
            error_log("Search error: " . $e->getMessage());
        }
        // Return a properly structured error response
        return [
            "hits" => [
                "hits" => [],
                "total" => ["value" => 0],
                "totalHits" => 0
            ],
            "aggregations" => [
                "term_hits" => [
                    "buckets" => []
                ]
            ]
        ];
    }

    $resultCnt = 0;
    $findCnt = 0;
    $highlightCount = 0;
    
    if (isset($request["q"]) && strlen($request["q"]) >= 1) {
        if (isset($results["hits"]["hits"])) {
            foreach ($results["hits"]["hits"] as $hit) {
                $resultCnt++;
                $results["hits"]["hits"][$resultCnt-1]["finds"] = array();
                $results["hits"]["hits"][$resultCnt-1]["highlight_count"] = 0;

                // Process all highlight sections
                if (isset($hit["highlight"]["attributes.textContents.textHTML"])) {
                    foreach ($hit["highlight"]["attributes.textContents.textHTML"] as $html) {
                        if (strlen($html) > 1) {
                            $dom = new DOMDocument();
                            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
                            $xPath = new DOMXPath($dom);
                            $elems = $xPath->query("//em");
                            $highlightCount = $elems->length;
                            $results["hits"]["hits"][$resultCnt-1]["highlight_count"] += $highlightCount;
                            
                            foreach($elems as $elem) {
                                $tmp["data-start"] = ($elem->parentNode->hasAttribute("data-start")) ? $elem->parentNode->getAttribute("data-start") : null;
                                $tmp["data-end"] = ($elem->parentNode->hasAttribute("data-end")) ? $elem->parentNode->getAttribute("data-end") : null;
                                $tmp["class"] = ($elem->parentNode->hasAttribute("class")) ? $elem->parentNode->getAttribute("class") : "";
                                $tmp["context"] = DOMinnerHTML($elem->parentNode);

                                // Only add unique finds
                                if (!in_array($tmp, $results["hits"]["hits"][$resultCnt-1]["finds"])) {
                                    $results["hits"]["hits"][$resultCnt-1]["finds"][] = $tmp;
                                    $findCnt++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $results;
}

/**
 * Get a list of media IDs from search results
 * 
 * This function performs a search and extracts only the media IDs from the results.
 * It's useful when you only need the IDs without the full search results.
 * 
 * @param array $request The request parameters containing search criteria
 * @return array An array containing the media IDs from the search results
 */
function getMediaIDListFromSearchResult($request) {

	global $ESClient;

	$data = getSearchBody($request, true);
	
	$searchParams = array("index" => "openparliamenttv_*", "body" => $data);
	
	try {
		$results = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$results = null;
	}

    $return = array();

    foreach ($results["hits"]["hits"] as $hit) {

        $resultInfo = array(
            "id" => $hit["_source"]["id"]
        );

        $return["results"][] = $resultInfo;

	}
	
	return $return;


}

/**
 * Enhanced autocomplete using enhanced statistics index
 * This function now delegates to the enhanced autocomplete system
 */
function searchAutocomplete($textQuery) {
    
    if (!isset($textQuery) || strlen($textQuery) <= 2 ) {
        return array();
    }
    
    require_once(__DIR__.'/functions.enhanced.php');

    try {
        $enhancedResults = searchAutocompleteEnhanced($textQuery, 9);
        
        // Transform enhanced results to match old format for backward compatibility
        $results = array();
        foreach ($enhancedResults as $result) {
            $results[] = array(
                "text" => highlightSearchTerm($result['text'], $textQuery),
                "score" => $result['frequency'],
                "freq" => $result['frequency'],
                "type" => $result['type']
            );
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Enhanced autocomplete fallback error: " . $e->getMessage());
        return array();
    }
}


/**
 * Get parliament indices for autocomplete search
 */
function getParliamentIndices() {
    global $config;
    $indices = array();
    
    foreach ($config["parliament"] as $parliamentKey => $parliamentConfig) {
        $indices[] = "openparliamenttv_" . $parliamentKey;
    }
    
    return $indices;
}

/**
 * Builds an OpenSearch query body based on request parameters
 * 
 * This function constructs a complex OpenSearch query based on various request parameters.
 * It handles filtering, text search, pagination, sorting, and aggregations.
 * 
 * @param array $request The request parameters containing search criteria
 * @param bool $getAllResults Whether to return all results (up to 10000) or paginate
 * @return array The OpenSearch query body
 */
function getSearchBody($request, $getAllResults) {
    global $config;
    
    // Initialize the filter structure
    $filter = [
        "must" => [],
        "should" => [],
        "must_not" => []
    ];
    
    // Apply default filters if not including all results
    if (!isset($request["includeAll"]) || $request["includeAll"] == false) {
        applyDefaultFilters($filter);
    }
    
    // Process request parameters and build filters
    $shouldCount = processRequestParameters($request, $filter, $getAllResults);
    
    // Build the main query structure
    $query = buildQueryStructure($filter, $request, $shouldCount);
    
    // Determine sorting
    $sort = determineSorting($request);
    
    // Calculate pagination parameters
    $pagination = calculatePagination($request, $getAllResults, $config);
    
    // Build the final query body
    $data = buildFinalQueryBody($query, $sort, $pagination, $getAllResults);
    
    // Add aggregations
    addAggregations($data);
    
    // Debug output
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("Search body: " . json_encode($data, JSON_PRETTY_PRINT));
    }
    
    return $data;
}

/**
 * Apply default filters to exclude certain types of content
 * 
 * This function adds default filters to the search query, such as excluding
 * specific agenda item titles and ensuring aligned speeches are included.
 * 
 * @param array &$filter The filter array to modify
 */
function applyDefaultFilters(&$filter) {
    // Filter out specific agenda item types
    $excludedTitles = [
        "Befragung", "Fragestunde", "Wahl der", "Wahl des", 
        "Sitzungseröffnung", "Sitzungsende"
    ];
    
    foreach ($excludedTitles as $title) {
        $filter["must_not"][] = [
            "wildcard" => [
                "relationships.agendaItem.data.attributes.title.keyword" => "*" . $title . "*"
            ]
        ];
    }
    
    // Hide non-public media
    $filter["must_not"][] = [
        "match" => [
            "attributes.public" => false
        ]
    ];
}

/**
 * Process request parameters and build filters
 * 
 * This function processes various request parameters and adds them to the filter array.
 * It handles parameters like electoralPeriod, sessionID, party, and various IDs.
 * 
 * @param array $request The request parameters
 * @param array &$filter The filter array to modify
 * @return int The count of "should" conditions
 */
function processRequestParameters($request, &$filter, $getAllResults = false) {
    $shouldCount = 0;
    
    foreach ($request as $requestKey => $requestValue) {
        // Skip empty values, but allow zero values
        if ($requestValue === "" || $requestValue === null) {
            continue;
        }
        
        switch ($requestKey) {
            case "parliament":
                // TODO: Implement parliament filtering
                break;
                
            case "electoralPeriod":
                if (strlen($requestValue) > 2) {
                    $filter["must"][] = [
                        "term" => [
                            "relationships.electoralPeriod.data.attributes.number" => $requestValue
                        ]
                    ];
                }
                break;
                
            case "electoralPeriodID":
                if (strlen($requestValue) > 2) {
                    $filter["must"][] = [
                        "term" => [
                            "relationships.electoralPeriod.data.id" => $requestValue
                        ]
                    ];
                }
                break;
                
            case "sessionID":
                if (strlen($requestValue) > 2) {
                    $filter["must"][] = [
                        "match" => [
                            "relationships.session.data.id" => $requestValue
                        ]
                    ];
                }
                break;
                
            case "sessionNumber":
                if (strlen($requestValue) >= 1) {
                    $filter["must"][] = [
                        "match" => [
                            "relationships.session.data.attributes.number" => $requestValue
                        ]
                    ];
                }
                break;
                
            case "agendaItemID":
                if (strlen($requestValue) >= 1) {
                    $agendaItemStringsplit = explode("-", $requestValue);
                    $agendaItemID = array_pop($agendaItemStringsplit);
                    $filter["must"][] = [
                        "match" => [
                            "relationships.agendaItem.data.id" => $agendaItemID
                        ]
                    ];
                }
                break;
            
            case "agendaItemTitle":
                if (strlen($requestValue) > 0) {
                    // Create a should query to match either title or officialTitle
                    $filter["must"][] = [
                        "bool" => [
                            "should" => [
                                [
                                    "wildcard" => [
                                        "relationships.agendaItem.data.attributes.title" => [
                                            "value" => "*" . strtolower($requestValue) . "*",
                                            "case_insensitive" => true
                                        ]
                                    ]
                                ],
                                [
                                    "wildcard" => [
                                        "relationships.agendaItem.data.attributes.officialTitle" => [
                                            "value" => "*" . strtolower($requestValue) . "*",
                                            "case_insensitive" => true
                                        ]
                                    ]
                                ]
                            ],
                            "minimum_should_match" => 1
                        ]
                    ];
                }
                break;
                
            case "party":
            case "faction":
                processPartyOrFactionFilter($requestKey, $requestValue, $filter, $shouldCount);
                break;
                
            case "abgeordnetenwatchID":
            case "fragDenStaatID":
                processExternalIDFilter($requestKey, $requestValue, $request, $filter);
                break;
                
            case "person":
                if (strlen($requestValue) > 1) {
                    $filter["must"][] = createNestedQuery(
                        "annotations.data",
                        [
                            [
                                "match_phrase" => [
                                    "relationships.people.data.label" => $requestValue
                                ]
                            ],
                            [
                                "match" => [
                                    "relationships.people.data.attributes.context" => 'main-speaker'
                                ]
                            ]
                        ]
                    );
                }
                break;
                
            case "personID":
            case "organisationID":
            case "documentID":
            case "termID":
            case "partyID":
            case "factionID":
                processEntityIDFilter($requestKey, $requestValue, $request, $filter, $shouldCount);
                break;
                
            case "procedureID":
                processProcedureIDFilter($requestValue, $request, $filter, $shouldCount);
                break;
                
            case "id":
                if (strlen($requestValue) > 3 && !$getAllResults) {
                    $filter["must"][] = [
                        "match_phrase" => [
                            "id" => $requestValue
                        ]
                    ];
                }
                break;
                
            case "dateFrom":
                $filter["must"][] = [
                    "range" => [
                        "attributes.dateStart" => [
                            "gte" => $requestValue
                        ]
                    ]
                ];
                break;
                
            case "dateTo":
                $filter["must"][] = [
                    "range" => [
                        "attributes.dateStart" => [
                            "lte" => $requestValue
                        ]
                    ]
                ];
                break;
                
            case "public":
                $filter["must"][] = [
                    "term" => [
                        "attributes.public" => $requestValue
                    ]
                ];
                break;
                
            case "aligned":
                $filter["must"][] = [
                    "term" => [
                        "attributes.aligned" => $requestValue
                    ]
                ];
                break;
                
            case "numberOfTexts":
                if (is_numeric($requestValue)) {
                    // Use a simple term query for all values including zero
                    $filter["must"][] = [
                        "term" => [
                            "attributes.textContentsCount" => intval($requestValue)
                        ]
                    ];
                }
                break;
        }
    }
    
    return $shouldCount;
}

/**
 * Process party or faction filter
 * 
 * This function adds a filter for party or faction based on the request parameters.
 * 
 * @param string $requestKey The request key (party or faction)
 * @param mixed $requestValue The request value
 * @param array &$filter The filter array to modify
 * @param int &$shouldCount The count of "should" conditions
 */
function processPartyOrFactionFilter($requestKey, $requestValue, &$filter, &$shouldCount) {
    if (is_array($requestValue)) {
        foreach ($requestValue as $partyOrFaction) {
            $filter["should"][] = [
                "match_phrase" => [
                    "relationships.people.data.attributes." . $requestKey . ".label" => $partyOrFaction
                ]
            ];
            $shouldCount++;
        }
    } else {
        $filter["must"][] = [
            "match" => [
                "relationships.people.data.attributes." . $requestKey . ".label" => $requestValue
            ]
        ];
    }
}

/**
 * Process external ID filter (abgeordnetenwatchID or fragDenStaatID)
 * 
 * This function adds a filter for external ID based on the request parameters.
 * 
 * @param string $requestKey The request key
 * @param mixed $requestValue The request value
 * @param array $request The full request array
 * @param array &$filter The filter array to modify
 */
function processExternalIDFilter($requestKey, $requestValue, $request, &$filter) {
    if ($requestKey == "abgeordnetenwatchID" && !isset($request["context"])) {
        $request["context"] = "main-speaker";
    }
    
    $context = isset($request["context"]) && strlen($request["context"]) > 2 
        ? $request["context"] 
        : 'main-speaker';
    
    $filter["must"][] = createNestedQuery(
        "annotations.data",
        [
            [
                "match" => [
                    "annotations.data.attributes.additionalInformation." . $requestKey => $requestValue
                ]
            ],
            [
                "match_phrase" => [
                    "annotations.data.attributes.context" => $context
                ]
            ]
        ]
    );
}

/**
 * Process entity ID filter (personID, organisationID, etc.)
 * 
 * This function adds a filter for entity ID based on the request parameters.
 * 
 * @param string $requestKey The request key
 * @param mixed $requestValue The request value
 * @param array $request The full request array
 * @param array &$filter The filter array to modify
 * @param int &$shouldCount The count of "should" conditions
 */
function processEntityIDFilter($requestKey, $requestValue, $request, &$filter, &$shouldCount) {
    // Determine resource type and default context
    $resourceType = determineResourceType($requestKey);
    $defaultContext = determineDefaultContext($requestKey);
    
    if (!isset($request["context"])) {
        $request["context"] = $defaultContext;
    }
    
    if (is_array($requestValue)) {
        foreach ($requestValue as $entityID) {
            $filter["should"][] = createEntityIDNestedQuery(
                $entityID, 
                $resourceType, 
                $request["context"]
            );
        }
        $shouldCount++;
    } else {
        $filter["must"][] = createEntityIDNestedQuery(
            $requestValue, 
            $resourceType, 
            $request["context"]
        );
    }
}

/**
 * Determine resource type from request key
 * 
 * This function determines the resource type based on the request key.
 * 
 * @param string $requestKey The request key
 * @return string The resource type
 */
function determineResourceType($requestKey) {
    if ($requestKey == "personID") {
        return "person";
    } else if ($requestKey == "partyID" || $requestKey == "factionID") {
        return "organisation";
    } else {
        return str_replace("ID", "", $requestKey);
    }
}

/**
 * Determine default context from request key
 * 
 * This function determines the default context based on the request key.
 * 
 * @param string $requestKey The request key
 * @return string The default context
 */
function determineDefaultContext($requestKey) {
    if ($requestKey == "personID") {
        return "main-speaker";
    } else if ($requestKey == "partyID") {
        return "main-speaker-party";
    } else if ($requestKey == "factionID") {
        return "main-speaker-faction";
    } else {
        return "";
    }
}

/**
 * Create a nested query for entity ID
 * 
 * This function creates a nested query for entity ID based on the resource type and context.
 * 
 * @param string $entityID The entity ID
 * @param string $resourceType The resource type
 * @param string $context The context
 * @return array The nested query
 */
function createEntityIDNestedQuery($entityID, $resourceType, $context) {
    $query = [
        "nested" => [
            "path" => "annotations.data",
            "query" => [
                "bool" => [
                    "must" => [
                        [
                            "match" => [
                                "annotations.data.id" => $entityID
                            ]
                        ],
                        [
                            "match" => [
                                "annotations.data.type" => $resourceType
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    if (isset($context) && strlen($context) > 2) {
        $query["nested"]["query"]["bool"]["must"][] = [
            "match_phrase" => [
                "annotations.data.attributes.context" => $context
            ]
        ];
    }
    
    return $query;
}

/**
 * Process procedure ID filter
 * 
 * This function adds a filter for procedure ID based on the request parameters.
 * 
 * @param mixed $requestValue The request value
 * @param array $request The full request array
 * @param array &$filter The filter array to modify
 * @param int &$shouldCount The count of "should" conditions
 */
function processProcedureIDFilter($requestValue, $request, &$filter, &$shouldCount) {
    if (strlen($requestValue) < 1) {
        return;
    }
    
    if (is_array($requestValue)) {
        foreach ($requestValue as $procedureID) {
            $filter["should"][] = createProcedureIDNestedQuery(
                $procedureID, 
                $request["context"] ?? null
            );
        }
        $shouldCount++;
    } else {
        $filter["must"][] = createProcedureIDNestedQuery(
            $requestValue, 
            $request["context"] ?? null
        );
    }
}

/**
 * Create a nested query for procedure ID
 * 
 * This function creates a nested query for procedure ID based on the context.
 * 
 * @param string $procedureID The procedure ID
 * @param string|null $context The context
 * @return array The nested query
 */
function createProcedureIDNestedQuery($procedureID, $context) {
    $query = [
        "nested" => [
            "path" => "annotations.data",
            "query" => [
                "bool" => [
                    "must" => [
                        [
                            "match" => [
                                "annotations.data.attributes.additionalInformation.procedureIDs" => $procedureID
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    if (isset($context) && strlen($context) > 2) {
        $query["nested"]["query"]["bool"]["must"][] = [
            "match_phrase" => [
                "annotations.data.attributes.context" => $context
            ]
        ];
    }
    
    return $query;
}

/**
 * Create a nested query
 * 
 * This function creates a nested query based on the path and must conditions.
 * 
 * @param string $path The path
 * @param array $mustConditions The must conditions
 * @return array The nested query
 */
function createNestedQuery($path, $mustConditions) {
    return [
        "nested" => [
            "path" => $path,
            "query" => [
                "bool" => [
                    "must" => $mustConditions
                ]
            ]
        ]
    ];
}

/**
 * Build the query structure
 * 
 * This function builds the query structure based on the filter, request, and should count.
 * 
 * @param array $filter The filter array
 * @param array $request The request array
 * @param int $shouldCount The count of "should" conditions
 * @return array The query structure
 */
function buildQueryStructure($filter, $request, $shouldCount) {
    $query = [
        "bool" => [
            "filter" => [
                "bool" => [
                    "must" => $filter["must"],
                    "should" => $filter["should"],
                    "must_not" => $filter["must_not"]
                ]
            ]
        ]
    ];
    
    // Process text query if present
    if (isset($request["q"]) && strlen($request["q"]) >= 1) {
        processTextQuery($request, $query);
    }
    
    // Set minimum should match if needed
    if ($shouldCount >= 1) {
        $query["bool"]["filter"]["bool"]["minimum_should_match"] = $shouldCount;
    }
    
    return $query;
}

/**
 * Process text query
 * 
 * This function processes the text query and adds it to the query structure.
 * 
 * @param array $request The request array
 * @param array &$query The query array to modify
 */
function processTextQuery($request, &$query) {
    // Normalize quotation marks
    $request["q"] = str_replace(['„','"','\'','«','«'], '"', $request["q"]);
    
    // Extract exact matches (text in quotes)
    $quotationMarksRegex = '/(["\'])(?:(?=(\\\\?))\2.)*?\1/m';
    preg_match_all($quotationMarksRegex, $request["q"], $exact_query_matches);
    
    // Get fuzzy match (text without quotes)
    $fuzzy_match = preg_replace($quotationMarksRegex, '', $request["q"]);
    
    $boolCondition = isset($request["id"]) ? "should" : "must";
    $query["bool"][$boolCondition] = [];
    
    // Process fuzzy match
    if (strlen($fuzzy_match) > 0) {
        processFuzzyMatch($fuzzy_match, $query, $boolCondition);
    }
    
    // Process exact matches
    if (!empty($exact_query_matches[0])) {
        processExactMatches($exact_query_matches[0], $query);
    }
}

/**
 * Process fuzzy match
 * 
 * This function processes fuzzy matches and adds them to the query structure.
 * 
 * @param string $fuzzy_match The fuzzy match text
 * @param array &$query The query array to modify
 * @param string $boolCondition The bool condition
 */
function processFuzzyMatch($fuzzy_match, &$query, $boolCondition) {
    $query_array = preg_split("/(\s)/", $fuzzy_match);
    
    foreach ($query_array as $query_item) {
        if (empty($query_item)) {
            continue;
        }
        
        if (strpos($query_item, '*') !== false) {
            // Wildcard query
            $query["bool"][$boolCondition][] = [
                "wildcard" => [
                    "attributes.textContents.textHTML" => [
                        "value" => $query_item,
                        "case_insensitive" => true,
                        "boost" => 1.0,
                        "rewrite" => "scoring_boolean"
                    ]
                ]
            ];
        } else {
            // Regular match
            $query["bool"][$boolCondition][] = [
                "match" => [
                    "attributes.textContents.textHTML" => [
                        "query" => $query_item,
                        "operator" => "or"
                    ]
                ]
            ];
        }
    }
}

/**
 * Process exact matches
 * 
 * This function processes exact matches and adds them to the query structure.
 * 
 * @param array $exact_query_matches The exact query matches
 * @param array &$query The query array to modify
 */
function processExactMatches($exact_query_matches, &$query) {
    foreach ($exact_query_matches as $exact_match) {
        $exact_match = preg_replace('/(["\'])/m', '', $exact_match);
        $exact_query_array = preg_split("/(\s)/", $exact_match);
        
        if (count($exact_query_array) > 1) {
            // Phrase match for multiple words
            $query["bool"]["must"][] = [
                "match_phrase" => [
                    "attributes.textContents.textHTML" => $exact_match
                ]
            ];
        } else {
            // Single word exact match
            $query["bool"]["must"][] = [
                "match" => [
                    "attributes.textContents.textHTML" => [
                        "query" => $exact_match,
                        "operator" => "and"
                    ]
                ]
            ];
        }
    }
}

/**
 * Determine sorting based on request
 * 
 * This function determines the sorting based on the request parameters.
 * 
 * @param array $request The request array
 * @return array The sort array
 */
function determineSorting($request) {
    if (isset($request["sort"])) {
        if ($request["sort"] == 'date-asc' || $request["sort"] == 'topic-asc') {
            return ["attributes.dateStartTimestamp" => "asc"];
        } else if ($request["sort"] == 'date-desc' || $request["sort"] == 'topic-desc') {
            return ["attributes.dateStartTimestamp" => "desc"];
        } else if ($request["sort"] == 'duration-asc') {
            return ["attributes.duration" => "asc"];
        } else if ($request["sort"] == 'duration-desc') {
            return ["attributes.duration" => "desc"];
        } else if ($request["sort"] == 'changed-asc') {
            return ["attributes.lastChangedTimestamp" => "asc"];
        } else if ($request["sort"] == 'changed-desc') {
            return ["attributes.lastChangedTimestamp" => "desc"];
        }
    }
    
    return ["_score"];
}

/**
 * Calculate pagination parameters
 * 
 * This function calculates pagination based on the request parameters.
 * 
 * @param array $request The request array
 * @param bool $getAllResults Whether to get all results
 * @param array $config The configuration array
 * @return array The pagination parameters
 */
function calculatePagination($request, $getAllResults, $config) {
    // Check if a limit parameter is set and use it to override the default
    $pageSize = $config["display"]["speechesPerPage"];
    if (isset($request["limit"]) && is_numeric($request["limit"]) && intval($request["limit"]) > 0) {
        $pageSize = intval($request["limit"]);
    }
    
    $maxFullResults = ($getAllResults === true) ? 10000 : $pageSize;
    $from = 0;
    
    // Bypass pagination when searching for a specific ID to ensure we find the item
    if (isset($request["id"]) && strlen($request["id"]) > 3) {
        $from = 0;
    } else if (isset($request["page"]) && !$getAllResults) {
        $from = (intval($request["page"]) - 1) * $pageSize;
    }
    
    return [
        "from" => $from,
        "size" => $maxFullResults
    ];
}

/**
 * Build the final query body
 * 
 * This function builds the final query body based on the query, sort, and pagination.
 * 
 * @param array $query The query structure
 * @param array $sort The sort array
 * @param array $pagination The pagination parameters
 * @param bool $getAllResults Whether to get all results
 * @return array The final query body
 */
function buildFinalQueryBody($query, $sort, $pagination, $getAllResults) {
    $data = [
        "from" => $pagination["from"],
        "size" => $pagination["size"],
        "sort" => $sort,
        "query" => $query
    ];
    
    if ($getAllResults === false) {
        $data["highlight"] = [
            "number_of_fragments" => 0,
            "fields" => [
                "attributes.textContents.textHTML" => new \stdClass()
            ]
        ];
    } else {
        $data["_source"] = ["id"];
    }
    
    return $data;
}

/**
 * Add aggregations to the query body
 * 
 * This function adds aggregations to the query body for counting types and dates.
 * 
 * @param array &$data The query body to modify
 */
function addAggregations(&$data) {
    // Nested aggregation for types count
    $data["aggs"]["types_count"]["nested"]["path"] = "annotations.data";
    
    // Factions aggregation
    $data["aggs"]["types_count"]["aggs"]["factions"]["filter"]["bool"]["filter"]["term"]["annotations.data.attributes.context"] = "main-speaker-faction";
    $data["aggs"]["types_count"]["aggs"]["factions"]["aggs"]["terms"]["terms"]["field"] = "annotations.data.id";
    $data["aggs"]["types_count"]["aggs"]["factions"]["aggs"]["terms"]["terms"]["size"] = 20;
    
    // Date range aggregations
    $data["aggs"]["dateFirst"]["min"]["field"] = "attributes.dateStart";
    $data["aggs"]["dateLast"]["max"]["field"] = "attributes.dateEnd";
    
    // Date histogram aggregation
    $data["aggs"]["datesCount"]["date_histogram"]["field"] = "attributes.dateStart";
    $data["aggs"]["datesCount"]["date_histogram"]["calendar_interval"] = "day";
    $data["aggs"]["datesCount"]["date_histogram"]["min_doc_count"] = 1;
    $data["aggs"]["datesCount"]["date_histogram"]["format"] = "yyyy-MM-dd";
}



/**
 * Search for agenda item autocomplete suggestions based on a query
 * 
 * This function provides autocomplete suggestions for agenda item titles.
 * It searches both title and officialTitle fields using match_phrase_prefix queries.
 * 
 * @param string $query The query to search for suggestions
 * @return array An array of autocomplete suggestions
 */
function searchAgendaItemAutocomplete($query) {
    
    if (!isset($query) || strlen($query) <= 2) {
        return array();
    }
    
    require_once(__DIR__.'/../../modules/utilities/functions.api.php');

    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return array();
    }

    $data = getAgendaItemAutocompleteSearchBody($query);
    
    $searchParams = array("index" => "openparliamenttv_*", "body" => $data);
    
    // Debug: Log the query for troubleshooting
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        error_log("Agenda Item Autocomplete Query: " . json_encode($data, JSON_PRETTY_PRINT));
    }
    
    try {
        $results = $ESClient->search($searchParams);
        
        // Debug: Log the results for troubleshooting
        global $DEBUG_MODE;
        if ($DEBUG_MODE) {
            error_log("Agenda Item Autocomplete Results: " . json_encode($results, JSON_PRETTY_PRINT));
        }
    } catch(Exception $e) {
        global $DEBUG_MODE;
        if ($DEBUG_MODE) {
            error_log("Agenda Item Autocomplete Error: " . $e->getMessage());
        }
        return array();
    }

    $return = array();
    
    if ($results && isset($results["hits"]["hits"])) {
        $seenTitles = array();
        
        foreach ($results["hits"]["hits"] as $hit) {
            $agendaItem = $hit["_source"]["relationships"]["agendaItem"]["data"];
            $highlight = $hit["highlight"] ?? array();
            
            if (isset($agendaItem["attributes"])) {
                $title = $agendaItem["attributes"]["title"] ?? "";
                $officialTitle = $agendaItem["attributes"]["officialTitle"] ?? "";
                $agendaItemId = $agendaItem["id"] ?? "";
                
                // Use highlighted title if available, otherwise use original
                $highlightedTitle = "";
                if (isset($highlight["relationships.agendaItem.data.attributes.title.autocomplete"][0])) {
                    $highlightedTitle = $highlight["relationships.agendaItem.data.attributes.title.autocomplete"][0];
                } else {
                    $highlightedTitle = $title;
                }
                
                // Use highlighted official title if available, otherwise use original
                $highlightedOfficialTitle = "";
                if (isset($highlight["relationships.agendaItem.data.attributes.officialTitle.autocomplete"][0])) {
                    $highlightedOfficialTitle = $highlight["relationships.agendaItem.data.attributes.officialTitle.autocomplete"][0];
                } else {
                    $highlightedOfficialTitle = $officialTitle;
                }
                
                // Add title if it exists, not already seen, and contains the query
                if (!empty($title) && !in_array($title, $seenTitles) && 
                    (stripos($title, $query) !== false || stripos($highlightedTitle, '<em>') !== false)) {
                    $return[] = array(
                        "text" => $title,
                        "highlightedText" => $highlightedTitle,
                        "id" => $agendaItemId,
                        "type" => "title"
                    );
                    $seenTitles[] = $title;
                }
                
                // Add official title if it exists, is different from title, not already seen, and contains the query
                if (!empty($officialTitle) && $officialTitle !== $title && !in_array($officialTitle, $seenTitles) &&
                    (stripos($officialTitle, $query) !== false || stripos($highlightedOfficialTitle, '<em>') !== false)) {
                    $return[] = array(
                        "text" => $officialTitle,
                        "highlightedText" => $highlightedOfficialTitle,
                        "id" => $agendaItemId,
                        "type" => "officialTitle"
                    );
                    $seenTitles[] = $officialTitle;
                }
            }
            
            // Limit results to 10 suggestions
            if (count($return) >= 10) {
                break;
            }
        }
    }

    return $return;
}

/**
 * Build an agenda item autocomplete search query body
 * 
 * This function creates a search query body specifically for agenda item autocomplete functionality.
 * It uses match_phrase_prefix queries on both title and officialTitle fields with highlighting.
 * 
 * @param string $query The query to search for suggestions
 * @return array The agenda item autocomplete search query body
 */
function getAgendaItemAutocompleteSearchBody($query) {
    
    $maxResults = 20; // Get more results to account for deduplication

    $data = array(
        "size" => $maxResults,
        "query" => array(
            "bool" => array(
                "should" => array(
                    array(
                        "match_phrase_prefix" => array(
                            "relationships.agendaItem.data.attributes.title.autocomplete" => array(
                                "query" => $query,
                                "max_expansions" => 10
                            )
                        )
                    ),
                    array(
                        "match_phrase_prefix" => array(
                            "relationships.agendaItem.data.attributes.officialTitle.autocomplete" => array(
                                "query" => $query,
                                "max_expansions" => 10
                            )
                        )
                    )
                ),
                "minimum_should_match" => 1
            )
        ),
        "highlight" => array(
            "number_of_fragments" => 0,
            "pre_tags" => array("<em>"),
            "post_tags" => array("</em>"),
            "fields" => array(
                "relationships.agendaItem.data.attributes.title.autocomplete" => new \stdClass(),
                "relationships.agendaItem.data.attributes.officialTitle.autocomplete" => new \stdClass()
            )
        ),
        "_source" => array(
            "relationships.agendaItem.data.attributes.title",
            "relationships.agendaItem.data.attributes.officialTitle",
            "relationships.agendaItem.data.id"
        )
    );

    return $data;
}

/**
 * Get the inner HTML content of a DOM node
 * 
 * This function extracts the inner HTML content of a DOM node,
 * which is useful for processing highlighted search results.
 * 
 * @param DOMNode $element The DOM node to extract inner HTML from
 * @return string The inner HTML content of the node
 */
function DOMinnerHTML(DOMNode $element) { 
    $innerHTML = ""; 
    $children  = $element->childNodes;

    foreach ($children as $child) 
    { 
        $innerHTML .= $element->ownerDocument->saveHTML($child);
    }

    return $innerHTML; 
}

/**
 * Highlight the search term in the text by wrapping it with <em> tags
 * 
 * @param string $text The text to highlight
 * @param string $searchTerm The search term to highlight
 * @return string The text with highlighted search term
 */
function highlightSearchTerm($text, $searchTerm) {
    if (empty($searchTerm)) {
        return $text;
    }
    
    // Case-insensitive replacement
    $pattern = '/(' . preg_quote($searchTerm, '/') . ')/i';
    $replacement = '<em>$1</em>';
    
    return preg_replace($pattern, $replacement, $text);
}

?>