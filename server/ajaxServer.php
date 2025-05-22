<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();

header('Content-Type: application/json');

$return["success"] = "false";
$return["text"] = "Nope!";
$return["return"] = "";

require_once (__DIR__."/../config.php");
include_once(__DIR__ . '/../modules/utilities/auth.php');
require_once (__DIR__."/../api/v1/api.php");

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

                    $return["text"] = "Entity added";
                    $apiResponse = apiV1([
                        "action" => "getItemsFromDB",
                        "itemType" => "entitySuggestion",
                        "id" => $_REQUEST["id"],
                        "idType" => "external"
                    ]);
                    if (isset($apiResponse["data"]) && isset($apiResponse["meta"]["requestStatus"]) && $apiResponse["meta"]["requestStatus"] == "success") {
                        $return["EntitysuggestionItem"] = $apiResponse["data"];
                    } else {
                        $return["EntitysuggestionItem"] = null;
                        error_log("API call to get entitySuggestion (external ID: ".$_REQUEST["id"].") failed for organisation. Response: ".json_encode($apiResponse));
                    }

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

                    $return["text"] = "Entity added";
                    $apiResponse = apiV1([
                        "action" => "getItemsFromDB",
                        "itemType" => "entitySuggestion",
                        "id" => $_REQUEST["id"],
                        "idType" => "external"
                    ]);
                    if (isset($apiResponse["data"]) && isset($apiResponse["meta"]["requestStatus"]) && $apiResponse["meta"]["requestStatus"] == "success") {
                        $return["EntitysuggestionItem"] = $apiResponse["data"];
                    } else {
                        $return["EntitysuggestionItem"] = null;
                        error_log("API call to get entitySuggestion (external ID: ".$_REQUEST["id"].") failed for person. Response: ".json_encode($apiResponse));
                    }

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

                    $return["text"] = "Entity added";
                    $apiResponse = apiV1([
                        "action" => "getItemsFromDB",
                        "itemType" => "entitySuggestion",
                        "id" => $_REQUEST["id"],
                        "idType" => "external"
                    ]);
                    if (isset($apiResponse["data"]) && isset($apiResponse["meta"]["requestStatus"]) && $apiResponse["meta"]["requestStatus"] == "success") {
                        $return["EntitysuggestionItem"] = $apiResponse["data"];
                    } else {
                        $return["EntitysuggestionItem"] = null;
                        error_log("API call to get entitySuggestion (external ID: ".$_REQUEST["id"].") failed for document. Response: ".json_encode($apiResponse));
                    }
                    
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

                    $return["text"] = "Entity added";
                    $apiResponse = apiV1([
                        "action" => "getItemsFromDB",
                        "itemType" => "entitySuggestion",
                        "id" => $_REQUEST["id"],
                        "idType" => "external"
                    ]);
                    if (isset($apiResponse["data"]) && isset($apiResponse["meta"]["requestStatus"]) && $apiResponse["meta"]["requestStatus"] == "success") {
                        $return["EntitysuggestionItem"] = $apiResponse["data"];
                    } else {
                        $return["EntitysuggestionItem"] = null;
                        error_log("API call to get entitySuggestion (external ID: ".$_REQUEST["id"].") failed for term. Response: ".json_encode($apiResponse));
                    }

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