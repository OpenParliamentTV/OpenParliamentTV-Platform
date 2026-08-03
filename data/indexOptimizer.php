<?php
/**
 * Deprecated path — moved to bin/optimize-index.php.
 *
 * Kept so instance crontabs keep working across the upgrade. Update the crontab
 * to call bin/optimize-index.php directly; this shim is removed in a future release.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

fwrite(STDERR, "[deprecated] " . __FILE__ . " has moved to bin/optimize-index.php — update your crontab.\n");

require __DIR__ . "/../bin/optimize-index.php";
