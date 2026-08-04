<?php
/**
 * IIIF Presentation API 3.0 — shared building blocks.
 *
 * Plain data, no envelope (D2): every function here returns a fragment of a
 * Manifest/Collection as a nested array. Serving it (content negotiation,
 * headers, status codes) is api/v1/modules/iiif.php.
 *
 * All absolute URIs are built from $config["dir"]["api"] / $config["dir"]["root"],
 * so the ids match whatever host the instance is served from.
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../i18n/language.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()
require_once(__DIR__ . "/../entities/media.php");   // mediaGetNode(), mediaMainSpeaker/Faction()
require_once(__DIR__ . "/../entities/session.php"); // sessionGetNode()
require_once(__DIR__ . "/../transcript/vtt.php");   // transcriptResolve(), transcriptTypeLabel()

// ---------------------------------------------------------------------------
// Small building blocks
// ---------------------------------------------------------------------------

/** Language map keyed by the instance UI/default language, e.g. ["de" => ["..."]]. */
function iiifLangMap($text, ?string $lang = null): array {
    $lang = $lang ?: LanguageManager::getInstance()->getCurrentLang();
    $values = is_array($text) ? array_values($text) : [(string) $text];
    return [$lang => $values];
}

/**
 * Load a media node for a Manifest, or null when there is nothing to serve.
 *
 * modules/entities returns null for "no such item" and throws for everything
 * else — a non-public speech, an unusable id, a database outage. IIIF answers
 * 404 to all of those alike, so they collapse into one null here.
 */
function iiifMediaNode(string $mediaID): ?array {
    try {
        return mediaGetNode($mediaID);
    } catch (Exception $e) {
        return null;
    }
}

/** Load a session node for a Manifest, or null when there is nothing to serve. */
function iiifSessionNode(string $sessionID): ?array {
    try {
        return sessionGetNode($sessionID);
    } catch (Exception $e) {
        return null;
    }
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

    $speaker = mediaMainSpeaker($annotationsData, $relationships['people']['data'] ?? []);
    $faction = mediaMainFaction($annotationsData, $relationships['organisations']['data'] ?? []);
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
