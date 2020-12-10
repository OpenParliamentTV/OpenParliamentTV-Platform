<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
ini_set('memory_limit', '500M');
date_default_timezone_set('CET');

require __DIR__.'/../vendor/autoload.php';

$ESClient = Elasticsearch\ClientBuilder::create()->build();

/*
$response = $ESClient->indices()->delete(array("index"=>"bundestag_speeches"));
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

	$indexParams = array("index" => "bundestag_speeches", "body" => $data);

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

	$url = "http://localhost:9200/bundestag_speeches/_mapping?pretty";
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
	
	//$index_media = json_decode(file_get_contents("index_media.json"),true);

	$index_people = json_decode(file_get_contents("index_people.json"),true);
	$index_people_fallback = json_decode(file_get_contents("mdb_stammdaten.json"),true);

	$queueFiles = array_values(array_diff(scandir(__DIR__."/input"), array('.', '..', '.DS_Store', '_index.json', '.gitignore')));

	foreach($queueFiles as $file) {

		$index_media = json_decode(file_get_contents(__DIR__."/input/".$file),true);

		foreach ($index_media as $k=>$m) {
		
			if (!isset($m["mediaID"]) || strlen( (string) $m["mediaID"] ) < 3 || !$m["aligned"]) {
				continue;
			}

			$speakerInfo = $index_people[$m["speakerID"]];

			if (!isset($speakerInfo)) {
				
				echo '<br><br>WARNING: Speaker info not found in AW data for speaker ID '.$m["speakerID"].'. Fallback to mdb_stammdaten.<br><br>';

				$speakerInfo = $index_people_fallback[$m["speakerID"]];

				if (!isset($speakerInfo)) {

					echo 'ERROR: Speaker info not found in mdb_stammdaten for speaker ID '.$m["speakerID"].'.<br><br>';

				}

			}

			$meta = array("id" => $m["id"],
				"mediaID" => $m["mediaID"],
				"duration" => $m["duration"],
				"aligned" => (isset($m["aligned"])) ? $m["aligned"] : true,
				"electoralPeriod" => $m["electoralPeriod"],
				"sessionNumber" => $m["sessionNumber"],
				"date" => $m["date"],
				"timestamp" => $m["timestamp"],
				"agendaItemTitle" => $m["agendaItemTitle"],
				"agendaItemSecondTitle" => $m["agendaItemSecondTitle"],
				"agendaItemThirdTitle" => $m["agendaItemThirdTitle"],
				"documents" => $m["documents"],
				"speakerID" => $m["speakerID"],
				"speakerDegree" => (isset($speakerInfo["degree"])) ? $speakerInfo["degree"] : $m["speakerDegree"],
				"speakerFirstName" => (isset($speakerInfo["first_name"])) ? $speakerInfo["first_name"] : $m["speakerFirstName"],
				"speakerLastName" => (isset($speakerInfo["last_name"])) ? $speakerInfo["last_name"] : $m["speakerLastName"],
				"speakerParty" => (isset($speakerInfo["party"])) ? $speakerInfo["party"] : $m["speakerParty"],
				"speakerRole" => $m["speakerRole"],
				"speakerGender" => $speakerInfo["gender"],
				"speakerPicture" => $speakerInfo["picture"],
				"aw_uuid" => $speakerInfo["aw_uuid"],
				"aw_username" => $speakerInfo["aw_username"]);
			
			$content = htmlspecialchars_decode($m['content']);
			
			$data = array( "meta" => $meta,
					"content" => $content);
						
			$docParams = array("index" => "bundestag_speeches", "id" => $m["id"], "body" => $data);

			try {
				$result = $ESClient->index($docParams);
			} catch(Exception $e) {
				$result = $e->getMessage();
			}

			echo '<pre>';
			print_r($result);
			echo '</pre>';

		}

		unlink(__DIR__."/input/".$file);

	}

}

?>