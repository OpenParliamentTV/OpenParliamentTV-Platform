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
 * Entity keys are multi-value (OR within), the rest are scalar. Entity tokens may
 * carry a per-entity role as "<id>~<role>" (see alertSplitRoleToken()).
 */
function alertCriteriaEntityKeys() {
    return ["personID", "organisationID", "factionID", "termID", "documentID"];
}
function alertCriteriaScalarKeys() {
    return ["q", "electoralPeriodID", "parliament", "context"];
}

/**
 * Default per-entity role (context) for an entity key, mirroring the search
 * backend's determineDefaultContext(). When a token's role equals this default it
 * is dropped during normalization so "Q1~main-speaker" and "Q1" compare equal.
 * Empty string means "no implied default" (match any context) — never stripped.
 */
function alertEntityKeyDefaultRole($key) {
    switch ($key) {
        case "personID":  return "main-speaker";
        case "factionID": return "main-speaker-faction";
        case "partyID":   return "main-speaker-party";
        default:          return ""; // organisationID, termID, documentID
    }
}

/**
 * Split an entity criteria token "<id>~<role>" into [id, role]; role is null when
 * the token carries no "~role" suffix. Mirrors parseEntityRoleToken() in the
 * search backend so criteria, search URLs and the index all speak the same dialect.
 *
 * @return array [string $id, string|null $role]
 */
function alertSplitRoleToken($token) {
    $token = (string)$token;
    $pos = strpos($token, "~");
    if ($pos === false) {
        return [$token, null];
    }
    return [substr($token, 0, $pos), substr($token, $pos + 1)];
}

/**
 * Canonicalize a single entity token: trim, and drop a "~role" suffix that merely
 * restates the key's default role (so equivalent tokens compare equal).
 */
function alertCanonicalEntityToken($key, $token) {
    list($id, $role) = alertSplitRoleToken(trim((string)$token));
    if ($id === "") {
        return "";
    }
    $default = alertEntityKeyDefaultRole($key);
    if ($role === null || $role === "" || ($default !== "" && $role === $default)) {
        return $id;
    }
    return $id . "~" . $role;
}

/**
 * Normalize + canonicalize raw criteria into a stable associative array:
 * entity keys become sorted, de-duplicated, non-empty string arrays; scalar keys
 * become trimmed non-empty strings. Empty values (and the presentation-only
 * `_labels` sidecar) are dropped entirely. The result is order-independent so two
 * equivalent criteria sets compare equal regardless of label snapshots.
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
        $vals = array_values(array_unique(array_filter(array_map(function ($v) use ($key) {
            return is_scalar($v) ? alertCanonicalEntityToken($key, $v) : "";
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
 * (e.g. "is this search already an alert?"). Ignores the `_labels` sidecar.
 */
function alertCriteriaCanonicalJson($criteria) {
    return json_encode(normalizeAlertCriteria($criteria), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Sanitize a raw `_labels` sidecar (id => {label, faction, color}) down to scalar
 * strings, keeping only entries actually referenced by the normalized criteria.
 * This snapshot lets the UI render person names / party colors without live lookups.
 */
function alertCriteriaCleanLabels($raw, $normalized) {
    if (!is_array($raw) || empty($raw["_labels"]) || !is_array($raw["_labels"])) {
        return [];
    }

    // Collect referenced ids (entity tokens, role suffix stripped).
    $referenced = [];
    foreach (alertCriteriaEntityKeys() as $key) {
        if (empty($normalized[$key])) { continue; }
        foreach ($normalized[$key] as $token) {
            list($id) = alertSplitRoleToken($token);
            $referenced[$id] = true;
        }
    }

    $out = [];
    foreach ($raw["_labels"] as $id => $meta) {
        $id = (string)$id;
        if (!isset($referenced[$id]) || !is_array($meta)) { continue; }
        $entry = [];
        foreach (["label", "type", "faction", "factionLabel", "color"] as $field) {
            if (isset($meta[$field]) && is_scalar($meta[$field]) && trim((string)$meta[$field]) !== "") {
                $entry[$field] = trim((string)$meta[$field]);
            }
        }
        if (!empty($entry)) {
            $out[$id] = $entry;
        }
    }
    return $out;
}

/**
 * Storage form of criteria: the canonical criteria plus a cleaned `_labels`
 * snapshot. This is what gets persisted in AlertCriteria so lists/notifications
 * can render chips offline. Equality/dedup still goes through the canonical form.
 */
function alertCriteriaForStorage($raw) {
    $normalized = normalizeAlertCriteria($raw);
    if (empty($normalized)) {
        return [];
    }
    $labels = alertCriteriaCleanLabels($raw, $normalized);
    if (!empty($labels)) {
        $normalized["_labels"] = $labels;
    }
    return $normalized;
}

function alertCriteriaStorageJson($raw) {
    return json_encode(alertCriteriaForStorage($raw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Human-readable one-line summary of criteria for default alert labels, emails and
 * any non-JS context. Uses the `_labels` snapshot for friendly names when present.
 */
function alertCriteriaSummary($criteria) {
    $c = normalizeAlertCriteria($criteria);
    $labels = (is_array($criteria) && !empty($criteria["_labels"]) && is_array($criteria["_labels"]))
        ? $criteria["_labels"] : [];
    $parts = [];
    if (!empty($c["q"])) {
        $parts[] = '"' . $c["q"] . '"';
    }
    foreach (alertCriteriaEntityKeys() as $key) {
        if (empty($c[$key])) { continue; }
        $names = array_map(function ($token) use ($labels) {
            list($id) = alertSplitRoleToken($token);
            return isset($labels[$id]["label"]) ? $labels[$id]["label"] : $id;
        }, $c[$key]);
        $parts[] = implode(", ", $names);
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
