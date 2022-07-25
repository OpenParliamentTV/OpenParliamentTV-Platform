<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();

header('Content-Type: application/json');

$return["success"] = "false";
$return["text"] = "Nope!";
$return["return"] = "";

require_once (__DIR__."/../config.php");
include_once(__DIR__ . '/../modules/utilities/auth.php');

switch ($_REQUEST["a"]) {

    case "lang":

        if ($_REQUEST["lang"] && in_array($_REQUEST["lang"], $acceptLang)) {

            $_SESSION["lang"] = $_REQUEST["lang"];

            $return["success"] = "true";
            $return["text"] = "Language has been set";
            $return["return"] = "";

        }

    break;



    case "conflictsTable":

        $auth = auth($_SESSION["userdata"]["id"], "conflicts", "request");

        if ($auth["meta"]["requestStatus"] != "success") {

            $alertText = $auth["errors"][0]["detail"];
            include_once (__DIR__."/../../../login/page.php");

        } else {

            require_once(__DIR__ . "/../modules/utilities/functions.conflicts.php");
            echo json_encode(getConflicts("all", $_REQUEST["limit"], $_REQUEST["offset"], $_REQUEST["search"], true), JSON_PRETTY_PRINT);

        }
        return;

    break;



	case "registerUser":

		if ($config["allow"]["register"]) {

			require_once(__DIR__."/../modules/user-management/register.backend.sql.php");

			$return = registerUser($_REQUEST["mail"],$_REQUEST["password"],$_REQUEST["passwordCheck"],$_REQUEST["name"]);

		} //TODO: Response if registration is not allowed

	break;

/*
	case "devAddTestuser":

		//TODO: Remove this function before deploy

		if ($config["mode"] == "dev") {

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
*/


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
	


	case "stats":
		require_once(__DIR__."/../modules/search/functions.php");
		
		$allowedParams = array_intersect_key($_REQUEST,array_flip(array("a","q","person","personID","context","party", "partyID","electoralPeriod", "electoralPeriodID","dateFrom","dateTo","gender","degree","abgeordnetenwatchID","faction","factionID","organisation","organisationID","speakerID","documentID", "agendaItemID","sessionNumber", "sessionID", "page", "sort", "parliament")));

		$return["success"] = "true";
		$return["text"] = "searchresults";
		$return["return"] = searchStats($allowedParams);

	break;
	default:
	break;


}

echo json_encode($return,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>