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
    $api_request = array_merge(
        json_decode(file_get_contents('php://input'), true) ?: [],
        $_GET,
        $request_param ?: []
    );

    if ((!$api_request["action"]) || (!$api_request["itemType"])) {
        return createApiResponse(
            createApiErrorMissingParameter("action")
        );
    }

    switch ($api_request["action"]) {

        // =============================================
        // Public API endpoints (no authentication required)
        // =============================================

        case "getItem":
            switch ($api_request["itemType"]) {
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaGetByID($api_request["id"], $db, $dbp);
                    return createApiResponse($item);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemGetByID($api_request["id"]);
                    return createApiResponse($item);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodGetByID($api_request["id"]);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "search":
            switch ($api_request["itemType"]) {
                case "people":
                    require_once (__DIR__."/modules/person.php");
                    $item = personSearch($api_request);
                    return createApiResponse($item);
                case "organisations":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationSearch($api_request);
                    return createApiResponse($item);
                case "documents":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentSearch($api_request);
                    return createApiResponse($item);
                case "terms":
                    require_once (__DIR__."/modules/term.php");
                    $item = termSearch($api_request);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaSearch($api_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "autocomplete":
            include_once(__DIR__."/modules/autocomplete.php");
            switch ($api_request["itemType"]) {
                case "text": 
                    $item = fulltextAutocomplete($api_request["q"]);
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
            switch ($api_request["itemType"]) {
                case "general": 
                    $item = statisticsGetGeneral($api_request);
                    return createApiResponse($item);
                case "entity":
                    $item = statisticsGetEntity($api_request);
                    return createApiResponse($item);
                case "terms":
                    $item = statisticsGetTerms($api_request);
                    return createApiResponse($item);
                case "compare-terms":
                    $item = statisticsCompareTerms($api_request);
                    return createApiResponse($item);
                case "network":
                    $item = statisticsGetNetwork($api_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("type")
                    );
            }
            break;

        case "user":
            require_once (__DIR__."/modules/user.php");
            switch ($api_request["itemType"]) {
                case "login":
                    $result = userLogin($api_request);
                    return createApiResponse($result);
                case "register":
                    $result = userRegister($api_request);
                    return createApiResponse($result);
                case "logout":
                    $result = userLogout();
                    return createApiResponse($result);
                case "password-reset":
                    $result = userPasswordReset($api_request);
                    return createApiResponse($result);
                case "password-reset-request":
                    $result = userPasswordResetRequest($api_request);
                    return createApiResponse($result);
                case "confirm-registration":
                    $result = userConfirmRegistration($api_request);
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
            switch ($api_request["itemType"]) {
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaAdd($api_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "changeItem":
            switch ($api_request["itemType"]) {
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $item = organisationChange($api_request);
                    return createApiResponse($item);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $item = documentChange($api_request);
                    return createApiResponse($item);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $item = termChange($api_request);
                    return createApiResponse($item);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $item = personChange($api_request);
                    return createApiResponse($item);
                case "media":
                    require_once (__DIR__."/modules/media.php");
                    $item = mediaChange($api_request);
                    return createApiResponse($item);
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $item = sessionChange($api_request);
                    return createApiResponse($item);
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $item = agendaItemChange($api_request);
                    return createApiResponse($item);
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodChange($api_request);
                    return createApiResponse($item);
                case "user":
                    require_once (__DIR__."/modules/user.php");
                    $item = userChange($api_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorMissingParameter("type")
                    );
            }
            break;

        case "getItemsFromDB":
            if (empty($api_request["itemType"])) {
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
            $api_request = array_merge($defaults, $api_request);

            switch ($api_request["itemType"]) {
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $result = personGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $result = organisationGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $result = documentGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $result = termGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                case "electoralPeriod":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $result = electoralPeriodGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                case "session":
                    require_once (__DIR__."/modules/session.php");
                    $result = sessionGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], false, $api_request["electoralPeriodID"]);
                    break;
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $result = agendaItemGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], false, $api_request["electoralPeriodID"], $api_request["sessionID"]);
                    break;
                case "user":
                    require_once (__DIR__."/modules/user.php");
                    $result = userGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"]);
                    break;
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
                    );
            }

            if ($result) {
                return createApiResponse(
                    createApiSuccessResponse(
                        $api_request["id"] !== "all" ? ($result["data"][0] ?? null) : $result["data"],
                        ["requestStatus" => "success"],
                        null,
                        null,
                        $api_request["id"] === "all" ? ["total" => $result["total"]] : null
                    )
                );
            } else {
                return createApiResponse(
                    createApiErrorDatabaseError()
                );
            }
            break;

        case "index":
            require_once (__DIR__."/modules/searchIndex.php");
            switch ($api_request["itemType"]) {
                case "update":
                    $result = searchIndexUpdate($api_request);
                    return createApiResponse($result);
                case "delete":
                    $result = searchIndexDelete($api_request);
                    return createApiResponse($result);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        default:
            return createApiResponse(
                createApiErrorMissingParameter("action")
            );
    }
}

?>