<?php

error_reporting(0);

require_once (__DIR__."./../../config.php");
require_once ("config.php");
require_once (__DIR__."./../../modules/utilities/functions.php");
require_once (__DIR__."./../../modules/utilities/safemysql.class.php");

function apiV1($action = false, $param = false) {

    global $config;

    $return["meta"]["api"]["version"] = 1;
    $return["meta"]["api"]["documentation"] = $config["dir"]["root"]."documentation/api";
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    if ((!$action) || (!$param)) {

        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
        array_push($return["errors"], $errorarray);
        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"].$_SERVER["REQUEST_URI"]);

    } else {

        switch ($action) {

            case "organisation":

                require_once (__DIR__."/modules/organisation.php");

                $item = organisationGetByID($param);

                if ($item["meta"]["requestStatus"] == "success") {

                    unset($return["errors"]);

                } else {

                    unset($return["data"]);

                }

                $return = array_replace_recursive($return, $item);

            break;



            case "document":

                require_once (__DIR__."/modules/document.php");

                $item = documentGetByID($param);

                if ($item["meta"]["requestStatus"] == "success") {

                    unset($return["errors"]);

                } else {

                    unset($return["data"]);

                }

                $return = array_replace_recursive($return, $item);

            break;



            case "term":

                require_once (__DIR__."/modules/term.php");

                $item = termGetByID($param);

                if ($item["meta"]["requestStatus"] == "success") {

                    unset($return["errors"]);

                } else {

                    unset($return["data"]);

                }

                $return = array_replace_recursive($return, $item);

            break;



            case "person":

                require_once (__DIR__."/modules/person.php");

                $item = personGetByID($param);

                if ($item["meta"]["requestStatus"] == "success") {

                    unset($return["errors"]);

                } else {

                    unset($return["data"]);

                }

                $return = array_replace_recursive($return, $item);

            break;



            case "media":

                require_once (__DIR__."/modules/media.php");

                $item = mediaGetByID($param);

                if ($item["meta"]["requestStatus"] == "success") {

                    unset($return["errors"]);

                } else {

                    unset($return["data"]);

                }

                $return = array_replace_recursive($return, $item);

            break;

            default:

                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Missing request parameter";
                $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
                array_push($return["errors"], $errorarray);

            break;



        }
    }

    return $return;

}

?>