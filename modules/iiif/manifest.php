<?php
/**
 * IIIF Presentation API 3.0 — Manifests.
 *
 * A speech Manifest is one Canvas; a session Manifest is one Canvas per public
 * speech plus a Range per agenda item. Both return null when there is nothing
 * to serve, which api/v1/modules/iiif.php turns into the ordinary OPTV 404.
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../i18n/language.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()
require_once(__DIR__ . "/helpers.php");

// ---------------------------------------------------------------------------
// Speech-level Manifest
// ---------------------------------------------------------------------------

function iiifGenerateMediaManifest(string $mediaID): ?array {
    global $config;

    $speech = iiifMediaNode($mediaID);
    if ($speech === null) {
        return null;
    }

    $attr = $speech['attributes'] ?? [];
    $rel = $speech['relationships'] ?? [];
    $api = $config['dir']['api'];
    $root = $config['dir']['root'];
    $parliament = $attr['parliament'] ?? '';
    $parliamentLabel = $attr['parliamentLabel'] ?? '';

    $sessionID = $rel['session']['data']['id'] ?? '';
    $agendaTitle = $rel['agendaItem']['data']['attributes']['title'] ?? '';
    $formattedDate = !empty($attr['dateStart']) ? date('d.m.Y', strtotime($attr['dateStart'])) : '';

    $speaker = mediaMainSpeaker($speech['annotations']['data'] ?? [], $rel['people']['data'] ?? []);
    $faction = mediaMainFaction($speech['annotations']['data'] ?? [], $rel['organisations']['data'] ?? []);
    $speakerLabel = $speaker['attributes']['label'] ?? '';
    $factionLabel = $faction['attributes']['label'] ?? '';

    $titleParts = array_filter([
        trim($speakerLabel . ($speakerLabel && $factionLabel ? ', ' : '') . $factionLabel),
        $formattedDate,
        $agendaTitle,
    ]);
    $manifestLabel = implode(' | ', $titleParts);
    if ($manifestLabel === '') {
        $manifestLabel = $mediaID;
    }

    $canvasID = $api . '/media/' . $mediaID . '/canvas';

    $manifest = [
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => $api . '/media/' . $mediaID . '?format=iiif',
        'type' => 'Manifest',
        'label' => iiifLangMap($manifestLabel),
        'metadata' => iiifBuildMediaMetadata($speech),
        'requiredStatement' => iiifRequiredStatement($parliamentLabel),
        'provider' => iiifProvider(),
        'items' => [iiifBuildCanvas($speech, $canvasID)],
        'homepage' => [[
            'id' => $root . '/media/' . $mediaID,
            'type' => 'Text',
            'format' => 'text/html',
            'label' => iiifLangMap(L::brand()),
        ]],
        'seeAlso' => [[
            'id' => $api . '/media/' . $mediaID,
            'type' => 'Dataset',
            'format' => 'application/json',
            'label' => iiifLangMap(L::brand()),
        ]],
    ];

    if (!empty($agendaTitle)) {
        $manifest['summary'] = iiifLangMap($agendaTitle);
    }
    if (!empty($attr['thumbnailURI'])) {
        $manifest['thumbnail'] = [[
            'id' => $attr['thumbnailURI'],
            'type' => 'Image',
            'format' => 'image/jpeg',
        ]];
    }
    if ($sessionID !== '') {
        $manifest['partOf'] = [[
            'id' => $api . '/session/' . $sessionID . '?format=iiif',
            'type' => 'Manifest',
            'label' => iiifLangMap(L::session() . ' ' . ($rel['session']['data']['attributes']['number'] ?? '')),
        ]];
    }

    return $manifest;
}

/** Metadata rows for a speech Manifest. */
function iiifBuildMediaMetadata(array $speech): array {
    $attr = $speech['attributes'] ?? [];
    $rel = $speech['relationships'] ?? [];

    $speaker = mediaMainSpeaker($speech['annotations']['data'] ?? [], $rel['people']['data'] ?? []);
    $faction = mediaMainFaction($speech['annotations']['data'] ?? [], $rel['organisations']['data'] ?? []);

    $entries = [
        iiifMetaEntry(L::parliament(), $attr['parliamentLabel'] ?? ''),
        iiifMetaEntry(L::electoralPeriod(), $rel['electoralPeriod']['data']['attributes']['number'] ?? ''),
        iiifMetaEntry(L::session(), $rel['session']['data']['attributes']['number'] ?? ''),
        iiifMetaEntry(L::date(), !empty($attr['dateStart']) ? date('Y-m-d', strtotime($attr['dateStart'])) : ''),
        iiifMetaEntry(L::contextmainSpeaker(), $speaker['attributes']['label'] ?? ''),
        iiifMetaEntry(L::faction(), $faction['attributes']['label'] ?? ''),
        iiifMetaEntry(L::agendaItem(), $rel['agendaItem']['data']['attributes']['title'] ?? ''),
    ];

    return array_values(array_filter($entries));
}

// ---------------------------------------------------------------------------
// Session-level Manifest (multi-Canvas + Ranges)
// ---------------------------------------------------------------------------

function iiifGenerateSessionManifest(string $sessionID): ?array {
    global $config;

    $session = iiifSessionNode($sessionID);
    if ($session === null) {
        return null;
    }

    $idInfo = optvParseStringId($sessionID);
    if (!$idInfo || !array_key_exists($idInfo['parliament'], $config['parliament'])) {
        return null;
    }
    $parliament = $idInfo['parliament'];
    $parliamentLabel = $config['parliament'][$parliament]['label'] ?? $parliament;

    $api = $config['dir']['api'];
    $sAttr = $session['attributes'] ?? [];
    $sessionNumber = $sAttr['number'] ?? '';
    $epID = $session['relationships']['electoralPeriod']['data']['id'] ?? '';
    $epNumber = $session['relationships']['electoralPeriod']['data']['attributes']['number'] ?? '';

    // Ordered list of public speeches in this session (agenda order, then media order).
    try {
        $dbp = optvDbConnection('parliament', $parliament);
    } catch (OptvDbException $e) {
        return null;
    }
    $rows = $dbp->getAll(
        "SELECT m.MediaID, m.MediaAgendaItemID, ai.AgendaItemOrder, ai.AgendaItemTitle
         FROM ?n m
         JOIN ?n ai ON ai.AgendaItemID = m.MediaAgendaItemID
         WHERE ai.AgendaItemSessionID = ?s AND m.MediaPublic = 1
         ORDER BY ai.AgendaItemOrder ASC, m.MediaOrder ASC",
        $config['parliament'][$parliament]['sql']['tbl']['Media'],
        $config['parliament'][$parliament]['sql']['tbl']['AgendaItem'],
        $sessionID
    );

    $canvases = [];
    $agendaGroups = []; // agendaItemID => ["title"=>..., "order"=>..., "canvasIDs"=>[]]

    foreach ($rows as $row) {
        $mediaID = $row['MediaID'];
        $speech = iiifMediaNode($mediaID);
        if ($speech === null) {
            continue;
        }
        $canvasID = $api . '/session/' . $sessionID . '/canvas/' . $mediaID;
        $canvases[] = iiifBuildCanvas($speech, $canvasID);

        $aiID = $row['MediaAgendaItemID'];
        if (!isset($agendaGroups[$aiID])) {
            $agendaGroups[$aiID] = [
                'title' => $row['AgendaItemTitle'] ?: ($parliamentLabel),
                'order' => (int) $row['AgendaItemOrder'],
                'canvasIDs' => [],
            ];
        }
        $agendaGroups[$aiID]['canvasIDs'][] = $canvasID;
    }

    $sessionLabel = $sessionNumber !== ''
        ? (L::session() . ' ' . $sessionNumber . ($epNumber !== '' ? ', ' . L::electoralPeriod() . ' ' . $epNumber : '') . ' — ' . $parliamentLabel)
        : $sessionID;

    $manifest = [
        '@context' => 'http://iiif.io/api/presentation/3/context.json',
        'id' => $api . '/session/' . $sessionID . '?format=iiif',
        'type' => 'Manifest',
        'label' => iiifLangMap($sessionLabel),
        'metadata' => array_values(array_filter([
            iiifMetaEntry(L::parliament(), $parliamentLabel),
            iiifMetaEntry(L::electoralPeriod(), $epNumber),
            iiifMetaEntry(L::session(), $sessionNumber),
            iiifMetaEntry(L::date(), !empty($sAttr['dateStart']) ? date('Y-m-d', strtotime($sAttr['dateStart'])) : ''),
        ])),
        'requiredStatement' => iiifRequiredStatement($parliamentLabel),
        'provider' => iiifProvider(),
        'items' => $canvases,
    ];

    // Ranges: one child Range per agenda item, grouping its Canvases.
    if (!empty($agendaGroups)) {
        $agendaRanges = [];
        foreach ($agendaGroups as $group) {
            $agendaRanges[] = [
                'id' => $api . '/session/' . $sessionID . '/range/agenda/' . $group['order'],
                'type' => 'Range',
                'label' => iiifLangMap($group['title']),
                'items' => array_map(function ($cid) {
                    return ['id' => $cid, 'type' => 'Canvas'];
                }, $group['canvasIDs']),
            ];
        }
        $manifest['structures'] = [[
            'id' => $api . '/session/' . $sessionID . '/range/root',
            'type' => 'Range',
            'label' => iiifLangMap(L::agendaItems()),
            'items' => $agendaRanges,
        ]];
    }

    if ($epID !== '') {
        $manifest['partOf'] = [[
            'id' => $api . '/iiif/collection/' . $parliament . '/' . $epID,
            'type' => 'Collection',
        ]];
    }

    return $manifest;
}
