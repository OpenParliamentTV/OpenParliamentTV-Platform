<?php
/**
 * CLI bootstrap — for scripts under bin/ and the cron entry points.
 *
 * Adds to bootstrap-core.php a hard CLI-only guard: these scripts live inside
 * the docroot, so a misconfigured server must not be able to run them over HTTP.
 * No session and no headers — this is not a web surface.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once(__DIR__ . "/bootstrap-core.php");
