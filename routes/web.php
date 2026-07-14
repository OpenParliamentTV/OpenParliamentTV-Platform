<?php defined('OPTV') or die();
/**
 * Frontend Route Definitions
 *
 * Each route carries metadata consumed by index.php and the handlers:
 *   - handler:  handler function in modules/routing/handlers.php
 *   - page:     legacy $_REQUEST["a"] identifier (drives head.php/header.php logic)
 *   - access:   access level for the centralized auth check ('public'|'admin').
 *               This is the AUTH dimension only — it is intentionally separate from
 *               the presentation `pageType` that handlers set on the render data
 *               (which drives footer/asset layout in head.php/footer.php). The two
 *               used to be one overloaded value; they are decoupled now so that
 *               relaxing auth (e.g. own-profile) can't accidentally change layout.
 *   - skipAuth: true to bypass the auth check entirely (public embeds, feeds)
 *   - accessResolver: optional callable(array $params): string returning 'public'|'admin'
 *                     to compute access dynamically (used by the user-detail
 *                     "own profile" case)
 */

use FastRoute\RouteCollector;

return function (RouteCollector $r) {

    // Local helper: GET route with the common metadata keys, plus any extras.
    // $access is the auth level ('public' default, or 'admin').
    $get = function (string $path, string $handler, string $page, string $access = 'public', array $extra = []) use ($r) {
        $r->addRoute('GET', $path, ['handler' => $handler, 'page' => $page, 'access' => $access] + $extra);
    };

    // ---- Search / home (public) ----
    $get('/', 'page_search', 'main');
    $get('/search', 'page_search', 'search');

    // ---- Static / simple public pages ----
    $get('/about', 'page_static', 'about');
    $get('/datapolicy', 'page_static', 'datapolicy');
    $get('/imprint', 'page_static', 'imprint');
    $get('/press', 'page_static', 'press');
    $get('/login', 'page_static', 'login');
    $get('/register', 'page_static', 'register');
    $get('/registerConfirm', 'page_static', 'registerConfirm');
    $get('/password-reset', 'page_static', 'password-reset');
    $get('/logout', 'page_logout', 'logout');

    // ---- Entity detail pages (public) ----
    foreach (['person', 'organisation', 'term', 'document', 'session', 'electoralPeriod', 'agendaItem'] as $entityType) {
        $get('/' . $entityType . '/{id}', 'page_entity', $entityType, 'public', ['entityType' => $entityType]);
    }
    $get('/media/{id}', 'page_media', 'media');

    // ---- Embed (public, skips auth) ----
    $get('/embed/{type}', 'page_embed_entity', 'embed-entity', 'public', ['skipAuth' => true]);

    // ---- Feeds (XML, bypass auth + layout) ----
    $get('/feed/{feedType}', 'page_feed', 'feed', 'public', ['skipAuth' => true]);
    $get('/feed/{feedType}/{id}', 'page_feed', 'feed', 'public', ['skipAuth' => true]);

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

    // Entity-typed manage pages: public at the auth gate, but the module enforces
    // login on the data itself (notifications additionally needs a session here).
    $get('/manage/alerts', 'page_admin', 'manage-alerts');
    $get('/notifications', 'page_admin', 'notifications', 'public', ['requireLogin' => true]);

    // ---- Admin / manage detail pages ----
    $get('/manage/media/{id}', 'page_admin_detail', 'manage-media', 'admin');
    $get('/manage/structure/{subpage}/{id}', 'page_admin_detail', 'manage-structure', 'admin');
    $get('/manage/users/{id}', 'page_admin_detail', 'manage-users', 'admin', [
        // Own profile is accessible to the user themselves; editing others is admin-only.
        // (Presentation stays 'admin' regardless — see page_admin_detail.)
        'accessResolver' => function (array $params) {
            return (($params['id'] ?? null) == ($_SESSION['userdata']['id'] ?? null)) ? 'public' : 'admin';
        },
    ]);
};
