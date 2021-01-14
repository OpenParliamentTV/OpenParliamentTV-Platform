<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();

header('Content-Type: application/json');

$return["success"] = "false";
$return["text"] = "Nope!";
$return["return"] = "";

require_once(__DIR__."/../config.php");

switch ($_REQUEST["a"]) {

	case "registerUser":

		if ($config["allow"]["register"]) {

			//require_once(__DIR__."/../modules/login/register.backend.json.php");
			require_once(__DIR__."/../modules/login/register.backend.sql.php");

			$return = registerUser($_REQUEST["mail"],$_REQUEST["password"],$_REQUEST["name"]);

		} //TODO: Response if registration is not allowed

	break;



	case "login":

		if ($config["allow"]["login"]) {

			//require_once(__DIR__."/../modules/login/login.backend.json.php");
			require_once(__DIR__."/../modules/login/login.backend.sql.php");

			$return = loginCheck($_REQUEST["mail"],$_REQUEST["password"]);

		} //TODO: Response if login is not allowed

	break;



	case "logout":

		require_once(__DIR__."/../modules/login/logout.backend.php");

		$return = logout();


	break;



	case "search":
		require_once(__DIR__."/../modules/search/functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","name","party","electoralPeriod","timefrom","timeto","gender","degree","aw_uuid","speakerID","sessionNumber", "page", "sort")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchSpeeches($allowedParams);

	break;



	case "getMedia":
		require_once(__DIR__."/../modules/player/functions.media.php");

		$return["success"] = "true";
		$return["text"] = "searchresults";

		//TODO AUTH for Conflicts
		$return["return"] = getMedia($_REQUEST["v"],$_REQUEST["p"],$_REQUEST["conflicts"]);

	break;

	case "getMediaDiffs":
		require_once(__DIR__."/../modules/player/functions.media.php");
		require_once(__DIR__."/../modules/utilities/functions.php");

		$return["success"] = "true";
		$return["text"] = "searchresults";

		//TODO AUTH
		$media1 = getMedia($_REQUEST["v1"],$_REQUEST["p1"]);
		$media2 = getMedia($_REQUEST["v2"],$_REQUEST["p2"]);
		$return["return1diff2"] = arrayRecursiveDiff($media1, $media2);
		$return["return2diff1"] = arrayRecursiveDiff($media2, $media1);
		$return["returnV2_1diff2"] = array_diff_assoc_recursive($media1, $media2);
		$return["returnV2_2diff1"] = array_diff_assoc_recursive($media2, $media1);

	break;



	case "stats":
		require_once(__DIR__."/../modules/search/functions.php");
		
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