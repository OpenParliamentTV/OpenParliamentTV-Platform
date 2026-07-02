<?php
/**
 * OpenParliamentTV Platform — Main Entry Point
 *
 * 1. Session, config, security, i18n bootstrap
 * 2. Plates template engine
 * 3. FastRoute dispatcher
 * 4. Centralized auth check at the route level
 * 5. Handler dispatch
 *
 * Clean URLs are routed here by .htaccess (single catch-all). The old
 * switch($_REQUEST["a"]) router and per-template auth boilerplate are gone;
 * routes live in routes/web.php and handlers in modules/routing/handlers.php.
 */

session_start();

require_once(__DIR__ . "/config.php"); // defines OPTV, $config, $acceptLang
require_once(__DIR__ . "/vendor/autoload.php");
require_once(__DIR__ . "/modules/utilities/security.php");
applySecurityHeaders();

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__ . "/modules/i18n/language.php");
require_once(__DIR__ . "/modules/utilities/functions.php");
// Load search functions at global scope so the OpenSearch client global
// ($ESClient, set at the top of search/functions.php) is available to the
// search/media handlers — which now run in function scope rather than the old
// global switch.
require_once(__DIR__ . "/modules/search/functions.php");

// Initialize language
$lang = LanguageManager::getInstance()->getCurrentLang();
$langJSONString = LanguageManager::getInstance()->getLangJSONString();

$color_scheme = isset($_COOKIE["color_scheme"]) ? $_COOKIE["color_scheme"] : false;
if ($color_scheme === false) $color_scheme = 'light';

$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent);

// Build the clean query-string ($paramStr) and result flag ($isResult) used by
// header.php and RSS autodiscovery — reuses the same allow-list as before.
$paramStr = "";
$allowedParams = filterAllowedSearchParams($_REQUEST, 'media');
$paramCount = 1;
foreach ($allowedParams as $k => $v) {
    $paramPrefix = ($paramCount == 1) ? "?" : "&";
    if (is_array($v)) {
        foreach ($v as $i) {
            $paramStr .= $paramPrefix . $k . "[]=" . urlencode($i);
        }
    } else {
        $paramStr .= $paramPrefix . $k . "=" . urlencode($_REQUEST[$k]);
    }
    $paramCount++;
}
$isResult = (strlen($paramStr) > 2);

// Plates engine (reads the globals set above)
require_once(__DIR__ . "/modules/templating/engine.php");
$plates = createPlatesEngine();

// Routing
require_once(__DIR__ . "/modules/routing/auth.php");
require_once(__DIR__ . "/modules/routing/handlers.php");

// --- Per-client page rate limit (coarse anti-scrape guard) ---
// Only true router-driven navigations reach here; static files, AJAX fragments
// and the image proxy are served directly by Apache and are not counted.
// Logged-in users and exemptIPs are exempted inside the limiter.
require_once(__DIR__ . "/modules/utilities/ratelimit.php");
$pageRateLimit = $config["rateLimit"]["pages"] ?? null;
if ($pageRateLimit) {
    $pageRetryAfter = optvRateLimitExceeded($pageRateLimit, 'page');
    if ($pageRetryAfter !== null) {
        render_429($plates, $pageRetryAfter);
        exit;
    }
}

// --- Build the request path (strip query string and any base path) ---
$uri = $_SERVER['REQUEST_URI'];
if (false !== ($pos = strpos($uri, '?'))) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$basePath = rtrim(parse_url($config['dir']['root'], PHP_URL_PATH) ?? '', '/');
if ($basePath !== '' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '' || $uri === false) {
    $uri = '/';
}

// --- Dispatch ---
$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/routes/web.php');
$routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        render_404($plates);
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        render_404($plates);
        break;

    case FastRoute\Dispatcher::FOUND:
        $routeData = $routeInfo[1];
        $params = $routeInfo[2];

        // Legacy compatibility shim: some templates / client JS still read
        // $_REQUEST["a"] (legacy page key) and $_REQUEST["id"]. The embed route's
        // {type} path segment is intentionally NOT mirrored, since the real
        // entity type arrives as ?type=... in the query string.
        $_REQUEST['a'] = $routeData['page'] ?? '';
        if (isset($params['id'])) {
            $_REQUEST['id'] = $params['id'];
        }

        // --- Centralized auth check (skipped for public embeds & feeds) ---
        // Uses the route's `access` level ('public'|'admin'), which is the AUTH
        // dimension only — separate from the presentation `pageType` the handlers
        // put on the render data.
        if (empty($routeData['skipAuth'])) {
            $access = $routeData['access'] ?? 'public';
            if (isset($routeData['accessResolver']) && is_callable($routeData['accessResolver'])) {
                $access = $routeData['accessResolver']($params);
            }

            $authResult = checkPageAuth($access);

            // Some entity-typed pages (e.g. notifications) are public by page type
            // but still require an authenticated session — reproduces the old
            // inline `empty($_SESSION["login"])` guard.
            if ($authResult === true && !empty($routeData['requireLogin']) && empty($_SESSION['login'])) {
                $authResult = ['alertText' => ''];
            }

            if ($authResult !== true) {
                optvRenderPage($plates, 'pages/login/page', [
                    'page' => 'login',
                    'pageType' => 'default',
                    'pageTitle' => L::login(),
                    'pageBreadcrumbs' => [['label' => L::login()]],
                    'alertText' => $authResult['alertText'],
                ]);
                break;
            }
        }

        // --- Dispatch to the handler ---
        $handlerName = $routeData['handler'];
        $handlerName($routeData, $params, $plates);
        break;
}
