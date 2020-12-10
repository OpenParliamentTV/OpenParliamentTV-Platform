<?php

require __DIR__.'/../vendor/autoload.php';

$ESClient = Elasticsearch\ClientBuilder::create()->build();

function getIndexCount() {
	
	global $ESClient;

	try {
		$return = $ESClient->count(['index' => 'bundestag_speeches']);
		$result = $return["count"];
	} catch(Exception $e) {
		print_r($e->getMessage());
		$result = 0;
	}
	
	return $result;
}

/**
 *
 * Translates $_REQUEST to searchable parameter for function searchSpeeches()
 * if $find is set (as string or array) it looks fot the content of the speeches for matches
 *
 * Right now there are following parameter:
 * $request["name"] - expects a string of a persons first or last name (or both). Case insensitive
 * $request["party"] - expects an array of partys. Case insensitive. like ["foo"] or ["foo","bar"]
 * $request["wahlperiode"] - expects an array of election periods. like ["foo"] or ["foo","bar"]
 * $request["timefrom"] - expects an unix timestamp and looks for newer speeches (>=)
 * $request["timeto"] - expects an unix timestamp and looks for older speeches (<=)
 * $request["gender"] - expects a string "male" or "female"
 * $request["degree"] - expects a string like "Dr." or "Prof. Dr."
 * $request["aw_uuid"] - expects a string of an Abgeordnetenwatch unique ID
 * $request["rednerID"] - expects a string of a Bundestag-PersonID
 * $request["sitzungsnummer"] - expects a string of a session number. Makes sense to combine it with wahlperiode or e.g. to get every first session of every period
 *
 * @param array $request
 * @return array
 */
function searchSpeeches($request) {

	global $ESClient;

	$data = getSearchBody($request, false);
	
	$searchParams = array("index" => "bundestag_speeches", "body" => $data);
	
	try {
		$results = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$results = null;
	}

	/*
	echo '<pre>';
	print_r($results); 
	echo '</pre>';
	*/

	$resultCnt = 0;
	$findCnt = 0;
	
	if (strlen($request["q"]) >= 1) {
		foreach ($results["hits"]["hits"] as $hit) {
		
			//if ($resultCnt >= $maxFullResults) { break; }
			$resultCnt++;
			$results["hits"]["hits"][$resultCnt-1]["finds"] = array();


			$html = $hit["highlight"]["content"][0];

			if ($html) {
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

					$tmp["data-start"] = $elem->parentNode->getAttribute("data-start");
					$tmp["data-end"] = $elem->parentNode->getAttribute("data-end");
					$tmp["class"] = ($elem->parentNode->hasAttribute("class")) ? $elem->parentNode->getAttribute("class") : "";
					$tmp["klasse"] = ($elem->parentNode->hasAttribute("klasse")) ? $elem->parentNode->getAttribute("klasse") : "";
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
	
	$searchParams = array("index" => "bundestag_speeches", "body" => $data);
	
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
		"speechesPerParty" => array(),
		"speechesPerGender" => array()
	));
	
	foreach ($results["hits"]["hits"] as $hit) {
	
		$resultInfo = array(
			"id" => $hit["_source"]["meta"]["id"],
			"date" => $hit["_source"]["meta"]["date"],
			"party" => $hit["_source"]["meta"]["speakerParty"],
			"electoralPeriod" => $hit["_source"]["meta"]["electoralPeriod"],
			"sessionNumber" => $hit["_source"]["meta"]["sessionNumber"],
			"gender" => $hit["_source"]["meta"]["speakerGender"]
		);

		$stats["results"][] = $resultInfo;

		$stats["info"]["speechesPerParty"][$hit["_source"]["meta"]["speakerParty"]]++;	


	}

	arsort($stats["info"]["speechesPerParty"]);

	//$results->totalFinds = $findCnt;

	
	/*
	echo '<pre>';
	print_r($results["hits"]["hits"][0]);
	echo '</pre>';
	*/
	

	return $stats;


}

function getPrevDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "bundestag_speeches", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"meta.timestamp" => array(
								"lt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"meta.timestamp" => array(
					"order" => "desc"
				)
			)
		));
	
	try {
		$result = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$result = null;
	}

	return json_encode($result["hits"]["hits"][0], true);
}

function getNextDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "bundestag_speeches", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"meta.timestamp" => array(
								"gt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"meta.timestamp" => array(
					"order" => "asc"
				)
			)
		));
	
	try {
		$result = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$result = null;
	}

	return json_encode($result["hits"]["hits"][0], true);
}

function getDocument($documentID) {
	global $ESClient;
	
	$docParams = array("index" => "bundestag_speeches", 
		"id" => $documentID, 
		"_source" => true);
	
	try {
		$doc = $ESClient->get($docParams);
	} catch(Exception $e) {
		//print_r($e->getMessage());
		$doc = null;
	}

	return json_encode($doc, true);
}

/*
 * @param array $request
 * @param bool $getAllResults
 * @return array
 */
function getSearchBody($request, $getAllResults) {
	$filter = array("must"=>array(), "should"=>array());

	//ONLY INCLUDE ALIGNED SPEECHES
	$filter["must"][] = array("match"=>array("meta.aligned" => true));

	$shouldCount = 0;

	foreach ($request as $requestKey => $requestValue) {
		
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

		} else if ($requestKey == "name" && strlen($requestValue) > 1) {
			/*
			$filter["should"][] = array("match"=>array("meta.speakerLastName" => $requestValue));
			$filter["should"][] = array("match"=>array("meta.speakerFirstName" => $requestValue));
			*/
			$filter["should"][] = array("multi_match"=>array(
				"query" => $requestValue,
				"type" => "cross_fields",
				"fields" => ["meta.speakerFirstName", "meta.speakerLastName"],
				"operator" => "and"
			));
			$shouldCount++;
			
		} else if ($requestKey == "timefrom") {
			$filter["must"][] = array("range"=>array("meta.date"=>array("gte"=>$requestValue)));
		} else if ($requestKey == "timeto") {
			$filter["must"][] = array("range"=>array("meta.date"=>array("lte"=>$requestValue)));
		} else if ($requestKey == "party") {
			if (is_array($requestValue)) {
				foreach ($requestValue as $party) {
					$filter["should"][] = array("match_phrase"=>array("meta.speakerParty" => $party));
				}
				$shouldCount++;
			} else {
				$filter["must"][] = array("match"=>array("meta.speakerParty" => $requestValue));
			}
		} else if ($requestKey == "id" && count($request) < 3) {
			$filter["must"][] = array("match"=>array("meta.id" => $requestValue));
		}
	}

	/*
	
	if ($request["wahlperiode"]) {
		$tmpRegex = "/(";
		foreach($request["wahlperiode"] as $tmpK=>$tmpParty) {
			$tmpRegex .= $tmpParty.($tmpK+1<count($request["wahlperiode"]) ? "|": "");
		}
		$tmpRegex .= ")/";

		$params[] = array(
			"key"=>"wahlperiode",
			"op"=>"regex",
			"val"=>$tmpRegex
		);
	}

	if ($request["gender"]) {
		$params[] = array(
			"key"=>"gender",
			"op"=>"=",
			"val"=>$request["gender"]
		);
	}

	if ($request["degree"]) {
		$params[] = array(
			"key"=>"degree",
			"op"=>"regex",
			"val"=>"/".$request["degree"]."/i"
		);
	}

	if ($request["aw_uuid"]) {
		$params[] = array(
			"key"=>"aw_uuid",
			"op"=>"=",
			"val"=>$request["aw_uuid"]
		);
	}

	if ($request["rednerID"]) {
		$params[] = array(
			"key"=>"rednerID",
			"op"=>"=",
			"val"=>$request["rednerID"]
		);
	}
	
	if ($request["sitzungsnummer"]) {
		$params[] = array(
			"key"=>"sitzungsnummer",
			"op"=>"=",
			"val"=>$request["sitzungsnummer"]
		);
	}

	//print_r($params);
	if (count($params)<1) {
		$params=false;
	}
	*/

	$query = array("bool"=>array(
			"filter"=>array("bool"=>array(
				"must"=>$filter["must"],
				"should"=>$filter["should"]))));

	$request["q"] = str_replace(['„','“','\'','«','«'], '"', $request["q"]);

	$quotationMarksRegex = '/(["\'])(?:(?=(\\\\?))\2.)*?\1/m';

	preg_match_all($quotationMarksRegex, $request["q"], $exact_query_matches);

	$fuzzy_match = preg_replace($quotationMarksRegex, '', $request["q"]);

	if (strlen($request["q"]) >= 1) {
		$query["bool"]["must"] = array();
		
		if (strlen($fuzzy_match) > 0) {
			$query["bool"]["must"][] = array("match"=>array("content" => array(
				"query"=>$fuzzy_match,
				"operator"=>"and",
				//"fuzziness"=>0,
				"prefix_length"=>0)));
		}

		foreach ($exact_query_matches[0] as $exact_match) {
			$exact_match = preg_replace('/(["\'])/m', '', $exact_match);
			$query["bool"]["must"][] = array("match_phrase"=>array("content"=>$exact_match));
		}
		

		//$query["bool"]["must"] = array("regexp"=>array("content"=>array("value"=>"(".$request["q"].")")));
	}

	if ($shouldCount >= 1) {
		$query["bool"]["filter"]["bool"]["minimum_should_match"] = $shouldCount;
	}

	if (isset($request["sort"]) && ($request["sort"] == 'date-asc' || $request["sort"] == 'topic-asc')) {
		$sort = array("meta.timestamp"=>"asc");
	} else if (isset($request["sort"]) && ($request["sort"] == 'date-desc' || $request["sort"] == 'topic-desc')) {
		$sort = array("meta.timestamp"=>"desc");
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
		//"query"=>array("bool"=>array("must"=>array("match_phrase"=>array("content"=>"Netzwerkdurchsetzungsgesetz")))));

	if ($getAllResults === false) {
		$data["highlight"] = array(
			"number_of_fragments"=>0,
			"fields"=>array("content"=>new \stdClass())
		);
	} else {
		$data["_source"] = ["meta"];
	}

	/*
	echo '<pre>';
	print_r($data);
	echo '</pre>';
	*/

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