<?php
/**
 * Alert CRUD API module (Plan B — notifications & alerts).
 *
 * An *alert* is a standing rule a user creates ("notify me about speeches matching
 * these search criteria"). All alerts live in the platform DB. Every endpoint here
 * operates on the current logged-in user's own alerts; anonymous callers are rejected.
 *
 * Routed from api/v1/api.php: action=alert, itemType=list|get|create|update|delete|status
 */

require_once(__DIR__ . "/../utilities.php");
require_once(__DIR__ . "/../../../modules/notifications/functions.php");

define("ALERT_MAX_PER_USER", 50);

function alertRequireUser() {
    $userId = notificationCurrentUserId();
    if (!$userId) {
        return createApiErrorResponse(401, 1, "messageAuthLoginRequiredTitle", "messageAuthLoginRequiredDetail");
    }
    return $userId;
}

/**
 * Map a DB row to an API object (decoding criteria JSON, adding a summary).
 */
function alertRowToObject($row) {
    $criteria = json_decode($row["AlertCriteria"], true);
    if (!is_array($criteria)) {
        $criteria = [];
    }
    return [
        "type" => "alert",
        "id" => (int)$row["AlertID"],
        "attributes" => [
            "label" => $row["AlertLabel"],
            "criteria" => $criteria,
            "criteriaSummary" => alertCriteriaSummary($criteria),
            "frequency" => $row["AlertFrequency"],
            "channelEmail" => (bool)$row["AlertChannelEmail"],
            "channelInApp" => (bool)$row["AlertChannelInApp"],
            "active" => (bool)$row["AlertActive"],
            "created" => $row["AlertCreated"],
            "lastTriggered" => $row["AlertLastTriggered"],
            "lastChanged" => $row["AlertLastChanged"],
        ],
    ];
}

/**
 * Extract criteria from a request: accepts a `criteria` param (JSON string or array)
 * or falls back to top-level search-style params on the request itself.
 */
function alertExtractCriteria($parameter) {
    $raw = [];
    if (isset($parameter["criteria"])) {
        $raw = is_array($parameter["criteria"]) ? $parameter["criteria"] : json_decode($parameter["criteria"], true);
    } else {
        $raw = $parameter; // pick the known keys out of the request directly
    }
    return normalizeAlertCriteria($raw);
}

function alertList($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $rows = $db->getAll(
        "SELECT * FROM ?n WHERE AlertUserID = ?i ORDER BY AlertCreated DESC",
        $config["platform"]["sql"]["tbl"]["Alert"], $userId
    );
    $out = array_map('alertRowToObject', $rows ?: []);
    return createApiSuccessResponse($out, [], null, null, count($out));
}

function alertGet($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $id = isset($parameter["id"]) ? (int)$parameter["id"] : 0;
    if (!$id) { return createApiErrorMissingParameter("id"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = $db->getRow(
        "SELECT * FROM ?n WHERE AlertID = ?i AND AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $id, $userId
    );
    if (!$row) { return createApiErrorNotFound("alert"); }
    return createApiSuccessResponse(alertRowToObject($row));
}

function alertCreate($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $criteria = alertExtractCriteria($parameter);
    if (empty($criteria)) {
        return createApiErrorResponse(422, 1, "messageErrorMissingParameter", "messageAlertCriteriaEmpty");
    }

    // Cap alerts per user.
    $count = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $userId);
    if ($count >= ALERT_MAX_PER_USER) {
        return createApiErrorResponse(429, 1, "messageAlertLimitTitle", "messageAlertLimitDetail");
    }

    $label = isset($parameter["label"]) ? trim((string)$parameter["label"]) : "";
    if ($label === "") {
        $label = alertCriteriaSummary($criteria) ?: "Alert";
    }
    $label = mb_substr($label, 0, 255);

    $frequency = $parameter["frequency"] ?? "realtime";
    if (!in_array($frequency, ["realtime", "daily", "weekly"], true)) {
        $frequency = "realtime";
    }
    $channelEmail = isset($parameter["channelEmail"]) ? (int)(bool)json_decode((string)$parameter["channelEmail"]) : 1;
    $channelInApp = isset($parameter["channelInApp"]) ? (int)(bool)json_decode((string)$parameter["channelInApp"]) : 1;

    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Alert"], [
        "AlertUserID" => $userId,
        "AlertLabel" => $label,
        "AlertCriteria" => alertCriteriaCanonicalJson($criteria),
        "AlertFrequency" => $frequency,
        "AlertChannelEmail" => $channelEmail,
        "AlertChannelInApp" => $channelInApp,
        "AlertActive" => 1,
    ]);
    $newId = $db->insertId();

    // Make sure the user has a preferences row.
    ensureNotificationPreference($userId, $db);

    $row = $db->getRow("SELECT * FROM ?n WHERE AlertID = ?i", $config["platform"]["sql"]["tbl"]["Alert"], $newId);
    return createApiSuccessResponse(alertRowToObject($row));
}

function alertUpdate($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $id = isset($parameter["id"]) ? (int)$parameter["id"] : 0;
    if (!$id) { return createApiErrorMissingParameter("id"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = $db->getRow("SELECT * FROM ?n WHERE AlertID = ?i AND AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $id, $userId);
    if (!$row) { return createApiErrorNotFound("alert"); }

    $set = [];
    if (isset($parameter["label"])) {
        $set["AlertLabel"] = mb_substr(trim((string)$parameter["label"]), 0, 255);
    }
    if (isset($parameter["frequency"]) && in_array($parameter["frequency"], ["realtime", "daily", "weekly"], true)) {
        $set["AlertFrequency"] = $parameter["frequency"];
    }
    if (isset($parameter["channelEmail"])) {
        $set["AlertChannelEmail"] = (int)(bool)json_decode((string)$parameter["channelEmail"]);
    }
    if (isset($parameter["channelInApp"])) {
        $set["AlertChannelInApp"] = (int)(bool)json_decode((string)$parameter["channelInApp"]);
    }
    if (isset($parameter["active"])) {
        $set["AlertActive"] = (int)(bool)json_decode((string)$parameter["active"]);
    }
    if (isset($parameter["criteria"])) {
        $criteria = alertExtractCriteria($parameter);
        if (empty($criteria)) {
            return createApiErrorResponse(422, 1, "messageErrorMissingParameter", "messageAlertCriteriaEmpty");
        }
        $set["AlertCriteria"] = alertCriteriaCanonicalJson($criteria);
    }

    if (empty($set)) {
        return createApiErrorMissingParameter("label");
    }

    $db->query("UPDATE ?n SET ?u WHERE AlertID = ?i AND AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $set, $id, $userId);

    $row = $db->getRow("SELECT * FROM ?n WHERE AlertID = ?i", $config["platform"]["sql"]["tbl"]["Alert"], $id);
    return createApiSuccessResponse(alertRowToObject($row));
}

function alertDelete($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $id = isset($parameter["id"]) ? (int)$parameter["id"] : 0;
    if (!$id) { return createApiErrorMissingParameter("id"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = $db->getRow("SELECT * FROM ?n WHERE AlertID = ?i AND AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $id, $userId);
    if (!$row) { return createApiErrorNotFound("alert"); }

    $db->query("DELETE FROM ?n WHERE AlertID = ?i AND AlertUserID = ?i",
        $config["platform"]["sql"]["tbl"]["Alert"], $id, $userId);

    return createApiSuccessResponse(["deleted" => $id]);
}

/**
 * Given criteria, report whether the current user already has an alert with the
 * same canonical criteria — drives the "Subscribed" button state.
 */
function alertStatus($parameter = []) {
    global $config;
    $userId = alertRequireUser();
    if (is_array($userId)) { return $userId; }

    $criteria = alertExtractCriteria($parameter);
    if (empty($criteria)) {
        return createApiSuccessResponse(["subscribed" => false, "alertID" => null]);
    }
    $canonical = alertCriteriaCanonicalJson($criteria);

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $match = $db->getOne(
        "SELECT AlertID FROM ?n WHERE AlertUserID = ?i AND AlertCriteria = ?s LIMIT 1",
        $config["platform"]["sql"]["tbl"]["Alert"], $userId, $canonical
    );

    return createApiSuccessResponse([
        "subscribed" => $match ? true : false,
        "alertID" => $match ? (int)$match : null,
    ]);
}
