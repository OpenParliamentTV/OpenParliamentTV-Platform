<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();

header('Content-Type: application/json');

$return["success"] = "false";
$return["text"] = "Nope!";
$return["return"] = "";

require_once (__DIR__."/../config.php");

switch ($_REQUEST["a"]) {

    case "conflictsTable":

        require_once (__DIR__."/../modules/utilities/functions.conflicts.php");
        echo json_encode(getConflicts("all",$_REQUEST["limit"],$_REQUEST["offset"],$_REQUEST["search"],true), JSON_PRETTY_PRINT);
        return;

    break;

	case "registerUser":

		if ($config["allow"]["register"]) {

			//require_once(__DIR__."/../modules/user-management/register.backend.json.php");
			require_once(__DIR__."/../modules/user-management/register.backend.sql.php");

			$return = registerUser($_REQUEST["mail"],$_REQUEST["password"],$_REQUEST["passwordCheck"],$_REQUEST["name"]);

		} //TODO: Response if registration is not allowed

	break;


	case "devAddTestuser":

		//TODO: Remove this function before deploy

		if ($config["mode"] == "dev") {

			//require_once(__DIR__."/../modules/user-management/register.backend.json.php");
			require_once(__DIR__."/../modules/user-management/register.backend.sql.php");

			$return[] = registerUser("admin@openparliament.tv","Admin!!11","DEV-Admin");
			$return[] = registerUser("test@openparliament.tv","User!!11","DEV-Test User");





			if (($return[0]["success"] == "true" ) && ($return[1]["success"] == "true")) {

				$opts = array(
					'host'	=> $config["platform"]["sql"]["access"]["host"],
					'user'	=> $config["platform"]["sql"]["access"]["user"],
					'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
					'db'	=> $config["platform"]["sql"]["db"]
				);
				$db = new SafeMySQL($opts);

				$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserActive=?i, UserRole=?s WHERE UserID=?i",1,"admin",$return[0]["UserID"]);
				$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserActive=?i WHERE UserID=?i",1,$return[1]["UserID"]);

				$return["success"] = "true";
				$return["text"] = "User Added";
			}

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


	/*
	case "search":
		require_once(__DIR__."/../modules/search/functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","name","party","electoralPeriod","timefrom","timeto","gender","degree","abgeordnetenwatchID","speakerID","sessionNumber", "page", "sort")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchSpeeches($allowedParams);

	break;
	*/


    /*
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

	case "importOrganisation":

		require_once(__DIR__."/../modules/import/functions.import.organisation.php");

		if (!$_REQUEST["wikidataID"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "wikidataID";
			$tmpArray["identifier"] = "#wikidataID";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["label"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "label";
			$tmpArray["identifier"] = "#label";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["websiteURI"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "websiteURI";
			$tmpArray["identifier"] = "#websiteURI";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if ($return["error"] == 0) {

			//TODO AUTH for importOrganisation
			$return = importOrganisation(
				$_REQUEST["type"],
				$_REQUEST["wikidataID"],
				$_REQUEST["label"],
				$_REQUEST["labelAlternative"],
				$_REQUEST["abstract"],
				$_REQUEST["thumbnailURI"],
				$_REQUEST["embedURI"],
				$_REQUEST["websiteURI"],
				$_REQUEST["socialMediaURIs"],
				$_REQUEST["color"],
				$_REQUEST["updateIfExisting"]
				);
		} else {
			$return["text"] = "Missing required information";
		}


	break;

	case "importTerm":

		require_once(__DIR__."/../modules/import/functions.import.term.php");

		if (!$_REQUEST["wikidataID"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "wikidataID";
			$tmpArray["identifier"] = "#wikidataID";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["label"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "label";
			$tmpArray["identifier"] = "#label";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["abstract"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "abstract";
			$tmpArray["identifier"] = "#abstract";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["sourceURI"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "sourceURI";
			$tmpArray["identifier"] = "#sourceURI";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if ($return["error"] == 0) {

			//TODO AUTH for importOrganisation

			$return = importTerm(
				$_REQUEST["type"],
				$_REQUEST["wikidataID"],
				$_REQUEST["label"],
				$_REQUEST["labelAlternative"],
				$_REQUEST["abstract"],
				$_REQUEST["thumbnailURI"],
				$_REQUEST["embedURI"],
				$_REQUEST["sourceURI"],
				$_REQUEST["updateIfExisting"]
				);
		} else {
			$return["text"] = "Missing required information";
		}


	break;
	case "importDocument":

		require_once(__DIR__."/../modules/import/functions.import.document.php");

		if (!$_REQUEST["type"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "type";
			$tmpArray["identifier"] = "#type";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["label"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "label";
			$tmpArray["identifier"] = "#label";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["abstract"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "abstract";
			$tmpArray["identifier"] = "#abstract";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["sourceURI"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "sourceURI";
			$tmpArray["identifier"] = "#sourceURI";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["parliament"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "parliament";
			$tmpArray["identifier"] = "#parliament";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if ($return["error"] == 0) {

			//TODO AUTH for importOrganisation

			$return = importDocument(
				$_REQUEST["type"],
				$_REQUEST["wikidataID"],
				$_REQUEST["label"],
				$_REQUEST["labelAlternative"],
				$_REQUEST["abstract"],
				$_REQUEST["thumbnailURI"],
				$_REQUEST["sourceURI"],
				$_REQUEST["embedURI"],
				$_REQUEST["parliament"],
				$_REQUEST["documentIssue"],
				$_REQUEST["updateIfExisting"]
				);
		} else {
			$return["text"] = "Missing required information";
		}


	break;
	case "importPerson":

		require_once(__DIR__."/../modules/import/functions.import.person.php");

		if (!$_REQUEST["wikidataID"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "wikidataID";
			$tmpArray["identifier"] = "#wikidataID";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if (!$_REQUEST["label"]) {
			$return["error"] = 1;
			$tmpArray["parameter"] = "label";
			$tmpArray["identifier"] = "#label";
			$tmpArray["msg"] = "Field required";
			$return["reasons"][] = $tmpArray;
		}
		if ($return["error"] == 0) {

			//TODO AUTH for importPerson

			$return = importPerson(
				$_REQUEST["type"],
				$_REQUEST["wikidataID"],
				$_REQUEST["label"],
				$_REQUEST["firstName"],
				$_REQUEST["lastName"],
				$_REQUEST["degree"],
				$_REQUEST["birthDate"],
				$_REQUEST["gender"],
				$_REQUEST["abstract"],
				$_REQUEST["thumbnailURI"],
				$_REQUEST["embedURI"],
				$_REQUEST["websiteURI"],
				$_REQUEST["originID"],
				$_REQUEST["partyOrganisationID"],
				$_REQUEST["factionOrganisationID"],
				$_REQUEST["socialMediaURIs"],
				$_REQUEST["additionalInformation"],
				$_REQUEST["updateIfExisting"]
				);
		} else {
			$return["text"] = "Missing required information";
		}


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



	case "manageUsersGet":

		require_once(__DIR__."/../modules/user-management/users.backend.php");

		$users = getUsers($_REQUEST["id"]);

		if (($users) && ($users["success"] == "true")) {

			$return["success"] = "true";
			$return["text"] = "users overview";
			$return["data"] = $users["return"];

		} else {

			$return = $users;

		}

	break;
*/


	case "stats":
		require_once(__DIR__."/../modules/search/functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","person","personID","context","party","electoralPeriod","dateFrom","dateTo","gender","degree","abgeordnetenwatchID","faction","factionID","organisation","organisationID","speakerID","sessionNumber", "page", "sort")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchStats($allowedParams);

	break;
	default:
	break;


}

echo json_encode($return,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>