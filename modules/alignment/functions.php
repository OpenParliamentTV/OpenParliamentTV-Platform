<?php

require_once (__DIR__."/../../config.php");
require_once(__DIR__.'/../../api/v1/api.php');
require_once (__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/textArrayConverters.php");

//exportAlignmentInput("DE-0190003002");
exportAlignmentInput();

function exportAlignmentInput($mediaID = false) {
	
	global $config;

	if ($mediaID) {
		
		$data = apiV1([
			"action"=>"getItem", 
			"itemType"=>"media", 
			"id"=>$mediaID
		]);

		//TODO: Choose correct textContents, not just first index
		if (isset($data["data"]["attributes"]["textContents"]) && count($data["data"]["attributes"]["textContents"]) != 0) {

			$mediaFileURI = ($data["data"]["attributes"]["audioFileURI"]) ? $data["data"]["attributes"]["audioFileURI"] : $data["data"]["attributes"]["videoFileURI"];

			$textObject = json_encode($data["data"]["attributes"]["textContents"][0], true);

			saveAlignmentInputToFile($textObject, $data["data"]["attributes"]["textContents"][0]["type"], $mediaFileURI, $data["data"]["id"]);

		}

	} else {


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
	        $errorarray["detail"] = "Connecting to parliament database failed";
	        array_push($return["errors"], $errorarray);
	        return $return;

	    }

	    $allMediaIDs = $dbp->getAll("SELECT MediaID FROM media");


		foreach ($allMediaIDs as $id) { 
			
			$data = apiV1([
				"action"=>"getItem", 
				"itemType"=>"media", 
				"id"=>$id["MediaID"]
			]);

			//TODO: Choose correct textContents, not just first index
			if (isset($data["data"]["attributes"]["textContents"]) && count($data["data"]["attributes"]["textContents"]) != 0) {

				$mediaFileURI = ($data["data"]["attributes"]["audioFileURI"]) ? $data["data"]["attributes"]["audioFileURI"] : $data["data"]["attributes"]["videoFileURI"];

				$textObject = json_encode($data["data"]["attributes"]["textContents"][0], true);
				saveAlignmentInputToFile($textObject, $data["data"]["attributes"]["textContents"][0]["type"], $mediaFileURI, $data["data"]["id"]);

			}

		}
	}
}

function saveAlignmentInputToFile($textObject, $textType, $mediaFileURI, $mediaID) {

	$alignmentInputXML = textObjectToAlignmentInput($textObject, $mediaFileURI, $mediaID);
	file_put_contents("data/".$mediaID."_".$textType.".xml", $alignmentInputXML);

}

?>