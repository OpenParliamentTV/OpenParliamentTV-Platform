<?php
/**
 * Bounded on-disk cache for generated meta-image page cards (entity / media /
 * search / default). Quote images are NOT cached here — they have unbounded
 * cardinality and rely on HTTP cache headers instead.
 *
 * Files are keyed "{type}_{id}_{lang}_{v}.png" where {v} is a content version
 * hash (label + thumbnail version + lang). When the underlying data changes the
 * version changes, a fresh file is written, and stale variants for the same
 * {type}_{id}_{lang} are pruned — so the cache stays at ~one file per entity per
 * language. Requires modules/images/functions.php for the cache-dir base.
 */

require_once(__DIR__ . '/../images/functions.php');

/**
 * Absolute path of the meta-image cache directory (sibling of the entity image
 * cache, e.g. <root>/cache/meta-images), no trailing slash.
 */
function metaImageCacheDir() {
    return dirname(optvImageCacheDir()) . '/meta-images';
}

/**
 * Short content-version hash. Changing any input busts the cached file.
 */
function metaImageVersion(array $parts) {
    return substr(md5(implode('|', $parts)), 0, 8);
}

/**
 * Sanitize an id for safe use in a cache filename (ids are already validated for
 * entity types, but media ids and search keys are looser).
 */
function metaImageSafeKeyPart($s) {
    return preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $s);
}

/**
 * Build the cache key (without extension) for a card.
 */
function metaImageCacheKey($type, $id, $lang, $version) {
    return metaImageSafeKeyPart($type) . '_' . metaImageSafeKeyPart($id) . '_'
        . metaImageSafeKeyPart($lang) . '_' . metaImageSafeKeyPart($version);
}

/**
 * Full filesystem path for a cache key.
 */
function metaImageCachePath($key) {
    return metaImageCacheDir() . '/' . $key . '.png';
}

/**
 * Serve a cached card if present. Returns true (and exits via readfile) on hit,
 * false on miss.
 */
function metaImageCacheServe($key) {
    $path = metaImageCachePath($key);
    if (!is_file($path)) {
        return false;
    }
    // Discard any buffered whitespace emitted by required files so it can't
    // corrupt the binary image stream.
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    return true;
}

/**
 * Store PNG bytes for a card atomically (temp file + rename) and prune stale
 * version variants for the same {type}_{id}_{lang}. Best-effort: failures are
 * swallowed (the image is still served live by the caller).
 */
function metaImageCacheStore($key, $pngBytes) {
    $dir = metaImageCacheDir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $finalPath = metaImageCachePath($key);
    $tmp = $dir . '/.' . $key . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $pngBytes, LOCK_EX) === false) {
        @unlink($tmp);
        return;
    }
    if (!@rename($tmp, $finalPath)) {
        @unlink($tmp);
        return;
    }

    // Prune older version variants: same prefix up to the last "_" (version).
    $prefix = substr($key, 0, strrpos($key, '_') + 1);
    foreach (glob($dir . '/' . $prefix . '*.png') ?: [] as $old) {
        if ($old !== $finalPath) {
            @unlink($old);
        }
    }
}
