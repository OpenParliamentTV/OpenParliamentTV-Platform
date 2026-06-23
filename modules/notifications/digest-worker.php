<?php
/**
 * Digest worker (Plan B). Aggregates undigested alert notifications for alerts of
 * a given frequency (daily/weekly) into one email per user, sends, and marks them
 * digested. Run from cron (daily 08:00, weekly Mon 08:00).
 *
 * Usage: php modules/notifications/digest-worker.php --frequency=daily|weekly
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
require_once(__DIR__ . "/email-functions.php");

function digestWorkerLog($type, $msg) {
    file_put_contents(__DIR__ . "/digest-worker.log",
        date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

if (!is_cli()) { exit(1); }
if (empty($config["allow"]["notifications"])) { exit; }

$opts = getopt("", ["frequency:"]);
$frequency = $opts["frequency"] ?? "daily";
if (!in_array($frequency, ["daily", "weekly"], true)) {
    digestWorkerLog("error", "Invalid frequency: " . $frequency);
    exit(1);
}

$lockFile = __DIR__ . "/digest-worker_" . $frequency . ".lock";
if (file_exists($lockFile)) {
    if (time() - filemtime($lockFile) < 3600) { exit; }
    @unlink($lockFile);
}
touch($lockFile);
register_shutdown_function(function () use ($lockFile) { if (file_exists($lockFile)) { @unlink($lockFile); } });

try {
    $db = new SafeMySQL([
        'host' => $config["platform"]["sql"]["access"]["host"],
        'user' => $config["platform"]["sql"]["access"]["user"],
        'pass' => $config["platform"]["sql"]["access"]["passwd"],
        'db'   => $config["platform"]["sql"]["db"],
    ]);

    $tblN = $config["platform"]["sql"]["tbl"]["Notification"];
    $tblA = $config["platform"]["sql"]["tbl"]["Alert"];
    $tblU = $config["platform"]["sql"]["tbl"]["User"];
    $tblP = $config["platform"]["sql"]["tbl"]["NotificationPreference"];

    $rows = $db->getAll(
        "SELECT n.*, a.AlertLabel, u.UserMail, u.UserName,
                p.NotificationPreferenceUnsubscribeToken AS token
         FROM ?n n
         JOIN ?n a ON a.AlertID = n.NotificationAlertID
         JOIN ?n u ON u.UserID = n.NotificationUserID
         LEFT JOIN ?n p ON p.NotificationPreferenceUserID = n.NotificationUserID
         WHERE n.NotificationDigested = 0
           AND n.NotificationType = 'alert'
           AND a.AlertFrequency = ?s
           AND a.AlertChannelEmail = 1
           AND u.UserActive = 1
           AND (p.NotificationPreferenceEmailEnabled = 1 OR p.NotificationPreferenceEmailEnabled IS NULL)
         ORDER BY n.NotificationUserID, a.AlertID, n.NotificationCreated",
        $tblN, $tblA, $tblU, $tblP, $frequency
    );

    // Group: user -> alertLabel -> items
    $byUser = [];
    foreach ($rows ?: [] as $n) {
        $uid = (int)$n["NotificationUserID"];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = ["mail" => $n["UserMail"], "name" => $n["UserName"], "token" => $n["token"], "groups" => [], "ids" => []];
        }
        $label = $n["AlertLabel"];
        if (!isset($byUser[$uid]["groups"][$label])) {
            $byUser[$uid]["groups"][$label] = ["label" => $label, "items" => []];
        }
        $byUser[$uid]["groups"][$label]["items"][] = $n;
        $byUser[$uid]["ids"][] = (int)$n["NotificationID"];
    }

    $usersSent = 0;
    $brand = function_exists('L') ? L("brand") : "Open Parliament TV";

    foreach ($byUser as $uid => $u) {
        if (empty($u["mail"]) || empty($u["ids"])) { continue; }

        $subject = "[" . $brand . "] " . ucfirst($frequency);
        $html = renderAlertDigestEmail($u["name"], array_values($u["groups"]), $frequency, notificationManageUrl(), notificationUnsubscribeUrl($u["token"]));

        $ok = sendNotificationEmail($u["mail"], $u["name"], $subject, $html, $u["token"]);
        if ($ok) {
            $db->query("UPDATE ?n SET NotificationDigested = 1, NotificationEmailSent = 1, NotificationEmailSentAt = NOW() WHERE NotificationID IN (?a)",
                $tblN, $u["ids"]);
            $usersSent++;
        } else {
            digestWorkerLog("error", "Failed to send $frequency digest to " . $u["mail"]);
        }
    }

    if ($usersSent > 0) {
        digestWorkerLog("info", "$frequency digest sent to $usersSent user(s)");
    }
} catch (Exception $e) {
    digestWorkerLog("error", $e->getMessage());
    exit(1);
}
