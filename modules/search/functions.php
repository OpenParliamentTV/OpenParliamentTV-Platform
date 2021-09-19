<?php

require_once(__DIR__.'/../../vendor/autoload.php');
require_once(__DIR__.'/../../config.php');

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
		$return = $ESClient->count(['index' => 'openparliamenttv_de']);
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

    if ($request["id"]) {
        require_once(__DIR__.'/../utilities/functions.php');
        $itemInfos = getInfosFromStringID($request["id"]);
        $parliament = strtolower($itemInfos["parliament"]);
    } else {
        //TODO: How to seach in all indexes?
        $parliament = "de";
    }


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
	
	$searchParams = array("index" => "openparliamenttv_".$parliament, "body" => $data);
	
	try {
		//$results = $ESClient->get(['index' => "openparliamenttv_".$parliament, 'id' => 'DE-BB-0070047001']);

		$results = $ESClient->search($searchParams);
	} catch(Exception $e) {
		$results = $e->getMessage();
	}

	/*
	echo '<pre>';
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

function searchStats($request) {

	global $ESClient;

	$data = getSearchBody($request, true);
	
	$searchParams = array("index" => "openparliamenttv_de", "body" => $data);
	
	try {
		$results = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$results = null;
	}

	$resultCnt = 0;
	$findCnt = 0;

	$stats = array("results" => array(), "info" => array(
		"totalSpeeches" => $results["hits"]["total"]["value"],
		"speechesPerFaction" => array(),
		"speechesPerGender" => array()
	));
			
	foreach ($results["hits"]["hits"] as $hit) {
		
		$normalizedDate = date("Y-m-d", strtotime($hit["_source"]["attributes"]["dateStart"]));

		$resultInfo = array(
			"id" => $hit["_source"]["id"],
			"date" => $normalizedDate,
			"faction" => $hit["_source"]["relationships"]["organisations"]["data"][0]["attributes"]["labelAlternative"],
			"electoralPeriod" => $hit["_source"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"],
			"sessionNumber" => $hit["_source"]["relationships"]["session"]["data"]["attributes"]["number"]
		);

		$stats["results"][] = $resultInfo;

		$stats["info"]["speechesPerFaction"][$hit["_source"]["relationships"]["organisations"]["data"][0]["attributes"]["labelAlternative"]]++;	


	}

	arsort($stats["info"]["speechesPerFaction"]);

	//$results->totalFinds = $findCnt;

	
	/*
	echo '<pre>';
	print_r($results["hits"]["hits"][0]);
	echo '</pre>';
	*/
	

	return $stats;


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
	
	$searchParams = array("index" => "openparliamenttv_de", "body" => $data);
	
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
	
	$filter = array("must"=>array(), "should"=>array(), "must_not"=>array());

	//ONLY INCLUDE ALIGNED SPEECHES
	//$filter["must"][] = array("match"=>array("attributes.aligned" => true));
	
	// FILTER OUT FRAGESTTUNDE ETC.
	/*
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Befragung"));
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Fragestunde"));
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Wahl der"));
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Wahl des"));
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Sitzungseröffnung"));
	$filter["must_not"][] = array("match"=>array("relationships.agendaItem.data.attributes.title" => "Sitzungsende"));
	*/
	$shouldCount = 0;

	foreach ($request as $requestKey => $requestValue) {
		
		/*
		if ($requestKey != "a" && 
			$requestKey != "q" && 
			$requestKey != "name" && 
			$requestKey != "timefrom" && 
			$requestKey != "timeto" && 
			$requestKey != "party" && 
			$requestKey != "playresults" &&
			$requestKey != "page" && 
			$requestKey != "id" &&
			$requestKey != "sort") {
			
			if (strlen($requestValue) > 0) {
				$filter["must"][] = array("match"=>array("meta.".$requestKey => $requestValue));
			}
		*/
		if ($requestKey == "parliament" && strlen($requestValue) > 2) {
			
			//???

		} else if ($requestKey == "electoralPeriod" && strlen($requestValue) > 2) {
			
			$filter["must"][] = array("term"=>array("relationships.electoralPeriod.data.attributes.number" => $requestValue));

		} else if ($requestKey == "electoralPeriodID" && strlen($requestValue) > 2) {
			
			$filter["must"][] = array("term"=>array("relationships.electoralPeriod.data.id" => $requestValue));

		} else if ($requestKey == "sessionID" && strlen($requestValue) > 2) {
			
			$filter["must"][] = array("match"=>array("relationships.session.data.id" => $requestValue));

		} else if ($requestKey == "sessionNumber" && strlen($requestValue) >= 1) {
			
			$filter["must"][] = array("match"=>array("relationships.session.data.attributes.number" => $requestValue));

		} else if ($requestKey == "agendaItemID" && strlen($requestValue) >= 1) {
			
			$filter["must"][] = array("match"=>array("relationships.agendaItem.data.id" => $requestValue));

		} else if ($requestKey == "dateFrom") {
			
			$filter["must"][] = array("range"=>array("attributes.dateStart"=>array("gte"=>$requestValue)));

		} else if ($requestKey == "dateTo") {
			
			$filter["must"][] = array("range"=>array("attributes.dateStart"=>array("lte"=>$requestValue)));

		} else if ($requestKey == "party" || $requestKey == "faction") {
			if (is_array($requestValue)) {
				foreach ($requestValue as $partyOrFaction) {
					
					//TODO: Get mainSpeaker from people Array 
					//TODO: people.data[0].attributes.party needs labelAlternative as well!
					$filter["should"][] = array("match_phrase"=>array("relationships.people.data.attributes.".$requestKey.".labelAlternative" => $partyOrFaction));

				}
				$shouldCount++;
			} else {
				
				//TODO: Get mainSpeaker from people Array 
				//TODO: people.data[0].attributes.party needs labelAlternative as well!
				$filter["must"][] = array("match"=>array("relationships.people.data.attributes.".$requestKey.".labelAlternative" => $requestValue));

			}
		} else if ($requestKey == "partyID") {
			if (is_array($requestValue)) {
				foreach ($requestValue as $partyID) {
					
					$filter["should"][] = array(
						"nested" => array(
							"path" => "relationships.organisations.data",
							"query" => array("bool" => array("must"=>array(
								array("match" => array(
									"relationships.organisations.data.id" => $partyID
								)),
								array("match_phrase" => array(
									"relationships.organisations.data.attributes.context" => 'main-speaker-party'
								))
							)))
						)
					);

				}

				$shouldCount++;
				
			} else {
				
				$filter["must"][] = array(
					"nested" => array(
						"path" => "relationships.organisations.data",
						"query" => array("bool" => array("must"=>array(
							array("match" => array(
								"relationships.organisations.data.id" => $requestValue
							)),
							array("match_phrase" => array(
								"relationships.organisations.data.attributes.context" => 'main-speaker-party'
							))
						)))
					)
				);

			}
		} else if ($requestKey == "factionID") {
			if (is_array($requestValue)) {
				foreach ($requestValue as $factionID) {
					
					$filter["should"][] = array(
						"nested" => array(
							"path" => "relationships.organisations.data",
							"query" => array("bool" => array("must"=>array(
								array("match" => array(
									"relationships.organisations.data.id" => $factionID
								)),
								array("match_phrase" => array(
									"relationships.organisations.data.attributes.context" => 'main-speaker-faction'
								))
							)))
						)
					);

				}

				$shouldCount++;
				
			} else {
				
				$filter["must"][] = array(
					"nested" => array(
						"path" => "relationships.organisations.data",
						"query" => array("bool" => array("must"=>array(
							array("match" => array(
								"relationships.organisations.data.id" => $requestValue
							)),
							array("match_phrase" => array(
								"relationships.organisations.data.attributes.context" => 'main-speaker-faction'
							))
						)))
					)
				);

			}
		} else if ($requestKey == "organisationID") {
			
			$filter["should"][] = array("multi_match"=>array(
				"query" => $requestValue,
				"type" => "cross_fields",
				"fields" => ["relationships.people.data.attributes.party.id", "relationships.people.data.attributes.faction.id"],
				"operator" => "or"
			));
			$shouldCount++;

		} else if ($requestKey == "abgeordnetenwatchID") {
			
			if (isset($request["context"]) && strlen($request["context"]) > 2) {
				$filter["must"][] = array(
					"nested" => array(
						"path" => "relationships.people.data",
						"query" => array("bool" => array("must"=>array(
							array("match" => array(
								"relationships.people.data.attributes.additionalInformation.abgeordnetenwatchID" => $requestValue
							)),
							array("match_phrase" => array(
								"relationships.people.data.attributes.context" => $request["context"]
							))
						)))
					)
				);

			} else {
				
				$filter["must"][] = array(
					"nested" => array(
						"path" => "relationships.people.data",
						"query" => array("bool" => array("must"=>array(
							array("match" => array(
								"relationships.people.data.attributes.additionalInformation.abgeordnetenwatchID" => $requestValue
							)),
							array("match" => array(
								"relationships.people.data.attributes.context" => 'main-speaker'
							))
						)))
					)
				);

			}

		} else if ($requestKey == "person" && strlen($requestValue) > 1) {
			
			$filter["must"][] = array(
				"nested" => array(
					"path" => "relationships.people.data",
					"query" => array("bool" => array("must"=>array(
						array("match_phrase" => array(
							"relationships.people.data.attributes.label" => $requestValue
						)),
						array("match" => array(
							"relationships.people.data.attributes.context" => 'main-speaker'
						))
					)))
				)
			);
			
		} else if ($requestKey == "personID") {
			
			if (is_array($requestValue)) {

				foreach ($requestValue as $personID) {

					if (isset($request["context"]) && strlen($request["context"]) > 2) {
						$filter["should"][] = array(
							"nested" => array(
								"path" => "relationships.people.data",
								"query" => array("bool" => array("must"=>array(
									array("match" => array(
										"relationships.people.data.id" => $personID
									)),
									array("match_phrase" => array(
										"relationships.people.data.attributes.context" => $request["context"]
									))
								)))
							)
						);

					} else {
						
						$filter["should"][] = array(
							"nested" => array(
								"path" => "relationships.people.data",
								"query" => array("bool" => array("must"=>array(
									array("match" => array(
										"relationships.people.data.id" => $personID
									)),
									array("match" => array(
										"relationships.people.data.attributes.context" => 'main-speaker'
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
							"path" => "relationships.people.data",
							"query" => array("bool" => array("must"=>array(
								array("match" => array(
									"relationships.people.data.id" => $requestValue
								)),
								array("match_phrase" => array(
									"relationships.people.data.attributes.context" => $request["context"]
								))
							)))
						)
					);

				} else {
					
					$filter["must"][] = array(
						"nested" => array(
							"path" => "relationships.people.data",
							"query" => array("bool" => array("must"=>array(
								array("match" => array(
									"relationships.people.data.id" => $requestValue
								)),
								array("match" => array(
									"relationships.people.data.attributes.context" => 'main-speaker'
								))
							)))
						)
					);

				}

			}
			
			
		} else if ($requestKey == "documentID") {
			
			$filter["must"][] = array("match"=>array("relationships.documents.data.id" => $requestValue));

		} else if ($requestKey == "termID") {
			
			$filter["must"][] = array("match"=>array("relationships.terms.data.id" => $requestValue));

		} else if ($requestKey == "id" && strlen($requestValue) > 3 && !$getAllResults) {
			$filter["must"][] = array("match_phrase"=>array("id" => $requestValue));
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
			
			//TODO: Check which item in textContents is the right one
			$query["bool"][$boolCondition][] = array("match"=>array("attributes.textContents.textHTML" => array(
				"query"=>$fuzzy_match,
				"operator"=>"and",
				//"fuzziness"=>0,
				"prefix_length"=>0)));
		}

		foreach ($exact_query_matches[0] as $exact_match) {
			$exact_match = preg_replace('/(["\'])/m', '', $exact_match);
			$query["bool"]["must"][] = array("match_phrase"=>array("attributes.textContents.textHTML"=>$exact_match));
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

	$maxFullResults = ($getAllResults === true) ? 10000 : 40;

	if ((!$_REQUEST["a"] || count($request) < 2) && !$getAllResults) {
		$maxFullResults = 10;
	}

	$from = 0;

	if ($request["page"] && !$getAllResults) {
		$from = (intval($request["page"])-1) * $maxFullResults;
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
		$data["_source"] = ["id", "attributes.dateStart", "relationships"];
	}

	/*
	echo '<pre>';
	print_r($data);
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

function addPartyIndicators($HTMLString) {
	
	/*
	$HTMLString = preg_replace('/((SPD)|(CDU)|(CSU)|(FPD)|(AfD))/m', '<span class="partyIndicator" data-party="$1">$1</span>', $HTMLString);
	$HTMLString = preg_replace('/((B&#xDC;NDNIS\s90\/DIE\sGR&#xDC;NEN)|(BÜNDNIS\s90\/DIE\sGRÜNEN)|(B&Uuml;NDNIS\s90\/DIE\sGR&Uuml;NEN))/m', '<span class="partyIndicator" data-party="DIE GRÜNEN">$1</span>', $HTMLString);
	$HTMLString = preg_replace('/((DIE\sLINKE)|(LINKEN))/m', '<span class="partyIndicator" data-party="DIE LINKE">$1</span>', $HTMLString);
	*/

	return $HTMLString;
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