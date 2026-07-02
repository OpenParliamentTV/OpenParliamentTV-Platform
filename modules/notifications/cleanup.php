<?php
/**
 * Notification cleanup (Plan B). Deletes notifications older than a retention
 * window (default 90 days) to keep the table bounded. Run daily via cron.
 *
 * Usage: php modules/notifications/cleanup.php [--days=90]
 */

require_once(__DIR__ . "/../../config.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");

function cleanupLog($type, $msg) {
    file_put_contents(__DIR__ . "/cleanup.log",
        date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

if (!is_cli()) { exit(1); }

$opts = getopt("", ["days::"]);
$days = isset($opts["days"]) && $opts["days"] !== "" ? max(1, (int)$opts["days"]) : 90;

try {
    $db = new SafeMySQL([
        'host' => $config["platform"]["sql"]["access"]["host"],
        'user' => $config["platform"]["sql"]["access"]["user"],
        'pass' => $config["platform"]["sql"]["access"]["passwd"],
        'db'   => $config["platform"]["sql"]["db"],
    ]);

    $db->query("DELETE FROM ?n WHERE NotificationCreated < (NOW() - INTERVAL ?i DAY)",
        $config["platform"]["sql"]["tbl"]["Notification"], $days);
    $deleted = $db->affectedRows();

    cleanupLog("info", "Deleted $deleted notification(s) older than $days days");
    echo "Deleted $deleted notification(s) older than $days days\n";
} catch (Exception $e) {
    cleanupLog("error", $e->getMessage());
    exit(1);
}
