<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/iiif/manifest.php");
require_once (__DIR__."/../../../modules/iiif/collection.php");

/**
 * HTTP surface for IIIF Presentation API 3.0.
 *
 * Manifests are served from the same URIs as the existing OPTV JSON API via
 * content negotiation, plus dedicated Collection endpoints. Everything that
 * builds a Manifest or Collection lives in modules/iiif/; what is left here is
 * the negotiation and the dispatch, which only make sense over HTTP.
 *
 * A null from the generators means "cannot express this as IIIF" — the caller
 * falls back to normal OPTV routing, which produces the correct error response
 * for a missing or non-public item.
 */

// ---------------------------------------------------------------------------
// Request detection / dispatch
// ---------------------------------------------------------------------------

/**
 * Whether the current request should be answered with IIIF instead of OPTV JSON.
 */
function isIIIFRequest(): bool {
    if (isset($_GET['format']) && strtolower($_GET['format']) === 'iiif') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ($accept === '') {
        return false;
    }
    // Explicit IIIF profile, or any ld+json (no more specific OPTV profile exists).
    if (stripos($accept, 'iiif.io/api/presentation') !== false) {
        return true;
    }
    if (stripos($accept, 'application/ld+json') !== false) {
        return true;
    }
    return false;
}

/**
 * If the request targets a resource we can express as IIIF, return the Manifest
 * array; otherwise null (caller falls back to normal OPTV routing, which also
 * produces the correct error responses for missing/non-public items).
 */
function handleIIIFRequest(array $req): ?array {
    if (($req['action'] ?? '') !== 'getItem') {
        return null;
    }
    $id = $req['id'] ?? '';
    if ($id === '') {
        return null;
    }
    switch ($req['itemType'] ?? '') {
        case 'media':
            return iiifGenerateMediaManifest($id);
        case 'session':
            return iiifGenerateSessionManifest($id);
        default:
            return null;
    }
}
