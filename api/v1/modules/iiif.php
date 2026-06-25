<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.entities.php");
require_once (__DIR__."/../../../modules/i18n/language.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/media.php");
require_once (__DIR__."/session.php");
require_once (__DIR__."/transcript.php");

/**
 * IIIF Presentation API 3.0 generation.
 *
 * IIIF Manifests are served from the same URIs as the existing OPTV JSON API via
 * content negotiation (see isIIIFRequest()/handleIIIFRequest()), plus dedicated
 * Collection endpoints. Transcripts are surfaced as `supplementing` annotations
 * (reusing the standalone transcript module), named entities as `tagging` annotations.
 *
 * All absolute URIs are built from $config["dir"]["api"] / $config["dir"]["root"],
 * so the Manifest ids match whatever host the instance is served from.
 */

// ---------------------------------------------------------------------------
// Request detection / dispatch
// ---------------------------------------------------------------------------

/**
 * Whether the current request should be answered with IIIF instead of OPTV JSON.
 */
function isIIIFRequest(): bool {
    if (isset($_GET['format']) && strtolower($_GET['format']) === 'iiif') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ($accept === '') {
        return false;
    }
    // Explicit IIIF profile, or any ld+json (no more specific OPTV profile exists).
    if (stripos($accept, 'iiif.io/api/presentation') !== false) {
        return true;
    }
    if (stripos($accept, 'application/ld+json') !== false) {
        return true;
    }
    return false;
}

/**
 * If the request targets a resource we can express as IIIF, return the Manifest
 * array; otherwise null (caller falls back to normal OPTV routing, which also
 * produces the correct error responses for missing/non-public items).
 */
function handleIIIFRequest(array $req): ?array {
    if (($req['action'] ?? '') !== 'getItem') {
        return null;
    }
    $id = $req['id'] ?? '';
    if ($id === '') {
        return null;
    }
    switch ($req['itemType'] ?? '') {
        case 'media':
            return iiifGenerateMediaManifest($id);
        case 'session':
            return iiifGenerateSessionManifest($id);
        default:
            return null;
    }
}

// ---------------------------------------------------------------------------
// Small building blocks
// ---------------------------------------------------------------------------

/** Language map keyed by the instance UI/default language, e.g. ["de" => ["..."]]. */
function iiifLangMap($text, ?string $lang = null): array {
    $lang = $lang ?: LanguageManager::getInstance()->getCurrentLang();
    $values = is_array($text) ? array_values($text) : [(string) $text];
    return [$lang => $values];
}

/** Validate a module response envelope and return its data, or null on error. */
function iiifResponseData($resp): ?array {
    if (!is_array($resp) || ($resp['meta']['requestStatus'] ?? '') !== 'success') {
        return null;
    }
    return $resp['data'] ?? null;
}

/** Build one metadata entry, or null when the value is empty. */
function iiifMetaEntry(string $labelText, $valueText): ?array {
    if ($valueText === null || $valueText === '') {
        return null;
    }
    return [
        'label' => iiifLangMap($labelText),
        'value' => iiifLangMap((string) $valueText),
    ];
}

/** The shared provider Agent block. */
function iiifProvider(): array {
    global $config;
    $root = $config['dir']['root'];
    return [[
        'id' => $root,
        'type' => 'Agent',
        'label' => iiifLangMap(L::brand()),
        'homepage' => [[
            'id' => $root,
            'type' => 'Text',
            'format' => 'text/html',
        ]],
    ]];
}

/** Attribution shown by viewers (requiredStatement). */
function iiifRequiredStatement(string $parliamentLabel): array {
    return [
        'label' => iiifLangMap(L::brand()),
        'value' => iiifLangMap(L::brand() . ' — ' . $parliamentLabel),
    ];
}

// ---------------------------------------------------------------------------
// Canvas + annotations (shared by speech and session Manifests)
// ---------------------------------------------------------------------------

/**
 * Build a Canvas from a single media item's data.
 *
 * @param array  $speech   The media item (= $resp["data"] from mediaGetByID)
 * @param string $canvasID Full Canvas URI (differs between speech/session Manifests)
 */
function iiifBuildCanvas(array $speech, string $canvasID): array {
    $attr = $speech['attributes'] ?? [];
    $mediaID = $speech['id'] ?? '';
    $duration = (float) ($attr['duration'] ?? 0);
    $annotationsData = $speech['annotations']['data'] ?? [];
    $relationships = $speech['relationships'] ?? [];

    $speaker = getMainSpeakerFromPeopleArray($annotationsData, $relationships['people']['data'] ?? []);
    $faction = getMainFactionFromOrganisationsArray($annotationsData, $relationships['organisations']['data'] ?? []);
    $speakerLabel = $speaker['attributes']['label'] ?? '';
    $factionLabel = $faction['attributes']['label'] ?? '';
    $canvasLabel = trim($speakerLabel . ($speakerLabel && $factionLabel ? ', ' : '') . $factionLabel);
    if ($canvasLabel === '') {
        $canvasLabel = $mediaID;
    }

    $canvas = [
        'id' => $canvasID,
        'type' => 'Canvas',
        'label' => iiifLangMap($canvasLabel),
        'duration' => $duration,
        'items' => [[
            'id' => $canvasID . '/page',
            'type' => 'AnnotationPage',
            'items' => [[
                'id' => $canvasID . '/page/video',
                'type' => 'Annotation',
                'motivation' => 'painting',
                'body' => [
                    'id' => $attr['videoFileURI'] ?? '',
                    'type' => 'Video',
                    'format' => 'video/mp4',
                    'duration' => $duration,
                ],
                'target' => $canvasID,
            ]],
        ]],
    ];

    if (!empty($attr['thumbnailURI'])) {
        $canvas['thumbnail'] = [[
            'id' => $attr['thumbnailURI'],
            'type' => 'Image',
            'format' => 'image/jpeg',
        ]];
    }

    $annotationPages = [];

    $transcriptItems = iiifBuildTranscriptAnnotations($attr['textContents'] ?? [], $mediaID, $canvasID);
    if (!empty($transcriptItems)) {
        $annotationPages[] = [
            'id' => $canvasID . '/annotations/transcripts',
            'type' => 'AnnotationPage',
            'items' => $transcriptItems,
        ];
    }

    $entityItems = iiifBuildEntityAnnotations($speech, $canvasID);
    if (!empty($entityItems)) {
        $annotationPages[] = [
            'id' => $canvasID . '/annotations/entities',
            'type' => 'AnnotationPage',
            'label' => iiifLangMap(L::entities()),
            'items' => $entityItems,
        ];
    }

    if (!empty($annotationPages)) {
        $canvas['annotations'] = $annotationPages;
    }

    return $canvas;
}

/**
 * One `supplementing` Annotation per available transcript (type + language).
 * Bodies point at the standalone WebVTT endpoint.
 */
function iiifBuildTranscriptAnnotations(array $textContents, string $mediaID, string $canvasID): array {
    global $config;
    $api = $config['dir']['api'];
    $out = [];

    foreach ($textContents as $tc) {
        $type = $tc['type'] ?? '';
        if ($type === '') {
            continue;
        }
        $lang = transcriptNormalizeLang($tc['language'] ?? '');
        $label = transcriptTypeLabel($type);

        $vttURL = $api . '/media/' . rawurlencode($mediaID) . '/transcript.vtt'
            . '?type=' . rawurlencode($type)
            . ($lang !== '' ? '&lang=' . rawurlencode($lang) : '');

        $body = [
            'id' => $vttURL,
            'type' => 'Text',
            'format' => 'text/vtt',
            'label' => iiifLangMap($label),
        ];
        if ($lang !== '') {
            $body['language'] = $lang;
        }

        $out[] = [
            'id' => $canvasID . '/annotations/transcript/' . $type . ($lang !== '' ? '-' . $lang : ''),
            'type' => 'Annotation',
            'motivation' => 'supplementing',
            'label' => iiifLangMap($label),
            'body' => $body,
            'target' => $canvasID,
        ];
    }

    return $out;
}

/**
 * Named-entity `tagging` annotations (Phase 2). Reuses the same annotation +
 * relationship data that drives FrameTrail; one mixed AnnotationPage per Canvas,
 * each item tagged with its entity type and (for Wikidata Q-ids) linked out.
 */
function iiifBuildEntityAnnotations(array $speech, string $canvasID): array {
    $annotationsData = $speech['annotations']['data'] ?? [];
    $relationships = $speech['relationships'] ?? [];
    if (empty($annotationsData)) {
        return [];
    }

    $typeToRel = [
        'person' => 'people',
        'organisation' => 'organisations',
        'term' => 'terms',
        'document' => 'documents',
    ];

    $out = [];
    $index = 0;
    foreach ($annotationsData as $annotation) {
        $timeStart = $annotation['attributes']['timeStart'] ?? null;
        $timeEnd = $annotation['attributes']['timeEnd'] ?? null;
        if ($timeStart === null || $timeEnd === null || $timeStart === '' || $timeEnd === '') {
            continue;
        }
        $type = $annotation['type'] ?? '';
        $relKey = $typeToRel[$type] ?? null;
        if ($relKey === null) {
            continue;
        }
        $entityID = $annotation['id'] ?? '';

        $label = '';
        foreach (($relationships[$relKey]['data'] ?? []) as $rel) {
            if (($rel['id'] ?? null) === $entityID) {
                $label = $rel['attributes']['label'] ?? '';
                break;
            }
        }
        if ($label === '') {
            continue;
        }

        $index++;
        // Label as an embedded TextualBody; for Wikidata Q-ids add a separate
        // referenced body linking out to the entity (avoids putting an id on a
        // TextualBody, which is semantically embedded-only).
        $textualBody = [
            'type' => 'TextualBody',
            'value' => $label,
            'format' => 'text/plain',
        ];
        if (preg_match('/^Q\d+$/', $entityID)) {
            $body = [
                $textualBody,
                [
                    'id' => 'http://www.wikidata.org/entity/' . $entityID,
                    'type' => 'Text',
                    'format' => 'text/html',
                ],
            ];
        } else {
            $body = $textualBody;
        }

        $out[] = [
            'id' => $canvasID . '/annotations/entity/' . $index,
            'type' => 'Annotation',
            'motivation' => 'tagging',
            'body' => $body,
            'target' => [
                'type' => 'SpecificResource',
                'source' => $canvasID,
                'selector' => [
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => 't=' . $timeStart . ',' . $timeEnd,
                ],
            ],
        ];
    }

    return $out;
}

// ---------------------------------------------------------------------------
// Speech-level Manifest
// ---------------------------------------------------------------------------

function iiifGenerateMediaManifest(string $mediaID): ?array {
    global $config;

    $speech = iiifResponseData(mediaGetByID($mediaID));
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

    $speaker = getMainSpeakerFromPeopleArray($speech['annotations']['data'] ?? [], $rel['people']['data'] ?? []);
    $faction = getMainFactionFromOrganisationsArray($speech['annotations']['data'] ?? [], $rel['organisations']['data'] ?? []);
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

    $speaker = getMainSpeakerFromPeopleArray($speech['annotations']['data'] ?? [], $rel['people']['data'] ?? []);
    $faction = getMainFactionFromOrganisationsArray($speech['annotations']['data'] ?? [], $rel['organisations']['data'] ?? []);

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

    $session = iiifResponseData(sessionGetByID($sessionID));
    if ($session === null) {
        return null;
    }

    $idInfo = getInfosFromStringID($sessionID);
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
    $dbp = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($dbp)) {
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
        $speech = iiifResponseData(mediaGetByID($mediaID));
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

    $dbp = getApiDatabaseConnection('parliament', $parliamentCode);
    if (!is_object($dbp)) {
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
