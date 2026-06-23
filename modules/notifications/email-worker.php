<?php
/**
 * Email worker (Plan B). Sends pending real-time alert emails and system
 * broadcast/event emails, then marks them sent. Intended to run frequently
 * (every 1-2 min) via cron. Digest (daily/weekly) alerts are handled separately
 * by digest-worker.php and are skipped here.
 *
 * Usage: php modules/notifications/email-worker.php [--limit=200]
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

function emailWorkerLog($type, $msg) {
    file_put_contents(__DIR__ . "/email-worker.log",
        date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

if (!is_cli()) { exit(1); }
if (empty($config["allow"]["notifications"])) { exit; }

$opts = getopt("", ["limit::"]);
$limit = isset($opts["limit"]) && $opts["limit"] !== "" ? max(1, (int)$opts["limit"]) : 200;

$lockFile = __DIR__ . "/email-worker.lock";
if (file_exists($lockFile)) {
    if (time() - filemtime($lockFile) < 600) { exit; }
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

    // Real-time alert notifications + immediate system messages, for active users
    // whose email is enabled. Digest alerts (daily/weekly) are excluded here.
    $rows = $db->getAll(
        "SELECT n.*, u.UserMail, u.UserName,
                p.NotificationPreferenceUnsubscribeToken AS token,
                p.NotificationPreferenceEmailEnabled AS emailEnabled
         FROM ?n n
         JOIN ?n u ON u.UserID = n.NotificationUserID
         LEFT JOIN ?n p ON p.NotificationPreferenceUserID = n.NotificationUserID
         LEFT JOIN ?n a ON a.AlertID = n.NotificationAlertID
         WHERE n.NotificationEmailSent = 0
           AND u.UserActive = 1
           AND (p.NotificationPreferenceEmailEnabled = 1 OR p.NotificationPreferenceEmailEnabled IS NULL)
           AND (
                 (n.NotificationType = 'alert' AND a.AlertFrequency = 'realtime' AND a.AlertChannelEmail = 1)
                 OR n.NotificationType IN ('system_broadcast', 'system_event')
               )
         ORDER BY n.NotificationCreated ASC
         LIMIT ?i",
        $tblN, $tblU, $tblP, $tblA, $limit
    );

    $sent = 0; $failed = 0;
    $brand = function_exists('L') ? L("brand") : "Open Parliament TV";

    foreach ($rows ?: [] as $n) {
        if (empty($n["UserMail"])) { continue; }

        if ($n["NotificationType"] === "alert") {
            $subject = "[" . $brand . "] " . $n["NotificationTitle"];
            $html = renderAlertRealtimeEmail($n, $n["UserName"], notificationManageUrl(), notificationUnsubscribeUrl($n["token"]));
        } else {
            $subject = $n["NotificationTitle"];
            $html = renderSystemBroadcastEmail($n, $n["UserName"], notificationUnsubscribeUrl($n["token"]));
        }

        $ok = sendNotificationEmail($n["UserMail"], $n["UserName"], $subject, $html, $n["token"]);
        if ($ok) {
            $db->query("UPDATE ?n SET NotificationEmailSent = 1, NotificationEmailSentAt = NOW() WHERE NotificationID = ?i",
                $tblN, (int)$n["NotificationID"]);
            $sent++;
        } else {
            $failed++;
            emailWorkerLog("error", "Failed to send notification " . $n["NotificationID"] . " to " . $n["UserMail"]);
        }
    }

    if ($sent > 0 || $failed > 0) {
        emailWorkerLog("info", "Email worker run: sent=$sent failed=$failed");
    }
} catch (Exception $e) {
    emailWorkerLog("error", $e->getMessage());
    exit(1);
}
