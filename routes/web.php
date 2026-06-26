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
 */

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // Local helper: GET route with the common metadata keys, plus any extras.
    $get = function (string $path, string $handler, string $page, string $pageType, array $extra = []) use ($r) {
        $r->addRoute('GET', $path, ['handler' => $handler, 'page' => $page, 'pageType' => $pageType] + $extra);
    };

    // ---- Search / home (public) ----
    $get('/', 'page_search', 'main', 'default');
    $get('/search', 'page_search', 'search', 'default');

    // ---- Static / simple public pages ----
    $get('/about', 'page_static', 'about', 'default');
    $get('/datapolicy', 'page_static', 'datapolicy', 'default');
    $get('/imprint', 'page_static', 'imprint', 'default');
    $get('/press', 'page_static', 'press', 'default');
    $get('/login', 'page_static', 'login', 'default');
    $get('/register', 'page_static', 'register', 'default');
    $get('/registerConfirm', 'page_static', 'registerConfirm', 'default');
    $get('/password-reset', 'page_static', 'password-reset', 'default');
    $get('/logout', 'page_logout', 'logout', 'default');

    // ---- Entity detail pages ----
    foreach (['person', 'organisation', 'term', 'document', 'session', 'electoralPeriod', 'agendaItem'] as $entityType) {
        $get('/' . $entityType . '/{id}', 'page_entity', $entityType, 'entity', ['entityType' => $entityType]);
    }
    $get('/media/{id}', 'page_media', 'media', 'entity');

    // ---- Embed (public, skips auth) ----
    $get('/embed/{type}', 'page_embed_entity', 'embed-entity', 'embed', ['skipAuth' => true]);

    // ---- Feeds (XML, bypass auth + layout) ----
    $get('/feed/{feedType}', 'page_feed', 'feed', 'default', ['skipAuth' => true]);
    $get('/feed/{feedType}/{id}', 'page_feed', 'feed', 'default', ['skipAuth' => true]);

    // ---- Admin / manage list pages ----
    $get('/manage', 'page_admin', 'manage', 'admin');
    $get('/manage/settings', 'page_admin', 'manage-settings', 'admin');
    $get('/manage/conflicts', 'page_admin', 'manage-conflicts', 'admin');
    $get('/manage/entities', 'page_admin', 'manage-entities', 'admin');
    $get('/manage/entity-suggestions', 'page_admin', 'manage-entity-suggestions', 'admin');
    $get('/manage/import', 'page_admin', 'manage-import', 'admin');
    $get('/manage/media', 'page_admin', 'manage-media', 'admin');
    $get('/manage/structure', 'page_admin', 'manage-structure', 'admin');
    $get('/manage/users', 'page_admin', 'manage-users', 'admin');
    $get('/statistics', 'page_admin', 'statistics', 'admin');

    // Entity-typed manage pages (logged-in users; module enforces login on data) —
    // preserve old pageType 'entity'.
    $get('/manage/alerts', 'page_admin', 'manage-alerts', 'entity');
    $get('/notifications', 'page_admin', 'notifications', 'entity', ['requireLogin' => true]);

    // ---- Admin / manage detail pages ----
    $get('/manage/conflicts/{id}', 'page_admin_detail', 'manage-conflicts', 'admin');
    $get('/manage/media/{id}', 'page_admin_detail', 'manage-media', 'admin');
    $get('/manage/structure/{subpage}/{id}', 'page_admin_detail', 'manage-structure', 'admin');
    $get('/manage/users/{id}', 'page_admin_detail', 'manage-users', 'admin', [
        // Own profile is accessible to the user themselves; editing others is admin-only.
        'pageTypeResolver' => function (array $params) {
            return (($params['id'] ?? null) == ($_SESSION['userdata']['id'] ?? null)) ? 'default' : 'admin';
        },
    ]);
};
