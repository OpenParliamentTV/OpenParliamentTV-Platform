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
        if ($_REQUEST["lang"] && isset($acceptLang) && array_key_exists($_REQUEST["lang"], $acceptLang)) {
            $_SESSION["lang"] = $_REQUEST["lang"];
            $return["success"] = "true";
            $return["text"] = "Language has been set to " . $_REQUEST["lang"];
            $return["return"] = $_REQUEST["lang"];
        } else {
            $return["success"] = "false";
            $return["text"] = "Invalid language code";
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
            exit;
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
    case "entitysuggestionGetTable":

        $auth = auth($_SESSION["userdata"]["id"], "entitysuggestion", "entitysuggestionGetTable");

        if ($auth["meta"]["requestStatus"] != "success") {

            $alertText = $auth["errors"][0]["detail"];
            include_once (__DIR__."/../../../login/page.php");

        } else {

            require_once(__DIR__ . "/../modules/utilities/functions.entities.php");
            echo json_encode(getEntitySuggestionTable("all", $_REQUEST["limit"], $_REQUEST["offset"], $_REQUEST["search"], $_REQUEST["sort"], $_REQUEST["order"], true), JSON_PRETTY_PRINT);
            return;
        }

    break;

    case "entityAdd":

        $auth = auth($_SESSION["userdata"]["id"], "entity", "add");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";

        } else {
            require_once (__DIR__."/../modules/utilities/functions.php");
            switch ($_REQUEST["entityType"]) {
                case "organisation":
                    require_once(__DIR__."/../api/v1/modules/organisation.php");
                    $return = organisationAdd($_REQUEST);

                    //TODO Transfer $return["meta"]["requestStatus"] = success/error to $return["success"] = true/false - also in frontend
                    //$return["success"] = "true";
                    $return["text"] = "Entity added";
                    require_once (__DIR__."/../modules/utilities/functions.entities.php");
                    $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external");
                    if ($return["EntitysuggestionItem"]) {
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
                    require_once (__DIR__."/../modules/utilities/functions.entities.php");
                    $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external");
                    if ($return["EntitysuggestionItem"]) {
                        $return["sessions"] = array();
                        foreach ($return["EntitysuggestionItem"]["EntitysuggestionContext"] as $item) {
                            $itemInfos = getInfosFromStringID($item);
                            $tmpFileName = substr($itemInfos["electoralPeriodNumber"],1).substr($itemInfos["sessionNumber"],1)."-session.json";
                            $return["sessions"][$itemInfos["parliament"]][$tmpFileName]["fileExists"] = is_file(__DIR__."/../data/repos/".$itemInfos["parliament"]."/processed/".$tmpFileName);
                        }
                    }
                break;
                case "document":
                    require_once(__DIR__."/../api/v1/modules/document.php");
                    $return = documentAdd($_REQUEST);

                    //TODO Transfer $return["meta"]["requestStatus"] = success/error to $return["success"] = true/false - also in frontend
                    //$return["success"] = "true";
                    $return["text"] = "Entity added";
                    require_once (__DIR__."/../modules/utilities/functions.entities.php");
                    $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external"); //TODO: MISC LOC or anything else
                    if ($return["EntitysuggestionItem"]) {
                        $return["sessions"] = array();
                        foreach ($return["EntitysuggestionItem"]["EntitysuggestionContext"] as $item) {
                            $itemInfos = getInfosFromStringID($item);
                            $tmpFileName = substr($itemInfos["electoralPeriodNumber"],1).substr($itemInfos["sessionNumber"],1)."-session.json";
                            $return["sessions"][$itemInfos["parliament"]][$tmpFileName]["fileExists"] = is_file(__DIR__."/../data/repos/".$itemInfos["parliament"]."/processed/".$tmpFileName);
                        }
                    }
                break;
                case "term":
                    require_once(__DIR__."/../api/v1/modules/term.php");
                    $return = termAdd($_REQUEST);

                    //TODO Transfer $return["meta"]["requestStatus"] = success/error to $return["success"] = true/false - also in frontend
                    //$return["success"] = "true";
                    $return["text"] = "Entity added";
                    require_once (__DIR__."/../modules/utilities/functions.entities.php");
                    $return["EntitysuggestionItem"] = getEntitySuggestion($_REQUEST["id"],"external"); //TODO: MISC LOC or anything else
                    if ($return["EntitysuggestionItem"]) {
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

    case "entityGetFromAdditionalDataService":

        $auth = auth($_SESSION["userdata"]["id"], "additionalDataService", "getItem");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";


        } else {
            require_once (__DIR__."/../modules/utilities/functions.php");
            try {

                $return = json_decode(file_get_contents($config["ads"]["api"]["uri"]."?key=".$config["ads"]["api"]["key"]."&type=".$_REQUEST["type"]."&wikidataID=".$_REQUEST["wikidataID"]),true);

            } catch (Exception $e) {

                $return["success"] = "false";
                $return["text"] = "error:".$e->getMessage();


            }

        }

    break;

    case "searchIndexUpdate":

        $auth = auth($_SESSION["userdata"]["id"], "searchIndex", "update");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";

        } else {

            if (!array_key_exists($_REQUEST["parliament"], $config["parliament"])) {

                $return["success"] = "false";
                $return["text"] = "parliament parameter missing or false";
                $return["code"] = "403";

            } else {

                require_once (__DIR__."/../modules/utilities/functions.php");

                if ($_REQUEST["MediaIDs"]) {
                    $ids = " --ids ".$_REQUEST["MediaIDs"];
                }

                executeAsyncShellCommand($config["bin"]["php"]." ".realpath(__DIR__."/../data/cronUpdater.php")." --parliament ".$_REQUEST["parliament"]." --justUpdateSearchIndex true".$ids);

                $return["success"] = "true";
                $return["text"] = "Process should have started";
                $return["code"] = "200";

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

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";

        } else {

            require_once (__DIR__."/../data/updateSearchIndex.php");
            deleteSearchIndex($_REQUEST["parliament"],(($_REQUEST["init"] ? $_REQUEST["init"] : true)));

            //TODO Response
        }

    break;

    case "runCronUpdater":

        $auth = auth($_SESSION["userdata"]["id"], "updater", "run");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";


        } else {
            require_once (__DIR__."/../modules/utilities/functions.php");
            executeAsyncShellCommand($config["bin"]["php"]." ".realpath(__DIR__."/../data/cronUpdater.php"));
            $return["success"] = "true";
            $return["text"] = "cronUpdater";

        }

    break;

    case "runAdditionalDataService":

        $auth = auth($_SESSION["userdata"]["id"], "additionalDataService", "run");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";


        } else {

            require_once (__DIR__."/../modules/utilities/functions.php");
            executeAsyncShellCommand($config["bin"]["php"]." ".realpath(__DIR__."/../data/cronAdditionalDataService.php")." --type ".$_REQUEST["type"]);
            $return["success"] = "true";
            $return["text"] = "additionalDataService";

        }

    break;


    case "runAdditionalDataServiceForSpecificEntities":

        $auth = auth($_SESSION["userdata"]["id"], "additionalDataService", "runForSpecificEntities");

        if ($auth["meta"]["requestStatus"] != "success") {

            $return["success"] = "false";
            $return["text"] = "Forbidden";
            $return["code"] = "403";


        } else {

            require_once (__DIR__."/../data/updateEntityFromService.php");

            try {

                foreach ($_REQUEST["ids"] as $k=>$id) {

                    $return["ret"][] = updateEntityFromService($_REQUEST["type"][$k], $id, $config["ads"]["api"]["uri"], $config["ads"]["api"]["key"], $_REQUEST["language"]);

                }

                $return["success"] = "true";
                $return["text"] = "Items updated: ".count($_REQUEST["ids"]);
            } catch (Exception $e) {

                $return["success"] = "false";
                $return["text"] = "Error: ".$e->getMessage();

            }



        }

    break;

    case "getMediaIDListFromSearchResult":
        require_once(__DIR__."/../modules/search/functions.php");
        require_once(__DIR__."/../modules/utilities/functions.php");
        
        $allowedParams = filterAllowedSearchParams($_REQUEST, 'media');
        
        $return["success"] = "true";
        $return["text"] = "searchresults";
        $return["return"] = getMediaIDListFromSearchResult($allowedParams);
    break;

    default:
    break;


}

echo json_encode($return,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>