<?php
/**
 * Notification API module (Plan B — notifications & alerts).
 *
 * A *notification* is a single delivered message in a user's inbox — produced by an
 * alert match, an admin broadcast, or a system event. This module powers the bell,
 * the /notifications inbox, per-user preferences, the public unsubscribe link, and
 * the admin-only on-demand matcher trigger.
 *
 * Routed from api/v1/api.php: action=notification,
 *   itemType=list|unreadCount|markRead|markAllRead|preferences|unsubscribe|runMatch
 */

require_once(__DIR__ . "/../utilities.php");
require_once(__DIR__ . "/../../../modules/notifications/functions.php");

function notificationRequireUser() {
    $userId = notificationCurrentUserId();
    if (!$userId) {
        return createApiErrorResponse(401, 1, "messageAuthLoginRequiredTitle", "messageAuthLoginRequiredDetail");
    }
    return $userId;
}

function notificationRowToObject($row) {
    return [
        "type" => "notification",
        "id" => (int)$row["NotificationID"],
        "attributes" => [
            "notificationType" => $row["NotificationType"],
            "title" => $row["NotificationTitle"],
            "body" => $row["NotificationBody"],
            "link" => $row["NotificationLink"],
            "mediaID" => $row["NotificationMediaID"],
            "parliament" => $row["NotificationParliament"],
            "read" => (bool)$row["NotificationRead"],
            "created" => $row["NotificationCreated"],
            // Criteria snapshot of the originating alert (if still present), so the
            // inbox can render the same criteria chips as the manage/alerts list.
            "alertCriteria" => (!empty($row["AlertCriteria"]))
                ? (json_decode($row["AlertCriteria"], true) ?: null) : null,
        ],
        "relationships" => [
            "alert" => ["data" => $row["NotificationAlertID"] ? ["type" => "alert", "id" => (int)$row["NotificationAlertID"]] : null],
        ],
    ];
}

function notificationList($parameter = []) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $unreadOnly = !empty($parameter["unreadOnly"]) && json_decode((string)$parameter["unreadOnly"]);
    $limit = isset($parameter["limit"]) ? max(1, min(100, (int)$parameter["limit"])) : 20;
    $tbl = $config["platform"]["sql"]["tbl"]["Notification"];
    $alertTbl = $config["platform"]["sql"]["tbl"]["Alert"];

    // LEFT JOIN the alert so each alert notification carries its criteria snapshot.
    if ($unreadOnly) {
        $rows = $db->getAll("SELECT n.*, a.AlertCriteria FROM ?n n LEFT JOIN ?n a ON n.NotificationAlertID = a.AlertID WHERE n.NotificationUserID = ?i AND n.NotificationRead = 0 ORDER BY n.NotificationCreated DESC LIMIT ?i",
            $tbl, $alertTbl, $userId, $limit);
    } else {
        $rows = $db->getAll("SELECT n.*, a.AlertCriteria FROM ?n n LEFT JOIN ?n a ON n.NotificationAlertID = a.AlertID WHERE n.NotificationUserID = ?i ORDER BY n.NotificationCreated DESC LIMIT ?i",
            $tbl, $alertTbl, $userId, $limit);
    }
    $total = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE NotificationUserID = ?i", $tbl, $userId);
    $out = array_map('notificationRowToObject', $rows ?: []);
    return createApiSuccessResponse($out, [], null, null, $total);
}

function notificationUnreadCount($parameter = []) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $count = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE NotificationUserID = ?i AND NotificationRead = 0",
        $config["platform"]["sql"]["tbl"]["Notification"], $userId);
    return createApiSuccessResponse(["unreadCount" => $count]);
}

/**
 * Parse one or more notification ids from a request: accepts `id` (single) and/or
 * `ids` (array or comma-separated string). Returns a list of positive ints.
 */
function notificationParseIds($parameter) {
    $raw = [];
    if (isset($parameter["ids"])) {
        $raw = is_array($parameter["ids"]) ? $parameter["ids"] : explode(",", (string)$parameter["ids"]);
    }
    if (isset($parameter["id"])) {
        $raw[] = $parameter["id"];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $raw), function ($v) { return $v > 0; })));
    return $ids;
}

/**
 * Set the read flag on one or more of the user's own notifications.
 */
function notificationSetRead($parameter, $read) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $ids = notificationParseIds($parameter);
    if (empty($ids)) { return createApiErrorMissingParameter("id"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $db->query("UPDATE ?n SET NotificationRead = ?i WHERE NotificationID IN (?a) AND NotificationUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Notification"], $read ? 1 : 0, $ids, $userId);
    return createApiSuccessResponse(["ids" => $ids, "read" => (bool)$read, "affected" => $db->affectedRows()]);
}

function notificationMarkRead($parameter = []) {
    return notificationSetRead($parameter, 1);
}

function notificationMarkUnread($parameter = []) {
    return notificationSetRead($parameter, 0);
}

function notificationDelete($parameter = []) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $ids = notificationParseIds($parameter);
    if (empty($ids)) { return createApiErrorMissingParameter("id"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $db->query("DELETE FROM ?n WHERE NotificationID IN (?a) AND NotificationUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Notification"], $ids, $userId);
    return createApiSuccessResponse(["ids" => $ids, "deleted" => $db->affectedRows()]);
}

function notificationMarkAllRead($parameter = []) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $db->query("UPDATE ?n SET NotificationRead = 1 WHERE NotificationUserID = ?i AND NotificationRead = 0",
        $config["platform"]["sql"]["tbl"]["Notification"], $userId);
    return createApiSuccessResponse(["marked" => $db->affectedRows()]);
}

function notificationPreferences($parameter = []) {
    global $config;
    $userId = notificationRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $tbl = $config["platform"]["sql"]["tbl"]["NotificationPreference"];
    $pref = ensureNotificationPreference($userId, $db);

    // Treat any of the writable params being present as an update (POST).
    $writable = ["emailEnabled", "digestFrequency", "digestDay"];
    $hasUpdate = false;
    foreach ($writable as $k) { if (isset($parameter[$k])) { $hasUpdate = true; break; } }

    if ($hasUpdate) {
        $set = [];
        if (isset($parameter["emailEnabled"])) {
            $set["NotificationPreferenceEmailEnabled"] = (int)(bool)json_decode((string)$parameter["emailEnabled"]);
        }
        if (isset($parameter["digestFrequency"]) && in_array($parameter["digestFrequency"], ["daily", "weekly"], true)) {
            $set["NotificationPreferenceDigestFrequency"] = $parameter["digestFrequency"];
        }
        if (isset($parameter["digestDay"])) {
            $set["NotificationPreferenceDigestDay"] = max(1, min(7, (int)$parameter["digestDay"]));
        }
        if (!empty($set)) {
            $db->query("UPDATE ?n SET ?u WHERE NotificationPreferenceUserID = ?i", $tbl, $set, $userId);
            $pref = $db->getRow("SELECT * FROM ?n WHERE NotificationPreferenceUserID = ?i", $tbl, $userId);
        }
    }

    return createApiSuccessResponse([
        "emailEnabled" => (bool)$pref["NotificationPreferenceEmailEnabled"],
        "digestFrequency" => $pref["NotificationPreferenceDigestFrequency"],
        "digestDay" => (int)$pref["NotificationPreferenceDigestDay"],
    ]);
}

/**
 * Public, no-auth one-click unsubscribe by token (disables email for that user).
 */
function notificationUnsubscribe($parameter = []) {
    global $config;
    $token = isset($parameter["token"]) ? trim((string)$parameter["token"]) : "";
    if ($token === "") { return createApiErrorMissingParameter("token"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $tbl = $config["platform"]["sql"]["tbl"]["NotificationPreference"];
    $pref = $db->getRow("SELECT * FROM ?n WHERE NotificationPreferenceUnsubscribeToken = ?s", $tbl, $token);
    if (!$pref) { return createApiErrorNotFound("notification_preference"); }

    $db->query("UPDATE ?n SET NotificationPreferenceEmailEnabled = 0 WHERE NotificationPreferenceUnsubscribeToken = ?s",
        $tbl, $token);
    return createApiSuccessResponse(["unsubscribed" => true]);
}

/**
 * Admin-only: run alert matching on demand over the most recent N media items of a
 * parliament (the live-test trigger). Delegates to the matching engine.
 */
function notificationRunMatch($parameter = []) {
    if (!notificationCurrentUserIsAdmin()) {
        return createApiErrorResponse(403, 1, "messageAuthNotPermittedTitle", "messageAuthNotPermittedDetail");
    }
    $parliament = isset($parameter["parliament"]) ? trim((string)$parameter["parliament"]) : "";
    if ($parliament === "") { return createApiErrorMissingParameter("parliament"); }
    $last = isset($parameter["last"]) ? max(1, min(500, (int)$parameter["last"])) : 50;

    require_once(__DIR__ . "/../../../modules/notifications/alert-matcher.php");
    $result = runAlertMatchingForRecent($parliament, $last);
    return createApiSuccessResponse($result);
}
