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

require_once(__DIR__ . "/../bootstrap.php"); // session, config, headers, i18n, view globals

// Plates engine (reads the globals set above) + routing helpers.
require_once(__DIR__ . "/../modules/templating/engine.php");
$plates = createPlatesEngine();
require_once(__DIR__ . "/../modules/routing/auth.php");
require_once(__DIR__ . "/../modules/routing/handlers.php");

// Central auth gate — the API documentation is a public surface.
$authResult = authCheckPage('public');

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
