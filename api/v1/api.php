<?php

require_once (__DIR__."/../../config.php");
require_once (__DIR__."/../../modules/utilities/functions.php");
require_once (__DIR__."/../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/utilities.php");
require_once (__DIR__."/../../modules/utilities/security.php");
applySecurityHeaders();


/**
 * @param array|false $request_param The request. Pass an array for an internal
 *        call: it is used verbatim and the superglobals are ignored. Pass
 *        nothing (false) only from the external HTTP entry point, which is
 *        where the $_GET/$_POST/php://input merge belongs.
 */
function apiV1($request_param = false, $db = false, $dbp = false) {
    global $config;

    if ($request_param === false) {
        // External HTTP entry point (api/v1/index.php). php://input carries
        // JSON request bodies, which $_REQUEST does not see.
        $api_request = array_merge(
            $_GET,                                                     // Lowest precedence
            $_POST,                                                    // Overwrites GET if keys clash
            json_decode(file_get_contents('php://input'), true) ?: []  // Overwrites POST if keys clash
        );
    } else {
        // Internal call: exactly what the caller asked for, nothing else.
        //
        // These calls run inside an ordinary page or API request, so merging the
        // superglobals in used to hand the target action every query parameter
        // the visitor happened to send. Explicit params won on a key clash, but
        // any parameter the caller did NOT set leaked through — and a search is
        // steered by dozens of optional filters. Two live consequences, both
        // fixed by this: ?q=/&dateTo= on /api/v1/status silently filtered the
        // internal searches behind lastUpdated/lastSpeechDate, and /search?page=3
        // asked for page 3 of the 8-row faction list, emptying the filter bar.
        $api_request = $request_param;
    }

    return apiV1Handle($api_request, $db, $dbp);
}

/**
 * Everything between "the request is fully resolved" and the registry lookup:
 * the two required-parameter checks and the content negotiation.
 *
 * Split out of apiV1() so the HTTP entry point can share it. api/v1/index.php
 * builds its request from the route parameters *plus* the superglobals, which
 * apiV1() cannot do for it — apiV1() with an array means an internal call, and
 * those must never see the superglobals (5a).
 *
 * @return array|null The envelope, or null for handlers that exit.
 */
function apiV1Handle(array $api_request, $db = false, $dbp = false)
{
    if (empty($api_request["action"])) {
        return apiResponse(
            apiErrorMissingParameter("action")
        );
    }

    if (empty($api_request["itemType"])) {
        return apiResponse(
            apiErrorMissingParameter("itemType")
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

    return apiV1Dispatch($api_request, $db, $dbp);
}

/**
 * Registry lookup, replacing the switch that used to live in apiV1().
 *
 * Shared with the HTTP routes (routes/api.php), which is the point: the action
 * table and its public/admin metadata now have one home instead of being split
 * between this switch and a whitelist in auth.php.
 *
 * @param array $api_request Fully resolved request (action + itemType present).
 * @return array|null The envelope, or null for the handlers that exit.
 */
function apiV1Dispatch(array $api_request, $db = false, $dbp = false)
{
    require_once(__DIR__ . "/registry.php");

    $registry = apiRegistry();
    $action = $api_request["action"];

    if (!isset($registry[$action])) {
        return apiResponse(
            apiErrorInvalidParameter("action")
        );
    }

    $spec = $registry[$action];

    if (isset($spec["before"])) {
        $api_request = $spec["before"]($api_request);
    }

    // An action with 'any' ignores itemType as a dispatch key (entityDump uses
    // it as the entity selector instead).
    if (isset($spec["any"])) {
        $entry = $spec["any"];
    } elseif (isset($spec["itemTypes"][$api_request["itemType"]])) {
        $entry = $spec["itemTypes"][$api_request["itemType"]];
    } else {
        return apiResponse(
            isset($spec["invalidItemType"])
                ? apiErrorInvalidParameter("itemType", $spec["invalidItemType"])
                : apiErrorInvalidParameter("itemType")
        );
    }

    foreach ([$spec["file"] ?? null, $entry["file"] ?? null] as $file) {
        if ($file !== null) {
            require_once(__DIR__ . "/" . $file);
        }
    }

    return $entry["fn"]($api_request, $db, $dbp);
}
