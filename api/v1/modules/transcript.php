<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/i18n/language.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/entities/media.php");
require_once (__DIR__."/../../../modules/transcript/vtt.php");

/**
 * HTTP surface for `/api/v1/media/{id}/transcript.vtt`.
 *
 * The generation itself (picking a transcript out of a media node and rendering
 * WebVTT) lives in modules/transcript/vtt.php. What is left here is the part
 * that only makes sense over HTTP: resolving the media, choosing status codes,
 * and writing the response.
 */

/**
 * Send a plain-text 404 and terminate. Used for missing transcripts/media.
 */
function transcriptSend404(): void {
    header('Content-Type: text/plain; charset=utf-8', true, 404);
    echo "404 Not Found";
    exit;
}

/**
 * Serve a WebVTT transcript for a media item directly to the client.
 * Sets text/vtt headers, echoes the VTT and exits (bypassing the JSON encoder
 * in api/v1/index.php).
 *
 * @param string      $mediaID e.g. "DE-0190061003"
 * @param string|null $type    Requested TextType
 * @param string|null $lang    Requested language
 * @return void  (always exits)
 */
function transcriptServeVTT(string $mediaID, ?string $type = null, ?string $lang = null): void {
    if ($mediaID === '') {
        transcriptSend404();
    }

    try {
        $speech = mediaGetNode($mediaID);
    } catch (Exception $e) {
        // Covers non-public media (mediaGetNode enforces access), unusable ids
        // and database outages.
        $speech = null;
    }

    if ($speech === null || empty($speech['attributes'])) {
        transcriptSend404();
    }

    $textContents = $speech['attributes']['textContents'] ?? [];
    $defaultLang = LanguageManager::getDefaultLang();

    $textContent = transcriptResolve($textContents, $type, $lang, $defaultLang);
    if ($textContent === null) {
        transcriptSend404();
    }

    $vtt = transcriptGenerateVTT($textContent);

    // Discard any buffered output (stray whitespace from included files) so the
    // response body starts exactly with "WEBVTT" as the format requires.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/vtt; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *'); // IIIF viewers fetch cross-origin
    echo $vtt;
    exit;
}
