<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

header('Content-Type: application/json');

$return["success"] = "false";
$return["text"] = "Nope!";
$return["return"] = "";

switch ($_REQUEST["a"]) {


	case "search":
		require_once("functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","name","party","electoralPeriod","timefrom","timeto","gender","degree","aw_uuid","speakerID","sessionNumber", "page", "sort")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchSpeeches($allowedParams);

	break;
	case "stats":
		require_once("functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","name","party","electoralPeriod","timefrom","timeto","gender","degree","aw_uuid","speakerID","sessionNumber", "page", "sort")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchStats($allowedParams);

	break;
	default:
	break;


}

echo json_encode($return,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>