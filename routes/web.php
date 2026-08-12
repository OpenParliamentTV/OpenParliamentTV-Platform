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
    $get('/', 'pageSearch', 'main');
    $get('/search', 'pageSearch', 'search');
    $get('/statistics', 'pageStatistics', 'statistics');

    // ---- Static / simple public pages ----
    $get('/about', 'pageStatic', 'about');
    $get('/datapolicy', 'pageStatic', 'datapolicy');
    $get('/imprint', 'pageStatic', 'imprint');
    $get('/press', 'pageStatic', 'press');
    $get('/login', 'pageStatic', 'login');
    $get('/register', 'pageStatic', 'register');
    $get('/registerConfirm', 'pageStatic', 'registerConfirm');
    $get('/password-reset', 'pageStatic', 'password-reset');
    $get('/logout', 'pageLogout', 'logout');

    // ---- Entity detail pages (public) ----
    foreach (['person', 'organisation', 'term', 'document', 'session', 'electoralPeriod', 'agendaItem'] as $entityType) {
        $get('/' . $entityType . '/{id}', 'pageEntity', $entityType, 'public', ['entityType' => $entityType]);
    }
    $get('/media/{id}', 'pageMedia', 'media');

    // ---- Embed (public, skips auth) ----
    $get('/embed/{type}', 'pageEmbedEntity', 'embed-entity', 'public', ['skipAuth' => true]);

    // ---- Feeds (XML, bypass auth + layout) ----
    $get('/feed/{feedType}', 'pageFeed', 'feed', 'public', ['skipAuth' => true]);
    $get('/feed/{feedType}/{id}', 'pageFeed', 'feed', 'public', ['skipAuth' => true]);

    // ---- Admin / manage list pages ----
    $get('/manage', 'pageAdmin', 'manage', 'admin');
    $get('/manage/settings', 'pageAdmin', 'manage-settings', 'admin');
    $get('/manage/conflicts', 'pageAdmin', 'manage-conflicts', 'admin');
    $get('/manage/entities', 'pageAdmin', 'manage-entities', 'admin');
    $get('/manage/entitySuggestions', 'pageAdmin', 'manage-entitySuggestions', 'admin');
    $get('/manage/import', 'pageAdmin', 'manage-import', 'admin');
    $get('/manage/media', 'pageAdmin', 'manage-media', 'admin');
    $get('/manage/structure', 'pageAdmin', 'manage-structure', 'admin');
    $get('/manage/users', 'pageAdmin', 'manage-users', 'admin');

    // Entity-typed manage pages: public at the auth gate, but the module enforces
    // login on the data itself (notifications additionally needs a session here).
    $get('/manage/alerts', 'pageAdmin', 'manage-alerts');
    $get('/notifications', 'pageAdmin', 'notifications', 'public', ['requireLogin' => true]);
    $get('/manage/playlists', 'pageAdmin', 'manage-playlists', 'public', ['requireLogin' => true]);

    // A playlist is addressed only by its share token, and the handler 404s
    // unless the row is shared or the caller owns it — so this stays public.
    $get('/playlist/{token}', 'pagePlaylist', 'playlist');

    // ---- Admin / manage detail pages ----
    $get('/manage/media/{id}', 'pageAdminDetail', 'manage-media', 'admin');
    $get('/manage/structure/{subpage}/{id}', 'pageAdminDetail', 'manage-structure', 'admin');
    $get('/manage/users/{id}', 'pageAdminDetail', 'manage-users', 'admin', [
        // Own profile is accessible to the user themselves; editing others is admin-only.
        // (Presentation stays 'admin' regardless — see pageAdminDetail.)
        'accessResolver' => function (array $params) {
            return (($params['id'] ?? null) == ($_SESSION['userdata']['id'] ?? null)) ? 'public' : 'admin';
        },
    ]);
};
