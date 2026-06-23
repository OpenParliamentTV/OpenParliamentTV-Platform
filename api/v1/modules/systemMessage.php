<?php
/**
 * System message API module (Plan B — admin broadcasts & automated events).
 *
 * A *system message* is authored by an admin (broadcast) or emitted by the platform
 * (event). Creating one fans out a notification to every targeted active user, which
 * then surfaces in their bell/inbox and — if SendEmail is set — is emailed by the
 * email worker.
 *
 * Routed from api/v1/api.php: action=systemMessage, itemType=list|create
 */

require_once(__DIR__ . "/../utilities.php");
require_once(__DIR__ . "/../../../modules/notifications/functions.php");

function systemMessageRequireAdmin() {
    if (!notificationCurrentUserIsAdmin()) {
        return createApiErrorResponse(403, 1, "messageAuthNotPermittedTitle", "messageAuthNotPermittedDetail");
    }
    return true;
}

/**
 * Fan a system message out to all targeted active users by inserting one
 * notification per user. When $sendEmail is false the rows are pre-marked as
 * email-sent so the email worker skips them (in-app only). Returns the recipient count.
 */
function systemMessageFanOut($db, $type, $title, $body, $link, $targetRole, $sendEmail) {
    global $config;
    $tblN = $config["platform"]["sql"]["tbl"]["Notification"];
    $tblU = $config["platform"]["sql"]["tbl"]["User"];

    $emailSent = $sendEmail ? 0 : 1;

    if ($targetRole) {
        $db->query(
            "INSERT INTO ?n (NotificationUserID, NotificationType, NotificationTitle, NotificationBody, NotificationLink, NotificationEmailSent)
             SELECT UserID, ?s, ?s, ?s, ?s, ?i FROM ?n WHERE UserActive = 1 AND UserRole = ?s",
            $tblN, $type, $title, $body, $link, $emailSent, $tblU, $targetRole
        );
    } else {
        $db->query(
            "INSERT INTO ?n (NotificationUserID, NotificationType, NotificationTitle, NotificationBody, NotificationLink, NotificationEmailSent)
             SELECT UserID, ?s, ?s, ?s, ?s, ?i FROM ?n WHERE UserActive = 1",
            $tblN, $type, $title, $body, $link, $emailSent, $tblU
        );
    }
    return $db->affectedRows();
}

function systemMessageList($parameter = []) {
    global $config;
    $admin = systemMessageRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $rows = $db->getAll("SELECT * FROM ?n ORDER BY SystemMessageCreated DESC LIMIT 100",
        $config["platform"]["sql"]["tbl"]["SystemMessage"]);

    return createApiSuccessResponse(array_map(function ($r) {
        return [
            "type" => "systemMessage",
            "id" => (int)$r["SystemMessageID"],
            "attributes" => [
                "messageType" => $r["SystemMessageType"],
                "title" => $r["SystemMessageTitle"],
                "body" => $r["SystemMessageBody"],
                "link" => $r["SystemMessageLink"],
                "targetRole" => $r["SystemMessageTargetRole"],
                "sendEmail" => (bool)$r["SystemMessageSendEmail"],
                "created" => $r["SystemMessageCreated"],
            ],
        ];
    }, $rows ?: []));
}

function systemMessageCreate($parameter = []) {
    global $config;
    $admin = systemMessageRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $title = isset($parameter["title"]) ? trim((string)$parameter["title"]) : "";
    if ($title === "") { return createApiErrorMissingParameter("title"); }
    $title = mb_substr($title, 0, 500);
    $body = isset($parameter["body"]) ? (string)$parameter["body"] : null;
    $link = isset($parameter["link"]) ? trim((string)$parameter["link"]) : null;
    $targetRole = (!empty($parameter["targetRole"]) && in_array($parameter["targetRole"], ["admin", "user"], true)) ? $parameter["targetRole"] : null;
    $sendEmail = isset($parameter["sendEmail"]) ? (int)(bool)json_decode((string)$parameter["sendEmail"]) : 0;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["SystemMessage"], [
        "SystemMessageType" => "broadcast",
        "SystemMessageTitle" => $title,
        "SystemMessageBody" => $body,
        "SystemMessageLink" => $link,
        "SystemMessageCreatedBy" => notificationCurrentUserId(),
        "SystemMessageTargetRole" => $targetRole,
        "SystemMessageSendEmail" => $sendEmail,
    ]);

    $recipients = systemMessageFanOut($db, "system_broadcast", $title, $body, $link, $targetRole, $sendEmail);

    return createApiSuccessResponse(["recipients" => $recipients, "emailQueued" => (bool)$sendEmail]);
}

/**
 * Reusable helper for automated platform events (e.g. a new electoral period).
 * Call from wherever the event occurs; not exposed via the API.
 */
function createSystemEvent($title, $body = null, $link = null, $targetRole = null, $sendEmail = false, $db = false) {
    global $config;
    if (empty($config["allow"]["notifications"])) { return 0; }
    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return 0; }
    }
    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["SystemMessage"], [
        "SystemMessageType" => "event",
        "SystemMessageTitle" => mb_substr($title, 0, 500),
        "SystemMessageBody" => $body,
        "SystemMessageLink" => $link,
        "SystemMessageTargetRole" => $targetRole,
        "SystemMessageSendEmail" => $sendEmail ? 1 : 0,
    ]);
    return systemMessageFanOut($db, "system_event", $title, $body, $link, $targetRole, $sendEmail ? 1 : 0);
}
