<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/export/prepare.php");
require_once (__DIR__."/../../../modules/export/akomantoso.php");
require_once (__DIR__."/../../../modules/export/parlamint.php");

/**
 * HTTP surface for the XML exchange-format exports.
 *
 * Two formats are served from the existing media/session URIs via content
 * negotiation, mirroring the IIIF module:
 *  - Akoma Ntoso 1.0 (OASIS LegalDocML) <debate> documents
 *      ?format=akn (alias: akomantoso)   /  Accept: application/akn+xml
 *  - TEI following the ParlaMint conventions (Parla-CLARIN-aligned)
 *      ?format=parlamint (alias: tei)    /  Accept: application/tei+xml
 *
 * Optional ?type= / &lang= select among multiple transcripts (same semantics
 * as the WebVTT endpoint, via transcriptResolve()).
 *
 * What is left here is the negotiation, the dispatch and the response writing.
 * The data shaping lives in modules/export/prepare.php and the document
 * generators in modules/export/akomantoso.php / parlamint.php.
 */

// ---------------------------------------------------------------------------
// Request detection / dispatch
// ---------------------------------------------------------------------------

/**
 * Which export format the request asks for: "akn", "tei" or null.
 * An explicit ?format= param wins; otherwise the Accept header decides.
 */
function exportRequestedFormat(array $req): ?string {
    $format = strtolower(trim((string) ($req['format'] ?? '')));
    if ($format === 'akn' || $format === 'akomantoso') {
        return 'akn';
    }
    if ($format === 'parlamint' || $format === 'tei') {
        return 'tei';
    }
    if ($format !== '') {
        return null; // some other format (e.g. iiif) — not ours
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/akn+xml') !== false) {
        return 'akn';
    }
    if (stripos($accept, 'application/tei+xml') !== false) {
        return 'tei';
    }
    return null;
}

/**
 * If the request targets a resource we can express in the requested format,
 * serve the XML directly and exit. Otherwise return, so the caller falls back
 * to normal OPTV routing (which also produces the correct error responses for
 * missing/non-public items).
 */
function handleExportRequest(array $req): void {
    $format = exportRequestedFormat($req);
    if ($format === null || ($req['action'] ?? '') !== 'getItem') {
        return;
    }
    $id = $req['id'] ?? '';
    if ($id === '') {
        return;
    }
    $type = isset($req['type']) && $req['type'] !== '' ? (string) $req['type'] : null;
    $lang = isset($req['lang']) && $req['lang'] !== '' ? (string) $req['lang'] : null;

    switch ($req['itemType'] ?? '') {
        case 'media':
            $xml = ($format === 'akn')
                ? aknGenerateMedia($id, $type, $lang)
                : parlamintGenerateMedia($id, $type, $lang);
            break;
        case 'session':
            $xml = ($format === 'akn')
                ? aknGenerateSession($id, $type, $lang)
                : parlamintGenerateSession($id, $type, $lang);
            break;
        default:
            return;
    }

    if ($xml === null) {
        return; // fall through to normal routing
    }
    exportServeXML($xml, $format, $id);
}

/**
 * Send an XML document with the right headers and terminate (bypasses the
 * JSON encoder in api/v1/index.php, like transcriptServeVTT()).
 *
 * Browsers don't know the registered akn+xml/tei+xml media types and would
 * offer a download, so those are only sent when the client explicitly asked
 * for them via Accept; everyone else (browser address bar, plain curl) gets
 * application/xml, which renders inline as an XML tree.
 */
function exportServeXML(string $xml, string $format, string $id): void {
    // Discard any buffered output so the response starts with the XML declaration.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $specificType = ($format === 'akn') ? 'application/akn+xml' : 'application/tei+xml';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = (stripos($accept, $specificType) !== false) ? $specificType : 'application/xml';
    $suffix = ($format === 'akn') ? '.akn.xml' : '.tei.xml';
    header('Content-Type: ' . $contentType . '; charset=utf-8');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $id) . $suffix . '"');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *');
    echo $xml;
    exit;
}
