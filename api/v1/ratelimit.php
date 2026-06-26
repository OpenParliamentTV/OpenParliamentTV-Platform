<?php
/**
 * Lightweight per-client request rate limiter for the external API HTTP entry
 * point (api/v1/index.php). Internal apiV1() callers (web page handlers) never
 * reach this code, so normal page rendering is unaffected.
 *
 * DB-backed fixed window (no APCu/Redis available in this environment).
 * Fails open: if the limiter or its DB has any problem, traffic is allowed.
 *
 * Depends on getApiDatabaseConnection() + createApiErrorResponse() + SafeMySQL,
 * which are loaded via api/v1/api.php (utilities.php + safemysql.class.php).
 */

/**
 * @return array|null The standardized 429 error array when the client exceeded
 *                    the configured limit, or null when the request is allowed.
 */
function apiRateLimitCheck($action)
{
    global $config;

    $cfg = $config["api"]["rateLimit"] ?? null;
    if (!$cfg || empty($cfg["enabled"])) {
        return null;
    }

    // High-frequency, UI-driven actions are not throttled.
    if (in_array($action, $cfg["exemptActions"] ?? [], true)) {
        return null;
    }

    // Authenticated users (logged in) are exempt.
    if (!empty($_SESSION["userdata"]["id"])) {
        return null;
    }

    $ip = apiRateLimitClientIp(!empty($cfg["trustProxy"]));

    // Internal / allowlisted IPs are never limited.
    if (in_array($ip, $cfg["exemptIPs"] ?? [], true)) {
        return null;
    }

    $window = max(1, (int) ($cfg["window"] ?? 60));
    $limit  = max(1, (int) ($cfg["limit"] ?? 240));
    $windowStart = (int) (floor(time() / $window) * $window);
    $key = sha1(($config["salt"] ?? '') . '|' . $ip);

    $db = getApiDatabaseConnection('platform');
    if (!($db instanceof SafeMySQL)) {
        return null; // fail open
    }
    $tbl = $config["platform"]["sql"]["tbl"]["ApiRateLimit"] ?? "apiratelimit";

    try {
        // Atomic increment within the current window; reset when the window rolled over.
        $db->query(
            "INSERT INTO ?n (RateLimitKey, RateLimitWindowStart, RateLimitCount) VALUES (?s, ?i, 1)
             ON DUPLICATE KEY UPDATE
               RateLimitCount = IF(RateLimitWindowStart = ?i, RateLimitCount + 1, 1),
               RateLimitWindowStart = ?i",
            $tbl, $key, $windowStart, $windowStart, $windowStart
        );
        $count = (int) $db->getOne(
            "SELECT RateLimitCount FROM ?n WHERE RateLimitKey = ?s",
            $tbl, $key
        );
    } catch (Exception $e) {
        return null; // fail open
    }

    // Opportunistic cleanup of stale rows (~1% of requests).
    if (mt_rand(1, 100) === 1) {
        try {
            $db->query("DELETE FROM ?n WHERE RateLimitWindowStart < ?i", $tbl, $windowStart - $window);
        } catch (Exception $e) {
            // ignore — housekeeping only
        }
    }

    if ($count > $limit) {
        $retryAfter = ($windowStart + $window) - time();
        if ($retryAfter < 1) {
            $retryAfter = 1;
        }
        header('Retry-After: ' . $retryAfter);
        return createApiErrorResponse(
            429,
            "RATE_LIMIT_EXCEEDED",
            "messageErrorRateLimited",
            "messageErrorRateLimited",
            ["seconds" => $retryAfter],
            null,
            ["retryAfter" => $retryAfter]
        );
    }

    return null;
}

/** Resolve the client IP, optionally trusting proxy headers. */
function apiRateLimitClientIp($trustProxy)
{
    if ($trustProxy) {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
