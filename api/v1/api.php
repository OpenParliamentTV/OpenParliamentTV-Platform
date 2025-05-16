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

    // Merge all request sources with proper precedence
    $final_request = array_merge(
        json_decode(file_get_contents('php://input'), true) ?: [],
        $_GET,
        $request_param ?: []
    );

    if ((!$final_request["action"]) || (!$final_request["itemType"])) {
        return createApiResponse(
            createApiErrorMissingParameter("action")
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
                    return createApiResponse($item);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentGetByID($final_request["id"]);
                    return createApiResponse($item);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termGetByID($final_request["id"]);
                    return createApiResponse($item);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personGetByID($final_request["id"]);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaGetByID($final_request["id"], $db, $dbp);
                    return createApiResponse($item);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionGetByID($final_request["id"]);
                    return createApiResponse($item);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemGetByID($final_request["id"]);
                    return createApiResponse($item);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodGetByID($final_request["id"]);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "search":
            switch ($final_request["itemType"]) {
                case "people":
                    require_once (__DIR__."/modules/person.php");
                    $item = personSearch($final_request);
                    return createApiResponse($item);
                case "organisations":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationSearch($final_request);
                    return createApiResponse($item);
                case "documents":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentSearch($final_request);
                    return createApiResponse($item);
                case "terms":
                    require_once (__DIR__."/modules/term.php");
                    $item = termSearch($final_request);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaSearch($final_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "autocomplete":
            include_once(__DIR__."/modules/autocomplete.php");
            switch ($final_request["itemType"]) {
                case "text": 
                    $item = fulltextAutocomplete($final_request["q"]);
                    return createApiResponse(
                        createApiSuccessResponse(
                            $item["data"],
                            $item["meta"],
                            ["self" => htmlspecialchars($config["dir"]["root"]."/".$_SERVER["REQUEST_URI"])]
                        )
                    );
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "statistics":
            include_once(__DIR__."/modules/statistics.php");
            switch ($final_request["itemType"]) {
                case "general": 
                    $item = statisticsGetGeneral($final_request);
                    return createApiResponse($item);
                case "entity":
                    $item = statisticsGetEntity($final_request);
                    return createApiResponse($item);
                case "terms":
                    $item = statisticsGetTerms($final_request);
                    return createApiResponse($item);
                case "compare-terms":
                    $item = statisticsCompareTerms($final_request);
                    return createApiResponse($item);
                case "network":
                    $item = statisticsGetNetwork($final_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("type")
                    );
            }
            break;

        case "user":
            require_once (__DIR__."/modules/user.php");
            switch ($final_request["itemType"]) {
                case "login":
                    $result = userLogin($final_request);
                    return createApiResponse($result);
                case "register":
                    $result = userRegister($final_request);
                    return createApiResponse($result);
                case "logout":
                    $result = userLogout();
                    return createApiResponse($result);
                case "password-reset":
                    $result = userPasswordReset($final_request);
                    return createApiResponse($result);
                case "password-reset-request":
                    $result = userPasswordResetRequest($final_request);
                    return createApiResponse($result);
                case "confirm-registration":
                    $result = userConfirmRegistration($final_request);
                    return createApiResponse($result);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("action")
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
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "changeItem":
            switch ($final_request["itemType"]) {
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationChange($final_request);
                    return createApiResponse($item);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentChange($final_request);
                    return createApiResponse($item);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termChange($final_request);
                    return createApiResponse($item);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personChange($final_request);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaChange($final_request);
                    return createApiResponse($item);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionChange($final_request);
                    return createApiResponse($item);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemChange($final_request);
                    return createApiResponse($item);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodChange($final_request);
                    return createApiResponse($item);
                case "user":
                    require_once (__DIR__."/modules/user.php");
                    $item = userChange($final_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "getItemsFromDB":
            if (empty($final_request["itemType"])) {
                return createApiResponse(
                    createApiErrorMissingParameter("itemType")
                );
            }

            // Set default values for optional parameters
            $defaults = [
                "limit" => 10,
                "offset" => 0,
                "sort" => false,
                "order" => false,
                "search" => false,
                "id" => "all"
            ];
            $final_request = array_merge($defaults, $final_request);

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
                        createApiErrorInvalidParameter("itemType")
                    );
            }

            if ($result) {
                return createApiResponse(
                    createApiSuccessResponse(
                        $final_request["id"] !== "all" ? ($result["data"][0] ?? null) : $result["data"],
                        ["requestStatus" => "success"],
                        null,
                        null,
                        $final_request["id"] === "all" ? ["total" => $result["total"]] : null
                    )
                );
            } else {
                return createApiResponse(
                    createApiErrorDatabaseError()
                );
            }
            break;

        case "wikidataService":
            if (empty($final_request["str"])) {
                return createApiResponse(
                    createApiErrorMissingParameter("str")
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
                            if (matchesWikidataItem($v, $final_request["str"], $tmpType)) {
                                $v = processWikidataPartyAndFaction($v);
                                $v["parliament"] = $p;
                                $response["data"][] = $v;
                            }
                        }
                    }
                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Person")
                        );
                    }
                    break;

                case "party":
                    $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/parties.json"),true);
                    $response["data"] = filterWikidataItems($dump, $final_request["str"]);
                    
                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Party")
                        );
                    }
                    break;

                case "faction":
                    $dump = json_decode(file_get_contents(__DIR__."/../../data/wikidataDumps/factions.json"),true);
                    $response["data"] = filterWikidataItems($dump, $final_request["str"]);
                    
                    if (count($response["data"]) > 0) {
                        $response["meta"]["requestStatus"] = "success";
                        return createApiResponse($response);
                    } else {
                        return createApiResponse(
                            createApiErrorNotFound("Faction")
                        );
                    }
                    break;

                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("type")
                    );
            }
            break;

        default:
            return createApiResponse(
                createApiErrorMissingParameter("action")
            );
    }
}

/**
 * Helper function to check if a Wikidata item matches the search string
 */
function matchesWikidataItem($item, $searchStr, $type) {
    if (preg_match("/" . convertAccentsAndSpecialToNormal($searchStr) . "/ui", convertAccentsAndSpecialToNormal($item[$type]))) {
        return true;
    }
    
    if (isset($item["altLabel"])) {
        if (is_string($item["altLabel"])) {
            return preg_match("/" . convertAccentsAndSpecialToNormal($searchStr) . "/ui", convertAccentsAndSpecialToNormal($item["altLabel"]));
        } else if (is_array($item["altLabel"])) {
            foreach ($item["altLabel"] as $altLabel) {
                if (preg_match("/" . convertAccentsAndSpecialToNormal($searchStr) . "/ui", convertAccentsAndSpecialToNormal($altLabel))) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * Helper function to process party and faction data in Wikidata items
 */
function processWikidataPartyAndFaction($item) {
    // Process party
    if (gettype($item["party"]) == "array") {
        $item["party-original-array"] = $item["party"];
        $item["party"] = $item["party"][0];
    }
    if (preg_match("/www\.wiki/", $item["party"])) {
        $item["party-original-URL"] = $item["party"];
        $tmpArray = explode("/", $item["party"]);
        $item["party"] = array_pop($tmpArray);
        $item["partyLabelAlternative"] = apiV1(["action" => "wikidataService", "itemType" => "party", "str" => $item["party"]])["data"][0]["labelAlternative"];
    }

    // Process faction
    if (gettype($item["faction"]) == "array") {
        $item["faction-original-array"] = $item["faction"];
        $item["faction"] = $item["faction"][0];
    }
    if (preg_match("/www\.wiki/", $item["faction"])) {
        $item["faction-original-URL"] = $item["faction"];
        $tmpArray = explode("/", $item["faction"]);
        $item["faction"] = array_pop($tmpArray);
    }

    return $item;
}

/**
 * Helper function to filter Wikidata items based on search string
 */
function filterWikidataItems($items, $searchStr) {
    if (!preg_match("/(Q|P)\d+/i", $searchStr)) {
        $searchStr = preg_replace("/\s/u",".*", $searchStr);
        $searchStr = preg_replace("/\//","\\/", $searchStr);
        $type = "label";
    } else {
        $type = "id";
    }

    $results = [];
    foreach ($items as $k => $v) {
        if ((preg_match("/" . $searchStr . "/i", $v[$type])) || 
            ((($type == "label") && (gettype($v["labelAlternative"]) == "string")) && 
            (preg_match("/" . $searchStr . "/i", $v["labelAlternative"])))) {
            $results[] = $v;
        }
    }
    return $results;
}

?>