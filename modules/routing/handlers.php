<?php defined('OPTV') or die();
/**
 * Route Handler Functions
 *
 * Each handler receives:
 *   - $routeData: the route metadata from routes/web.php (handler, page, access, …)
 *   - $params:    URL parameters extracted by FastRoute ({id}, {type}, …)
 *   - $plates:    the shared Plates engine
 *
 * Handlers load data and set the presentation `pageType` on the render data
 * (drives footer/asset layout in head.php/footer.php) — this is independent of
 * the route's `access` level, which the dispatcher uses purely for auth. Auth is
 * already checked by the dispatcher before the handler runs (except routes
 * flagged 'skipAuth'). The
 * 'page' value carried on each route reproduces the legacy $_REQUEST["a"] string
 * so content/head.php and content/header.php keep their asset-loading / nav logic.
 */

use League\Plates\Engine;

require_once(__DIR__ . '/../../api/v1/api.php');
require_once(__DIR__ . '/../utilities/health.php');

/**
 * Render a page template with the standard variable contract every layout/head
 * partial expects. Page-specific data is merged on top.
 */
function optvRenderPage(Engine $plates, string $template, array $vars): void
{
    $health = optvServiceHealth();
    $defaults = [
        'page' => '',
        'pageType' => 'default',
        'pageTitle' => '',
        'pageDescription' => L::claim(),
        'pageBreadcrumbs' => [],
        'schemaItemScopeString' => '',
        'apiResult' => null,
        // Service health for the global header banner (see content/header.php).
        'searchAvailable' => $health['searchAvailable'],
    ];
    // Publish the page meta as shared engine data so the whole render tree
    // (page template → layout → base → head/header/footer/components) can read
    // it. Plates does not auto-propagate a child template's render data to its
    // layouts, so addData() is the reliable channel for cross-cutting variables.
    $plates->addData(array_merge($defaults, $vars));
    echo $plates->render($template);
}

/** Standard 404 response. */
function render_404(Engine $plates): void
{
    http_response_code(404);
    optvRenderPage($plates, 'pages/404/page', [
        'page' => '404',
        'pageType' => 'default',
        'pageTitle' => '404 - ' . L::messageErrorNotFound(),
        'pageDescription' => L::messageErrorNotFoundQuote() . ' - Jakob Maria Mierscheid, SPD',
    ]);
}

/** Standard 429 (Too Many Requests) response for the page rate limiter. */
function render_429(Engine $plates, int $retryAfter): void
{
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    // L::* uses vsprintf and won't fill the {seconds} named placeholder (that is
    // substituted client-side for the API), so do it here for the rendered page.
    $message = str_replace('{seconds}', (string) $retryAfter, L::messageErrorRateLimited());
    optvRenderPage($plates, 'pages/429/page', [
        'page' => '429',
        'pageType' => 'default',
        'pageTitle' => '429 - ' . $message,
        'rateLimitMessage' => $message,
    ]);
}

/** Dashboard breadcrumb stub reused across admin pages. */
function optvManageHomeCrumb(): array
{
    return ['label' => '<span class="icon-th-1 me-0"></span>', 'path' => '/manage'];
}

// =====================================================================
// FEED (bypasses Plates / auth / layout entirely)
// =====================================================================

/**
 * RSS/Atom feed endpoint. Mirrors the old `case "feed"`: emits XML and exits
 * before any template machinery runs.
 */
function page_feed(array $routeData, array $params, Engine $plates): void
{
    require_once(__DIR__ . '/../../modules/feed/functions.php');

    $feedType = $params['feedType'] ?? ($_REQUEST['feedType'] ?? 'media');
    if (isset($params['id'])) {
        $_REQUEST['id'] = $params['id'];
    }
    $feedFormat = (($_REQUEST['format'] ?? 'rss') === 'atom') ? 'atom' : 'rss';

    $feedOutput = generateFeed($feedType, $_REQUEST, $feedFormat);

    // Discard any stray output so the XML declaration starts at the first byte.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo $feedOutput;
    exit;
}

// =====================================================================
// ENTITY DETAIL PAGES
// =====================================================================

/**
 * Entity detail handler.
 * Covers: person, organisation, term, document, session, electoralPeriod, agendaItem.
 */
function page_entity(array $routeData, array $params, Engine $plates): void
{
    $entityType = $routeData['entityType'];
    $id = $params['id'] ?? ($_REQUEST['id'] ?? '');

    $apiResult = apiV1([
        'action' => 'getItem',
        'itemType' => $entityType,
        'id' => $id,
    ]);

    if ((!$apiResult) || ($apiResult['meta']['requestStatus'] ?? '') === 'error') {
        render_404($plates);
        return;
    }

    $meta = buildEntityMeta($entityType, $apiResult);

    optvRenderPage($plates, 'pages/' . $entityType . '/page', [
        'page' => $entityType,
        'pageType' => 'entity',
        'pageTitle' => $meta['title'],
        'pageDescription' => $meta['description'],
        'pageBreadcrumbs' => [['label' => $meta['title']]],
        'apiResult' => $apiResult,
        'data' => $apiResult['data'],
    ]);
}

/**
 * Build per-entity-type title and description, reproducing the old switch cases.
 */
function buildEntityMeta(string $type, array $apiResult): array
{
    $attrs = $apiResult['data']['attributes'];
    $rels = $apiResult['data']['relationships'] ?? [];
    $altLabel = $attrs['labelAlternative'][0] ?? $attrs['label'];
    $title = '';
    $description = '';

    switch ($type) {
        case 'person':
            $title = '<span class="icon-type-person"></span>' . $attrs['label'];
            $description = L::speeches() . ' ' . L::by() . ': ' . $attrs['label'];
            break;
        case 'organisation':
            $title = '<span class="icon-type-organisation"></span>' . $attrs['label'];
            $description = L::speeches() . ' ' . L::by() . ': ' . $altLabel;
            break;
        case 'term':
            $title = '<span class="icon-type-term"></span>' . $attrs['label'];
            $description = L::speeches() . ' ' . L::onTheSubject() . ': ' . $altLabel;
            break;
        case 'document':
            $title = '<span class="icon-type-document"></span>' . $attrs['label'];
            $description = L::speeches() . ' ' . L::basedOn() . ': ' . $altLabel;
            break;
        case 'session':
            $epNum = $rels['electoralPeriod']['data']['attributes']['number'] ?? '';
            $title = '<span class="icon-group"></span>' . L::session() . ' ' . $attrs['number'] . ' – ' . $epNum . '. ' . L::electoralPeriod() . ' - ' . $attrs['parliamentLabel'];
            $description = L::speeches() . ' ' . L::inDER() . ' ' . $attrs['number'] . '. ' . L::session() . ' - ' . $attrs['parliamentLabel'];
            break;
        case 'electoralPeriod':
            $title = '<span class="icon-check"></span>' . $attrs['number'] . '. ' . L::electoralPeriod() . ' - ' . $attrs['parliamentLabel'];
            $description = L::speeches() . ' ' . L::inDER() . ' ' . $attrs['number'] . '. ' . L::electoralPeriod() . ' - ' . $attrs['parliamentLabel'];
            break;
        case 'agendaItem':
            $epNum = $rels['electoralPeriod']['data']['attributes']['number'] ?? '';
            $sessNum = $rels['session']['data']['attributes']['number'] ?? '';
            $title = '<span class="icon-list-numbered"></span>' . L::agendaItem() . ': ' . $attrs['title'] . ' - ' . $epNum . '/' . $sessNum . ' - ' . $attrs['parliamentLabel'];
            $description = L::speeches() . ' ' . L::basedOn() . ': ' . ($attrs['officialTitle'] ?? '') . ' - ' . $attrs['title'];
            break;
    }

    return ['title' => $title, 'description' => $description];
}

// =====================================================================
// MEDIA PAGE
// =====================================================================

function page_media(array $routeData, array $params, Engine $plates): void
{
    $_REQUEST['id'] = $params['id'];

    // Sets $speech, $mainSpeaker, $mainFaction, $formattedDate, $textContentsHTML,
    // $emptyResult, $apiResult, $autoplayResults, $speechTitleShort, … from $_REQUEST.
    require_once(__DIR__ . '/../../modules/media/include.media.php');

    if (!empty($emptyResult)) {
        render_404($plates);
        return;
    }

    require_once(__DIR__ . '/../../modules/utilities/functions.entities.php');
    $flatDataArray = flattenEntityJSON($apiResult['data'][0] ?? $apiResult['data']);

    $pageTitle = $speechTitleShort;
    $pageDescription = L::speech() . ' ' . L::onTheSubject() . ' '
        . ($speech['relationships']['agendaItem']['data']['attributes']['title'] ?? '') . ' '
        . L::by() . ' ' . ($mainSpeaker['attributes']['label'] ?? '')
        . ' (' . ($speech['attributes']['parliamentLabel'] ?? '') . ', ' . ($formattedDate ?? '') . ')';

    // Pass through EVERY variable produced by include.media.php so the media
    // template — and content.player.php, which it includes inline and whose own
    // require_once(include.media.php) is a no-op once already loaded — see them
    // in scope, exactly like the old global-scope flow did.
    $vars = get_defined_vars();
    unset($vars['routeData'], $vars['params'], $vars['plates']);
    $vars['page'] = 'media';
    $vars['pageType'] = 'entity';
    $vars['pageTitle'] = $pageTitle;
    $vars['pageDescription'] = $pageDescription;

    optvRenderPage($plates, 'pages/media/page', $vars);
}

// =====================================================================
// EMBED
// =====================================================================

function page_embed_entity(array $routeData, array $params, Engine $plates): void
{
    $apiResult = apiV1([
        'action' => 'getItem',
        'itemType' => $_REQUEST['type'] ?? '',
        'id' => $_REQUEST['id'] ?? '',
    ]);

    require_once(__DIR__ . '/../../modules/utilities/functions.entities.php');
    $flatDataArray = isset($apiResult['data']) ? flattenEntityJSON($apiResult['data']) : [];

    optvRenderPage($plates, 'pages/embed/entity/page', [
        'page' => 'embed-entity',
        'pageType' => 'embed',
        'pageTitle' => 'Embed Entity',
        'pageDescription' => '',
        'apiResult' => $apiResult,
        'data' => $apiResult['data'] ?? null,
        'flatDataArray' => $flatDataArray,
    ]);
}

// =====================================================================
// STATIC / SIMPLE PAGES
// =====================================================================

/**
 * Static public pages (about, imprint, datapolicy, press, login, register, …).
 * Title and template are derived from the legacy page key.
 */
function page_static(array $routeData, array $params, Engine $plates): void
{
    $page = $routeData['page'];

    // page key => [template, title]
    $map = [
        'about' => ['pages/about/page', L::about()],
        'datapolicy' => ['pages/datapolicy/page', L::dataPolicy()],
        'imprint' => ['pages/imprint/page', L::imprint()],
        'press' => ['pages/press/page', L::press()],
        'login' => ['pages/login/page', L::login()],
        'register' => ['pages/register/page', L::registerNewAccount()],
        'registerConfirm' => ['pages/registerconfirm/page', L::registerConfirmMailAddress()],
        'password-reset' => ['pages/passwordreset/page', L::resetPassword()],
    ];

    [$template, $title] = $map[$page] ?? ['pages/404/page', ''];

    optvRenderPage($plates, $template, [
        'page' => $page,
        'pageType' => 'default',
        'pageTitle' => $title,
        'pageBreadcrumbs' => [['label' => $title]],
    ]);
}

/** Logout: clear the session via the API, then render the logout page. */
function page_logout(array $routeData, array $params, Engine $plates): void
{
    apiV1(['action' => 'user', 'itemType' => 'logout']);

    optvRenderPage($plates, 'pages/logout/page', [
        'page' => 'logout',
        'pageType' => 'default',
        'pageTitle' => L::logout(),
        'pageBreadcrumbs' => [['label' => L::logout()]],
    ]);
}

// =====================================================================
// SEARCH / HOME
// =====================================================================

function page_search(array $routeData, array $params, Engine $plates): void
{
    global $config;

    $page = $routeData['page']; // 'main' or 'search'
    $pageTitle = '';
    $pageDescription = L::claim();
    $renderVars = [];

    if ($page === 'search') {
        // Resolve person facet labels (and expose them to head.php as JSON).
        if (isset($_REQUEST['personID'])) {
            $personIDs = $_REQUEST['personID'];
            $personDataFromRequest = [];
            foreach ((array) $personIDs as $personID) {
                $personID = strtok((string) $personID, '~'); // strip "~role" suffix
                $personData = apiV1(['action' => 'getItem', 'itemType' => 'person', 'id' => $personID]);
                $personDataFromRequest[$personID] = $personData['data'] ?? null;
                $pageTitle .= ($personData['data']['attributes']['label'] ?? '') . ' ';
            }
            $renderVars['personDataFromRequest'] = $personDataFromRequest;
        }

        // Resolve organisation / document / term facet labels.
        foreach (['organisation', 'document', 'term'] as $entityType) {
            $paramName = $entityType . 'ID';
            if (!isset($_REQUEST[$paramName])) {
                continue;
            }
            $entityData = [];
            foreach ((array) $_REQUEST[$paramName] as $entityID) {
                $entityID = strtok((string) $entityID, '~');
                $resp = apiV1(['action' => 'getItem', 'itemType' => $entityType, 'id' => $entityID]);
                $entityData[$entityID] = $resp['data'] ?? null;
                $pageTitle .= ($resp['data']['attributes']['label'] ?? '') . ' ';
            }
            $renderVars[$entityType . 'DataFromRequest'] = $entityData;
        }

        $pageTitle .= h($_REQUEST['q'] ?? '');

        if (count($_REQUEST) < 2 || (empty($_REQUEST['q']) && empty($_REQUEST['personID']) && empty($_REQUEST['organisationID']) && empty($_REQUEST['documentID']) && empty($_REQUEST['termID']))) {
            $pageTitle .= L::search();
        } elseif (isset($_REQUEST['parliament']) && $_REQUEST['parliament'] && strlen($_REQUEST['parliament']) >= 2) {
            $pageTitle .= ' - ' . L::speeches() . ' - ' . ($config['parliament'][h($_REQUEST['parliament'])]['label'] ?? '');
        } else {
            $pageTitle .= ' - ' . L::speeches();
        }
    }

    require_once(__DIR__ . '/../../modules/search/include.search.php');

    optvRenderPage($plates, 'pages/search/page', array_merge([
        'page' => $page,
        'pageType' => 'default',
        'pageTitle' => $pageTitle,
        'pageDescription' => $pageDescription,
        'pageBreadcrumbs' => [],
    ], $renderVars));
}

// =====================================================================
// ADMIN LIST / SIMPLE MANAGE PAGES
// =====================================================================

/**
 * Admin and manage list pages (and the entity-typed alerts / notifications
 * pages). Title, template, pageType and breadcrumbs come from a per-page map.
 */
function page_admin(array $routeData, array $params, Engine $plates): void
{
    $page = $routeData['page'];
    $home = optvManageHomeCrumb();

    // page => [template, title, pageType, breadcrumbs]
    $map = [
        'manage' => ['pages/manage/page', '<span class="icon-th-1"></span>' . L::dashboard(), 'admin', null],
        'manage-settings' => ['pages/manage/settings/page', L::platformSettings(), 'admin', 'home'],
        'manage-conflicts' => ['pages/manage/conflicts/page', L::manageConflicts(), 'admin', 'home'],
        'manage-entities' => ['pages/manage/entities/page', L::manageEntities(), 'admin', 'home'],
        'manage-entity-suggestions' => ['pages/manage/entitySuggestions/page', L::manageEntitySuggestions(), 'admin', 'home'],
        'manage-import' => ['pages/manage/import/page', L::manageImport(), 'admin', 'home'],
        'manage-media' => ['pages/manage/media/page', L::manageMedia(), 'admin', 'home'],
        'manage-structure' => ['pages/manage/structure/page', L::manageStructure(), 'admin', 'home'],
        'manage-users' => ['pages/manage/users/page', L::manageUsers(), 'admin', 'home'],
        'statistics' => ['pages/statistics/page', 'Statistics', 'admin', null],
        'manage-alerts' => ['pages/manage/alerts/page', L::alertManageTitle(), 'entity', 'home'],
        'notifications' => ['pages/notifications/page', L::notifications(), 'entity', null],
    ];

    if (!isset($map[$page])) {
        render_404($plates);
        return;
    }
    [$template, $title, $pageType, $crumbStyle] = $map[$page];

    $breadcrumbs = ($crumbStyle === 'home') ? [$home, ['label' => $title]] : [['label' => $title]];

    optvRenderPage($plates, $template, [
        'page' => $page,
        'pageType' => $pageType,
        'pageTitle' => $title,
        'pageBreadcrumbs' => $breadcrumbs,
    ]);
}

// =====================================================================
// ADMIN DETAIL PAGES
// =====================================================================

function page_admin_detail(array $routeData, array $params, Engine $plates): void
{
    $page = $routeData['page'];
    $home = optvManageHomeCrumb();
    $id = $params['id'] ?? null;
    if ($id !== null) {
        $_REQUEST['id'] = $id;
    }

    switch ($page) {
        case 'manage-conflicts':
            optvRenderPage($plates, 'pages/manage/conflicts/conflict-detail/page', [
                'page' => 'manage-conflicts',
                'pageType' => 'admin',
                'pageTitle' => L::manageConflicts(),
                'pageBreadcrumbs' => [
                    $home,
                    ['label' => L::manageConflicts(), 'path' => '/manage/conflicts'],
                    ['label' => '<span class="icon-pencil"></span>'],
                ],
            ]);
            return;

        case 'manage-media':
            optvRenderPage($plates, 'pages/manage/media/media-detail/page', [
                'page' => 'manage-media',
                'pageType' => 'admin',
                'pageTitle' => '<span class="icon-pencil"></span>',
                'pageBreadcrumbs' => [
                    $home,
                    ['label' => L::manageMedia(), 'path' => '/manage/media'],
                    ['label' => '<span class="icon-pencil"></span>'],
                ],
            ]);
            return;

        case 'manage-users':
            $apiResult = apiV1(['action' => 'getItemsFromDB', 'itemType' => 'user', 'id' => $id]);
            if (($apiResult['meta']['requestStatus'] ?? '') !== 'success') {
                render_404($plates);
                return;
            }
            // Presentation is always the admin/manage chrome here (table + form
            // assets, no funding-logo footer) regardless of viewer. Who may open
            // the page is decided separately by the route's accessResolver: own
            // profile is 'public', editing other users stays 'admin'-only.
            optvRenderPage($plates, 'pages/manage/users/user-detail/page', [
                'page' => 'manage-users',
                'pageType' => 'admin',
                'pageTitle' => L::edit() . ': ' . L::user(),
                'pageBreadcrumbs' => [
                    $home,
                    ['label' => L::manageUsers(), 'path' => '/manage/users'],
                    ['label' => '<span class="icon-pencil"></span>' . $apiResult['data']['UserName']],
                ],
                'apiResult' => $apiResult,
            ]);
            return;

        case 'manage-structure':
            $subpage = $params['subpage'] ?? '';
            $structureMap = [
                'electoralPeriod' => ['ElectoralPeriodID', L::electoralPeriod()],
                'session' => ['SessionID', L::session()],
                'agendaItem' => ['AgendaItemID', L::agendaItem()],
            ];
            if (!isset($structureMap[$subpage])) {
                render_404($plates);
                return;
            }
            [$idField, $typeLabel] = $structureMap[$subpage];
            $apiResult = apiV1(['action' => 'getItemsFromDB', 'itemType' => $subpage, 'id' => $id]);
            if ((!$apiResult) || ($apiResult['meta']['requestStatus'] ?? '') === 'error') {
                render_404($plates);
                return;
            }
            optvRenderPage($plates, 'pages/manage/structure/' . $subpage . '-detail/page', [
                'page' => 'manage-structure-' . $subpage,
                'pageType' => 'admin',
                'pageTitle' => L::edit() . ': ' . $typeLabel,
                'pageBreadcrumbs' => [
                    $home,
                    ['label' => L::manageStructure(), 'path' => '/manage/structure'],
                    ['label' => '<span class="icon-pencil"></span>' . $typeLabel . ': ' . ($apiResult['data'][$idField] ?? '')],
                ],
                'apiResult' => $apiResult,
            ]);
            return;
    }

    render_404($plates);
}
