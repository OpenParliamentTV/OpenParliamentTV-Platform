<?php
/**
 * API-side wrapper around the shared per-client rate limiter
 * (modules/utilities/ratelimit.php). Only the external API HTTP entry point
 * (api/v1/index.php) calls this; internal apiV1() callers bypass it entirely.
 *
 * Adds the API-specific concern (exemptActions) on top of the shared core and
 * wraps a limit breach in the standardized JSON error response.
 *
 * Depends on createApiErrorResponse() (api/v1/utilities.php via api/v1/api.php).
 */

require_once(__DIR__ . "/../../modules/utilities/ratelimit.php");

/**
 * @return array|null The standardized 429 error array when the client exceeded
 *                    the configured limit, or null when the request is allowed.
 */
function apiRateLimitCheck($action)
{
    global $config;

    $cfg = $config["rateLimit"]["api"] ?? null;
    if (!$cfg || empty($cfg["enabled"])) {
        return null;
    }

    // High-frequency, UI-driven actions are not throttled.
    if (in_array($action, $cfg["exemptActions"] ?? [], true)) {
        return null;
    }

    $retryAfter = optvRateLimitExceeded($cfg, 'api');
    if ($retryAfter === null) {
        return null;
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
