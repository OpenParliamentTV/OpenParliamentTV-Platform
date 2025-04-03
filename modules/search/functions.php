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
 *
 * Translates $_REQUEST to searchable parameter for function searchSpeeches()
 * if $find is set (as string or array) it looks fot the content of the speeches for matches
 *
 * @param array $request
 * @return array
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
 * @param string $textQuery
 * @return array
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


/*
 * @param array $request
 * @param bool $getAllResults
 * @return array
 */

function getSearchBody($request, $getAllResults) {

    global $config;

    $filter = array("must"=>array(), "should"=>array(), "must_not"=>array());

    if(!$request["includeAll"] || $request["includeAll"] == false) {

        //ONLY INCLUDE ALIGNED SPEECHES
        //$filter["must"][] = array("match"=>array("attributes.aligned" => true));

        // FILTER OUT FRAGESTUNDE ETC.

        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Befragung"));
        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Fragestunde"));
        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Wahl der"));
        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Wahl des"));
        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Sitzungseröffnung"));
        $filter["must_not"][] = array("match_phrase"=>array("relationships.agendaItem.data.attributes.title" => "Sitzungsende"));

        //HIDE NON PUBLIC MEDIA
        $filter["must_not"][] = array("match"=>array("attributes.public" => 0));

    }

    $shouldCount = 0;

    foreach ($request as $requestKey => $requestValue) {

        if ($requestKey == "parliament" && strlen($requestValue) > 2) {

            //TODO

        } else if ($requestKey == "electoralPeriod" && strlen($requestValue) > 2) {

            $filter["must"][] = array("term"=>array("relationships.electoralPeriod.data.attributes.number" => $requestValue));

        } else if ($requestKey == "electoralPeriodID" && strlen($requestValue) > 2) {

            $filter["must"][] = array("term"=>array("relationships.electoralPeriod.data.id" => $requestValue));

        } else if ($requestKey == "sessionID" && strlen($requestValue) > 2) {

            $filter["must"][] = array("match"=>array("relationships.session.data.id" => $requestValue));

        } else if ($requestKey == "sessionNumber" && strlen($requestValue) >= 1) {

            $filter["must"][] = array("match"=>array("relationships.session.data.attributes.number" => $requestValue));

        } else if ($requestKey == "agendaItemID" && strlen($requestValue) >= 1) {
            $agendaItemStringsplit = explode("-",$requestValue);
            $agendaItemID = array_pop($agendaItemStringsplit);
            $filter["must"][] = array("match"=>array("relationships.agendaItem.data.id" => $agendaItemID));

        } else if ($requestKey == "party" || $requestKey == "faction") {
            
            // TODO: Filter by context from annotations context

            if (is_array($requestValue)) {
                foreach ($requestValue as $partyOrFaction) {

                    $filter["should"][] = array("match_phrase"=>array("relationships.people.data.attributes.".$requestKey.".label" => $partyOrFaction));

                }
                $shouldCount++;
            } else {

                $filter["must"][] = array("match"=>array("relationships.people.data.attributes.".$requestKey.".label" => $requestValue));

            }
        } else if ( $requestKey == "abgeordnetenwatchID" || 
                    $requestKey == "fragDenStaatID" ) {

            if ($requestKey == "abgeordnetenwatchID" && !isset($request["context"])) {
                    $request["context"] = "main-speaker";
            }

            if (isset($request["context"]) && strlen($request["context"]) > 2) {
                $filter["must"][] = array(
                    "nested" => array(
                        "path" => "annotations.data",
                        "query" => array("bool" => array("must"=>array(
                            array("match" => array(
                                "annotations.data.attributes.additionalInformation.".$requestKey => $requestValue
                            )),
                            array("match_phrase" => array(
                                "annotations.data.attributes.context" => $request["context"]
                            ))
                        )))
                    )
                );

            } else {

                $filter["must"][] = array(
                    "nested" => array(
                        "path" => "annotations.data",
                        "query" => array("bool" => array("must"=>array(
                            array("match" => array(
                                "annotations.data.attributes.additionalInformation.".$requestKey => $requestValue
                            )),
                            array("match" => array(
                                "annotations.data.attributes.context" => 'main-speaker'
                            ))
                        )))
                    )
                );

            }

        } else if ($requestKey == "person" && strlen($requestValue) > 1) {

            // TODO: Filter by context from annotations context

            $filter["must"][] = array(
                "nested" => array(
                    "path" => "annotations.data",
                    "query" => array("bool" => array("must"=>array(
                        array("match_phrase" => array(
                            "relationships.people.data.label" => $requestValue
                        )),
                        array("match" => array(
                            "relationships.people.data.attributes.context" => 'main-speaker'
                        ))
                    )))
                )
            );

        } else if ( $requestKey == "personID" || 
                    $requestKey == "organisationID" || 
                    $requestKey == "documentID" || 
                    $requestKey == "termID" || 
                    $requestKey == "partyID" || 
                    $requestKey == "factionID" ) {

            
            if ($requestKey == "personID") {
                $resourceType = "person";
                if(!isset($request["context"])) {
                    $request["context"] = "main-speaker";
                }
            } else if ($requestKey == "partyID") {
                $resourceType = "organisation";
                if(!isset($request["context"])) {
                    $request["context"] = "main-speaker-party";
                }
            } else if ($requestKey == "factionID") {
                $resourceType = "organisation";
                if(!isset($request["context"])) {
                    $request["context"] = "main-speaker-faction";
                }
            } else {
                $resourceType = str_replace("ID", "",$requestKey);
            }

            if (is_array($requestValue)) {

                foreach ($requestValue as $entityID) {

                    if (isset($request["context"]) && strlen($request["context"]) > 2) {
                        $filter["should"][] = array(
                            "nested" => array(
                                "path" => "annotations.data",
                                "query" => array("bool" => array("must"=>array(
                                    array("match" => array(
                                        "annotations.data.id" => $entityID
                                    )),
                                    array("match" => array(
                                        "annotations.data.type" => $resourceType
                                    )),
                                    array("match_phrase" => array(
                                        "annotations.data.attributes.context" => $request["context"]
                                    ))
                                )))
                            )
                        );

                    } else {

                        $filter["should"][] = array(
                            "nested" => array(
                                "path" => "annotations.data",
                                "query" => array("bool" => array("must"=>array(
                                    array("match" => array(
                                        "annotations.data.id" => $entityID
                                    )),
                                    array("match" => array(
                                        "annotations.data.type" => $resourceType
                                    ))
                                )))
                            )
                        );

                    }

                }

                $shouldCount++;

            } else {

                if (isset($request["context"]) && strlen($request["context"]) > 2) {
                    $filter["must"][] = array(
                        "nested" => array(
                            "path" => "annotations.data",
                            "query" => array("bool" => array("must"=>array(
                                array("match" => array(
                                    "annotations.data.id" => $requestValue
                                )),
                                array("match" => array(
                                    "annotations.data.type" => $resourceType
                                )),
                                array("match_phrase" => array(
                                    "annotations.data.attributes.context" => $request["context"]
                                ))
                            )))
                        )
                    );

                } else {

                    $filter["must"][] = array(
                        "nested" => array(
                            "path" => "annotations.data",
                            "query" => array("bool" => array("must"=>array(
                                array("match" => array(
                                    "annotations.data.id" => $requestValue
                                )),
                                array("match" => array(
                                    "annotations.data.type" => $resourceType
                                ))
                            )))
                        )
                    );

                }

            }

        } else if ($requestKey == "procedureID" && strlen($requestValue) >= 1) {

            if (is_array($requestValue)) {

                foreach ($requestValue as $procedureID) {

                    if (isset($request["context"]) && strlen($request["context"]) > 2) {
                        $filter["should"][] = array(
                            "nested" => array(
                                "path" => "annotations.data",
                                "query" => array("bool" => array("must"=>array(
                                    array("match" => array(
                                        "annotations.data.attributes.additionalInformation.procedureIDs" => $procedureID
                                    )),
                                    array("match_phrase" => array(
                                        "annotations.data.attributes.context" => $request["context"]
                                    ))
                                )))
                            )
                        );

                    } else {

                        $filter["should"][] = array(
                            "nested" => array(
                                "path" => "annotations.data",
                                "query" => array("bool" => array("must"=>array(
                                    array("match" => array(
                                        "annotations.data.attributes.additionalInformation.procedureIDs" => $procedureID
                                    ))
                                )))
                            )
                        );

                    }

                }

                $shouldCount++;

            } else {

                if (isset($request["context"]) && strlen($request["context"]) > 2) {
                    $filter["must"][] = array(
                        "nested" => array(
                            "path" => "annotations.data",
                            "query" => array("bool" => array("must"=>array(
                                array("match" => array(
                                    "annotations.data.attributes.additionalInformation.procedureIDs" => $requestValue
                                )),
                                array("match_phrase" => array(
                                    "annotations.data.attributes.context" => $request["context"]
                                ))
                            )))
                        )
                    );

                } else {

                    $filter["must"][] = array(
                        "nested" => array(
                            "path" => "annotations.data",
                            "query" => array("bool" => array("must"=>array(
                                array("match" => array(
                                    "annotations.data.attributes.additionalInformation.procedureIDs" => $requestValue
                                ))
                            )))
                        )
                    );

                }

            }

        } else if ($requestKey == "id" && strlen($requestValue) > 3 && !$getAllResults) {
            
            $filter["must"][] = array("match_phrase"=>array("id" => $requestValue));

        } else if ($requestKey == "dateFrom") {

            $filter["must"][] = array("range"=>array("attributes.dateStart"=>array("gte"=>$requestValue)));

        } else if ($requestKey == "dateTo") {

            $filter["must"][] = array("range"=>array("attributes.dateStart"=>array("lte"=>$requestValue)));

        }
    }

    $query = array("bool"=>array(
        "filter"=>array("bool"=>array(
            "must"=>$filter["must"],
            "should"=>$filter["should"],
            "must_not"=>$filter["must_not"]))));


    $request["q"] = str_replace(['„','“','\'','«','«'], '"', $request["q"]);

    $quotationMarksRegex = '/(["\'])(?:(?=(\\\\?))\2.)*?\1/m';

    preg_match_all($quotationMarksRegex, $request["q"], $exact_query_matches);

    $fuzzy_match = preg_replace($quotationMarksRegex, '', $request["q"]);

    if (strlen($request["q"]) >= 1) {
        $boolCondition = "must";
        if ($request["id"]) {
            $boolCondition = "should";
        }
        $query["bool"][$boolCondition] = array();

        if (strlen($fuzzy_match) > 0) {

            $query_array = preg_split("/(\s)/", $fuzzy_match);

            foreach ($query_array as $query_item) {
                if (strpos($query_item, '*') !== false) {

                    $query["bool"][$boolCondition][] = array(
                        "wildcard" => array("attributes.textContents.textHTML" => array(
                            "value"=>$query_item,
                            "case_insensitive"=>true,
                            "boost"=>1.0,
                            "rewrite"=>"scoring_boolean"
                        )
                        )
                    );

                } else if (strlen($query_item) != 0) {

                    //TODO: Check which item in textContents is the right one
                    $query["bool"][$boolCondition][] = array(
                        "match"=>array("attributes.textContents.textHTML" => array(
                            "query"=>$query_item,
                            //"operator"=>"and",
                            //"fuzziness"=>0,
                            "prefix_length"=>0
                        )
                        )
                    );

                }
            }
        }

        foreach ($exact_query_matches[0] as $exact_match) {
            $exact_match = preg_replace('/(["\'])/m', '', $exact_match);
            if (strpos($exact_match, '*') !== false) {

                $exact_query_array = preg_split("/(\s)/", $exact_match);

                $query["bool"]["must"]["span_near"] = array(
                    "clauses" => array(),
                    "slop" => 0,
                    "in_order" => true
                );

                foreach ($exact_query_array as $exact_query_item) {

                    if (strpos($exact_query_item, '*') !== false) {
                        $query["bool"]["must"]["span_near"]["clauses"][] = array(
                            "span_multi" => array(
                                "match" => array(
                                    "wildcard" => array(
                                        "attributes.textContents.textHTML" => array(
                                            "value"=>$exact_query_item,
                                            "case_insensitive"=>true
                                        )
                                    )
                                )
                            )
                        );
                    } else {
                        $query["bool"]["must"]["span_near"]["clauses"][] = array(
                            "span_term" => array(
                                "attributes.textContents.textHTML" => strtolower($exact_query_item)
                            )
                        );
                    }


                }

                /*
                ["must"][] = array(
                    "wildcard"=>array(
                        "attributes.textContents.textHTML"=>$exact_match
                    )
                );
                */

            } else {

                $query["bool"]["must"][] = array(
                    "match_phrase"=>array(
                        "attributes.textContents.textHTML"=>$exact_match
                    )
                );

            }
        }


        //$query["bool"]["must"] = array("regexp"=>array("attributes.textContents[0].textHTML"=>array("value"=>"(".$request["q"].")")));
    }
    if ($shouldCount >= 1) {
        $query["bool"]["filter"]["bool"]["minimum_should_match"] = $shouldCount;
    }

    //TODO: Check if timestamp is needed for date ordering
    if (isset($request["sort"]) && ($request["sort"] == 'date-asc' || $request["sort"] == 'topic-asc')) {
        $sort = array("attributes.timestamp"=>"asc");
    } else if (isset($request["sort"]) && ($request["sort"] == 'date-desc' || $request["sort"] == 'topic-desc')) {
        $sort = array("attributes.timestamp"=>"desc");
    } else {
        $sort = array("_score");
    }

    $maxFullResults = ($getAllResults === true) ? 10000 : $config["display"]["speechesPerPage"];


    //TODO: Check what this means and replace $_REQUEST with a function parameter if needed
    /*
    if ((!$_REQUEST["a"] || count($request) < 2) && !$getAllResults) {
        $maxFullResults = 10;
    }
    */

    $from = 0;

    if ($request["page"] && !$getAllResults) {
        $from = (intval($request["page"])-1) * $config["display"]["speechesPerPage"];
    }

    $data = array("from"=>$from, "size"=>$maxFullResults,
        "sort"=>$sort,
        "query"=>$query);
    //"query"=>array("bool"=>array("must"=>array("match_phrase"=>array("id"=>$request['id'])))));
    //"query"=>array("bool"=>array("filter"=>array("bool"=>array("must"=>array("match_phrase"=>array("id"=>$request['id'])))))));

    if ($getAllResults === false) {
        $data["highlight"] = array(
            "number_of_fragments"=>0,
            "fields"=>array("attributes.textContents.textHTML"=>new \stdClass())
        );
    } else {
        //$data["_source"] = ["id", "attributes.dateStart", "relationships", "annotations"];
        $data["_source"] = ["id"];
    }

    $data["aggs"]["types_count"]["nested"]["path"] = "annotations.data";
    $data["aggs"]["types_count"]["aggs"]["factions"]["filter"]["bool"]["filter"]["term"]["annotations.data.attributes.context"] = "main-speaker-faction";
    $data["aggs"]["types_count"]["aggs"]["factions"]["aggs"]["terms"]["terms"]["field"] = "annotations.data.id";
    $data["aggs"]["types_count"]["aggs"]["factions"]["aggs"]["terms"]["terms"]["size"] = 20;
    $data["aggs"]["dateFirst"]["min"]["field"] = "attributes.dateStart";
    $data["aggs"]["dateLast"]["max"]["field"] = "attributes.dateEnd";

    $data["aggs"]["datesCount"]["date_histogram"]["field"] = "attributes.dateStart";
    $data["aggs"]["datesCount"]["date_histogram"]["calendar_interval"] = "day";
    $data["aggs"]["datesCount"]["date_histogram"]["min_doc_count"] = 1;
    $data["aggs"]["datesCount"]["date_histogram"]["format"] = "yyyy-MM-dd";


    /*echo '<pre>';
    echo json_encode($data);
    echo '</pre>';
    */

    return $data;
}

/*
 * @param string $text
 * @return array
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