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

    default:
    break;


}

echo json_encode($return,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

?>