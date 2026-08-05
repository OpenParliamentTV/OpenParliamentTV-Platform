<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../bootstrap-core.php');

applySecurityHeaders();

header('Content-Type: application/json');
// API JSON is reachable by crawlers/agents (the sanctioned machine path) but
// must not be indexed as content pages.
header('X-Robots-Tag: noindex');

require_once(__DIR__ . "/api.php");
require_once(__DIR__ . "/registry.php");

/**
 * Resolve the request path against routes/api.php.
 *
 * Returns the path-derived parameters (action / itemType / id / …), or an empty
 * array when nothing matches — which is the legacy `?action=&itemType=` form,
 * where the query string alone carries the request.
 *
 * Dispatched as GET regardless of the real verb: mod_rewrite ignored the method
 * too, and the API decides on parameters rather than on the verb.
 */
function apiResolveRoutePath(): array
{
    global $config;

    $uri = $_SERVER['REQUEST_URI'];
    if (false !== ($pos = strpos($uri, '?'))) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    // Strip any base path plus the /api/v1 mount point.
    $basePath = rtrim(parse_url($config['dir']['root'], PHP_URL_PATH) ?? '', '/');
    if ($basePath !== '' && strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }
    $mount = '/api/v1';
    if (strpos($uri, $mount) === 0) {
        $uri = substr($uri, strlen($mount));
    }
    if ($uri === '' || $uri === false) {
        $uri = '/';
    }

    $dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../../routes/api.php');
    $routeInfo = $dispatcher->dispatch('GET', $uri);

    if ($routeInfo[0] !== FastRoute\Dispatcher::FOUND) {
        return [];
    }

    // Route metadata (fixed action/itemType) plus the extracted path segments.
    // `rest` only exists to reproduce the unanchored rewrite rules; it is not a
    // request parameter.
    $params = $routeInfo[2];
    unset($params['rest']);

    return array_merge($routeInfo[1], $params);
}

$routeParams = apiResolveRoutePath();

// Path parameters sit at the lowest precedence, so an explicit ?itemType=
// overrides the path segment. php://input carries JSON request bodies, which
// $_REQUEST does not see.
$api_request = array_merge(
    $routeParams,
    $_GET,
    $_POST,
    json_decode(file_get_contents('php://input'), true) ?: []
);

include_once(__DIR__ . '/../../modules/utilities/auth.php');
$userId = isset($_SESSION["userdata"]["id"]) ? $_SESSION["userdata"]["id"] : null;
// The action's public/admin metadata now comes from the shared registry rather
// than a second whitelist inside auth().
$auth = auth($userId, "apiV1", $api_request["action"] ?? null);

if ($auth["meta"]["requestStatus"] != "success") {

    $return = $auth;

} else {

    require_once(__DIR__ . "/ratelimit.php");

    // Per-client rate limit for external API requests (internal apiV1() callers
    // bypass this entry point entirely). Rejects with 429 over the limit, or 403
    // when an API key was presented but is unknown, revoked or expired.
    $rateLimitError = apiRateLimitCheck($api_request["action"] ?? "");
    if ($rateLimitError !== null) {
        http_response_code((int) ($rateLimitError["errors"][0]["status"] ?? 429));
        $return = $rateLimitError;
    } else {
        $return = apiV1Handle($api_request);
    }

}

echo json_encode($return, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
