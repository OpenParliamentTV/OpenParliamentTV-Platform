<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
ini_set('memory_limit', '500M');
date_default_timezone_set('CET');

require __DIR__.'/../vendor/autoload.php';

$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();

require_once (__DIR__."/../config.php");

setOptions();
updateIndex();

/**
 * @return mixed
 */
function setOptions() {

	global $ESClient;

	$data = array();

	$data["mappings"] = array( "properties"=>array(
		"relationships"=>array( "properties"=>array(
			"electoralPeriod"=>array( "properties"=>array(
				"data"=>array( "properties"=>array(
					"id"=>array(
						"type"=>"keyword"
					)
				))
			)),
			"session"=>array( "properties"=>array(
				"data"=>array( "properties"=>array(
					"id"=>array(
						"type"=>"keyword"
					)
				))
			)),
			"agendaItem"=>array( "properties"=>array(
				"data"=>array( "properties"=>array(
					"id"=>array(
						"type"=>"keyword"
					)
				))
			))
		))
	));

	/*
	echo "<pre>";
	print_r($data["mappings"]);
	echo "</pre>";
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
					"synonyms_path"=>"analysis/synonyms.txt"
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
function updateIndex() {

	global $ESClient;
	global $config;

    require_once("../api/v1/api.php");

	/*****************************************
	* START UPDATING INDEX PROGRAMMATICALLY
	* ToDo: Fix MySQL Query
	*****************************************/

	$parliament = "DE";

	$opts = array(
        'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
        'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
        'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
        'db'	=> $config["parliament"][$parliament]["sql"]["db"]
    );

    try {

        $dbp = new SafeMySQL($opts);

    } catch (exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Database connection error";
        $errorarray["detail"] = "Connecting to parliament database failed"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }

    $allMediaIDs = $dbp->getAll("SELECT MediaID FROM media");
	//print_r($allMediaIDs);

	foreach ($allMediaIDs as $id) { 
		
		$data = apiV1([
			"action"=>"getItem", 
			"itemType"=>"media", 
			"id"=>$id["MediaID"]
		]);

		//print_r($data["data"]);

		$docParams = array(
			"index" => "openparliamenttv_de", 
			"id" => $id["MediaID"], 
			"body" => json_encode($data["data"])
		);

		try {
			$result = $ESClient->index($docParams);
		} catch(Exception $e) {
			$result = $e->getMessage();
		}

		echo '<pre>';
		print_r($result);
		echo '</pre>';

	}

    /*****************************************
	* END UPDATING INDEX PROGRAMMATICALLY
	*****************************************/

}

?>