<?php
/**
 * Shared per-client request rate limiter, used by both HTTP entry points:
 *   - api/v1/index.php (external API)   → namespace 'api'
 *   - index.php        (web page router) → namespace 'page'
 *
 * Internal apiV1() callers (web page handlers) never reach either limiter, so
 * server-side rendering / sub-request fan-out is unaffected.
 *
 * DB-backed fixed window (no APCu/Redis available in this environment).
 * Fails open: if the limiter or its DB has any problem, traffic is allowed.
 * The namespace keeps each surface's counters separate for the same client IP.
 *
 * Depends on getApiDatabaseConnection() + SafeMySQL, loaded via api/v1/api.php
 * (which api/v1/index.php includes, and index.php pulls in through
 * modules/routing/handlers.php).
 */

/**
 * Consume one request against the given config block within its namespace.
 *
 * @param array  $cfg       A rate-limit config block (enabled/window/limit/
 *                          trustProxy/exemptIPs — see $config["rateLimit"][*]).
 * @param string $namespace Counter namespace ('api' | 'page') keeping the two
 *                          surfaces' windows independent for one client IP.
 * @param string|null $identityOverride Bucket the window on this literal key
 *                          instead of the client IP (the API layer passes
 *                          'key:<ApiKeyID>' for an API-key holder). When set, the
 *                          session and IP-allowlist exemptions are deliberately
 *                          skipped: a key's tier must apply even when the caller
 *                          also happens to hold a login session.
 * @param int|null $limitOverride Per-window limit to apply instead of $cfg["limit"].
 * @return int|null The Retry-After seconds when the client exceeded the limit,
 *                  or null when the request is allowed.
 */
function optvRateLimitExceeded(array $cfg, $namespace, $identityOverride = null, $limitOverride = null)
{
    global $config;

    if (empty($cfg["enabled"])) {
        return null;
    }

    $keyed = ($identityOverride !== null && $identityOverride !== '');

    if (!$keyed) {
        // Authenticated users (logged in) are exempt.
        if (!empty($_SESSION["userdata"]["id"])) {
            return null;
        }

        $ip = optvRateLimitClientIp(!empty($cfg["trustProxy"]));

        // Internal / allowlisted IPs are never limited.
        if (in_array($ip, $cfg["exemptIPs"] ?? [], true)) {
            return null;
        }
    }

    $window = max(1, (int) ($cfg["window"] ?? 60));
    $limit  = max(1, (int) ($limitOverride !== null ? $limitOverride : ($cfg["limit"] ?? 240)));
    $windowStart = (int) (floor(time() / $window) * $window);
    $key = $keyed
        ? $identityOverride
        : sha1(($config["salt"] ?? '') . '|' . $namespace . '|' . $ip);

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
        return $retryAfter < 1 ? 1 : $retryAfter;
    }

    return null;
}

/** Resolve the client IP, optionally trusting proxy headers. */
function optvRateLimitClientIp($trustProxy)
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
