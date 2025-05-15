<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once (__DIR__."/../../config.php");
require_once ("config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");

function apiV1($request_param = false, $db = false, $dbp = false) {

    global $config;

    $return["meta"]["api"]["version"] = 1;
    $return["meta"]["api"]["documentation"] = $config["dir"]["root"]."/api";
    $return["meta"]["api"]["license"]["label"] = "ODC Open Database License (ODbL) v1.0";
    $return["meta"]["api"]["license"]["link"] = "https://opendatacommons.org/licenses/odbl/1-0/";
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    // New request handling logic:
    $final_request = []; 

    // Get request body if it exists and merge
    $requestBody = json_decode(file_get_contents('php://input'), true);
    if ($requestBody) {
        $final_request = array_merge($final_request, $requestBody);
    }

    // Get URL parameters and merge
    $urlParams = $_GET;
    if ($urlParams) {
        $final_request = array_merge($final_request, $urlParams);
    }

    // Merge the explicitly passed $request_param, giving it precedence
    if ($request_param) {
        $final_request = array_merge($final_request, $request_param);
    }
    // End of new request handling logic

    if ((!$final_request["action"]) || (!$final_request["itemType"])) {

        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameters of the request are missing";
        array_push($return["errors"], $errorarray);
        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"]);

    } else {

        switch ($final_request["action"]) {

            // =============================================
            // Public API endpoints (no authentication required)
            // =============================================

            case "getItem":
                switch ($final_request["itemType"]) {
                    case "organisation":
                        require_once (__DIR__."/modules/organisation.php");
                        $item = organisationGetByID($final_request["id"]);
                        break;
                    case "document":
                        require_once (__DIR__."/modules/document.php");
                        $item = documentGetByID($final_request["id"]);
                        break;
                    case "term":
                        require_once (__DIR__."/modules/term.php");
                        $item = termGetByID($final_request["id"]);
                        break;
                    case "person":
                        require_once (__DIR__."/modules/person.php");
                        $item = personGetByID($final_request["id"]);
                        break;
                    case "media":
                        require_once (__DIR__."/modules/media.php");
                        $item = mediaGetByID($final_request["id"], $db, $dbp);
                        break;
                    case "session":
                        require_once (__DIR__."/modules/session.php");
                        $item = sessionGetByID($final_request["id"]);
                        break;
                    case "agendaItem":
                        require_once (__DIR__."/modules/agendaItem.php");
                        $item = agendaItemGetByID($final_request["id"]);
                        break;
                    case "electoralPeriod":
                        require_once (__DIR__."/modules/electoralPeriod.php");
                        $item = electoralPeriodGetByID($final_request["id"]);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Missing request parameter";
                        $errorarray["detail"] = "Required parameter (type) of the request is missing";
                        array_push($return["errors"], $errorarray);
                        break;
                }

                if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }
                $return = array_replace_recursive($return, $item);
                break;

            case "search":
                switch ($final_request["itemType"]) {
                    case "people":
                        require_once (__DIR__."/modules/person.php");
                        $item = personSearch($final_request);
                        break;
                    case "organisations":
                        require_once (__DIR__."/modules/organisation.php");
                        $item = organisationSearch($final_request);
                        break;
                    case "documents":
                        require_once (__DIR__."/modules/document.php");
                        $item = documentSearch($final_request);
                        break;
                    case "terms":
                        require_once (__DIR__."/modules/term.php");
                        $item = termSearch($final_request);
                        break;
                    case "media":
                        require_once (__DIR__."/modules/media.php");
                        $item = mediaSearch($final_request);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "2";
                        $errorarray["title"] = "Missing request parameter";
                        $errorarray["detail"] = "Required parameter of the request is missing";
                        array_push($return["errors"], $errorarray);
                        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"]);
                        break;
                }

                if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }
                $return = array_replace_recursive($return, $item);
                break;

            case "autocomplete":
                include_once(__DIR__."/modules/autocomplete.php");
                switch ($final_request["itemType"]) {
                    case "text": 
                        $item = fulltextAutocomplete($final_request["q"]);
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }
                        $return = array_replace_recursive($return, $item);
                        break;
                }
                $return["links"]["self"] = htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"]);
                break;

            case "statistics":
                include_once(__DIR__."/modules/statistics.php");
                switch ($final_request["itemType"]) {
                    case "general": 
                        $item = statisticsGetGeneral($final_request);
                        break;
                    case "entity":
                        $item = statisticsGetEntity($final_request);
                        break;
                    case "terms":
                        $item = statisticsGetTerms($final_request);
                        break;
                    case "compare-terms":
                        $item = statisticsCompareTerms($final_request);
                        break;
                    case "network":
                        $item = statisticsGetNetwork($final_request);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Invalid statistics type";
                        $errorarray["detail"] = "The requested statistics type is not supported";
                        array_push($return["errors"], $errorarray);
                        break;
                }
                
                if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }
                $return = array_replace_recursive($return, $item);
                break;

            case "user":
                require_once (__DIR__."/modules/user.php");
                switch ($final_request["itemType"]) {
                    case "login":
                        $result = userLogin($final_request);
                        break;
                    case "register":
                        $result = userRegister($final_request);
                        break;
                    case "logout":
                        $result = userLogout();
                        break;
                    case "password-reset":
                        $result = userPasswordReset($final_request);
                        break;
                    case "password-reset-request":
                        $result = userPasswordResetRequest($final_request);
                        break;
                    case "confirm-registration":
                        $result = userConfirmRegistration($final_request);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Invalid user action";
                        $errorarray["detail"] = "The requested user action is not supported";
                        array_push($return["errors"], $errorarray);
                        break;
                }
                
                if ($result) {
                    if (isset($result["meta"]["requestStatus"]) && $result["meta"]["requestStatus"] == "success") {
                        unset($return["errors"]);
                    } else {
                        unset($return["data"]);
                    }
                    $return = array_replace_recursive($return, $result);
                }
                break;

            // =============================================
            // Private API endpoints (authentication required)
            // =============================================

            case "addItem":
                switch ($final_request["itemType"]) {
                    case "media":
                        require_once (__DIR__."/modules/media.php");
                        $item = mediaAdd($final_request);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Missing request parameter";
                        $errorarray["detail"] = "Required parameter (type) of the request is missing";
                        array_push($return["errors"], $errorarray);
                        break;
                }

                if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }
                $return = array_replace_recursive($return, $item);
                break;

            case "changeItem":
                switch ($final_request["itemType"]) {
                    case "organisation":
                        require_once (__DIR__."/modules/organisation.php");
                        $item = organisationChange($final_request);
                        break;
                    case "document":
                        require_once (__DIR__."/modules/document.php");
                        $item = documentChange($final_request);
                        break;
                    case "term":
                        require_once (__DIR__."/modules/term.php");
                        $item = termChange($final_request);
                        break;
                    case "person":
                        require_once (__DIR__."/modules/person.php");
                        $item = personChange($final_request);
                        break;
                    case "media":
                        require_once (__DIR__."/modules/media.php");
                        $item = mediaChange($final_request);
                        break;
                    case "session":
                        require_once (__DIR__."/modules/session.php");
                        $item = sessionChange($final_request);
                        break;
                    case "agendaItem":
                        require_once (__DIR__."/modules/agendaItem.php");
                        $item = agendaItemChange($final_request);
                        break;
                    case "electoralPeriod":
                        require_once (__DIR__."/modules/electoralPeriod.php");
                        $item = electoralPeriodChange($final_request);
                        break;
                    case "user":
                        require_once (__DIR__."/modules/user.php");
                        $item = userChange($final_request);
                        break;
                    default:
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Missing request parameter";
                        $errorarray["detail"] = "Required parameter (type) of the request is missing";
                        array_push($return["errors"], $errorarray);
                        break;
                }

                if ($item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }
                $return = array_replace_recursive($return, $item);
                break;

            case "getItemsFromDB":
                if (empty($final_request["itemType"])) {
                    $return["meta"]["requestStatus"] = "error";
                    $return["errors"] = array();
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Missing parameter";
                    $errorarray["detail"] = "Parameter 'itemType' is required";
                    array_push($return["errors"], $errorarray);
                    return $return;
                }

                if (empty($final_request["limit"])) {
                    $final_request["limit"] = 10;
                }
                if (empty($final_request["offset"])) {
                    $final_request["offset"] = 0;
                }
                if (empty($final_request["sort"])) {
                    $final_request["sort"] = false;
                }
                if (empty($final_request["order"])) {
                    $final_request["order"] = false;
                }
                if (empty($final_request["search"])) {
                    $final_request["search"] = false;
                }
                if (empty($final_request["id"])) {
                    $final_request["id"] = "all";
                }

                switch ($final_request["itemType"]) {
                    case "person":
                        require_once (__DIR__."/modules/person.php");
                        $result = personGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    case "organisation":
                        require_once (__DIR__."/modules/organisation.php");
                        $result = organisationGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    case "document":
                        require_once (__DIR__."/modules/document.php");
                        $result = documentGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    case "term":
                        require_once (__DIR__."/modules/term.php");
                        $result = termGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    case "electoralPeriod":
                        require_once (__DIR__."/modules/electoralPeriod.php");
                        $result = electoralPeriodGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    case "session":
                        require_once (__DIR__."/modules/session.php");
                        $result = sessionGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"], false, $final_request["electoralPeriodID"]);
                        break;
                    case "agendaItem":
                        require_once (__DIR__."/modules/agendaItem.php");
                        $result = agendaItemGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"], false, $final_request["electoralPeriodID"], $final_request["sessionID"]);
                        break;
                    case "user":
                        require_once (__DIR__."/modules/user.php");
                        $result = userGetItemsFromDB($final_request["id"], $final_request["limit"], $final_request["offset"], $final_request["search"], $final_request["sort"], $final_request["order"]);
                        break;
                    default:
                        $return["meta"]["requestStatus"] = "error";
                        $return["errors"] = array();
                        $errorarray["status"] = "400";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Invalid parameter";
                        $errorarray["detail"] = "Parameter 'itemType' has an invalid value";
                        array_push($return["errors"], $errorarray);
                        return $return;
                }

                if ($result) {
                    $return["meta"]["requestStatus"] = "success";
                    if ($final_request["id"] !== "all") {
                        $return["data"] = $result["data"][0] ?? null;
                    } else {
                        $return["total"] = $result["total"];
                        $return["data"] = $result["data"];
                    }
                    unset($return["errors"]);
                } else {
                    $return["meta"]["requestStatus"] = "error";
                    $return["errors"] = array();
                    $errorarray["status"] = "500";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Database error";
                    $errorarray["detail"] = "Could not get overview";
                    array_push($return["errors"], $errorarray);
                    return $return;
                }
                break;

            case "wikidataService":
                $return["data"] = array();
                switch ($final_request["itemType"]) {
                    case "person":
                        if ($final_request["str"]) {
                            foreach ($config["parliament"] as $p=>$v) {
                                if (file_exists($v["cache"]["wp"]."/people.json")) {
                                    $dump[$p] = json_decode(file_get_contents($v["cache"]["wp"]."/people.json"),true);
                                }
                            }

                            if (!preg_match("/(Q|P)\d+/i", $final_request["str"])) {
                                $final_request["str"] = preg_replace("/\s/u",".*", $final_request["str"]);
                                $tmpType = "label";
                            } else {
                                $tmpType = "id";
                            }

                            foreach ($dump as $p=>$d) {
                                foreach ($d as $k => $v) {
                                    $success = false;

                                    if (preg_match("/" . convertAccentsAndSpecialToNormal($final_request["str"]) . "/ui", convertAccentsAndSpecialToNormal($v[$tmpType]))) {
                                        $success = true;
                                    } else if (isset($v["altLabel"])) {
                                        if (is_string($v["altLabel"])) {
                                            if (preg_match("/" . convertAccentsAndSpecialToNormal($final_request["str"]) . "/ui", convertAccentsAndSpecialToNormal($v["altLabel"]))) {
                                                $success = true;
                                            }
                                        } else if (is_array($v["altLabel"])) {
                                            foreach ($v["altLabel"] as $altLabel) {
                                                if (preg_match("/" . convertAccentsAndSpecialToNormal($final_request["str"]) . "/ui", convertAccentsAndSpecialToNormal($altLabel))) {
                                                    $success = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }

                                    if ($success) {
                                        $return["meta"]["requestStatus"] = "success";

                                        if (gettype($v["party"]) == "array") {
                                            $v["party-original-array"] = $v["party"];
                                            $v["party"] = $v["party"][0];
                                        }

                                        if (preg_match("/www\.wiki/", $v["party"])) {
                                            $v["party-original-URL"] = $v["party"];
                                            $tmpArray = explode("/", $v["party"]);
                                            $v["party"] = array_pop($tmpArray);
                                            $v["partyLabelAlternative"] = apiV1(["action" => "wikidataService", "itemType" => "party", "str" => $v["party"]])["data"][0]["labelAlternative"];
                                        }

                                        if (gettype($v["faction"]) == "array") {
                                            $v["faction-original-array"] = $v["faction"];
                                            $v["faction"] = $v["faction"][0];
                                        }

                                        if (preg_match("/www\.wiki/", $v["faction"])) {
                                            $v["faction-original-URL"] = $v["faction"];
                                            $tmpArray = explode("/", $v["faction"]);
                                            $v["faction"] = array_pop($tmpArray);
                                        }

                                        $v["parliament"] = $p;
                                        $return["data"][] = $v;
                                    }
                                }
                            }
                            if (count($return["data"]) > 0) {
                                return $return;
                            } else {
                                $return["meta"]["requestStatus"] = "error";
                                $return["errors"] = array();
                                $errorarray["status"] = "404";
                                $errorarray["code"] = "1";
                                $errorarray["title"] = "No results";
                                $errorarray["detail"] = "Person not found in dump";
                                array_push($return["errors"], $errorarray);
                            }
                        } else {
                            $return["meta"]["requestStatus"] = "error";
                            $return["errors"] = array();
                            $errorarray["status"] = "503";
                            $errorarray["code"] = "1";
                            $errorarray["title"] = "Missing Parameter str";
                            $errorarray["detail"] = "missing parameter str";
                            array_push($return["errors"], $errorarray);
                        }
                        break;

                    case "party":
                        if ($final_request["str"]) {
                            $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/parties.json"),true);

                            if (!preg_match("/(Q|P)\d+/i", $final_request["str"])) {
                                $final_request["str"] = preg_replace("/\s/u",".*", $final_request["str"]);
                                $final_request["str"] = preg_replace("/\//","\\/", $final_request["str"]);
                                $tmpType = "label";
                            } else {
                                $tmpType = "id";
                            }

                            $return["data"] = [];

                            foreach ($dump as $k => $v) {
                                if ((preg_match("/" . $final_request["str"] . "/i", $v[$tmpType])) || ((($tmpType == "label") && (gettype($v["labelAlternative"]) == "string")) && (preg_match("/" . $final_request["str"] . "/i", $v["labelAlternative"])))) {
                                    $return["meta"]["requestStatus"] = "success";
                                    $return["data"][] = $v;
                                }
                            }

                            if (count($return["data"]) > 0) {
                                return $return;
                            } else {
                                $return["meta"]["requestStatus"] = "error";
                                $return["errors"] = array();
                                $errorarray["status"] = "404";
                                $errorarray["code"] = "1";
                                $errorarray["title"] = "No results";
                                $errorarray["detail"] = "Party not found in dump";
                                array_push($return["errors"], $errorarray);
                            }
                        } else {
                            $return["meta"]["requestStatus"] = "error";
                            $return["errors"] = array();
                            $errorarray["status"] = "503";
                            $errorarray["code"] = "1";
                            $errorarray["title"] = "Missing Parameter str";
                            $errorarray["detail"] = "missing parameter str";
                            array_push($return["errors"], $errorarray);
                        }
                        break;

                    case "faction":
                        if ($final_request["str"]) {
                            $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/factions.json"),true);

                            if (!preg_match("/(Q|P)\d+/i", $final_request["str"])) {
                                $final_request["str"] = preg_replace("/\s/u",".*", $final_request["str"]);
                                $final_request["str"] = preg_replace("/\//","\\/", $final_request["str"]);
                                $tmpType = "label";
                            } else {
                                $tmpType = "id";
                            }

                            $return["data"] = [];

                            foreach ($dump as $k => $v) {
                                if (
                                    (preg_match("/" . $final_request["str"] . "/i", $v[$tmpType]))
                                    || (
                                        (($tmpType == "label") && (gettype($v["labelAlternative"]) == "string"))
                                        && (preg_match("/" . $final_request["str"] . "/i", $v["labelAlternative"]))
                                    )
                                ) {
                                    $return["meta"]["requestStatus"] = "success";
                                    $return["data"][] = $v;
                                }
                            }

                            if (count($return["data"]) > 0) {
                                return $return;
                            } else {
                                $return["meta"]["requestStatus"] = "error";
                                $return["errors"] = array();
                                $errorarray["status"] = "404";
                                $errorarray["code"] = "1";
                                $errorarray["title"] = "No results";
                                $errorarray["detail"] = "Faction not found in dump";
                                array_push($return["errors"], $errorarray);
                            }
                        } else {
                            $return["meta"]["requestStatus"] = "error";
                            $return["errors"] = array();
                            $errorarray["status"] = "503";
                            $errorarray["code"] = "1";
                            $errorarray["title"] = "Missing Parameter str";
                            $errorarray["detail"] = "missing parameter str";
                            array_push($return["errors"], $errorarray);
                        }
                        break;
                }
                break;

            default:
                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Missing request parameter";
                $errorarray["detail"] = "Required parameter (action) of the request is missing";
                array_push($return["errors"], $errorarray);
                break;
        }

    }

    return $return;

}

?>