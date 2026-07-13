<?php

session_start();

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/utilities/security.php');
applySecurityHeaders();

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json');
// API JSON is reachable by crawlers/agents (the sanctioned machine path) but
// must not be indexed as content pages.
header('X-Robots-Tag: noindex');


include_once(__DIR__ . '/../../modules/utilities/auth.php');
$userId = isset($_SESSION["userdata"]["id"]) ? $_SESSION["userdata"]["id"] : null;
$auth = auth($userId, "apiV1", $_REQUEST["action"]);

if ($auth["meta"]["requestStatus"] != "success") {

    $return = $auth;

} else {

    require_once(__DIR__ . "/api.php");
    require_once(__DIR__ . "/ratelimit.php");

    // Per-client rate limit for external API requests (internal apiV1() callers
    // bypass this entry point entirely). Rejects with 429 over the limit, or 403
    // when an API key was presented but is unknown, revoked or expired.
    $rateLimitError = apiRateLimitCheck($_REQUEST["action"] ?? "");
    if ($rateLimitError !== null) {
        http_response_code((int) ($rateLimitError["errors"][0]["status"] ?? 429));
        $return = $rateLimitError;
    } else {
        $return = apiV1($_REQUEST);
    }

}

echo json_encode($return, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



?>