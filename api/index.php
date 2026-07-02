<?php
/**
 * API Documentation (/api) — thin Apache entry point.
 *
 * `/api` is served from this real directory (DirectoryIndex), separate from the
 * FastRoute front controller, but it now renders through the same Plates engine
 * and central auth as every other page: it bootstraps the engine, runs the
 * 'public' auth gate, and hands off to optvRenderPage('pages/api/page'). The page
 * template uses the standard layout('layout/default') contract — so it gets the
 * shared head/header/footer/core-scripts wiring instead of duplicating the shell.
 */

session_start();

require_once(__DIR__ . "/../config.php"); // defines OPTV, $config, $acceptLang
require_once(__DIR__ . "/../vendor/autoload.php");
require_once(__DIR__ . "/../modules/utilities/security.php");
applySecurityHeaders();

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__ . "/../modules/i18n/language.php");
require_once(__DIR__ . "/../modules/utilities/functions.php");

// Initialize language / view globals consumed by the Plates engine (engine.php
// reads these as ambient globals when building the shared template data).
$lang = LanguageManager::getInstance()->getCurrentLang();
$langJSONString = LanguageManager::getInstance()->getLangJSONString();

$color_scheme = isset($_COOKIE["color_scheme"]) ? $_COOKIE["color_scheme"] : false;
if ($color_scheme === false) $color_scheme = 'light';

$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent);

// Plates engine (reads the globals set above) + routing helpers.
require_once(__DIR__ . "/../modules/templating/engine.php");
$plates = createPlatesEngine();
require_once(__DIR__ . "/../modules/routing/auth.php");
require_once(__DIR__ . "/../modules/routing/handlers.php");

// Central auth gate — the API documentation is a public surface.
$authResult = checkPageAuth('public');

if ($authResult !== true) {
    optvRenderPage($plates, 'pages/login/page', [
        'page' => 'login',
        'pageType' => 'default',
        'pageTitle' => L::login(),
        'pageBreadcrumbs' => [['label' => L::login()]],
        'alertText' => $authResult['alertText'],
    ]);
} else {
    $title = 'API ' . L::documentation();
    optvRenderPage($plates, 'pages/api/page', [
        'page' => 'api',
        'pageType' => 'default',
        'pageTitle' => $title,
        'pageDescription' => L::messageOpenData(),
        'pageBreadcrumbs' => [['label' => $title]],
    ]);
}
