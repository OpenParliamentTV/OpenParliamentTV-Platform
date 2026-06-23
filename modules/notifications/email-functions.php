<?php
/**
 * Email helpers shared by the email + digest workers (Plan B).
 * Sends notification emails through the existing PHPMailer wrapper, adding the
 * RFC 8058 List-Unsubscribe / List-Unsubscribe-Post headers required by Gmail/Yahoo.
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../send-mail/functions.php");
require_once(__DIR__ . "/../i18n/language.php");
require_once(__DIR__ . "/templates/alert-realtime.php");
require_once(__DIR__ . "/templates/alert-digest.php");
require_once(__DIR__ . "/templates/system-broadcast.php");

function notificationUnsubscribeUrl($token) {
    global $config;
    return $config["dir"]["root"] . "/api/v1/notification/unsubscribe?token=" . rawurlencode($token);
}

function notificationManageUrl() {
    global $config;
    return $config["dir"]["root"] . "/manage/alerts";
}

/**
 * Send a single notification email with one-click List-Unsubscribe headers.
 */
function sendNotificationEmail($toEmail, $toName, $subject, $html, $unsubscribeToken) {
    $headers = [];
    if (!empty($unsubscribeToken)) {
        $url = notificationUnsubscribeUrl($unsubscribeToken);
        $headers["List-Unsubscribe"] = "<" . $url . ">";
        $headers["List-Unsubscribe-Post"] = "List-Unsubscribe=One-Click";
    }
    return sendMail($toEmail, $toName, $subject, $html, true, null, null, $headers);
}
