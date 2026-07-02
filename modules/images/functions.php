<?php

/**
 * Local image cache for Wikidata-derived entity thumbnails.
 *
 * Entity thumbnails (person/organisation/term/document) are stored in the
 * platform DB as full Wikimedia Commons URLs (`*ThumbnailURI`). Hotlinking them
 * gets us rate-limited/blocked by Wikimedia. Instead we route every rendered
 * thumbnail through a local proxy (content/client/images/entity-image.php) which
 * downloads the image once, re-encodes it to an optimized WebP keyed by the
 * entity's Wikidata QID, and serves it from disk thereafter.
 *
 * This module holds the shared logic used by both the API data builders (to
 * rewrite `thumbnailURI` values to the proxy URL) and the proxy endpoint itself
 * (download, encode, store, host allow-list).
 */

// Entity types that own a QID-keyed thumbnail.
const OPTV_IMAGE_ENTITY_TYPES = ['person', 'organisation', 'term', 'document'];

// Hosts we are willing to download images from (SSRF guard).
const OPTV_IMAGE_ALLOWED_HOSTS = ['upload.wikimedia.org', 'commons.wikimedia.org'];

// Wikimedia only renders thumbnails at a fixed set of widths now; requesting any
// other width returns HTTP 400 ("Use thumbnail sizes listed on ...").
// See https://www.mediawiki.org/wiki/Common_thumbnail_sizes
const OPTV_IMAGE_WIKIMEDIA_WIDTHS = [20, 40, 60, 120, 250, 330, 500, 960, 1280, 1920, 3840];

// Width we download from Wikimedia: the smallest allowed bucket that still covers
// our display need (~130px CSS, retina 2x ~= 260px). 330 is the next bucket >= 300.
const OPTV_IMAGE_FETCH_WIDTH = 330;

// Target width for the cached image. Display max is ~130px CSS (retina 2x ~= 260px),
// so 300px covers every use without upscaling.
const OPTV_IMAGE_MAX_WIDTH = 300;

// WebP encoding quality (0-100).
const OPTV_IMAGE_WEBP_QUALITY = 82;
// JPEG fallback quality (0-100) when WebP is unavailable in this GD build.
const OPTV_IMAGE_JPEG_QUALITY = 85;

// Cache file extensions we may produce, in serve/probe priority order.
const OPTV_IMAGE_EXTENSIONS = ['webp', 'png', 'jpg'];

/**
 * True if this GD build can encode at least one of our output formats.
 */
function optvImageCanEncode() {
    return function_exists('imagewebp') || function_exists('imagepng') || function_exists('imagejpeg');
}

/**
 * Pick the output format for an image. WebP is preferred everywhere (smallest,
 * keeps transparency). Where WebP is unavailable we fall back per content: JPEG
 * for opaque photos (good compression), PNG only when transparency is present
 * (logos), so we never bloat a photo into a multi-hundred-KB PNG.
 *
 * @param bool $hasAlpha whether the source image carries meaningful transparency
 * @return array|null    ['ext' => ..., 'mime' => ...] or null if GD can't encode
 */
function optvImageOutputFormatFor($hasAlpha) {
    if (function_exists('imagewebp')) {
        return ['ext' => 'webp', 'mime' => 'image/webp'];
    }
    if ($hasAlpha && function_exists('imagepng')) {
        return ['ext' => 'png', 'mime' => 'image/png'];
    }
    if (function_exists('imagejpeg')) {
        return ['ext' => 'jpg', 'mime' => 'image/jpeg'];
    }
    if (function_exists('imagepng')) {
        return ['ext' => 'png', 'mime' => 'image/png'];
    }
    return null;
}

/**
 * Heuristically detect whether a GD image carries (partial) transparency.
 * Samples a coarse grid to stay cheap on larger sources.
 */
function optvImageHasAlpha($img) {
    if (imagecolortransparent($img) >= 0) {
        return true;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $stepX = max(1, (int) floor($w / 64));
    $stepY = max(1, (int) floor($h / 64));
    for ($x = 0; $x < $w; $x += $stepX) {
        for ($y = 0; $y < $h; $y += $stepY) {
            // Alpha channel is bits 24-30 of the color; 0 = opaque, 127 = transparent.
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * MIME type for a cache file extension.
 */
function optvImageMimeForExt($ext) {
    switch ($ext) {
        case 'webp': return 'image/webp';
        case 'png':  return 'image/png';
        case 'jpg':  return 'image/jpeg';
    }
    return 'application/octet-stream';
}

/**
 * Absolute filesystem path of the image cache directory (no trailing slash).
 */
function optvImageCacheDir() {
    global $config;
    if (!empty($config["dir"]["imageCache"])) {
        return rtrim($config["dir"]["imageCache"], '/');
    }
    // Default: <platform root>/cache/images (this file lives in modules/images/).
    return dirname(__DIR__, 2) . '/cache/images';
}

/**
 * True if $id is a valid entity id for the given type. Person/organisation/term
 * are keyed by Wikidata QID (e.g. "Q42"); documents use an integer DocumentID.
 */
function optvImageIsValidId($type, $id) {
    if (!is_string($id) && !is_int($id)) {
        return false;
    }
    $id = (string) $id;
    if ($type === 'document') {
        return preg_match('/^[0-9]+$/', $id) === 1;
    }
    return preg_match('/^Q[0-9]+$/', $id) === 1;
}

/**
 * True if $url points at a host we are allowed to download from.
 */
function optvImageIsAllowedSource($url) {
    if (!is_string($url) || $url === '') {
        return false;
    }
    $host = parse_url($url, PHP_URL_HOST);
    return $host !== null && in_array(strtolower($host), OPTV_IMAGE_ALLOWED_HOSTS, true);
}

/**
 * Rewrite a Wikimedia Commons thumbnail URL to use an allowed thumbnail width.
 *
 * Wikimedia rejects arbitrary widths (e.g. the 300px URLs ADS historically
 * stored) with HTTP 400. The width token of a `/thumb/.../{W}px-{file}` URL is
 * replaced with OPTV_IMAGE_FETCH_WIDTH. URLs without a width token (originals)
 * are returned unchanged.
 */
function optvImageNormalizeSourceURL($url) {
    if (!is_string($url) || $url === '') {
        return $url;
    }
    return preg_replace('#/(\d+)px-#', '/' . OPTV_IMAGE_FETCH_WIDTH . 'px-', $url, 1);
}

/**
 * Path to a usable CA bundle, working around environments whose PHP curl points
 * at a non-existent default bundle. Returns null to fall back to curl's default.
 */
function optvImageCaBundle() {
    static $resolved = false;
    static $path = null;
    if ($resolved) {
        return $path;
    }
    $resolved = true;
    $candidates = [
        ini_get('curl.cainfo'),
        ini_get('openssl.cafile'),
        '/etc/ssl/cert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/usr/local/etc/openssl/cert.pem',
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            $path = $candidate;
            return $path;
        }
    }
    return $path;
}

/**
 * Short version hash derived from the source URL. When the source URL changes
 * (e.g. ADS picks up a new Wikidata image), this changes too, which busts both
 * the browser cache and the on-disk cache.
 */
function optvImageVersion($sourceURL) {
    return substr(md5((string) $sourceURL), 0, 8);
}

/**
 * Build the local proxy URL for an entity thumbnail.
 *
 * Returns the original $sourceURL untouched when it is not a cacheable Wikimedia
 * image or the id/type are not valid, so non-Wikidata edge cases pass through.
 *
 * @param string $type      person|organisation|term|document
 * @param string $id        entity id (QID, or integer DocumentID for documents)
 * @param string $sourceURL the raw Wikimedia Commons URL
 * @return string|null      proxy URL, or the unchanged input
 */
function thumbnailCacheURL($type, $id, $sourceURL) {
    global $config;

    if (!in_array($type, OPTV_IMAGE_ENTITY_TYPES, true)
        || !optvImageIsValidId($type, $id)
        || !optvImageIsAllowedSource($sourceURL)) {
        return $sourceURL;
    }

    $root = isset($config["dir"]["root"]) ? rtrim($config["dir"]["root"], '/') : '';
    return $root . '/content/client/images/entity-image.php?type=' . rawurlencode($type)
        . '&id=' . rawurlencode((string) $id)
        . '&v=' . optvImageVersion($sourceURL);
}

/**
 * Rewrite the `thumbnailURI` of a JSON:API-shaped entity node in place.
 *
 * Expects $node to have ["type"], ["id"] and ["attributes"]["thumbnailURI"].
 * No-op if the shape or values don't qualify.
 */
function applyThumbnailCache(array &$node) {
    if (!isset($node["type"], $node["id"])
        || !isset($node["attributes"]) || !is_array($node["attributes"])
        || empty($node["attributes"]["thumbnailURI"])) {
        return;
    }
    $node["attributes"]["thumbnailURI"] = thumbnailCacheURL(
        $node["type"],
        $node["id"],
        $node["attributes"]["thumbnailURI"]
    );
}

/**
 * Cache filename stem, namespaced by type to avoid any cross-type id collision
 * (e.g. "person_Q42", "document_123"). Both type and id must already be valid.
 */
function optvImageCacheStem($type, $id) {
    return $type . '_' . $id;
}

/**
 * Recursively rewrite every JSON:API entity node (type + id + attributes.thumbnailURI)
 * found anywhere in a response structure. Idempotent: nodes already pointing at the
 * local proxy, and non-entity / flat shapes, are left untouched. Used as a safety net
 * at the API response boundary to catch nested entities (e.g. search results whose
 * thumbnails come straight from the search index).
 */
function applyThumbnailCacheRecursive(&$node) {
    if (!is_array($node)) {
        return;
    }
    applyThumbnailCache($node); // no-op unless this node is an entity node
    foreach ($node as &$child) {
        if (is_array($child)) {
            applyThumbnailCacheRecursive($child);
        }
    }
    unset($child);
}

/**
 * Path of the cached image for an entity, for a given extension.
 */
function optvImageCachePath($type, $id, $ext) {
    return optvImageCacheDir() . '/' . optvImageCacheStem($type, $id) . '.' . $ext;
}

/**
 * Locate an existing cached image file for an entity, probing known extensions
 * in priority order. Returns ['path' => ..., 'mime' => ...] or null.
 */
function optvImageCachedFile($type, $id) {
    $stem = optvImageCacheDir() . '/' . optvImageCacheStem($type, $id);
    foreach (OPTV_IMAGE_EXTENSIONS as $ext) {
        $path = $stem . '.' . $ext;
        if (is_file($path)) {
            return ['path' => $path, 'mime' => optvImageMimeForExt($ext)];
        }
    }
    return null;
}

/**
 * Path of the sidecar marker recording the source URL the image was built from.
 */
function optvImageSourceMarkerPath($type, $id) {
    return optvImageCacheDir() . '/' . optvImageCacheStem($type, $id) . '.src';
}

/**
 * Download $sourceURL, re-encode to an optimized image (max width
 * OPTV_IMAGE_MAX_WIDTH, WebP if this GD build supports it, else PNG/JPEG) and
 * store it (plus its source marker) atomically.
 *
 * @return bool true on success, false on any download/decode/encode/write error.
 */
function optvImageFetchAndStore($type, $id, $sourceURL) {
    if (!in_array($type, OPTV_IMAGE_ENTITY_TYPES, true)
        || !optvImageIsValidId($type, $id)
        || !optvImageIsAllowedSource($sourceURL)) {
        return false;
    }

    if (!optvImageCanEncode()) {
        return false;
    }

    $dir = optvImageCacheDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $bytes = optvImageDownload(optvImageNormalizeSourceURL($sourceURL));
    if ($bytes === false || $bytes === '') {
        return false;
    }

    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        return false;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    if ($srcW < 1 || $srcH < 1) {
        imagedestroy($src);
        return false;
    }

    // Only downscale; never upscale.
    if ($srcW > OPTV_IMAGE_MAX_WIDTH) {
        $dstW = OPTV_IMAGE_MAX_WIDTH;
        $dstH = (int) round($srcH * (OPTV_IMAGE_MAX_WIDTH / $srcW));
    } else {
        $dstW = $srcW;
        $dstH = $srcH;
    }

    $hasAlpha = optvImageHasAlpha($src);
    $format = optvImageOutputFormatFor($hasAlpha);
    if ($format === null) {
        imagedestroy($src);
        return false;
    }

    $dst = imagecreatetruecolor($dstW, $dstH);
    // Preserve transparency (logos are often transparent PNGs).
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    // PNG/JPEG: flatten onto white (no alpha channel); WebP keeps transparency.
    if ($format['ext'] === 'jpg') {
        $flat = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $dstW, $dstH, $white);
        imagecopy($flat, $dst, 0, 0, 0, 0, $dstW, $dstH);
        imagedestroy($dst);
        $dst = $flat;
    }

    // Encode to a temp file in the cache dir, then rename atomically.
    $tmp = $dir . '/.' . optvImageCacheStem($type, $id) . '.' . getmypid() . '.tmp';
    switch ($format['ext']) {
        case 'webp':
            $ok = imagewebp($dst, $tmp, OPTV_IMAGE_WEBP_QUALITY);
            break;
        case 'png':
            $ok = imagepng($dst, $tmp, 9);
            break;
        case 'jpg':
            $ok = imagejpeg($dst, $tmp, OPTV_IMAGE_JPEG_QUALITY);
            break;
        default:
            $ok = false;
    }
    imagedestroy($dst);

    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        return false;
    }

    if (!@rename($tmp, optvImageCachePath($type, $id, $format['ext']))) {
        @unlink($tmp);
        return false;
    }

    // Remove any stale copies of the entity in other formats so the resolver
    // doesn't serve an outdated one.
    foreach (OPTV_IMAGE_EXTENSIONS as $ext) {
        if ($ext !== $format['ext']) {
            @unlink(optvImageCachePath($type, $id, $ext));
        }
    }

    // Record the source URL so future requests can detect a changed source.
    @file_put_contents(optvImageSourceMarkerPath($type, $id), $sourceURL, LOCK_EX);

    return true;
}

/**
 * Resolve the authoritative current source URL for an entity thumbnail from the
 * platform DB. Returns the URL string, or null if the entity/column is empty or
 * the DB is unreachable.
 */
function optvImageDbSourceURL($type, $id) {
    global $config;

    if (!in_array($type, OPTV_IMAGE_ENTITY_TYPES, true) || !optvImageIsValidId($type, $id)) {
        return null;
    }

    try {
        $db = new SafeMySQL([
            'host' => $config["platform"]["sql"]["access"]["host"],
            'user' => $config["platform"]["sql"]["access"]["user"],
            'pass' => $config["platform"]["sql"]["access"]["passwd"],
            'db'   => $config["platform"]["sql"]["db"],
        ]);

        $map = [
            'person'       => [$config["platform"]["sql"]["tbl"]["Person"],       'PersonID',       'PersonThumbnailURI'],
            'organisation' => [$config["platform"]["sql"]["tbl"]["Organisation"], 'OrganisationID', 'OrganisationThumbnailURI'],
            'term'         => [$config["platform"]["sql"]["tbl"]["Term"],         'TermID',         'TermThumbnailURI'],
            'document'     => [$config["platform"]["sql"]["tbl"]["Document"],     'DocumentID',     'DocumentThumbnailURI'],
        ];
        list($table, $idCol, $thumbCol) = $map[$type];

        $idPlaceholder = ($type === 'document') ? '?i' : '?s';
        $idValue       = ($type === 'document') ? (int) $id : $id;

        $source = $db->getOne("SELECT $thumbCol FROM ?n WHERE $idCol=$idPlaceholder LIMIT 1", $table, $idValue);
        return ($source === null || $source === '') ? null : $source;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Ensure a locally cached copy of an entity thumbnail exists and return its
 * filesystem path, or null if none is available.
 *
 * Shared by the proxy endpoint (entity-image.php, which then streams the file)
 * and the meta-image renderers (which composite it). Uses the DB as the source
 * of truth, serves an existing cache when the source is unchanged, and fetches +
 * encodes on a miss/changed source (reusing optvImageFetchAndStore).
 *
 * Requires SafeMySQL to be available (caller includes safemysql.class.php).
 *
 * @return string|null absolute path to the cached image, or null on failure
 */
function optvImageEnsureCached($type, $id) {
    if (!in_array($type, OPTV_IMAGE_ENTITY_TYPES, true) || !optvImageIsValidId($type, $id)) {
        return null;
    }

    $cached       = optvImageCachedFile($type, $id); // ['path'=>..,'mime'=>..] or null
    $storedSource = is_file(optvImageSourceMarkerPath($type, $id))
        ? (string) file_get_contents(optvImageSourceMarkerPath($type, $id))
        : '';

    $currentSource = optvImageDbSourceURL($type, $id);

    // DB unreachable / no source: serve a stale cache if we have one.
    if ($currentSource === null) {
        return $cached !== null ? $cached['path'] : null;
    }

    // Cache present and source unchanged -> use it.
    if ($cached !== null && $currentSource === $storedSource) {
        return $cached['path'];
    }

    // (Re)build, then return the fresh copy.
    if (optvImageIsAllowedSource($currentSource) && optvImageFetchAndStore($type, $id, $currentSource)) {
        $fresh = optvImageCachedFile($type, $id);
        if ($fresh !== null) {
            return $fresh['path'];
        }
    }

    // Build failed: fall back to a stale cache if present.
    return $cached !== null ? $cached['path'] : null;
}

/**
 * Fetch raw image bytes from an allowed host with a descriptive User-Agent and a
 * short timeout. Returns the body string or false on failure (incl. non-2xx).
 */
function optvImageDownload($url) {
    $userAgent = 'OpenParliamentTV-Platform/1.0 image-cache (https://github.com/OpenParliamentTV)';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => $userAgent,
        ];
        $caBundle = optvImageCaBundle();
        if ($caBundle !== null) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            return false;
        }
        return $body;
    }

    $context = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 15, 'header' => 'User-Agent: ' . $userAgent],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? false : $body;
}
