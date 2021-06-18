<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

require_once (__DIR__."./../../config.php");
require_once (__DIR__."./../../modules/utilities/functions.php");
require_once (__DIR__."./../../modules/utilities/safemysql.class.php");


$return["meta"]["api"]["version"] = 1;
$return["meta"]["api"]["documentation"] = "https://de.openparliament.tv/api";
$return["meta"]["requestStatus"] = "error";
$return["errors"] = array();

if ((!$_REQUEST["a"]) || (!$_REQUEST["param"])) {

    $errorarray["status"] = "422";
    $errorarray["code"] = "1";
    $errorarray["title"] = "Missing request parameter";
    $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
    array_push($return["errors"], $errorarray);
    $return["links"]["self"] = ""; //TODO: Prevent XSS.

} else {

    switch ($_REQUEST["a"]) {

        case "organisation":

            require_once (__DIR__."/modules/organisation.php");

            $item = organisationGetByID($_REQUEST["param"]);

            if ($item["meta"]["requestStatus"] == "success") {

                unset($return["errors"]);

            } else {

                unset($return["data"]);

            }

            $return = array_replace_recursive($return, $item);

        break;



        case "document":

            require_once (__DIR__."/modules/document.php");

            $item = documentGetByID($_REQUEST["param"]);

            if ($item["meta"]["requestStatus"] == "success") {

                unset($return["errors"]);

            } else {

                unset($return["data"]);

            }

            $return = array_replace_recursive($return, $item);

        break;



        case "term":

            require_once (__DIR__."/modules/term.php");

            $item = termGetByID($_REQUEST["param"]);

            if ($item["meta"]["requestStatus"] == "success") {

                unset($return["errors"]);

            } else {

                unset($return["data"]);

            }

            $return = array_replace_recursive($return, $item);

        break;



        case "person":

            require_once (__DIR__."/modules/person.php");

            $item = personGetByID($_REQUEST["param"]);

            if ($item["meta"]["requestStatus"] == "success") {

                unset($return["errors"]);

            } else {

                unset($return["data"]);

            }

            $return = array_replace_recursive($return, $item);

        break;



        case "media":

            require_once (__DIR__."/modules/media.php");

            $item = mediaGetByID($_REQUEST["param"]);

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
header('Content-Type: application/json');
echo json_encode($return, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



?>