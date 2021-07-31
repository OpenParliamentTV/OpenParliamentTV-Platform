<?php

require_once(__DIR__.'/../../vendor/autoload.php');

/*
 * TODO: REMOVE IF OTHER WORKS
 *
$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();
*/
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

function getMainSpeakerFromPeopleArray($peopleArray) {
	
	foreach ($peopleArray as $person) {
		if ($person['attributes']['context'] == 'main-speaker') {
			$mainSpeaker = $person;
			break;
		}
	}

	if (!isset($mainSpeaker)) {
		$mainSpeaker = $peopleArray[0];
	}
	
	return $mainSpeaker;
}

function getMainFactionFromOrganisationsArray($organisationsArray) {
	
	foreach ($organisationsArray as $organisation) {
		if ($organisation['attributes']['context'] == 'main-speaker-faction') {
			$mainFaction = $organisation;
			break;
		}
	}

	if (!isset($mainFaction)) {
		$mainFaction = $organisationsArray[0];
	}

	return $mainFaction;
}

function getPrevDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "openparliamenttv_de", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"attributes.timestamp" => array(
								"lt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"attributes.timestamp" => array(
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
	
	$searchParams = array("index" => "openparliamenttv_de", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"attributes.timestamp" => array(
								"gt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"attributes.timestamp" => array(
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
	
	$docParams = array("index" => "openparliamenttv_de", 
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


?>