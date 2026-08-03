<?php
/**
 * Runtime state paths.
 *
 * All mutable state the platform writes at runtime — caches, logs, import
 * working directories, cloned data repositories — lives under a single
 * web-denied `var/` tree, so that the docroot contains only code and static
 * assets. Nothing under `var/` is served directly by Apache; the two image
 * caches are streamed by their proxy endpoints instead.
 *
 * Layout:
 *   var/cache/lang/          compiled i18n language caches
 *   var/cache/api/           API-side caches (remote repo status, service health)
 *   var/cache/images/        entity thumbnails (served via entity-image.php)
 *   var/cache/meta-images/   generated OG/social cards (served via meta-image.php)
 *   var/log/                 all cron/worker logs, dev mail dumps
 *   var/data/input/          session files awaiting import
 *   var/data/done/           imported session files
 *   var/data/progress/       progress JSON + lock files
 *   var/data/repos/          cloned parliament data repositories
 */

/**
 * Absolute path inside the runtime state directory (no trailing slash).
 *
 * `$sub` is a slash-separated path relative to `var/`, e.g. "cache/lang" or
 * "data/progress". The directory is created on first use, so callers can write
 * into the returned path without their own mkdir dance.
 *
 * The location is configurable via $config["dir"]["var"] (absolute path);
 * when unset or empty it defaults to `<platform root>/var`.
 */
function optvVarPath(string $sub = ''): string {
    global $config;
    static $base = null;

    if ($base === null) {
        $configured = isset($config["dir"]["var"]) ? trim((string) $config["dir"]["var"]) : '';
        // This file lives in modules/utilities/, so two levels up is the root.
        $base = $configured !== '' ? rtrim($configured, '/') : dirname(__DIR__, 2) . '/var';
    }

    $path = $base;
    if ($sub !== '') {
        $path .= '/' . trim(str_replace('\\', '/', $sub), '/');
    }

    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }

    return $path;
}

/**
 * Absolute path of a file inside the runtime state directory. The file's
 * parent directory is created if missing; the file itself is not touched.
 *
 * Example: optvVarFilePath('log', 'import.log') => <root>/var/log/import.log
 */
function optvVarFilePath(string $sub, string $fileName): string {
    return optvVarPath($sub) . '/' . ltrim($fileName, '/');
}
