<?php
/**
 * Standalone alert matcher (Plan B — notifications & alerts).
 *
 * Runs alert matching on demand over recent media, so notifications can be
 * generated for live testing without waiting for a full cronUpdater run. Also the
 * backend for the admin "run matching over last N" button.
 *
 * CLI usage:
 *   php data/notificationMatcher.php --parliament=DE [--last=50]
 *   php data/notificationMatcher.php --parliament=DE --media-ids=DE-0210900001,DE-0210900002
 *
 * Mirrors the cron lock/logging conventions of cronUpdater.php.
 */

require_once(__DIR__ . "/../config.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('memory_limit', '512M');

require_once(__DIR__ . "/../modules/utilities/functions.php");
require_once(__DIR__ . "/../api/v1/utilities.php");
require_once(__DIR__ . "/../api/v1/api.php");
require_once(__DIR__ . "/../modules/notifications/alert-matcher.php");

function notificationMatcherLog($type = "info", $msg) {
    file_put_contents(__DIR__ . "/notificationMatcher.log",
        date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

if (!is_cli()) {
    exit(1);
}

$input = getopt("", ["parliament:", "last::", "media-ids::"]);
$parliament = !empty($input["parliament"]) ? $input["parliament"] : "DE";
$last = isset($input["last"]) && $input["last"] !== "" ? max(1, (int)$input["last"]) : 50;

$lockFile = __DIR__ . "/notificationMatcher_" . $parliament . ".lock";
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 3600) {
        notificationMatcherLog("warn", "Matcher already running for $parliament, exiting.");
        exit;
    }
    @unlink($lockFile);
}
touch($lockFile);
register_shutdown_function(function () use ($lockFile) {
    if (file_exists($lockFile)) { @unlink($lockFile); }
});

if (empty($config["allow"]["notifications"])) {
    notificationMatcherLog("info", "Notifications feature disabled; nothing to do.");
    exit;
}

try {
    if (!empty($input["media-ids"])) {
        $db = getApiDatabaseConnection('platform');
        $dbp = getApiDatabaseConnection('parliament', $parliament);
        $ids = array_filter(array_map('trim', explode(",", $input["media-ids"])));
        $scanned = 0; $created = 0;
        foreach ($ids as $id) {
            $resp = apiV1(["action" => "getItem", "itemType" => "media", "id" => $id], $db, $dbp);
            if (($resp["meta"]["requestStatus"] ?? "") === "success" && !empty($resp["data"])) {
                $scanned++;
                $created += matchMediaItemAgainstAlerts($resp["data"], $parliament, $db);
            }
        }
        $result = ["parliament" => $parliament, "scanned" => $scanned, "notificationsCreated" => $created];
    } else {
        $result = runAlertMatchingForRecent($parliament, $last);
    }

    notificationMatcherLog("info", "Matching complete: " . json_encode($result));
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    notificationMatcherLog("error", $e->getMessage());
    exit(1);
}
