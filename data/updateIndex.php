<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
ini_set('memory_limit', '500M');
date_default_timezone_set('CET');

require __DIR__.'/../vendor/autoload.php';

//$hosts = ["https://admin:admin@localhost:9200"];
$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();

require_once (__DIR__."/../config.php");

/*
$response = $ESClient->indices()->delete(array("index"=>"openparliamenttv_de"));
echo '<pre>';
print_r($response);
echo '</pre>';
*/

setOptions();
updateIndex();

/**
 * @return mixed
 */
function setOptions() {

	global $ESClient;

	$data = array();

	/*
	$data["mappings"] = array( "properties"=>array(
		"content"=>array(
			"type"=>"text",
			"analyzer"=>"speech_html",
			"search_analyzer"=>"standard"
	)));
	*/

	$data["settings"] = array(
		"index"=>array("max_ngram_diff"=>20),
		"number_of_replicas"=>0,
		"number_of_shards"=>2,
		"analysis"=>array(
			"analyzer"=>array(
				"default"=>array(
					"type"=>"custom",
					//"tokenizer"=>"nGramTokenizer",
					"tokenizer"=>"standard",
					"filter"=>["lowercase", "custom_stemmer", "custom_synonyms"]
				)
			),
			/*
			"tokenizer"=>array(
				"nGramTokenizer"=>array(
					"type"=>"nGram",
					"min_gram"=> 6,
					"max_gram"=> 20
				)
			),
			*/
			"filter"=>array(
				"custom_stemmer"=>array(
					"type"=>"stemmer",
					"name"=>"light_german"
				),
				"custom_synonyms"=>array(
					"type"=>"synonym_graph",
					"lenient"=>true,
					"synonyms_path"=>"synonyms.txt"
				)
			)
		)
	);

	$indexParams = array("index" => "openparliamenttv_de", "body" => $data);

	try {
		$result = $ESClient->indices()->create($indexParams);
	} catch(Exception $e) {
		$result = $e->getMessage();
	}

	echo '<pre>';
	print_r($result);
	echo '</pre>';

}

/**
 * @return mixed
 */
function setMapping() {

	global $ESClient;

	/*
	$data = array( "properties"=>array(
		"content"=>array(
			"type"=>"text",
			"analyzer"=>"speech_html",
			"search_analyzer"=>"standard"
	)));

	$url = "http://localhost:9200/openparliamenttv_de/_mapping?pretty";
	$ch = curl_init( $url );

	$payload = json_encode($data);

	echo '<pre>';
	print_r($payload);
	echo '</pre>';

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$result = curl_exec($ch);
	curl_close($ch);

	echo '<pre>';
	print_r($result);
	echo '</pre>';
	*/

}



/**
 * @return mixed
 */
function updateIndex() {

	global $ESClient;
	global $config;

    require_once("../api/v1/api.php");

	$data = apiV1(["action"=>"getItem", "itemType"=>"media", "id"=>"DE-0190003001"]);

	//print_r($data["data"]);
	//return;

	//$data = '';

	$docParams = array("index" => "openparliamenttv_de", "id" => "DE-0190003001", "body" => json_encode($data["data"]));

	try {
		$result = $ESClient->index($docParams);
	} catch(Exception $e) {
		$result = $e->getMessage();
	}

	echo '<pre>';
	print_r($result);
	echo '</pre>';

}

?>