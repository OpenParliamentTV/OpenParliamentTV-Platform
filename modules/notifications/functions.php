<?php
/**
 * Shared helpers for the notifications & alerts feature (Plan B).
 *
 * Pure-ish helpers used by the API modules (alert.php, notification.php) and the
 * matching engine (alert-matcher.php). Database access goes through the platform
 * connection (SafeMySQL) since all notification tables live in the platform DB.
 */

require_once(__DIR__ . "/../../api/v1/utilities.php");

/**
 * Criteria keys an alert may carry. Mirrors the search-API parameter names so an
 * alert's criteria == a search URL == an RSS feed query, round-trippable.
 * Entity keys are multi-value (OR within), the rest are scalar.
 */
function alertCriteriaEntityKeys() {
    return ["personID", "organisationID", "termID", "documentID"];
}
function alertCriteriaScalarKeys() {
    return ["q", "electoralPeriodID", "parliament", "context"];
}

/**
 * Normalize + canonicalize raw criteria into a stable associative array:
 * entity keys become sorted, de-duplicated, non-empty string arrays; scalar keys
 * become trimmed non-empty strings. Empty values are dropped entirely. The result
 * is order-independent so two equivalent criteria sets compare equal.
 *
 * @return array canonical criteria (may be empty)
 */
function normalizeAlertCriteria($raw) {
    if (!is_array($raw)) {
        return [];
    }
    $out = [];

    foreach (alertCriteriaEntityKeys() as $key) {
        if (!isset($raw[$key])) {
            continue;
        }
        $vals = is_array($raw[$key]) ? $raw[$key] : [$raw[$key]];
        $vals = array_values(array_unique(array_filter(array_map(function ($v) {
            return is_scalar($v) ? trim((string)$v) : "";
        }, $vals), function ($v) {
            return $v !== "";
        })));
        if (!empty($vals)) {
            sort($vals);
            $out[$key] = $vals;
        }
    }

    foreach (alertCriteriaScalarKeys() as $key) {
        if (!isset($raw[$key]) || !is_scalar($raw[$key])) {
            continue;
        }
        $v = trim((string)$raw[$key]);
        if ($v !== "") {
            $out[$key] = $v;
        }
    }

    ksort($out);
    return $out;
}

/**
 * Canonical JSON string for a criteria array, used for equality comparison
 * (e.g. "is this search already an alert?").
 */
function alertCriteriaCanonicalJson($criteria) {
    return json_encode(normalizeAlertCriteria($criteria), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Human-readable one-line summary of criteria for default alert labels / display.
 */
function alertCriteriaSummary($criteria) {
    $c = normalizeAlertCriteria($criteria);
    $parts = [];
    if (!empty($c["q"])) {
        $parts[] = '"' . $c["q"] . '"';
    }
    foreach (alertCriteriaEntityKeys() as $key) {
        if (!empty($c[$key])) {
            $parts[] = $key . ": " . implode(", ", $c[$key]);
        }
    }
    return $parts ? implode(" · ", $parts) : "";
}

/**
 * Current logged-in user id from session, or null.
 */
function notificationCurrentUserId() {
    return isset($_SESSION["userdata"]["id"]) && $_SESSION["userdata"]["id"]
        ? (int)$_SESSION["userdata"]["id"]
        : null;
}

/**
 * Is the current session an admin?
 */
function notificationCurrentUserIsAdmin() {
    return isset($_SESSION["userdata"]["role"]) && $_SESSION["userdata"]["role"] === "admin";
}

/**
 * Ensure a notification_preference row exists for the user (lazy creation),
 * returning the row. Generates an unsubscribe token on first creation.
 */
function ensureNotificationPreference($userId, $db) {
    global $config;
    $tbl = $config["platform"]["sql"]["tbl"]["NotificationPreference"];

    $pref = $db->getRow("SELECT * FROM ?n WHERE NotificationPreferenceUserID = ?i", $tbl, $userId);
    if ($pref) {
        return $pref;
    }

    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $db->query(
        "INSERT INTO ?n SET NotificationPreferenceUserID = ?i, NotificationPreferenceUnsubscribeToken = ?s",
        $tbl, $userId, $token
    );
    return $db->getRow("SELECT * FROM ?n WHERE NotificationPreferenceUserID = ?i", $tbl, $userId);
}

/**
 * Insert a notification, deduplicated on (AlertID, MediaID) for alert matches.
 * Returns true if a new row was inserted, false if it already existed.
 */
function createNotification($db, $fields) {
    global $config;
    $tbl = $config["platform"]["sql"]["tbl"]["Notification"];

    $row = [
        "NotificationUserID"   => $fields["userID"],
        "NotificationAlertID"  => $fields["alertID"] ?? null,
        "NotificationType"     => $fields["type"],
        "NotificationTitle"    => $fields["title"],
        "NotificationBody"     => $fields["body"] ?? null,
        "NotificationLink"     => $fields["link"] ?? null,
        "NotificationMediaID"  => $fields["mediaID"] ?? null,
        "NotificationParliament" => $fields["parliament"] ?? null,
    ];

    // INSERT IGNORE relies on the unique (AlertID, MediaID) key to dedup alert matches.
    $db->query("INSERT IGNORE INTO ?n SET ?u", $tbl, $row);
    return $db->affectedRows() > 0;
}
