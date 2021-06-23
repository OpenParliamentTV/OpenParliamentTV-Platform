<?php

require_once(__DIR__.'/../../vendor/autoload.php');

$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();

function getPrevDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "openparliamenttv_de", 
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
	
	$searchParams = array("index" => "openparliamenttv_de", 
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