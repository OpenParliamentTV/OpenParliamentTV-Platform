<?php

//error_reporting(0);

require_once (__DIR__."/../../config.php");
require_once ("config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");

function apiV1($request = false) { // TODO: action: getItem; type: media; id: DE-0190002123

    global $config;

    $return["meta"]["api"]["version"] = 1;
    $return["meta"]["api"]["documentation"] = $config["dir"]["root"]."documentation/api";
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    if ((!$request["action"]) || (!$request["itemType"])) {

        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
        array_push($return["errors"], $errorarray);
        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"].$_SERVER["REQUEST_URI"]);

    } else {

        switch ($request["action"]) {

            case "getItem":



                switch ($request["itemType"]) {

                    case "organisation":

                        require_once (__DIR__."/modules/organisation.php");

                        $item = organisationGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                        break;



                    case "document":

                        require_once (__DIR__."/modules/document.php");

                        $item = documentGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                        break;



                    case "term":

                        require_once (__DIR__."/modules/term.php");

                        $item = termGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                        break;



                    case "person":

                        require_once (__DIR__."/modules/person.php");

                        $item = personGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                        break;



                    case "media":

                        require_once (__DIR__."/modules/media.php");

                        $item = mediaGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                    break;



                    case "session":

                        require_once (__DIR__."/modules/session.php");

                        $item = sessionGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                    break;



                    case "agendaItem":

                        require_once (__DIR__."/modules/agendaItem.php");

                        $item = agendaItemGetByID($request["id"]);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                        $return = array_replace_recursive($return, $item);

                    break;



                    case "electoralPeriod":

                        require_once (__DIR__."/modules/electoralPeriod.php");

                        $item = electoralPeriodGetByID($request["id"]);

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
                        $errorarray["detail"] = "Required parameter (type) of the request is missing"; //TODO: Description
                        array_push($return["errors"], $errorarray);

                    break;
                }
            break;

            case "search":

                switch ($request["itemType"]) {

                    case "people":

                        require_once (__DIR__."/modules/person.php");

                        $item = personSearch($_REQUEST);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                    break;

                    case "organisation":

                        require_once (__DIR__."/modules/organisation.php");

                        $item = organisationSearch($_REQUEST);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                    break;

                    case "document":

                        require_once (__DIR__."/modules/document.php");

                        $item = documentSearch($_REQUEST);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                    break;

                    case "term":

                        require_once (__DIR__."/modules/term.php");

                        $item = termSearch($_REQUEST);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                    break;

                    case "media":

                        require_once (__DIR__."/modules/media.php");

                        $item = mediaSearch($_REQUEST);

                        if ($item["meta"]["requestStatus"] == "success") {

                            unset($return["errors"]);

                        } else {

                            unset($return["data"]);

                        }

                    break;

                    default:

                        //Wrong $itemType
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "2";
                        $errorarray["title"] = "Missing request parameter";
                        $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
                        array_push($return["errors"], $errorarray);
                        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"].$_SERVER["REQUEST_URI"]);
                    break;
                }


                if ($item) {
                    $return = array_replace_recursive($return, $item);
                }


            break;
            default:

                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Missing request parameter";
                $errorarray["detail"] = "Required parameter (action) of the request is missing"; //TODO: Description
                array_push($return["errors"], $errorarray);

                break;

        }





        }

    return $return;

}

?>