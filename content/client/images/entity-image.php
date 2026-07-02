<?php
/**
 * Local image proxy / cache for Wikidata-derived entity thumbnails.
 *
 * Request: entity-image.php?type={person|organisation|term|document}&id={ID}&v={hash8}
 *
 * - Serves a cached, WebP-optimized copy of the entity's Wikimedia Commons
 *   thumbnail from disk (keyed by entity id), downloading & encoding it on the
 *   first request (lazy cache).
 * - "Refresh on source change": the `v` query param is a hash of the source URL
 *   the calling page knew about; when it no longer matches the cached copy we
 *   revalidate against the DB and re-download if the source actually changed.
 * - On any download/encode failure we 302-redirect to the original Wikimedia URL
 *   so the image still shows (graceful fallback).
 *
 * The cache directory itself is web-denied (.htaccess); all access goes through here.
 */

// Never let warnings/notices corrupt the binary image output.
ini_set('display_errors', '0');

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../../../modules/utilities/safemysql.class.php');
require_once(__DIR__ . '/../../../modules/images/functions.php');

$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
$id   = isset($_GET['id']) ? (string) $_GET['id'] : '';
$v    = isset($_GET['v']) ? (string) $_GET['v'] : '';

// Validate strictly before touching the filesystem or DB (path-traversal / SSRF guard).
if (!in_array($type, OPTV_IMAGE_ENTITY_TYPES, true) || !optvImageIsValidId($type, $id)) {
    http_response_code(404);
    exit;
}

$markerPath = optvImageSourceMarkerPath($type, $id);

$storedSource = is_file($markerPath) ? (string) file_get_contents($markerPath) : '';
$cached       = optvImageCachedFile($type, $id); // ['path'=>..,'mime'=>..] or null

/**
 * Stream a cached image with long-lived cache headers and conditional-GET support.
 */
function optvImageServeFile($cached, $etagSeed) {
    $path = $cached['path'];
    $etag = '"' . substr(md5($etagSeed . '|' . filemtime($path)), 0, 16) . '"';

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=31536000, immutable');
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $cached['mime']);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

/**
 * 302-redirect to the original Wikimedia URL (graceful fallback). Not cached, so
 * the next request retries the local cache.
 */
function optvImageRedirectToSource($sourceURL) {
    if (optvImageIsAllowedSource($sourceURL)) {
        // Redirect to a width Wikimedia actually serves (the stored URL may use a
        // width that now returns 400), so the fallback image still loads.
        header('Cache-Control: no-store');
        header('Location: ' . optvImageNormalizeSourceURL($sourceURL), true, 302);
        exit;
    }
    http_response_code(404);
    exit;
}

// Fast path: cache exists and the caller's version matches what we built it from.
if ($cached !== null && $v !== '' && $storedSource !== '' && $v === optvImageVersion($storedSource)) {
    optvImageServeFile($cached, $storedSource);
}

// Otherwise resolve the authoritative current source URL from the platform DB.
// Returns null when the DB is unreachable or the entity has no thumbnail.
$currentSource = optvImageDbSourceURL($type, $id);
if ($currentSource === null) {
    // Serve a stale cache if we have one, else give up.
    if ($cached !== null) {
        optvImageServeFile($cached, $storedSource);
    }
    http_response_code(404);
    exit;
}

// Cache is present and the source hasn't changed -> serve it (revalidated).
if ($cached !== null && $currentSource === $storedSource) {
    optvImageServeFile($cached, $storedSource);
}

// Need to (re)build. Download + encode + store; on success serve the fresh copy.
if (optvImageIsAllowedSource($currentSource) && optvImageFetchAndStore($type, $id, $currentSource)) {
    $fresh = optvImageCachedFile($type, $id);
    if ($fresh !== null) {
        optvImageServeFile($fresh, $currentSource);
    }
}

// Build failed. Serve a stale cache if we have one, otherwise fall back to remote.
if ($cached !== null) {
    optvImageServeFile($cached, $storedSource);
}
optvImageRedirectToSource($currentSource);
