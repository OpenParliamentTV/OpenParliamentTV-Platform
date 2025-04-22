<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');
require_once(__DIR__ .'/../../modules/utilities/functions.entities.php');

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
//print_r($ESClientBuilder);

$ESClient = $ESClientBuilder->build();



/**
 * Get the total count of documents in the OpenSearch index
 * 
 * @return int The total count of documents in the index
 */
function getIndexCount() {
	
	global $ESClient;

	try {
		$return = $ESClient->count(['index' => 'openparliamenttv_*']);
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
 * @return array The search results
 */
function searchSpeeches($request) {
    //print_r($request);

    require_once(__DIR__.'/../../vendor/autoload.php');
    require(__DIR__.'/../../config.php');

    /*
    if ($request["id"]) {
        require_once(__DIR__.'/../utilities/functions.php');
        $itemInfos = getInfosFromStringID($request["id"]);
        $parliament = strtolower($itemInfos["parliament"]);
    } else {
        //TODO: How to seach in all indexes?
        $parliament = "de";
    }
    */

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


	$data = getSearchBody($request, false);

	$searchParams = array("index" => "openparliamenttv_*", "body" => $data);

    //echo json_encode($searchParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	
	try {
		//Example: $results = $ESClient->get(['index' => "openparliamenttv_*", 'id' => 'DE-BB-0070047001']);

		$results = $ESClient->search($searchParams);
		//print_r($results);
	} catch(Exception $e) {
		$results = $e->getMessage();
	}

    /*
     * TODO: The result of what faction has how many speeches, how many speeches are there in general and is there are speeches without
     * a faction is now present at $results["aggregations"]["types_count"] ...
     *
     *
        echo '<pre style="color: #fff">';
        print_r($results);
        echo '</pre>';
    */

	$resultCnt = 0;
	$findCnt = 0;
	
	if (strlen($request["q"]) >= 1) {
		if (isset($results["hits"]["hits"])) {
			foreach ($results["hits"]["hits"] as $hit) {
		
				//if ($resultCnt >= $maxFullResults) { break; }
				$resultCnt++;
				$results["hits"]["hits"][$resultCnt-1]["finds"] = array();

				$html = $hit["highlight"]["attributes.textContents.textHTML"][0];

				if (strlen($html) > 1) {
					$dom = new DOMDocument();
					@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
					$xPath = new DOMXPath($dom);
					//$elems = $xPath->query("//div[@class='rede']/*");
					//$elems = $xPath->query("//div | //p/span");
					$elems = $xPath->query("//em");
					
					/*
					echo '<pre>';
					print_r($html); 
					echo '</pre>';
					*/

					foreach($elems as $k=>$elem) {

						$tmp["data-start"] = ($elem->parentNode->hasAttribute("data-start")) ? $elem->parentNode->getAttribute("data-start") : null;
						$tmp["data-end"] = ($elem->parentNode->hasAttribute("data-end")) ? $elem->parentNode->getAttribute("data-end") : null;
						$tmp["class"] = ($elem->parentNode->hasAttribute("class")) ? $elem->parentNode->getAttribute("class") : "";
						$tmp["context"] = DOMinnerHTML($elem->parentNode);

						if (!in_array($tmp, $results["hits"]["hits"][$resultCnt-1]["finds"])) {
							$results["hits"]["hits"][$resultCnt-1]["finds"][] = $tmp;
							$findCnt++;
						}
						

						/*
						echo '<pre>';
						print_r(DOMinnerHTML($elem->parentNode));
						echo '</pre>';
						*/
						
						
					}
				}

			}
		}
		
	}



	//$results->totalFinds = $findCnt;

	
	/*
	echo '<pre>';
	print_r($results["hits"]["hits"][0]);
	echo '</pre>';
	*/
	

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
 * Search for autocomplete suggestions based on a text query
 * 
 * This function provides autocomplete suggestions for a given text query.
 * It's useful for implementing search suggestions as the user types.
 * 
 * @param string $textQuery The text query to search for suggestions
 * @return array An array of autocomplete suggestions
 */
function searchAutocomplete($textQuery) {
    
    if (!isset($textQuery) || !strlen($textQuery) > 2 ) {
        return array();
    }
    
    require_once(__DIR__.'/../../vendor/autoload.php');
    require(__DIR__.'/../../config.php');

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

    $data = getAutocompleteSearchBody($textQuery);
    
    $searchParams = array("index" => "openparliamenttv_*", "body" => $data);
    
    try {
        $results = $ESClient->search($searchParams);
    } catch(Exception $e) {
        //print_r($e->getMessage());
        $results = null;
    }

    /*
    echo '<pre>';
    print_r($results); 
    echo '</pre>';
    */

    if ($results && isset($results["suggest"]["autosuggest"][0]["options"])) {
        $return = $results["suggest"]["autosuggest"][0]["options"];
    } else {
        $return = array();
    }

    return $return;

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
    if (!$request["includeAll"] || $request["includeAll"] == false) {
        applyDefaultFilters($filter);
    }
    
    // Process request parameters and build filters
    $shouldCount = processRequestParameters($request, $filter);
    
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
            "match_phrase" => [
                "relationships.agendaItem.data.attributes.title" => $title
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
function processRequestParameters($request, &$filter) {
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
    
    // Determine bool condition
    $boolCondition = $request["id"] ? "should" : "must";
    $query["bool"][$boolCondition] = [];
    
    // Add a should clause for title boosting
    $query["bool"]["should"] = [];
    
    // Process fuzzy match
    if (strlen($fuzzy_match) > 0) {
        $query_array = preg_split("/(\s)/", $fuzzy_match);
        
        foreach ($query_array as $query_item) {
            if (empty($query_item)) {
                continue;
            }
            
            if (strpos($query_item, '*') !== false) {
                // Wildcard query with boost for text content
                $query["bool"][$boolCondition][] = [
                    "wildcard" => [
                        "attributes.textContents.textHTML" => [
                            "value" => $query_item,
                            "case_insensitive" => true,
                            "boost" => 5.0,  // Higher boost for text content wildcard matches
                            "rewrite" => "scoring_boolean"
                        ]
                    ]
                ];
            } else {
                // Regular match query with boost for text content
                $query["bool"][$boolCondition][] = [
                    "match" => [
                        "attributes.textContents.textHTML" => [
                            "query" => $query_item,
                            "prefix_length" => 0,
                            "boost" => 5.0  // Higher boost for text content matches
                        ]
                    ]
                ];
            }
            
            // Add very small boost for title match
            $query["bool"]["should"][] = [
                "match" => [
                    "relationships.agendaItem.data.attributes.title" => [
                        "query" => $query_item,
                        "boost" => 0.2  // Very small boost for title matches
                    ]
                ]
            ];
        }
    }
    
    // Process exact matches
    if (!empty($exact_query_matches[0])) {
        foreach ($exact_query_matches[0] as $exact_match) {
            $exact_match = preg_replace('/(["\'])/m', '', $exact_match);
            
            if (strpos($exact_match, '*') !== false) {
                // Wildcard exact match
                $exact_query_array = preg_split("/(\s)/", $exact_match);
                
                $span_near = [
                    "clauses" => [],
                    "slop" => 0,
                    "in_order" => true,
                    "boost" => 6.0  // Highest boost for exact text content matches
                ];
                
                foreach ($exact_query_array as $exact_query_item) {
                    if (empty($exact_query_item)) {
                        continue;
                    }
                    
                    if (strpos($exact_query_item, '*') !== false) {
                        $span_near["clauses"][] = [
                            "span_multi" => [
                                "match" => [
                                    "wildcard" => [
                                        "attributes.textContents.textHTML" => [
                                            "value" => $exact_query_item,
                                            "case_insensitive" => true
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $span_near["clauses"][] = [
                            "span_term" => [
                                "attributes.textContents.textHTML" => strtolower($exact_query_item)
                            ]
                        ];
                    }
                }
                
                $query["bool"]["must"][] = ["span_near" => $span_near];
            } else {
                // Regular exact match with boost for text content
                $query["bool"]["must"][] = [
                    "match_phrase" => [
                        "attributes.textContents.textHTML" => [
                            "query" => $exact_match,
                            "boost" => 6.0  // Highest boost for exact text content matches
                        ]
                    ]
                ];
            }
            
            // Add very small boost for exact title match
            $query["bool"]["should"][] = [
                "match_phrase" => [
                    "relationships.agendaItem.data.attributes.title" => [
                        "query" => $exact_match,
                        "boost" => 0.3  // Slightly higher but still very small boost for exact title matches
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
    
    if (isset($request["page"]) && !$getAllResults) {
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
 * Build an autocomplete search query body
 * 
 * This function creates a search query body specifically for autocomplete functionality.
 * It uses the suggest API to provide text suggestions as the user types.
 * 
 * @param string $text The text query to search for suggestions
 * @return array The autocomplete search query body
 */
function getAutocompleteSearchBody($text) {

    $maxResults = 4;

    $data = array(
        "suggest" => array(
            "text" => $text,
            "autosuggest" => array(
                "term" => array(
                    "field" => "attributes.textContents.textHTML.autocomplete",
                    "size" => $maxResults,
                    "sort" => "score",
                    "min_doc_freq" => 3,
                    "suggest_mode" => "always",
                    "min_word_length" => 3
                )
            )
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

?>