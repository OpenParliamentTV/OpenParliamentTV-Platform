<?php
/**
 * IIIF Presentation API 3.0 — Collections.
 *
 * The static hierarchy (parliament -> electoral period -> session) and the
 * dynamic search Collection, which is the IIIF counterpart to the RSS search
 * feed. Items are lightweight Manifest references, so no media node is built
 * per entry.
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../i18n/language.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()
require_once(__DIR__ . "/helpers.php");
require_once(__DIR__ . "/../../api/v1/modules/media.php"); // mediaSearch()

// ---------------------------------------------------------------------------
// Collections
// ---------------------------------------------------------------------------

/**
 * Top-level Collection (parliament → electoral periods) or, when an electoral
 * period is given, the Collection of that period's sessions.
 */
function iiifGenerateCollection(string $parliamentCode, ?string $electoralPeriodID = null): ?array {
    global $config;

    if (!array_key_exists($parliamentCode, $config['parliament'])) {
        return null;
    }
    $api = $config['dir']['api'];
    $parliamentLabel = $config['parliament'][$parliamentCode]['label'] ?? $parliamentCode;

    try {
        $dbp = optvDbConnection('parliament', $parliamentCode);
    } catch (OptvDbException $e) {
        return null;
    }

    if ($electoralPeriodID === null) {
        // Parliament → list electoral periods as sub-Collections.
        $periods = $dbp->getAll(
            "SELECT ElectoralPeriodID, ElectoralPeriodNumber FROM ?n ORDER BY ElectoralPeriodNumber DESC",
            $config['parliament'][$parliamentCode]['sql']['tbl']['ElectoralPeriod']
        );
        $items = [];
        foreach ($periods as $p) {
            $items[] = [
                'id' => $api . '/iiif/collection/' . $parliamentCode . '/' . $p['ElectoralPeriodID'],
                'type' => 'Collection',
                'label' => iiifLangMap(L::electoralPeriod() . ' ' . $p['ElectoralPeriodNumber']),
            ];
        }

        return [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $api . '/iiif/collection/' . $parliamentCode,
            'type' => 'Collection',
            'label' => iiifLangMap($parliamentLabel),
            'requiredStatement' => iiifRequiredStatement($parliamentLabel),
            'provider' => iiifProvider(),
            'items' => $items,
        ];
    }

    // Electoral period → list sessions as Manifest references.
    $sessions = $dbp->getAll(
        "SELECT SessionID, SessionNumber, SessionDateStart FROM ?n WHERE SessionElectoralPeriodID = ?s ORDER BY SessionNumber ASC",
        $config['parliament'][$parliamentCode]['sql']['tbl']['Session'],
        $electoralPeriodID
    );
    $items = [];
    foreach ($sessions as $s) {
        $items[] = [
            'id' => $api . '/session/' . $s['SessionID'] . '?format=iiif',
            'type' => 'Manifest',
            'label' => iiifLangMap(L::session() . ' ' . $s['SessionNumber']),
        ];
    }

    $epNumber = $dbp->getOne(
        "SELECT ElectoralPeriodNumber FROM ?n WHERE ElectoralPeriodID = ?s",
        $config['parliament'][$parliamentCode]['sql']['tbl']['ElectoralPeriod'],
        $electoralPeriodID
    );

    return [
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => $api . '/iiif/collection/' . $parliamentCode . '/' . $electoralPeriodID,
        'type' => 'Collection',
        'label' => iiifLangMap(L::electoralPeriod() . ' ' . ($epNumber ?: $electoralPeriodID)),
        'requiredStatement' => iiifRequiredStatement($parliamentLabel),
        'provider' => iiifProvider(),
        'partOf' => [[
            'id' => $api . '/iiif/collection/' . $parliamentCode,
            'type' => 'Collection',
            'label' => iiifLangMap($parliamentLabel),
        ]],
        'items' => $items,
    ];
}

/**
 * Dynamic Collection built from a media search — the IIIF counterpart to the
 * RSS search feed. Takes the same allowed media-search params (personID, termID,
 * q, dateFrom/To, context, …) and returns a Collection whose items are Manifest
 * references to the matching media items. Mirrors feedFetchSpeeches() in the
 * feed module; like a Collection everywhere, items are lightweight references
 * (no per-item mediaGetByID). Returns null when there are no matches.
 */
function iiifGenerateSearchCollection(array $params): ?array {
    global $config;

    $api = $config['dir']['api'];

    // Same filtering/limits as the RSS search feed, minus presentation params.
    $request = filterAllowedSearchParams($params, 'media');
    unset($request['limit'], $request['page'], $request['sort'], $request['format'],
          $request['includeAll'], $request['public'], $request['getAllResults'], $request['fields'],
          $request['a'], $request['queryOnly'], $request['paginationMode'], $request['showHome'],
          $request['feedType']);

    // The search filters that define this collection (for its self id + label).
    $filters = $request;

    $request['action']   = 'search';
    $request['itemType'] = 'media';
    $request['sort']     = 'date-desc';
    $request['limit']    = 200;
    $request['page']     = 1;

    $result = mediaSearch($request);
    if (!isset($result['data']) || !is_array($result['data']) || count($result['data']) === 0) {
        return null;
    }

    $parliamentLabel = '';
    $items = [];
    foreach ($result['data'] as $d) {
        $mediaID = $d['id'] ?? '';
        if ($mediaID === '') {
            continue;
        }
        $attr  = $d['attributes'] ?? [];
        $rel   = $d['relationships'] ?? [];
        $annos = $d['annotations']['data'] ?? [];
        if ($parliamentLabel === '' && !empty($attr['parliamentLabel'])) {
            $parliamentLabel = $attr['parliamentLabel'];
        }

        $speaker = mediaMainSpeaker($annos, $rel['people']['data'] ?? []);
        $faction = mediaMainFaction($annos, $rel['organisations']['data'] ?? []);
        $speakerLabel = $speaker['attributes']['label'] ?? '';
        $factionLabel = $faction['attributes']['label'] ?? '';
        $agendaTitle  = $rel['agendaItem']['data']['attributes']['title'] ?? '';
        $label = trim($speakerLabel . ($speakerLabel && $factionLabel ? ' (' . $factionLabel . ')' : ''));
        if ($agendaTitle !== '') {
            $label = $label !== '' ? $label . ' — ' . $agendaTitle : $agendaTitle;
        }
        if ($label === '') {
            $label = $mediaID;
        }

        $items[] = [
            'id' => $api . '/media/' . rawurlencode($mediaID) . '?format=iiif',
            'type' => 'Manifest',
            'label' => iiifLangMap($label),
        ];
    }

    if (empty($items)) {
        return null;
    }

    $parliamentLabel = $parliamentLabel !== '' ? $parliamentLabel : L::brand();
    $query = empty($filters) ? '' : '?' . preg_replace('/%5B\d+%5D=/i', '%5B%5D=', http_build_query($filters));

    return [
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => $api . '/iiif/search' . $query,
        'type' => 'Collection',
        'label' => iiifLangMap(L::brand() . ' — ' . L::search()),
        'requiredStatement' => iiifRequiredStatement($parliamentLabel),
        'provider' => iiifProvider(),
        'items' => $items,
    ];
}
