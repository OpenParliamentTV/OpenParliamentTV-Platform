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