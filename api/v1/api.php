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

    if ((!$final_request["action"]) || (!$final_request["itemType"])) {
        return createApiResponse(
            createApiErrorMissingParameter("action"),
            $config
        );
    }

    switch ($final_request["action"]) {

        // =============================================
        // Public API endpoints (no authentication required)
        // =============================================

        case "getItem":
            switch ($final_request["itemType"]) {
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaGetByID($final_request["id"], $db, $dbp);
                    return createApiResponse($item, $config);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodGetByID($final_request["id"]);
                    return createApiResponse($item, $config);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type"),
                        $config
                    );
            }
            break;

        case "search":
            switch ($final_request["itemType"]) {
                case "people":
                    require_once (__DIR__."/modules/person.php");
                    $item = personSearch($final_request);
                    return createApiResponse($item, $config);
                case "organisations":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationSearch($final_request);
                    return createApiResponse($item, $config);
                case "documents":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentSearch($final_request);
                    return createApiResponse($item, $config);
                case "terms":
                    require_once (__DIR__."/modules/term.php");
                    $item = termSearch($final_request);
                    return createApiResponse($item, $config);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaSearch($final_request);
                    return createApiResponse($item, $config);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type"),
                        $config
                    );
            }
            break;

        case "autocomplete":
            include_once(__DIR__."/modules/autocomplete.php");
            switch ($final_request["itemType"]) {
                case "text": 
                    $item = fulltextAutocomplete($final_request["q"]);
                    return createApiResponse(
                        $item,
                        $config,
                        ["links" => ["self" => htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"])]]
                    );
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type"),
                        $config
                    );
            }
            break;

        case "statistics":
            include_once(__DIR__."/modules/statistics.php");
            switch ($final_request["itemType"]) {
                case "general": 
                    $item = statisticsGetGeneral($final_request);
                    return createApiResponse($item, $config);
                case "entity":
                    $item = statisticsGetEntity($final_request);
                    return createApiResponse($item, $config);
                case "terms":
                    $item = statisticsGetTerms($final_request);
                    return createApiResponse($item, $config);
                case "compare-terms":
                    $item = statisticsCompareTerms($final_request);
                    return createApiResponse($item, $config);
                case "network":
                    $item = statisticsGetNetwork($final_request);
                    return createApiResponse($item, $config);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("type"),
                        $config
                    );
            }
            break;

        case "user":
            require_once (__DIR__."/modules/user.php");
            switch ($final_request["itemType"]) {
                case "login":
                    $result = userLogin($final_request);
                    return createApiResponse($result, $config);
                case "register":
                    $result = userRegister($final_request);
                    return createApiResponse($result, $config);
                case "logout":
                    $result = userLogout();
                    return createApiResponse($result, $config);
                case "password-reset":
                    $result = userPasswordReset($final_request);
                    return createApiResponse($result, $config);
                case "password-reset-request":
                    $result = userPasswordResetRequest($final_request);
                    return createApiResponse($result, $config);
                case "confirm-registration":
                    $result = userConfirmRegistration($final_request);
                    return createApiResponse($result, $config);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("action"),
                        $config
                    );
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
                    return createApiResponse($item, $config);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type"),
                        $config
                    );
            }
            break;

        case "changeItem":
            switch ($final_request["itemType"]) {
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationChange($final_request);
                    return createApiResponse($item, $config);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentChange($final_request);
                    return createApiResponse($item, $config);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termChange($final_request);
                    return createApiResponse($item, $config);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personChange($final_request);
                    return createApiResponse($item, $config);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaChange($final_request);
                    return createApiResponse($item, $config);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionChange($final_request);
                    return createApiResponse($item, $config);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemChange($final_request);
                    return createApiResponse($item, $config);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodChange($final_request);
                    return createApiResponse($item, $config);
                case "user":
                    require_once (__DIR__."/modules/user.php");
                    $item = userChange($final_request);
                    return createApiResponse($item, $config);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type"),
                        $config
                    );
            }
            break;

        case "getItemsFromDB":
            if (empty($final_request["itemType"])) {
                return createApiResponse(
                    createApiErrorMissingParameter("itemType"),
                    $config
                );
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
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType"),
                        $config
                    );
            }

            if ($result) {
                $response = [
                    "meta" => ["requestStatus" => "success"],
                    "data" => $final_request["id"] !== "all" ? ($result["data"][0] ?? null) : $result["data"]
                ];
                if ($final_request["id"] === "all") {
                    $response["total"] = $result["total"];
                }
                return createApiResponse($response, $config);
            } else {
                return createApiResponse(
                    createApiErrorDatabaseError(),
                    $config
                );
            }
            break;

        case "wikidataService":
            if (empty($final_request["str"])) {
                return createApiResponse(
                    createApiErrorMissingParameter("str"),
                    $config
                );
            }

            $response = ["data" => []];
            switch ($final_request["itemType"]) {
                case "person":
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
                                $response["data"][] = $v;
                            }
                        }
                    }
                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response, $config);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Person"),
                            $config
                        );
                    }
                    break;

                case "party":
                    $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/parties.json"),true);

                    if (!preg_match("/(Q|P)\d+/i", $final_request["str"])) {
                        $final_request["str"] = preg_replace("/\s/u",".*", $final_request["str"]);
                        $final_request["str"] = preg_replace("/\//","\\/", $final_request["str"]);
                        $tmpType = "label";
                    } else {
                        $tmpType = "id";
                    }

                    foreach ($dump as $k => $v) {
                        if ((preg_match("/" . $final_request["str"] . "/i", $v[$tmpType])) || ((($tmpType == "label") && (gettype($v["labelAlternative"]) == "string")) && (preg_match("/" . $final_request["str"] . "/i", $v["labelAlternative"])))) {
                            $response["data"][] = $v;
                        }
                    }

                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response, $config);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Party"),
                            $config
                        );
                    }
                    break;

                case "faction":
                    $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/factions.json"),true);

                    if (!preg_match("/(Q|P)\d+/i", $final_request["str"])) {
                        $final_request["str"] = preg_replace("/\s/u",".*", $final_request["str"]);
                        $final_request["str"] = preg_replace("/\//","\\/", $final_request["str"]);
                        $tmpType = "label";
                    } else {
                        $tmpType = "id";
                    }

                    foreach ($dump as $k => $v) {
                        if (
                            (preg_match("/" . $final_request["str"] . "/i", $v[$tmpType]))
                            || (
                                (($tmpType == "label") && (gettype($v["labelAlternative"]) == "string"))
                                && (preg_match("/" . $final_request["str"] . "/i", $v["labelAlternative"]))
                            )
                        ) {
                            $response["data"][] = $v;
                        }
                    }

                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response, $config);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Faction"),
                            $config
                        );
                    }
                    break;

                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("type"),
                        $config
                    );
            }
            break;

        default:
            return createApiResponse(
                createApiErrorMissingParameter("action"),
                $config
            );
    }
}

?>