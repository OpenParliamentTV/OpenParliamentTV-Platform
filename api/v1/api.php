<?php

require_once (__DIR__."/../../config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/utilities.php");
require_once (__DIR__."/../../modules/utilities/security.php");
applySecurityHeaders();


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

    // Content negotiation: serve IIIF Manifests from existing media/session URIs.
    require_once (__DIR__."/modules/iiif.php");
    if (isIIIFRequest()) {
        $iiifResult = handleIIIFRequest($api_request);
        if ($iiifResult !== null) {
            header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
            header('Access-Control-Allow-Origin: *'); // IIIF viewers fetch cross-origin
            return $iiifResult;
        }
        // null => fall through to normal routing (produces proper error responses)
    }

    // Content negotiation: Akoma Ntoso / TEI-ParlaMint XML exports from the
    // same media/session URIs (?format=akn|parlamint or Accept header).
    // Serves the XML directly and exits; falls through on unavailable items.
    require_once (__DIR__."/modules/export.php");
    if (exportRequestedFormat($api_request) !== null) {
        handleExportRequest($api_request);
    }


    switch ($api_request["action"]) {

        // =============================================
        // Public API endpoints (no authentication required)
        // =============================================
        // These actions are whitelisted in auth.php for "apiV1" action and are thus public by default

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

        case "transcript":
            require_once (__DIR__."/modules/transcript.php");
            switch ($api_request["itemType"]) {
                case "vtt":
                    // Serves text/vtt directly and exits (bypasses JSON encoder).
                    transcriptServeVTT(
                        $api_request["id"] ?? "",
                        $api_request["type"] ?? null,
                        $api_request["lang"] ?? null
                    );
                    return; // unreachable; transcriptServeVTT exits
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        case "iiif":
            require_once (__DIR__."/modules/iiif.php");
            switch ($api_request["itemType"]) {
                case "collection":
                    $collection = iiifGenerateCollection(
                        $api_request["parliament"] ?? "",
                        $api_request["electoralPeriod"] ?? null
                    );
                    if ($collection === null) {
                        return createApiResponse(
                            createApiErrorInvalidParameter("parliament")
                        );
                    }
                    header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
                    header('Access-Control-Allow-Origin: *');
                    return $collection;
                case "search":
                    $collection = iiifGenerateSearchCollection($api_request);
                    if ($collection === null) {
                        return createApiResponse(
                            createApiErrorNotFound("media")
                        );
                    }
                    header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
                    header('Access-Control-Allow-Origin: *');
                    return $collection;
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
                case "electoralPeriods":
                    require_once (__DIR__."/modules/electoralPeriod.php");
                    $item = electoralPeriodSearch($api_request);
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
                    return createApiResponse($item);
                case "agendaItem": 
                    $item = agendaItemAutocomplete($api_request["q"]);
                    return createApiResponse($item);
                case "entities": 
                    $item = entitiesAutocomplete($api_request["q"]);
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

        case "alert":
            require_once (__DIR__."/modules/alert.php");
            switch ($api_request["itemType"]) {
                case "list":
                    return createApiResponse(alertList($api_request));
                case "get":
                    return createApiResponse(alertGet($api_request));
                case "create":
                    return createApiResponse(alertCreate($api_request));
                case "update":
                    return createApiResponse(alertUpdate($api_request));
                case "delete":
                    return createApiResponse(alertDelete($api_request));
                case "status":
                    return createApiResponse(alertStatus($api_request));
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        case "systemMessage":
            require_once (__DIR__."/modules/systemMessage.php");
            switch ($api_request["itemType"]) {
                case "list":
                    return createApiResponse(systemMessageList($api_request));
                case "create":
                    return createApiResponse(systemMessageCreate($api_request));
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
                    );
            }
            break;

        case "notification":
            require_once (__DIR__."/modules/notification.php");
            switch ($api_request["itemType"]) {
                case "list":
                    return createApiResponse(notificationList($api_request));
                case "unreadCount":
                    return createApiResponse(notificationUnreadCount($api_request));
                case "markRead":
                    return createApiResponse(notificationMarkRead($api_request));
                case "markUnread":
                    return createApiResponse(notificationMarkUnread($api_request));
                case "markAllRead":
                    return createApiResponse(notificationMarkAllRead($api_request));
                case "delete":
                    return createApiResponse(notificationDelete($api_request));
                case "preferences":
                    return createApiResponse(notificationPreferences($api_request));
                case "unsubscribe":
                    return createApiResponse(notificationUnsubscribe($api_request));
                case "runMatch":
                    return createApiResponse(notificationRunMatch($api_request));
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
        // These actions are NOT whitelisted in auth.php, so they require admin authentication.
        
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
                    $result = sessionGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], false, $api_request["electoralPeriodID"] ?? null);
                    break;
                case "agendaItem":
                    require_once (__DIR__."/modules/agendaItem.php");
                    $result = agendaItemGetItemsFromDB($api_request["id"], $api_request["limit"], $api_request["offset"], $api_request["search"], $api_request["sort"], $api_request["order"], false, $api_request["electoralPeriodID"] ?? null, $api_request["sessionID"] ?? null);
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

            // A getItemsFromDB function may return a ready-made API error
            // response (e.g. parliament DB unreachable) — pass it through
            // instead of wrapping it as an empty success.
            if (is_array($result) && (($result["meta"]["requestStatus"] ?? null) === "error")) {
                return createApiResponse($result);
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
                case "status":
                    $result = searchIndexGetStatus($api_request);
                    return createApiResponse($result);
                case "statistics-update":
                    $result = searchIndexTriggerStatisticsUpdate($api_request);
                    return createApiResponse($result);
                case "statistics-status":
                    $result = searchIndexGetStatisticsStatus($api_request);
                    return createApiResponse($result);
                case "optimize":
                    $result = searchIndexOptimize($api_request);
                    return createApiResponse($result);
                case "optimization-status":
                    $result = searchIndexGetOptimizationStatus($api_request);
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
                    $result = importGetCronUpdaterStatus($api_request);
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
        
        case "cleanup":
            switch ($api_request["itemType"]) {
                case "entity-suggestions":
                    require_once (__DIR__."/modules/entitySuggestion.php");
                    $cleanupResponse = entitySuggestionCleanup($db);
                    return createApiResponse($cleanupResponse);
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
                case "word-trends":
                    $item = statisticsGetWordTrends($api_request);
                    return createApiResponse($item);
                case "entity-counts":
                    $item = statisticsGetEntityCounts($api_request);
                    return createApiResponse($item);
                default:
                    return createApiResponse(
                        createApiErrorInvalidParameter("itemType")
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