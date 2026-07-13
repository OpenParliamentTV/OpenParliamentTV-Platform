<?php
/**
 * Public API key module.
 *
 * An API key grants a client a RAISED rate limit on the public read endpoints.
 * It is NOT an authentication mechanism: it unlocks no admin action, and the
 * public API stays reachable without one. Admin actions remain session-gated.
 *
 * Only a sha256 hash of the secret is stored. The raw key is returned exactly
 * once, from apiKeyAdd(), and can never be recovered afterwards.
 *
 * Routed from api/v1/api.php as itemType "apiKey" on the private admin actions
 * getItemsFromDB | addItem | changeItem | deleteItem. Those actions are absent
 * from the apiV1 whitelist in modules/utilities/auth.php, so the entry point
 * already restricts them to admins; apiKeyRequireAdmin() additionally guards the
 * internal apiV1() callers, which bypass that gate.
 *
 * apiKeyResolve() is the read path used by the rate limiter on every keyed
 * request, so this module deliberately depends on nothing beyond utilities.php.
 */

require_once(__DIR__ . "/../utilities.php");

/** Prefix identifying an Open Parliament TV key, and stored non-secret in ApiKeyPrefix. */
define("APIKEY_PREFIX", "optv_");

/** Length of the non-secret identifying prefix kept in ApiKeyPrefix (fits varchar(16)). */
define("APIKEY_PREFIX_LENGTH", 13);

function apiKeyRequireAdmin() {
    $isAdmin = isset($_SESSION["userdata"]["role"]) && $_SESSION["userdata"]["role"] === "admin";
    if (!$isAdmin) {
        return createApiErrorResponse(403, 1, "messageAuthNotPermittedTitle", "messageAuthNotPermittedDetail");
    }
    return true;
}

/** Hash a raw key for storage/lookup. The secret is 192 bits of CSPRNG output, so a plain sha256 (not a KDF) is appropriate. */
function apiKeyHash($rawKey) {
    return hash("sha256", $rawKey);
}

/**
 * Mint a new secret. Returns [raw, prefix, hash]; only prefix + hash are ever persisted.
 */
function apiKeyGenerate() {
    $raw = APIKEY_PREFIX . bin2hex(random_bytes(24));
    return [$raw, substr($raw, 0, APIKEY_PREFIX_LENGTH), apiKeyHash($raw)];
}

/**
 * Normalize an optional expiry into a DATETIME string, or null when unset.
 * Returns false when the given value cannot be parsed.
 */
function apiKeyParseExpires($value) {
    if ($value === null || trim((string)$value) === "") {
        return null;
    }
    $timestamp = strtotime((string)$value);
    if ($timestamp === false) {
        return false;
    }
    return date("Y-m-d H:i:s", $timestamp);
}

/**
 * Resolve a raw key presented by a client to its active, unexpired record.
 * Returns ["id" => int, "rateLimit" => int|null] or null when the key is unknown,
 * revoked or expired. Lookup is by the UNIQUE hash column, so no timing-safe
 * comparison is required — no secret is compared byte by byte here.
 */
function apiKeyResolve($rawKey, $db) {
    global $config;

    if (!is_string($rawKey) || $rawKey === "" || !($db instanceof SafeMySQL)) {
        return null;
    }

    $row = $db->getRow(
        "SELECT ApiKeyID, ApiKeyRateLimit FROM ?n
         WHERE ApiKeyHash = ?s AND ApiKeyActive = 1
           AND (ApiKeyExpires IS NULL OR ApiKeyExpires > NOW())",
        $config["platform"]["sql"]["tbl"]["ApiKey"],
        apiKeyHash($rawKey)
    );

    if (!$row) {
        return null;
    }

    return [
        "id" => (int)$row["ApiKeyID"],
        "rateLimit" => $row["ApiKeyRateLimit"] === null ? null : (int)$row["ApiKeyRateLimit"],
    ];
}

/**
 * Record usage, at most once per minute per key, so this does not become a write
 * on every single request.
 */
function apiKeyTouchLastUsed($apiKeyID, $db) {
    global $config;

    if (!($db instanceof SafeMySQL)) {
        return;
    }

    try {
        $db->query(
            "UPDATE ?n SET ApiKeyLastUsed = NOW()
             WHERE ApiKeyID = ?i
               AND (ApiKeyLastUsed IS NULL OR ApiKeyLastUsed < NOW() - INTERVAL 60 SECOND)",
            $config["platform"]["sql"]["tbl"]["ApiKey"],
            $apiKeyID
        );
    } catch (Exception $e) {
        // Usage tracking must never break a request.
    }
}

/**
 * List keys for the admin table. Mirrors userGetItemsFromDB()'s contract, which
 * bootstrap-table's responseHandler depends on: total at the root, raw rows in data.
 *
 * ApiKeyHash is never selected — the stored secret hash must not reach a client.
 */
function apiKeyGetItemsFromDB($id = "all", $limit = 10, $offset = 0, $search = false, $sort = false, $order = false) {
    global $config;

    $admin = apiKeyRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db;
    }

    $tblKey = $config["platform"]["sql"]["tbl"]["ApiKey"];
    $tblUser = $config["platform"]["sql"]["tbl"]["User"];

    if ($id == "all") {
        $where = "1";
    } else {
        $where = $db->parse("k.ApiKeyID=?i", $id);
    }

    if (!empty($search)) {
        $where .= $db->parse(
            " AND (LOWER(k.ApiKeyLabel) LIKE LOWER(?s) OR LOWER(k.ApiKeyPrefix) LIKE LOWER(?s) OR LOWER(u.UserName) LIKE LOWER(?s))",
            "%".$search."%",
            "%".$search."%",
            "%".$search."%"
        );
    }

    // Allowlist maps the client-supplied sort field onto a literal, qualified column.
    // ?n cannot be used here because it would backtick the alias-qualified name as a whole.
    $sortable = [
        "ApiKeyPrefix" => "k.ApiKeyPrefix",
        "ApiKeyLabel" => "k.ApiKeyLabel",
        "ApiKeyOwnerName" => "u.UserName",
        "ApiKeyRateLimit" => "k.ApiKeyRateLimit",
        "ApiKeyActive" => "k.ApiKeyActive",
        "ApiKeyCreated" => "k.ApiKeyCreated",
        "ApiKeyExpires" => "k.ApiKeyExpires",
        "ApiKeyLastUsed" => "k.ApiKeyLastUsed",
    ];

    $orderLimit = "";
    if (!empty($sort) && isset($sortable[$sort])) {
        $direction = (strtolower((string)$order) === "asc") ? "ASC" : "DESC";
        $orderLimit .= " ORDER BY ".$sortable[$sort]." ".$direction;
    } else {
        $orderLimit .= " ORDER BY k.ApiKeyCreated DESC";
    }

    if ($limit != 0) {
        $orderLimit .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
    }

    // Count under the same filter as the rows, so paging stays correct while searching.
    $total = $db->getOne(
        "SELECT COUNT(k.ApiKeyID) FROM ?n k LEFT JOIN ?n u ON u.UserID = k.ApiKeyOwnerUserID WHERE ?p",
        $tblKey, $tblUser, $where
    );

    $rows = $db->getAll(
        "SELECT k.ApiKeyID, k.ApiKeyPrefix, k.ApiKeyLabel, k.ApiKeyOwnerUserID,
                k.ApiKeyRateLimit, k.ApiKeyActive, k.ApiKeyCreated, k.ApiKeyExpires,
                k.ApiKeyLastUsed, u.UserName AS ApiKeyOwnerName
         FROM ?n k LEFT JOIN ?n u ON u.UserID = k.ApiKeyOwnerUserID
         WHERE ?p",
        $tblKey, $tblUser, $where.$orderLimit
    );

    foreach ($rows as &$row) {
        $row["ApiKeyActive"] = (bool)$row["ApiKeyActive"];
        $row["ApiKeyRateLimit"] = $row["ApiKeyRateLimit"] === null ? null : (int)$row["ApiKeyRateLimit"];
    }
    unset($row);

    return [
        "meta" => [
            "requestStatus" => "success"
        ],
        "total" => $total,
        "data" => $rows
    ];
}

/**
 * Issue a new key. The raw secret is returned here and nowhere else — it is not
 * stored and cannot be recovered, so the caller must surface it to the admin now.
 */
function apiKeyAdd($parameter = [], $db = false) {
    global $config;

    $admin = apiKeyRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $label = isset($parameter["ApiKeyLabel"]) ? trim((string)$parameter["ApiKeyLabel"]) : "";
    if ($label === "") {
        return createApiErrorMissingParameter("ApiKeyLabel");
    }
    $label = mb_substr($label, 0, 191);

    $ownerUserID = (isset($parameter["ApiKeyOwnerUserID"]) && $parameter["ApiKeyOwnerUserID"] !== "")
        ? (int)$parameter["ApiKeyOwnerUserID"]
        : null;

    $rateLimit = (isset($parameter["ApiKeyRateLimit"]) && $parameter["ApiKeyRateLimit"] !== "")
        ? (int)$parameter["ApiKeyRateLimit"]
        : null;
    if ($rateLimit !== null && $rateLimit < 1) {
        return createApiErrorInvalidParameter("ApiKeyRateLimit");
    }

    $expires = apiKeyParseExpires($parameter["ApiKeyExpires"] ?? null);
    if ($expires === false) {
        return createApiErrorInvalidParameter("ApiKeyExpires");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    list($rawKey, $prefix, $hash) = apiKeyGenerate();

    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["ApiKey"], [
        "ApiKeyHash" => $hash,
        "ApiKeyPrefix" => $prefix,
        "ApiKeyLabel" => $label,
        "ApiKeyOwnerUserID" => $ownerUserID,
        "ApiKeyRateLimit" => $rateLimit,
        "ApiKeyExpires" => $expires,
        "ApiKeyActive" => 1,
    ]);

    return createApiSuccessResponse([
        "type" => "apiKey",
        "id" => (int)$db->insertId(),
        "attributes" => [
            // Shown to the admin once; never retrievable again.
            "key" => $rawKey,
            "prefix" => $prefix,
            "label" => $label,
            "rateLimit" => $rateLimit,
            "expires" => $expires,
        ],
    ]);
}

/**
 * Update a key's metadata. Setting ApiKeyActive to 0 is the revoke path.
 * The stored hash can never be changed here.
 */
function apiKeyChange($parameter = [], $db = false) {
    global $config;

    $admin = apiKeyRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $id = isset($parameter["id"]) ? (int)$parameter["id"] : 0;
    if ($id < 1) {
        return createApiErrorMissingParameter("id");
    }

    $update = [];

    if (isset($parameter["ApiKeyLabel"])) {
        $label = trim((string)$parameter["ApiKeyLabel"]);
        if ($label === "") {
            return createApiErrorInvalidParameter("ApiKeyLabel");
        }
        $update["ApiKeyLabel"] = mb_substr($label, 0, 191);
    }

    if (isset($parameter["ApiKeyOwnerUserID"])) {
        $update["ApiKeyOwnerUserID"] = ($parameter["ApiKeyOwnerUserID"] === "")
            ? null
            : (int)$parameter["ApiKeyOwnerUserID"];
    }

    if (isset($parameter["ApiKeyRateLimit"])) {
        if ($parameter["ApiKeyRateLimit"] === "") {
            $update["ApiKeyRateLimit"] = null;
        } else {
            $rateLimit = (int)$parameter["ApiKeyRateLimit"];
            if ($rateLimit < 1) {
                return createApiErrorInvalidParameter("ApiKeyRateLimit");
            }
            $update["ApiKeyRateLimit"] = $rateLimit;
        }
    }

    if (isset($parameter["ApiKeyExpires"])) {
        $expires = apiKeyParseExpires($parameter["ApiKeyExpires"]);
        if ($expires === false) {
            return createApiErrorInvalidParameter("ApiKeyExpires");
        }
        $update["ApiKeyExpires"] = $expires;
    }

    if (isset($parameter["ApiKeyActive"])) {
        $update["ApiKeyActive"] = (int)(bool)json_decode((string)$parameter["ApiKeyActive"]);
    }

    if (empty($update)) {
        return createApiErrorMissingParameter();
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["ApiKey"];

    if (!$db->getOne("SELECT ApiKeyID FROM ?n WHERE ApiKeyID = ?i", $tbl, $id)) {
        return createApiErrorNotFound("apiKey");
    }

    $db->query("UPDATE ?n SET ?u WHERE ApiKeyID = ?i", $tbl, $update, $id);

    return createApiSuccessResponse(["type" => "apiKey", "id" => $id]);
}

function apiKeyDelete($parameter = [], $db = false) {
    global $config;

    $admin = apiKeyRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $id = isset($parameter["id"]) ? (int)$parameter["id"] : 0;
    if ($id < 1) {
        return createApiErrorMissingParameter("id");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["ApiKey"];

    if (!$db->getOne("SELECT ApiKeyID FROM ?n WHERE ApiKeyID = ?i", $tbl, $id)) {
        return createApiErrorNotFound("apiKey");
    }

    $db->query("DELETE FROM ?n WHERE ApiKeyID = ?i", $tbl, $id);

    return createApiSuccessResponse(["type" => "apiKey", "id" => $id]);
}
