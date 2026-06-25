<?php defined('OPTV') or die();
/**
 * Frontend Route Definitions
 *
 * Each route carries metadata consumed by index.php and the handlers:
 *   - handler:  handler function in modules/routing/handlers.php
 *   - page:     legacy $_REQUEST["a"] identifier (drives head.php/header.php logic)
 *   - pageType: used for the centralized auth check ('default'|'results'|'entity'|'admin')
 *   - skipAuth: true to bypass the auth check entirely (public embeds, feeds)
 *   - pageTypeResolver: optional callable(array $params): string to compute pageType
 *                       dynamically (used by the user-detail "own profile" case)
 *
 * NOTE: campaigns/{slug} and documentation/{section} are intentionally omitted —
 * they had .htaccess rules but no working handler (they 404 today).
 */

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // ---- Search / home (public) ----
    $r->addRoute('GET', '/', ['handler' => 'page_search', 'page' => 'main', 'pageType' => 'default']);
    $r->addRoute('GET', '/search', ['handler' => 'page_search', 'page' => 'search', 'pageType' => 'default']);

    // ---- Static / simple public pages ----
    $r->addRoute('GET', '/about', ['handler' => 'page_static', 'page' => 'about', 'pageType' => 'default']);
    $r->addRoute('GET', '/datapolicy', ['handler' => 'page_static', 'page' => 'datapolicy', 'pageType' => 'default']);
    $r->addRoute('GET', '/imprint', ['handler' => 'page_static', 'page' => 'imprint', 'pageType' => 'default']);
    $r->addRoute('GET', '/press', ['handler' => 'page_static', 'page' => 'press', 'pageType' => 'default']);
    $r->addRoute('GET', '/login', ['handler' => 'page_static', 'page' => 'login', 'pageType' => 'default']);
    $r->addRoute('GET', '/register', ['handler' => 'page_static', 'page' => 'register', 'pageType' => 'default']);
    $r->addRoute('GET', '/registerConfirm', ['handler' => 'page_static', 'page' => 'registerConfirm', 'pageType' => 'default']);
    $r->addRoute('GET', '/password-reset', ['handler' => 'page_static', 'page' => 'password-reset', 'pageType' => 'default']);
    $r->addRoute('GET', '/logout', ['handler' => 'page_logout', 'page' => 'logout', 'pageType' => 'default']);

    // ---- Entity detail pages ----
    foreach (['person', 'organisation', 'term', 'document', 'session', 'electoralPeriod', 'agendaItem'] as $entityType) {
        $r->addRoute('GET', '/' . $entityType . '/{id}', [
            'handler' => 'page_entity', 'page' => $entityType, 'pageType' => 'entity', 'entityType' => $entityType,
        ]);
    }
    $r->addRoute('GET', '/media/{id}', ['handler' => 'page_media', 'page' => 'media', 'pageType' => 'entity']);

    // ---- Embed (public, skips auth) ----
    $r->addRoute('GET', '/embed/{type}', ['handler' => 'page_embed_entity', 'page' => 'embed-entity', 'pageType' => 'embed', 'skipAuth' => true]);

    // ---- Feeds (XML, bypass auth + layout) ----
    $r->addRoute('GET', '/feed/{feedType}', ['handler' => 'page_feed', 'page' => 'feed', 'pageType' => 'default', 'skipAuth' => true]);
    $r->addRoute('GET', '/feed/{feedType}/{id}', ['handler' => 'page_feed', 'page' => 'feed', 'pageType' => 'default', 'skipAuth' => true]);

    // ---- Admin / manage list pages ----
    $r->addRoute('GET', '/manage', ['handler' => 'page_admin', 'page' => 'manage', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/settings', ['handler' => 'page_admin', 'page' => 'manage-settings', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/conflicts', ['handler' => 'page_admin', 'page' => 'manage-conflicts', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/entities', ['handler' => 'page_admin', 'page' => 'manage-entities', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/entity-suggestions', ['handler' => 'page_admin', 'page' => 'manage-entity-suggestions', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/import', ['handler' => 'page_admin', 'page' => 'manage-import', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/media', ['handler' => 'page_admin', 'page' => 'manage-media', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/structure', ['handler' => 'page_admin', 'page' => 'manage-structure', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/users', ['handler' => 'page_admin', 'page' => 'manage-users', 'pageType' => 'admin']);
    $r->addRoute('GET', '/statistics', ['handler' => 'page_admin', 'page' => 'statistics', 'pageType' => 'admin']);

    // Entity-typed manage pages (logged-in users; module enforces login on data) —
    // preserve old pageType 'entity'.
    $r->addRoute('GET', '/manage/alerts', ['handler' => 'page_admin', 'page' => 'manage-alerts', 'pageType' => 'entity']);
    $r->addRoute('GET', '/notifications', ['handler' => 'page_admin', 'page' => 'notifications', 'pageType' => 'entity', 'requireLogin' => true]);

    // ---- Admin / manage detail pages ----
    $r->addRoute('GET', '/manage/conflicts/{id}', ['handler' => 'page_admin_detail', 'page' => 'manage-conflicts', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/media/{id}', ['handler' => 'page_admin_detail', 'page' => 'manage-media', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/structure/{subpage}/{id}', ['handler' => 'page_admin_detail', 'page' => 'manage-structure', 'pageType' => 'admin']);
    $r->addRoute('GET', '/manage/users/{id}', [
        'handler' => 'page_admin_detail',
        'page' => 'manage-users',
        'pageType' => 'admin',
        // Own profile is accessible to the user themselves; editing others is admin-only.
        'pageTypeResolver' => function (array $params) {
            return (($params['id'] ?? null) == ($_SESSION['userdata']['id'] ?? null)) ? 'default' : 'admin';
        },
    ]);
};
