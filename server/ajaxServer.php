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

        if ($_REQUEST["lang"] && array_key_exists($_REQUEST["lang"], $acceptLang)) {

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

    break;

    case "reimportSessions":

        $auth = auth($_SESSION["userdata"]["id"], "entitysuggestion", "get");

        if ($auth["meta"]["requestStatus"] != "success") {

            //TODO: response
            //$alertText = $auth["errors"][0]["detail"];
            //include_once (__DIR__."/../../../login/page.php");

        } else {

            $return["success"] = "true";
            $return["text"] = "Reimport Sessions";

            require_once (__DIR__."/../modules/utilities/functions.php");
            foreach($_REQUEST["files"] as $parliament=>$files) {
                foreach ($files as $file) {
                    copy(__DIR__."/../data/repos/".$parliament."/processed/".$file,__DIR__."/../data/input/".$file);
                }
            }

        }

    break;

    case "entitysuggestionGet":

        $auth = auth($_SESSION["userdata"]["id"], "entitysuggestion", "get");

        if ($auth["meta"]["requestStatus"] != "success") {

            //TODO: response
            //$alertText = $auth["errors"][0]["detail"];
            //include_once (__DIR__."/../../../login/page.php");

        } else {
            $return["success"] = "true";
            $return["text"] = "Get entity suggestion";
            require_once(__DIR__ . "/../modules/utilities/functions.entities.php");
            $return["return"] = getEntitySuggestion($_REQUEST["id"]);

        }

    break;

    case "entityAdd":

        $auth = auth($_SESSION["userdata"]["id"], "entity", "add");

        if ($auth["meta"]["requestStatus"] != "success") {

            //TODO Response

        } else {
            require_once (__DIR__."/../modules/utilities/functions.php");
            switch ($_REQUEST["entityType"]) {
                case "organisation":
                    require_once(__DIR__."/../api/v1/modules/organisation.php");
                    $return = organisationAdd($_REQUEST);

                    //TODO Transfer $return["meta"]["requestStatus"] = success/error to $return["success"] = true/false - also in frontend
                    //$return["success"] = "true";
                    $return["text"] = "Entity added";
                    if ($_REQUEST["entitysuggestionid"]) {
                        require_once (__DIR__."/../modules/utilities/functions.entities.php");
                        $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external", "ORG");
                        $return["sessions"] = array();
                        foreach ($return["EntitysuggestionItem"]["EntitysuggestionContext"] as $item) {
                            $itemInfos = getInfosFromStringID($item);
                            $tmpFileName = substr($itemInfos["electoralPeriodNumber"],1).substr($itemInfos["sessionNumber"],1)."-session.json";
                            $return["sessions"][$itemInfos["parliament"]][$tmpFileName]["fileExists"] = is_file(__DIR__."/../data/repos/".$itemInfos["parliament"]."/processed/".$tmpFileName);
                        }
                    }
                break;
                case "person":
                    require_once(__DIR__."/../api/v1/modules/person.php");
                    $return = personAdd($_REQUEST);

                    //TODO Transfer $return["meta"]["requestStatus"] = success/error to $return["success"] = true/false - also in frontend
                    //$return["success"] = "true";
                    $return["text"] = "Entity added";
                    if ($_REQUEST["entitysuggestionid"]) {
                        require_once (__DIR__."/../modules/utilities/functions.entities.php");
                        $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external", "PERSON");
                        $return["sessions"] = array();
                        foreach ($return["EntitysuggestionItem"]["EntitysuggestionContext"] as $item) {
                            $itemInfos = getInfosFromStringID($item);
                            $tmpFileName = substr($itemInfos["electoralPeriodNumber"],1).substr($itemInfos["sessionNumber"],1)."-session.json";
                            $return["sessions"][$itemInfos["parliament"]][$tmpFileName]["fileExists"] = is_file(__DIR__."/../data/repos/".$itemInfos["parliament"]."/processed/".$tmpFileName);
                        }
                    }

                break;
                default:

                break;
            }

        }

    break;

    case "searchIndexUpdate":

        $auth = auth($_SESSION["userdata"]["id"], "searchIndex", "update");

        if ($auth["meta"]["requestStatus"] != "success") {

            //TODO Response

        } else {

            require_once (__DIR__."/../modules/utilities/safemysql.class.php");
            require_once (__DIR__."/../api/v1/api.php");
            //require_once (__DIR__."/../api/v1/modules/media.php");

            if (!$db) {
                try {

                    $db = new SafeMySQL(array(
                        'host'	=> $config["platform"]["sql"]["access"]["host"],
                        'user'	=> $config["platform"]["sql"]["access"]["user"],
                        'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                        'db'	=> $config["platform"]["sql"]["db"]
                    ));

                } catch (exception $e) {

                    $return["meta"]["requestStatus"] = "error";
                    $return["errors"] = array();
                    $errorarray["status"] = "503";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Database connection error";
                    $errorarray["detail"] = "Connecting to platform database failed";
                    array_push($return["errors"], $errorarray);
                    echo json_encode($return);
                    exit;

                }
            }


            if (!$dbp) {
                try {

                    $dbp = new SafeMySQL(array(
                        'host'	=> $config["parliament"][$_REQUEST["parliament"]]["sql"]["access"]["host"],
                        'user'	=> $config["parliament"][$_REQUEST["parliament"]]["sql"]["access"]["user"],
                        'pass'	=> $config["parliament"][$_REQUEST["parliament"]]["sql"]["access"]["passwd"],
                        'db'	=> $config["parliament"][$_REQUEST["parliament"]]["sql"]["db"]
                    ));

                } catch (exception $e) {

                    $return["meta"]["requestStatus"] = "error";
                    $return["errors"] = array();
                    $errorarray["status"] = "503";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Database connection error";
                    $errorarray["detail"] = "Connecting to parliament database failed";
                    array_push($return["errors"], $errorarray);
                    echo json_encode($return);
                    exit;

                }
            }

            if ($_REQUEST["type"] == "all") {

                $mediaIDs = $dbp->getAll("SELECT MediaID FROM ?n", $config["parliament"][$_REQUEST["parliament"]]["sql"]["tbl"]["Media"]);

            } elseif ($_REQUEST["type"] == "specific") {

                $mediaIDs = explode(",",$_REQUEST["mediaIDs"]);

            }


            $mediaItems = array();
            require_once (__DIR__."/../data/updateSearchIndex.php");

            foreach ($mediaIDs as $mediaID) {
                $requestID = (is_array($mediaID) ? $mediaID["MediaID"] : $mediaID);
                try {
                    $tmpMedia = apiV1([
                        "action" => "getItem",
                        "itemType" => "media",
                        "id" => $requestID
                    ], $db, $dbp);
                    array_push($mediaItems, $tmpMedia);

                    if (count($mediaItems) == 30) {
                        updateSearchIndex($_REQUEST["parliament"], $mediaItems, true);
                        $mediaItems = array();
                    }

                } catch (Exception $e) {
                    $errorarray["title"] = "Get Media failed";
                    $errorarray["detail"] = $e->getMessage();
                    array_push($return["errors"], $errorarray);
                }
            }

            if (!empty($mediaItems)) {
                updateSearchIndex($_REQUEST["parliament"], $mediaItems, true);
            }



            try {

                //$updatedItems = updateSearchIndex($_REQUEST["parliament"], $mediaItems, true);
                $return["success"] = "true";
                $return["text"] = "Search Index Updated";

            } catch (Exception $e) {

                $return["success"] = "false";
                $return["text"] = "Failed: Search Index Update";

            }




        }

    break;

    case "searchIndexDelete":

        if (!$_REQUEST["parliament"]) {

            $return["success"] = "false";
            $return["text"] = "Failed: Parliament parameter missing";
            return json_encode($return);
        }

        $auth = auth($_SESSION["userdata"]["id"], "searchIndex", "delete");

        if ($auth["meta"]["requestStatus"] != "success") {

            //TODO Response

        } else {

            require_once (__DIR__."/../data/updateSearchIndex.php");
            deleteSearchIndex($_REQUEST["parliament"],(($_REQUEST["init"] ? $_REQUEST["init"] : true)));

            //TODO Response
        }

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