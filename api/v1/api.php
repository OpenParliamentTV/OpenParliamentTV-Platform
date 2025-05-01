<?php
error_reporting(0);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once (__DIR__."/../../config.php");
require_once ("config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");

function apiV1($request = false, $db = false, $dbp = false) {

    global $config;

    $return["meta"]["api"]["version"] = 1;
    $return["meta"]["api"]["documentation"] = $config["dir"]["root"]."/api";
    $return["meta"]["api"]["license"]["label"] = "ODC Open Database License (ODbL) v1.0";
    $return["meta"]["api"]["license"]["link"] = "https://opendatacommons.org/licenses/odbl/1-0/";
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    if ((!$request["action"]) || (!$request["itemType"])) {

        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameters of the request are missing";
        array_push($return["errors"], $errorarray);
        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"]);

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
                        $item = mediaGetByID($request["id"], $db, $dbp);

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
                        $errorarray["detail"] = "Required parameter (type) of the request is missing";
                        array_push($return["errors"], $errorarray);

                        break;
                }
                break;

            case "search":

                switch ($request["itemType"]) {

                    case "people":

                        require_once (__DIR__."/modules/person.php");
                        $item = personSearch($request);

                        if ($item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        break;

                    case "organisations":

                        require_once (__DIR__."/modules/organisation.php");
                        $item = organisationSearch($request);

                        if ($item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        break;

                    case "documents":

                        require_once (__DIR__."/modules/document.php");
                        $item = documentSearch($request);

                        if ($item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        break;

                    case "terms":

                        require_once (__DIR__."/modules/term.php");
                        $item = termSearch($request);

                        if ($item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        break;

                    case "media":

                        require_once (__DIR__."/modules/media.php");
                        $item = mediaSearch($request);

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
                        $errorarray["detail"] = "Required parameter of the request is missing";
                        array_push($return["errors"], $errorarray);
                        $return["links"]["self"] = htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"]);

                        break;
                }

                if ($item) {
                    $return = array_replace_recursive($return, $item);
                }

                break;
            
            case "wikidataService":
                $return["data"] = array();
                switch ($request["itemType"]) {

                    case "person":

                        if ($request["str"]) {

                            foreach ($config["parliament"] as $p=>$v) {
                                if (file_exists($v["cache"]["wp"]."/people.json")) {
                                    $dump[$p] = json_decode(file_get_contents($v["cache"]["wp"]."/people.json"),true);
                                }

                            }

                            if (!preg_match("/(Q|P)\d+/i", $request["str"])) {

                                $request["str"] = preg_replace("/\s/u",".*", $request["str"]);
                                //$request["str"] = preg_replace("/\xa0/",".*", $request["str"]);

                                $tmpType = "label";

                            } else {

                                $tmpType = "id";

                            }

                            foreach ($dump as $p=>$d) {

                                foreach ($d as $k => $v) {
                                    $success = false;

                                    if (preg_match("/" . convertAccentsAndSpecialToNormal($request["str"]) . "/ui", convertAccentsAndSpecialToNormal($v[$tmpType]))) {
                                        $success = true;
                                    } else if (isset($v["altLabel"])) {
                                        if (is_string($v["altLabel"])) {
                                            if (preg_match("/" . convertAccentsAndSpecialToNormal($request["str"]) . "/ui", convertAccentsAndSpecialToNormal($v["altLabel"]))) {
                                                $success = true;
                                            }
                                        } else if (is_array($v["altLabel"])) {
                                            foreach ($v["altLabel"] as $altLabel) {
                                                if (preg_match("/" . convertAccentsAndSpecialToNormal($request["str"]) . "/ui", convertAccentsAndSpecialToNormal($altLabel))) {
                                                    $success = true;
                                                    break;
                                                }
                                            }
                                        }

                                    }

                                    if ($success) {

                                        $return["meta"]["requestStatus"] = "success";

                                        //FIX URL and Arrays in Party and Faction
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
                                // No Result found.
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

                        if ($request["str"]) {

                            $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/parties.json"),true);
                            /*
                            foreach ($config["parliament"] as $p=>$v) {
                                $dump[$p] = json_decode(file_get_contents($v["cache"]["wp"]["parties"]),true);
                            }
                            */

                            if (!preg_match("/(Q|P)\d+/i", $request["str"])) {

                                $request["str"] = preg_replace("/\s/u",".*", $request["str"]);
                                //$request["str"] = preg_replace("/\xa0/",".*", $request["str"]);
                                $request["str"] = preg_replace("/\//","\\/", $request["str"]);

                                $tmpType = "label";

                            } else {

                                $tmpType = "id";

                            }

                            $return["data"] = [];

                            //foreach ($dump as $p=>$d) {

                                foreach ($dump as $k => $v) {


                                    if ((preg_match("/" . $request["str"] . "/i", $v[$tmpType])) || ((($tmpType == "label") && (gettype($v["labelAlternative"]) == "string")) && (preg_match("/" . $request["str"] . "/i", $v["labelAlternative"])))) {

                                        $return["meta"]["requestStatus"] = "success";

                                        $return["data"][] = $v;


                                    }

                                }

                            //}

                            if (count($return["data"]) > 0) {
                                return $return;
                            } else {
                                // No Result found.

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

                        if ($request["str"]) {

                            $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/factions.json"),true);
                            /*foreach ($config["parliament"] as $p=>$v) {
                                $dump[$p] = json_decode(file_get_contents($v["cache"]["wp"]["factions"]),true);
                            }*/

                            if (!preg_match("/(Q|P)\d+/i", $request["str"])) {

                                $request["str"] = preg_replace("/\s/u",".*", $request["str"]);
                                $request["str"] = preg_replace("/\//","\\/", $request["str"]);


                                $tmpType = "label";

                            } else {

                                $tmpType = "id";

                            }

                            $return["data"] = [];

                            //foreach ($dump as $p=>$d) {

                                foreach ($dump as $k => $v) {

                                    if (
                                        (preg_match("/" . $request["str"] . "/i", $v[$tmpType]))
                                        || (
                                            (($tmpType == "label") && (gettype($v["labelAlternative"]) == "string"))
                                            && (preg_match("/" . $request["str"] . "/i", $v["labelAlternative"]))
                                        )
                                    ) {

                                        $return["meta"]["requestStatus"] = "success";

                                        $return["data"][] = $v;


                                    }

                                }
                            //}

                            if (count($return["data"]) > 0) {
                                return $return;
                            } else {
                                // No Result found.

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

            case "addMedia":

                include_once(__DIR__."/modules/media.php");
                $item = mediaAdd($request);

                if ($item["meta"]["requestStatus"] == "success") {
                    unset($return["errors"]);
                } else {
                    unset($return["data"]);
                }

                $return = array_replace_recursive($return, $item);

                break;

            case "autocomplete":

                include_once(__DIR__."/modules/autocomplete.php");
                
                switch ($request["itemType"]) {

                    case "text": 
                        
                        $item = fulltextAutocomplete($request["q"]);

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

            case "getOverview":
                
                if (empty($request["itemType"])) {

                    $return["meta"]["requestStatus"] = "error";
                    $return["errors"] = array();
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Missing parameter";
                    $errorarray["detail"] = "Parameter 'itemType' is required";
                    array_push($return["errors"], $errorarray);
                    return $return;

                }

                if (empty($request["limit"])) {
                    $request["limit"] = 10;
                }

                if (empty($request["offset"])) {
                    $request["offset"] = 0;
                }

                if (empty($request["sort"])) {
                    $request["sort"] = false;
                }

                if (empty($request["order"])) {
                    $request["order"] = false;
                }

                if (empty($request["search"])) {
                    $request["search"] = false;
                }

                // Set getCount to true by default for the getOverview action
                if (!isset($request["getCount"])) {
                    $request["getCount"] = true;
                }

                if (empty($request["id"])) {
                    $request["id"] = "all";
                }

                switch ($request["itemType"]) {
                    case "person":
                        
                        require_once (__DIR__."/modules/person.php");
                        $result = personGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"]);
                        break;

                    case "organisation":
                        
                        require_once (__DIR__."/modules/organisation.php");
                        $result = organisationGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"]);
                        break;

                    case "document":
                        
                        require_once (__DIR__."/modules/document.php");
                        $result = documentGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"]);
                        break;

                    case "term":
                        
                        require_once (__DIR__."/modules/term.php");
                        $result = termGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"]);
                        break;

                    case "electoralPeriod":
                        
                        require_once (__DIR__."/modules/electoralPeriod.php");
                        $result = electoralPeriodGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"]);
                        break;

                    case "session":
                        
                        require_once (__DIR__."/modules/session.php");
                        $result = sessionGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"], false, $request["electoralPeriodID"]);
                        break;

                    case "agendaItem":
                        
                        require_once (__DIR__."/modules/agendaItem.php");
                        $result = agendaItemGetOverview($request["id"], $request["limit"], $request["offset"], $request["search"], $request["sort"], $request["order"], $request["getCount"], false, $request["electoralPeriodID"], $request["sessionID"]);
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
                    
                    // Check if the result has a total count
                    if (isset($result["total"]) && isset($result["rows"])) {
                        $return["total"] = $result["total"];
                        $return["data"] = $result["rows"];
                    } else {
                        $return["data"] = $result;
                    }

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

            case "statistics":

                include_once(__DIR__."/modules/statistics.php");
                
                switch ($request["itemType"]) {

                    case "general": 
                        
                        $item = statisticsGetGeneral($request);
                        
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }
                        $return = array_replace_recursive($return, $item);

                        break;

                    case "entity":
                        
                        $item = statisticsGetEntity($request);
                        
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        $return = array_replace_recursive($return, $item);
                        
                        break;

                    case "terms":
                        
                        $item = statisticsGetTerms($request);
                        
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        $return = array_replace_recursive($return, $item);
                        
                        break;

                    case "compare-terms":
                        
                        $item = statisticsCompareTerms($request);
                        
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        $return = array_replace_recursive($return, $item);
                        
                        break;

                    case "network":
                        
                        $item = statisticsGetNetwork($request);
                        
                        if (isset($item["meta"]["requestStatus"]) && $item["meta"]["requestStatus"] == "success") {
                            unset($return["errors"]);
                        } else {
                            unset($return["data"]);
                        }

                        $return = array_replace_recursive($return, $item);
                        
                        break;

                    default:
                        
                        $errorarray["status"] = "422";
                        $errorarray["code"] = "1";
                        $errorarray["title"] = "Invalid statistics type";
                        $errorarray["detail"] = "The requested statistics type is not supported";
                        array_push($return["errors"], $errorarray);
                        
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