<?php
/**
 * API-side wrapper around the shared per-client rate limiter
 * (modules/utilities/ratelimit.php). Only the external API HTTP entry point
 * (api/v1/index.php) calls this; internal apiV1() callers bypass it entirely.
 *
 * Adds the two API-specific concerns on top of the shared core — exemptActions,
 * and API keys — and wraps a limit breach in the standardized JSON error response.
 *
 * An API key raises the caller's rate limit. It is not an authentication
 * mechanism: it grants no additional actions, and the public API remains usable
 * without one. See api/v1/modules/apiKey.php.
 *
 * Depends on createApiErrorResponse() (api/v1/utilities.php via api/v1/api.php).
 */

require_once(__DIR__ . "/../../modules/utilities/ratelimit.php");
require_once(__DIR__ . "/modules/apiKey.php");

/**
 * The raw key presented by the client, or null when none was sent.
 * The X-API-Key header is the documented path; the query parameter is a fallback
 * for clients that cannot set headers (it does leak the key into access logs).
 */
function apiRateLimitPresentedKey()
{
    if (!empty($_SERVER["HTTP_X_API_KEY"])) {
        return trim((string) $_SERVER["HTTP_X_API_KEY"]);
    }
    if (!empty($_REQUEST["apikey"])) {
        return trim((string) $_REQUEST["apikey"]);
    }
    return null;
}

/**
 * @return array|null The standardized error array when the request must be
 *                    rejected (429 over the limit, or 403 for a bad key), or
 *                    null when the request is allowed.
 */
function apiRateLimitCheck($action)
{
    global $config;

    $cfg = $config["rateLimit"]["api"] ?? null;
    if (!$cfg || empty($cfg["enabled"])) {
        return null;
    }

    $identity = null;
    $limit = null;

    // Resolve a presented key BEFORE the exemptActions and (inside the shared
    // core) the session/IP checks, so a key holder always lands in their own
    // tier and a broken key is always reported rather than silently downgraded.
    $rawKey = apiRateLimitPresentedKey();
    if ($rawKey !== null) {
        $db = getApiDatabaseConnection('platform');

        // No DB: the limiter fails open, so a key holder must not be rejected here either.
        if ($db instanceof SafeMySQL) {
            $apiKey = apiKeyResolve($rawKey, $db);

            if ($apiKey === null) {
                return createApiErrorResponse(
                    403,
                    "INVALID_API_KEY",
                    "messageErrorInvalidApiKeyTitle",
                    "messageErrorInvalidApiKeyDetail"
                );
            }

            apiKeyTouchLastUsed($apiKey["id"], $db);

            $identity = "key:" . $apiKey["id"];
            $limit = $apiKey["rateLimit"] !== null
                ? $apiKey["rateLimit"]
                : (int) ($cfg["keyedLimit"] ?? 2000);
        }
    }

    // Advertise the tier in effect, so a key holder can see their key is working.
    header('X-RateLimit-Limit: ' . (int) ($limit !== null ? $limit : ($cfg["limit"] ?? 240)));

    // High-frequency, UI-driven actions are not throttled.
    if (in_array($action, $cfg["exemptActions"] ?? [], true)) {
        return null;
    }

    $retryAfter = optvRateLimitExceeded($cfg, 'api', $identity, $limit);
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
