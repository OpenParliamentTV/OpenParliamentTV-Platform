<?php
/**
 * Unified meta-image (OG / social card) endpoint.
 *
 * Direct-access endpoint fetched by URL (referenced from content/components/metadata.php
 * for og:image/twitter:image and from the media player's quote modal). It must NOT
 * carry the OPTV guard — it bootstraps its own config/session/i18n below.
 *
 * Routing (by ?type, defaulting to quote when t&f present, else default):
 *   type=quote (or id+t+f)        -> renderQuoteImage()   [HTTP cache headers only]
 *   type=person|organisation|term|document&id -> renderEntityImage()
 *   type=media&id                 -> renderMediaImage()
 *   type=search&q[&personID]      -> renderSearchImage()
 *   else                          -> renderDefaultImage()
 *
 * "Meta-image" (not "share-image") is used everywhere on purpose: names containing
 * "share" get blocked by some adblockers.
 */

// Never let warnings/notices corrupt the binary image output.
ini_set('display_errors', '0');
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../../../modules/utilities/security.php');

// Meta images always render in the configured default language (no per-language
// variants). Force it via $_REQUEST['lang'] (highest priority in LanguageManager,
// without mutating the visitor's session) before i18n initialises.
global $acceptLang;
$metaImageLang = 'de';
if (isset($acceptLang) && is_array($acceptLang)) {
    foreach ($acceptLang as $code => $info) {
        if (!empty($info['default'])) { $metaImageLang = $info['short'] ?? $code; break; }
    }
}
$_REQUEST['lang'] = $metaImageLang;

// Internal apiV1() calls merge $_GET/$_POST into their request params. Clear them
// so this request's own query params (notably the entity `id`) don't leak in as
// stray media-search filters. The endpoint reads its own params from $_REQUEST,
// which is a separate superglobal and keeps its values.
$_GET = [];
$_POST = [];

require_once(__DIR__ . '/../../../modules/i18n/language.php'); // initialises L:: (default language)
require_once(__DIR__ . '/../../../modules/utilities/safemysql.class.php');
require_once(__DIR__ . '/../../../modules/images/functions.php');

/**
 * Total speeches for an entity (search/media filtered by its id). Returns null
 * when unavailable or zero, so the footer count is simply omitted.
 */
function metaImageCountText($type, $id) {
    $map = [
        'person' => 'personID', 'organisation' => 'organisationID', 'term' => 'termID',
        'document' => 'documentID', 'session' => 'sessionID',
        'electoralPeriod' => 'electoralPeriodID', 'agendaItem' => 'agendaItemID',
    ];
    if (!isset($map[$type]) || $id === '') {
        return null;
    }
    // agendaItem pages use a "DE-1234" id, but the agendaItemID search filter
    // expects the bare integer; other types use their id verbatim.
    $searchId = ($type === 'agendaItem') ? preg_replace('/^[A-Za-z]+-?/', '', $id) : $id;
    $res = apiV1(['action' => 'search', 'itemType' => 'media', $map[$type] => $searchId, 'limit' => 1]);
    return metaImageFormatCount($res['meta']['results']['total'] ?? null);
}

$metaCfg = require(__DIR__ . '/../../../modules/meta-image/config.php');

$ENTITY_TYPES = ['person', 'organisation', 'term', 'document'];
$INFO_TYPES   = ['session', 'electoralPeriod', 'agendaItem']; // date/structural types, no photo
$ROOT_DIR     = dirname(__DIR__, 3); // platform root
$THUMBNAIL    = $ROOT_DIR . '/content/client/images/thumbnail.png';

/**
 * Last-resort fallback: stream the static brand thumbnail.
 */
function metaImageFallback($thumbnailPath) {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    if (is_file($thumbnailPath)) {
        readfile($thumbnailPath);
    }
    exit;
}

/**
 * Emit PNG bytes as the response.
 */
function metaImageEmit($png, $cacheControl) {
    if ($png === false || $png === '' || $png === null) {
        return false;
    }
    ob_clean();
    header('Content-Type: image/png');
    header('Cache-Control: ' . $cacheControl);
    echo $png;
    ob_end_flush();
    exit;
}

// ---- routing ----
$type = isset($_REQUEST['type']) ? (string) $_REQUEST['type'] : '';
if ($type === '') {
    $type = (isset($_REQUEST['t']) && isset($_REQUEST['f'])) ? 'quote' : 'default';
}

$lang = $metaImageLang;

// =====================================================================
// QUOTE (existing behaviour; HTTP cache headers only, no disk cache)
// =====================================================================
if ($type === 'quote') {
    require_once($ROOT_DIR . '/modules/media/include.media.php');
    require_once($ROOT_DIR . '/modules/meta-image/quote.php');

    $quote = '';
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
        $timings   = preg_match('/^[0-9.,]+$/', $_REQUEST['t']) ? $_REQUEST['t'] : '';
        $fragments = preg_match('/^[a-zA-Z0-9,äöüÄÖÜßéèêëáàâäíìîïóòôöúùûü\s]+$/', $_REQUEST['f']) ? $_REQUEST['f'] : '';
        if ($timings && $fragments && !empty($textContentsHTML)) {
            $quote = getQuoteFromRequestParams($timings, $fragments, $textContentsHTML);
        }
    }

    $author = $mainSpeaker['attributes']['label'] ?? '';
    if (isset($mainFaction['attributes']['label'])) {
        $authorSecondary = $mainFaction['attributes']['label'] . ' | ' . ($formattedDate ?? '');
    } else {
        $authorSecondary = $formattedDate ?? '';
    }

    $theme = (isset($_REQUEST['c']) && in_array($_REQUEST['c'], ['l', 'd'], true)) ? $_REQUEST['c'] : 'l';

    $png = renderQuoteImage($theme, $quote, $author, $authorSecondary, $mainSpeaker['id'] ?? '', $metaCfg);
    metaImageEmit($png, 'public, max-age=2592000, immutable');
    metaImageFallback($THUMBNAIL);
}

// From here on we use the page renderers + disk cache.
require_once($ROOT_DIR . '/modules/meta-image/page.php');
require_once($ROOT_DIR . '/modules/meta-image/cache.php');

// =====================================================================
// ENTITY (person / organisation / term / document)
// =====================================================================
if (in_array($type, $ENTITY_TYPES, true)) {
    $id = isset($_REQUEST['id']) ? (string) $_REQUEST['id'] : '';
    if (!optvImageIsValidId($type, $id)) {
        metaImageFallback($THUMBNAIL);
    }

    require_once($ROOT_DIR . '/api/v1/api.php');
    $apiResult = apiV1(['action' => 'getItem', 'itemType' => $type, 'id' => $id]);
    if (!$apiResult || ($apiResult['meta']['requestStatus'] ?? '') === 'error' || empty($apiResult['data'])) {
        metaImageFallback($THUMBNAIL);
    }
    $data = $apiResult['data'];

    // Party indicator: the person getItem carries the faction id+label but not
    // its colour, so resolve it from the faction's organisation getItem (same
    // colour the web UI uses for .partyIndicator). Enrich the data node in place.
    $factionColor = '';
    if ($type === 'person' && !empty($data['relationships']['faction']['data']['id'])) {
        $factionId = $data['relationships']['faction']['data']['id'];
        $f = apiV1(['action' => 'getItem', 'itemType' => 'organisation', 'id' => $factionId]);
        if ($f && !empty($f['data']['attributes']['color'])) {
            $factionColor = $f['data']['attributes']['color'];
            $data['relationships']['faction']['data']['attributes']['color'] = $factionColor;
        }
    }

    $countText = metaImageCountText($type, $id);

    $version = metaImageVersion([
        $data['attributes']['label'] ?? '',
        $data['attributes']['thumbnailURI'] ?? '',
        $factionColor,
        (string) $countText,
        $lang,
    ]);
    $key = metaImageCacheKey($type, $id, $lang, $version);

    if (!metaImageCacheServe($key)) {
        $png = renderEntityImage($type, $data, $metaCfg, $countText);
        if ($png === false || $png === '') {
            metaImageFallback($THUMBNAIL);
        }
        metaImageCacheStore($key, $png);
        metaImageEmit($png, 'public, max-age=86400');
    }
    exit;
}

// =====================================================================
// INFO TYPES (session / electoralPeriod / agendaItem): date/structural,
// no photo — a type glyph + title + date subtitle.
// =====================================================================
if (in_array($type, $INFO_TYPES, true)) {
    $id = isset($_REQUEST['id']) ? (string) $_REQUEST['id'] : '';
    if (!preg_match('/^[A-Za-z0-9-]+$/', $id)) {
        metaImageFallback($THUMBNAIL);
    }

    require_once($ROOT_DIR . '/api/v1/api.php');
    $apiResult = apiV1(['action' => 'getItem', 'itemType' => $type, 'id' => $id]);
    if (!$apiResult || ($apiResult['meta']['requestStatus'] ?? '') === 'error' || empty($apiResult['data'])) {
        metaImageFallback($THUMBNAIL);
    }
    $a    = $apiResult['data']['attributes'] ?? [];
    $rels = $apiResult['data']['relationships'] ?? [];
    $parliament = $a['parliamentLabel'] ?? '';
    $epNum = $rels['electoralPeriod']['data']['attributes']['number'] ?? '';

    // Full date formatting (same DD.MM.YYYY as the media card dates).
    $fmtDate = function ($d) { return !empty($d) ? date('d.m.Y', strtotime($d)) : ''; };
    $sub = function (array $parts) {
        return implode('  ·  ', array_filter($parts, function ($p) { return $p !== '' && $p !== null; }));
    };

    switch ($type) {
        case 'electoralPeriod':
            $title = ($a['number'] ?? '') . '. ' . L::electoralPeriod();
            // Full date range; "01.01.2008 - " while still running (no end date).
            $start = $fmtDate($a['dateStart'] ?? '');
            $end   = $fmtDate($a['dateEnd'] ?? '');
            $subtitle = [$parliament, $start !== '' ? $start . ' - ' . $end : ''];
            break;
        case 'session':
            $title = L::session() . ' ' . ($a['number'] ?? '');
            $subtitle = [
                $epNum !== '' ? $epNum . '. ' . L::electoralPeriod() : '',
                $parliament,
                $fmtDate($a['dateStart'] ?? ''),
            ];
            break;
        case 'agendaItem':
        default:
            $title    = $a['title'] ?? '';
            $subtitle = $sub([$parliament, $a['officialTitle'] ?? '']);
            break;
    }

    $countText = metaImageCountText($type, $id);
    $version = metaImageVersion([$title, (is_array($subtitle) ? implode('|', $subtitle) : $subtitle), (string) $countText, $lang]);
    $key = metaImageCacheKey($type, $id, $lang, $version);

    if (!metaImageCacheServe($key)) {
        $png = renderInfoImage($type, $title, $subtitle, $metaCfg, $countText);
        if ($png === false || $png === '') {
            metaImageFallback($THUMBNAIL);
        }
        metaImageCacheStore($key, $png);
        metaImageEmit($png, 'public, max-age=86400');
    }
    exit;
}

// =====================================================================
// MEDIA (no quote selection)
// =====================================================================
if ($type === 'media') {
    require_once($ROOT_DIR . '/modules/media/include.media.php');
    if (!empty($emptyResult) || !isset($speech)) {
        metaImageFallback($THUMBNAIL);
    }

    $title = $speech['relationships']['agendaItem']['data']['attributes']['title']
        ?? ($mainSpeaker['attributes']['label'] ?? '');

    // Resolve the faction colour for the speaker's party badge (same source the
    // entity/person cards use), since include.media.php doesn't carry it.
    $factionColor = '';
    if (!empty($mainFaction['id'])) {
        require_once($ROOT_DIR . '/api/v1/api.php');
        $mf = apiV1(['action' => 'getItem', 'itemType' => 'organisation', 'id' => $mainFaction['id']]);
        if ($mf && !empty($mf['data']['attributes']['color'])) {
            $factionColor = $mf['data']['attributes']['color'];
        }
    }

    // "Abstract": the start of the speech transcript (plain text), if available.
    $speechExcerpt = '';
    if (!empty($textContentsHTMLRaw)) {
        $speechExcerpt = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($textContentsHTMLRaw))), 0, 400);
    }

    $args = [
        'speakerId'    => $mainSpeaker['id'] ?? '',
        'title'        => $title,
        'speaker'      => $mainSpeaker['attributes']['label'] ?? '',
        'faction'      => $mainFaction['attributes']['label'] ?? '',
        'factionColor' => $factionColor,
        'abstract'     => $speechExcerpt,
        'date'         => $formattedDate ?? '',
    ];

    $version = metaImageVersion([
        $args['title'], $args['speaker'], $args['speakerId'], $args['faction'], $factionColor, mb_substr($speechExcerpt, 0, 60), $args['date'], $lang,
    ]);
    $key = metaImageCacheKey('media', $_REQUEST['id'] ?? '', $lang, $version);

    if (!metaImageCacheServe($key)) {
        $png = renderMediaImage($args, $metaCfg);
        if ($png === false || $png === '') {
            metaImageFallback($THUMBNAIL);
        }
        metaImageCacheStore($key, $png);
        metaImageEmit($png, 'public, max-age=86400');
    }
    exit;
}

// =====================================================================
// SEARCH
// =====================================================================
if ($type === 'search') {
    require_once($ROOT_DIR . '/api/v1/api.php');

    $q = isset($_REQUEST['q']) ? (string) $_REQUEST['q'] : '';

    // The query label is composed by the page (metadata.php) from the already
    // resolved facet labels and passed as `label`. We only run the count search
    // here, passing the filter ids through (they may be arrays, e.g. personID[]).
    $searchInput = ['action' => 'search', 'itemType' => 'media', 'limit' => 1];
    if ($q !== '') { $searchInput['q'] = $q; }
    $keyParts = ['q=' . $q];
    foreach (['personID', 'organisationID', 'termID', 'documentID'] as $param) {
        if (empty($_REQUEST[$param])) {
            continue;
        }
        $searchInput[$param] = $_REQUEST[$param];
        $ids = is_array($_REQUEST[$param]) ? $_REQUEST[$param] : [$_REQUEST[$param]];
        $keyParts[] = $param . '=' . implode(',', array_map('strval', $ids));
    }

    // Prefer the pre-composed label; fall back to just the quoted query.
    $queryText = isset($_REQUEST['label']) && $_REQUEST['label'] !== ''
        ? (string) $_REQUEST['label']
        : ($q !== '' ? '“' . $q . '”' : '');
    $keyParts[] = 'label=' . $queryText;

    $count = null;
    $searchResult = apiV1($searchInput);
    if ($searchResult && isset($searchResult['meta']['results']['total'])) {
        $count = (int) $searchResult['meta']['results']['total'];
    }

    $args = ['query' => $queryText, 'count' => $count];

    $version = metaImageVersion(array_merge($keyParts, [(string) $count, $lang]));
    $key = metaImageCacheKey('search', md5(implode('|', $keyParts)), $lang, $version);

    if (!metaImageCacheServe($key)) {
        $png = renderSearchImage($args, $metaCfg);
        if ($png === false || $png === '') {
            metaImageFallback($THUMBNAIL);
        }
        metaImageCacheStore($key, $png);
        metaImageEmit($png, 'public, max-age=86400');
    }
    exit;
}

// =====================================================================
// DEFAULT / FALLBACK
// =====================================================================
$title       = isset($_REQUEST['title']) ? (string) $_REQUEST['title'] : strip_tags(L::claimShort());
$description  = isset($_REQUEST['description']) ? (string) $_REQUEST['description'] : '';
$png = renderDefaultImage($title, $description, $THUMBNAIL, $metaCfg);
if (!metaImageEmit($png, 'public, max-age=86400')) {
    metaImageFallback($THUMBNAIL);
}
