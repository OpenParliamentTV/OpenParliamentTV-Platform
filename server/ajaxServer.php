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

			//require_once(__DIR__."/../modules/user-management/register.backend.json.php");
			require_once(__DIR__."/../modules/user-management/register.backend.sql.php");

			$return = registerUser($_REQUEST["mail"],$_REQUEST["password"],$_REQUEST["name"]);

		} //TODO: Response if registration is not allowed

	break;

	case "devAddTestuser":

		if ($config["mode"] == "dev") {

			//require_once(__DIR__."/../modules/user-management/register.backend.json.php");
			require_once(__DIR__."/../modules/user-management/register.backend.sql.php");
			$return["success"] = "true";
			$return["text"] = "User Added";

			$return[] = registerUser("admin@admin.com","admin","DEV-Admin");
			$return[] = registerUser("test@test.com","test","DEV-Test User");

		}
		//TODO: Response if registration is not allowed

	break;



	case "login":

		if ($config["allow"]["login"]) {

			//require_once(__DIR__."/../modules/user-management/login.backend.json.php");
			require_once(__DIR__."/../modules/user-management/login.backend.sql.php");

			$return = loginCheck($_REQUEST["mail"],$_REQUEST["password"]);

		} //TODO: Response if login is not allowed

	break;



	case "logout":

		require_once(__DIR__."/../modules/user-management/logout.backend.php");

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
		require_once(__DIR__."/../modules/media/functions.media.php");

		$return["success"] = "true";
		$return["text"] = "searchresults";

		//TODO AUTH for Conflicts
		$return["return"] = getMedia($_REQUEST["v"],$_REQUEST["p"],$_REQUEST["conflicts"]);

	break;

	case "getMediaDiffs":
		require_once(__DIR__."/../modules/media/functions.media.php");
		require_once(__DIR__."/../modules/utilities/functions.php");

		$return["success"] = "true";
		$return["text"] = "searchresults";

		//TODO AUTH
		//TODO Which Diff works for our case?
		$media1 = getMedia($_REQUEST["v1"],$_REQUEST["p1"]);
		$media2 = getMedia($_REQUEST["v2"],$_REQUEST["p2"]);
		$return["return1diff2"] = arrayRecursiveDiff($media1, $media2);
		$return["return2diff1"] = arrayRecursiveDiff($media2, $media1);
		$return["returnV2_1diff2"] = array_diff_assoc_recursive($media1, $media2);
		$return["returnV2_2diff1"] = array_diff_assoc_recursive($media2, $media1);

	break;

	case "mediaAdd":
		require_once(__DIR__."/../modules/import/functions.import.php");


		$return["error"] = 0;

		if (!array_key_exists($_REQUEST["parliament"],$config["parliament"])) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "parliament";
			$tmpArray["identifier"] = "#parliament";
			$tmpArray["msg"] = "Parliament does not exist in config";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["electoralPeriod"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "electoralPeriod";
			$tmpArray["identifier"] = "#electoralPeriod";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["sessionNumber"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "sessionNumber";
			$tmpArray["identifier"] = "#sessionNumber";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["agendaItemTitle"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "agendaItemTitle";
			$tmpArray["identifier"] = "#agendaItemTitle";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["speakerFirstName"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "speakerFirstName";
			$tmpArray["identifier"] = "#speakerFirstName";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["speakerLastName"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "speakerLastName";
			$tmpArray["identifier"] = "#speakerLastName";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["mediaURL"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "mediaURL";
			$tmpArray["identifier"] = "#mediaURL";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if (!$_REQUEST["mediaOriginalURL"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "mediaOriginalURL";
			$tmpArray["identifier"] = "#mediaOriginalURL";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}

		if ($return["error"] == 0) {
			$return["success"] = "true";
			$return["text"] = "mediaAdd";

			//TODO AUTH for mediaAdd
			$return["return"] = importParliamentMedia("forminput",$_REQUEST["parliament"],"",$_REQUEST);
		}




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