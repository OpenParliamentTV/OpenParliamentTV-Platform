<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once (__DIR__."/../../config.php");
require_once ("config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../modules/utilities/functions.api.php");

function apiV1($request_param = false, $db = false, $dbp = false) {
    global $config;

    // Merge all request sources with proper precedence
    $api_request = array_merge(
        $_GET,                                                      // Lowest precedence
        $_POST,                                                     // Overwrites GET if keys clash
        json_decode(file_get_contents('php://input'), true) ?: [],   // Overwrites POST if keys clash (usually one or the other is empty)
        $request_param ?: []                                        // Highest precedence, for internal calls
    );

    if (empty($api_request["action"])) {
        return createApiResponse(
            createApiErrorMissingParameter("action")
        );
    }

    if (empty($api_request["itemType"])) {
        return createApiResponse(
            createApiErrorMissingParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        case "status":
            require_once (__DIR__."/modules/status.php");
            switch ($api_request["itemType"]) {
                case "all":
                    $statusInfo = getStatus($api_request);
                    return createApiResponse($statusInfo);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        case "lang":
            global $acceptLang; 
            switch ($api_request["itemType"]) {
                case "set":
                    if (!empty($api_request["lang"]) && isset($acceptLang) && array_key_exists($api_request["lang"], $acceptLang)) {
                        $_SESSION["lang"] = $api_request["lang"];
                        return createApiResponse(
                            createApiSuccessResponse(
                                $api_request["lang"],
                                ["requestStatus" => "success", "message" => "Language has been set to " . $api_request["lang"]]
                            )
                        );
                    } else {
                        return createApiResponse(
                            createApiErrorInvalidParameter("lang", "Invalid language code")
                        );
                    }
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
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
                    $addResponse = mediaAdd($api_request, $db, $dbp);
                    return createApiResponse($addResponse);
                case "conflict":
                    require_once (__DIR__."/modules/conflict.php");
                    $addResponse = conflictAdd($api_request, $db);
                    return createApiResponse($addResponse);
                case "entitySuggestion": 
                    require_once (__DIR__."/modules/entitySuggestion.php");
                    $addResponse = entitySuggestionAdd($api_request, $db);
                    return createApiResponse($addResponse);
                case "person":
                    require_once (__DIR__."/modules/person.php");
                    $addResponse = personAdd($api_request, $db, $dbp);
                    return createApiResponse($addResponse);
                case "organisation":
                    require_once (__DIR__."/modules/organisation.php");
                    $addResponse = organisationAdd($api_request, $db, $dbp);
                    return createApiResponse($addResponse);
                case "document":
                    require_once (__DIR__."/modules/document.php");
                    $addResponse = documentAdd($api_request, $db, $dbp);
                    return createApiResponse($addResponse);
                case "term":
                    require_once (__DIR__."/modules/term.php");
                    $addResponse = termAdd($api_request, $db, $dbp);
                    return createApiResponse($addResponse);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
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
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;
        
        case "deleteItem":
            switch ($api_request["itemType"]) {
                case "entitySuggestion":
                    require_once (__DIR__."/modules/entitySuggestion.php");
                    $deleteResponse = entitySuggestionDelete($api_request["EntitysuggestionID"] ?? null, $db);
                    return createApiResponse($deleteResponse);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType", "Invalid itemType for deleteItem action.")
                    );
            }
            break;

        case "getItemsFromDB":
            
            // Set default values for common optional parameters
            $common_defaults = [
                "limit" => 10,
                "offset" => 0,
                "sort" => false,
                "order" => false,
                "search" => false,
                "id" => "all"
            ];
            $api_request = array_merge($common_defaults, $api_request);

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
                case "conflict": 
                    require_once (__DIR__."/modules/conflict.php");
                    // Handle conflict-specific parameters
                    $includeResolved = $api_request["includeResolved"] ?? false;
                    $getStats = $api_request["getStats"] ?? false;
                    $result = conflictGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], $includeResolved, $getStats
                    );
                    break;
                case "entitySuggestion":
                    require_once (__DIR__."/modules/entitySuggestion.php");
                    $idType = $api_request["idType"] ?? "external"; // Read idType, default to external
                    $result = entitySuggestionGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], $idType);
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
                        $api_request["id"] === "all" ? $result["total"] : null
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
                case "full-update":
                    $result = searchIndexTriggerFullUpdate($api_request);
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

        case "import":
            require_once (__DIR__."/modules/import.php");
            switch ($api_request["itemType"]) {
                case "reimport-sessions":
                    $result = reimportSessions($api_request, $db);
                    return createApiResponse($result);
                case "run":
                    $result = importRunCronUpdater($api_request);
                    return createApiResponse($result);
                case "status":
                    $result = importGetCronUpdaterStatus();
                    return createApiResponse($result);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType", "Invalid itemType for import action.")
                    );
            }
            break;

        case "externalData": 
            require_once (__DIR__."/modules/externalData.php");
            switch ($api_request["itemType"]) {
                case "get-info": 
                    $result = externalDataGetInfo($api_request);
                    return createApiResponse($result);
                case "update-entities": 
                    $result = externalDataUpdateEntities($api_request);
                    return createApiResponse($result);
                case "full-update": 
                    $result = externalDataTriggerFullUpdate($api_request);
                    return createApiResponse($result);
                case "status": 
                    $result = externalDataGetFullUpdateStatus($api_request);
                    return createApiResponse($result);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType", "Invalid itemType for externalData action.")
                    );
            }
            break;

        default:
            return createApiResponse(
                createApiErrorInvalidParameter("action")
            );
    }

    // Fallback for unknown actions or itemTypes handled by createApiErrorInvalidParameter
    if (!isset($return)) {
        $return = createApiErrorInvalidParameter("action or itemType");
    }

    return $return;
}