<?php

require_once (__DIR__."/export.php");

/**
 * Akoma Ntoso 1.0 (OASIS LegalDocML) <debate> generation.
 *
 * A media item becomes a partial debate record (one debateSection for its
 * agenda item); a session becomes the debate record of the whole sitting,
 * with one debateSection per agenda item.
 *
 * AV mapping: Akoma Ntoso has no timeline/synchronization mechanism, so each
 * <speech> carries absolute startTime/endTime (media dateStart + sentence
 * offsets, xsd:dateTime) and refersTo a TLCObject whose href is a W3C Media
 * Fragment URI (video.mp4#t=start,end) deep-linking the exact clip segment.
 * Sentence-level alignment is served by the IIIF/TEI/WebVTT endpoints.
 */

const AKN_NS = 'http://docs.oasis-open.org/legaldocml/ns/akn/3.0';

function aknGenerateMedia(string $mediaID, ?string $type = null, ?string $lang = null): ?string {
    global $config;

    $media = exportPrepareMedia($mediaID, $type, $lang);
    if ($media === null) {
        return null;
    }
    $attr = $media['attr'];
    $rel = $media['rel'];

    $speakerLabel = $media['mainSpeaker']['attributes']['label'] ?? '';
    $factionLabel = $media['mainFaction']['attributes']['label'] ?? '';
    $agendaTitle = $rel['agendaItem']['data']['attributes']['title'] ?? '';
    $date = exportDateOnly($attr['dateStart'] ?? null);

    $titleParts = array_filter([
        trim($speakerLabel . ($speakerLabel && $factionLabel ? ', ' : '') . $factionLabel),
        $date,
        $agendaTitle,
    ]);
    $title = $titleParts ? implode(' | ', $titleParts) : $media['id'];

    return aknBuildDebate([
        'docID' => $media['id'],
        'parliament' => $attr['parliament'] ?? '',
        'parliamentLabel' => $attr['parliamentLabel'] ?? '',
        'date' => $date,
        'lang' => $media['lang'],
        'title' => $title,
        'apiURL' => $config['dir']['api'] . '/media/' . rawurlencode($media['id']) . '?format=akn',
        'pageURL' => $config['dir']['root'] . '/media/' . rawurlencode($media['id']),
        'creator' => exportPlainText($attr['creator'] ?? ''),
        'license' => exportPlainText($attr['license'] ?? ''),
    ], [[
        'title' => $agendaTitle !== '' ? $agendaTitle : ($attr['parliamentLabel'] ?? $media['id']),
        'items' => [$media],
    ]]);
}

function aknGenerateSession(string $sessionID, ?string $type = null, ?string $lang = null): ?string {
    global $config;

    $prepared = exportPrepareSession($sessionID, $type, $lang);
    if ($prepared === null) {
        return null;
    }
    $sAttr = $prepared['session']['attributes'] ?? [];
    $sessionNumber = $sAttr['number'] ?? '';
    $epNumber = $prepared['session']['relationships']['electoralPeriod']['data']['attributes']['number'] ?? '';
    $date = exportDateOnly($sAttr['dateStart'] ?? null);

    $title = $sessionNumber !== ''
        ? (L::session() . ' ' . $sessionNumber . ($epNumber !== '' ? ', ' . L::electoralPeriod() . ' ' . $epNumber : '') . ' — ' . $prepared['parliamentLabel'])
        : $prepared['id'];

    $firstAttr = $prepared['sections'][0]['items'][0]['attr'] ?? [];

    $sections = [];
    foreach ($prepared['sections'] as $section) {
        $sections[] = ['title' => $section['title'], 'items' => $section['items']];
    }

    return aknBuildDebate([
        'docID' => $prepared['id'],
        'parliament' => $prepared['parliament'],
        'parliamentLabel' => $prepared['parliamentLabel'],
        'date' => $date,
        'lang' => $prepared['lang'],
        'title' => $title,
        'apiURL' => $config['dir']['api'] . '/session/' . rawurlencode($prepared['id']) . '?format=akn',
        'pageURL' => $config['dir']['root'] . '/session/' . rawurlencode($prepared['id']),
        'creator' => exportPlainText($firstAttr['creator'] ?? ''),
        'license' => exportPlainText($firstAttr['license'] ?? ''),
    ], $sections);
}

// ---------------------------------------------------------------------------
// Document assembly
// ---------------------------------------------------------------------------

/**
 * Build the full akomaNtoso document.
 *
 * $ctx: docID, parliament, parliamentLabel, date (Y-m-d|null), lang (639-1),
 *       title, apiURL, pageURL, creator, license
 * $sections: [ ['title' => ..., 'items' => [preparedMedia, ...]], ... ]
 */
function aknBuildDebate(array $ctx, array $sections): string {
    global $config;

    // Pre-pass: assign speech eIds and collect all TLC references, because
    // <references> precedes <debateBody> in document order.
    $tlc = aknCollectReferences($ctx, $sections);

    $w = new XMLWriter();
    $w->openMemory();
    $w->setIndent(true);
    $w->setIndentString('  ');
    $w->startDocument('1.0', 'UTF-8');

    exportXmlStart($w, 'akomaNtoso', ['xmlns' => AKN_NS]);
    exportXmlStart($w, 'debate', ['name' => 'debateRecord']);

    aknWriteMeta($w, $ctx, $tlc);

    // Preface: document title plus source attribution.
    $w->startElement('preface');
    $w->startElement('p');
    exportXmlElement($w, 'docTitle', $ctx['title']);
    $w->endElement();
    $attribution = trim(($ctx['creator'] !== '' ? $ctx['creator'] : $ctx['parliamentLabel'])
        . ($ctx['license'] !== '' ? ' — ' . $ctx['license'] : ''));
    if ($attribution !== '') {
        exportXmlElement($w, 'p', $attribution . ' (' . L::brand() . ', ' . $ctx['pageURL'] . ')');
    }
    $w->endElement(); // preface

    $w->startElement('debateBody');
    $sectionIndex = 0;
    foreach ($sections as $section) {
        $sectionIndex++;
        exportXmlStart($w, 'debateSection', [
            'name' => 'agendaItem',
            'eId' => 'dbsect_' . $sectionIndex,
        ]);
        exportXmlElement($w, 'heading', $section['title']);
        foreach ($section['items'] as $media) {
            aknWriteMediaGroups($w, $media, $tlc);
        }
        $w->endElement(); // debateSection
    }
    $w->endElement(); // debateBody

    $w->endElement(); // debate
    $w->endElement(); // akomaNtoso
    $w->endDocument();

    return $w->outputMemory();
}

/**
 * Walk all utterance groups once: assign speech eIds, register TLCPerson/
 * TLCOrganization/TLCRole entries and per-speech media-fragment TLCObjects.
 *
 * Returns:
 *  - persons/organizations/objects/roles: eId => [href, showAs]
 *  - speechRefs: spl_key ("{mediaID}:{groupIndex}") => ['eId','by','as','refersTo']
 */
function aknCollectReferences(array $ctx, array $sections): array {
    global $config;

    $tlc = [
        'persons' => [],
        'organizations' => [],
        'roles' => [],
        'objects' => [],
        'speechRefs' => [],
    ];

    // The platform itself (source of the identification block) and the parliament.
    $tlc['organizations']['openparliamenttv'] = [
        'href' => $config['dir']['root'],
        'showAs' => L::brand(),
    ];
    $tlc['organizations']['parliament'] = [
        'href' => $config['dir']['root'],
        'showAs' => $ctx['parliamentLabel'] !== '' ? $ctx['parliamentLabel'] : $ctx['parliament'],
    ];

    $speechCount = 0;
    foreach ($sections as $section) {
        foreach ($section['items'] as $media) {
            $mAttr = $media['attr'];
            foreach ($media['groups'] as $groupIndex => $group) {
                if ($group['kind'] !== 'utterance') {
                    continue;
                }
                $speechCount++;
                $eId = 'spch_' . $speechCount;

                // Speaker
                $person = $group['person'];
                if ($person !== null) {
                    $personEId = 'person-' . exportIdToken($person['id']);
                    $href = exportEntityURI($person['id'], 'person');
                    $showAs = $person['attributes']['label'] ?? $group['speaker'];
                } else {
                    $personEId = 'person-' . exportIdToken($group['speaker'] !== '' ? $group['speaker'] : 'unknown');
                    $href = $config['dir']['root'];
                    $showAs = $group['speaker'] !== '' ? $group['speaker'] : 'Unknown';
                }
                $tlc['persons'][$personEId] = ['href' => $href, 'showAs' => $showAs];

                // Faction of the speaker
                $faction = $person['attributes']['faction'] ?? null;
                if (is_array($faction) && !empty($faction['id'])) {
                    $orgEId = 'org-' . exportIdToken($faction['id']);
                    $tlc['organizations'][$orgEId] = [
                        'href' => exportEntityURI($faction['id'], 'organisation'),
                        'showAs' => $faction['label'] ?? $faction['id'],
                    ];
                }

                // Role (chair)
                $as = null;
                if (exportIsChairStatus($group['speakerstatus'])) {
                    $tlc['roles']['chair'] = [
                        'href' => 'https://www.wikidata.org/entity/Q140686',
                        'showAs' => 'Chair',
                    ];
                    $as = '#chair';
                }

                // Media fragment deep link for this speech
                $refersTo = null;
                if (!empty($mAttr['videoFileURI']) && $group['timeStart'] !== null && $group['timeEnd'] !== null) {
                    $objectEId = 'mediaFragment_' . $speechCount;
                    $tlc['objects'][$objectEId] = [
                        'href' => $mAttr['videoFileURI'] . '#t='
                            . exportFormatSeconds($group['timeStart']) . ','
                            . exportFormatSeconds($group['timeEnd']),
                        'showAs' => 'Video ' . exportFormatSeconds($group['timeStart']) . 's–'
                            . exportFormatSeconds($group['timeEnd']) . 's',
                    ];
                    $refersTo = '#' . $objectEId;
                }

                $tlc['speechRefs'][$media['id'] . ':' . $groupIndex] = [
                    'eId' => $eId,
                    'by' => '#' . $personEId,
                    'as' => $as,
                    'refersTo' => $refersTo,
                    'startTime' => $group['timeStart'] !== null
                        ? exportAbsoluteTime($mAttr['dateStart'] ?? null, $group['timeStart']) : null,
                    'endTime' => $group['timeEnd'] !== null
                        ? exportAbsoluteTime($mAttr['dateStart'] ?? null, $group['timeEnd']) : null,
                ];
            }

            // Full media resources
            foreach (['videoFileURI' => 'Video', 'audioFileURI' => 'Audio'] as $key => $label) {
                if (!empty($mAttr[$key])) {
                    $tlc['objects']['media-' . strtolower($label) . '-' . exportIdToken($media['id'])] = [
                        'href' => $mAttr[$key],
                        'showAs' => $label . ' ' . $media['id'],
                    ];
                }
            }
            if (!empty($mAttr['sourcePage'])) {
                $tlc['objects']['source-' . exportIdToken($media['id'])] = [
                    'href' => $mAttr['sourcePage'],
                    'showAs' => L::source() . ' ' . $media['id'],
                ];
            }
        }
    }

    return $tlc;
}

/** The <meta> block: FRBR identification + TLC references. */
function aknWriteMeta(XMLWriter $w, array $ctx, array $tlc): void {
    $countryCode = strtolower(substr($ctx['parliament'], 0, 2));
    $date = $ctx['date'] ?? date('Y-m-d');
    $lang3 = exportLang3($ctx['lang']);

    $workURI = '/akn/' . $countryCode . '/debate/' . $date . '/' . $ctx['docID'];
    $exprURI = $workURI . '/' . $lang3 . '@';

    $w->startElement('meta');
    exportXmlStart($w, 'identification', ['source' => '#openparliamenttv']);

    $w->startElement('FRBRWork');
    exportXmlElement($w, 'FRBRthis', null, ['value' => $workURI . '/!main']);
    exportXmlElement($w, 'FRBRuri', null, ['value' => $workURI]);
    exportXmlElement($w, 'FRBRdate', null, ['date' => $date, 'name' => 'sitting']);
    exportXmlElement($w, 'FRBRauthor', null, ['href' => '#parliament']);
    exportXmlElement($w, 'FRBRcountry', null, ['value' => $countryCode]);
    $w->endElement();

    $w->startElement('FRBRExpression');
    exportXmlElement($w, 'FRBRthis', null, ['value' => $exprURI . '/!main']);
    exportXmlElement($w, 'FRBRuri', null, ['value' => $exprURI]);
    exportXmlElement($w, 'FRBRdate', null, ['date' => $date, 'name' => 'sitting']);
    exportXmlElement($w, 'FRBRauthor', null, ['href' => '#parliament']);
    exportXmlElement($w, 'FRBRlanguage', null, ['language' => $lang3]);
    $w->endElement();

    $w->startElement('FRBRManifestation');
    exportXmlElement($w, 'FRBRthis', null, ['value' => $ctx['apiURL']]);
    exportXmlElement($w, 'FRBRuri', null, ['value' => $ctx['apiURL']]);
    exportXmlElement($w, 'FRBRdate', null, ['date' => date('Y-m-d'), 'name' => 'generation']);
    exportXmlElement($w, 'FRBRauthor', null, ['href' => '#openparliamenttv']);
    exportXmlElement($w, 'FRBRformat', null, ['value' => 'application/akn+xml']);
    $w->endElement();

    $w->endElement(); // identification

    exportXmlStart($w, 'references', ['source' => '#openparliamenttv']);
    foreach ($tlc['organizations'] as $eId => $ref) {
        exportXmlElement($w, 'TLCOrganization', null, ['eId' => $eId, 'href' => $ref['href'], 'showAs' => $ref['showAs']]);
    }
    foreach ($tlc['persons'] as $eId => $ref) {
        exportXmlElement($w, 'TLCPerson', null, ['eId' => $eId, 'href' => $ref['href'], 'showAs' => $ref['showAs']]);
    }
    foreach ($tlc['roles'] as $eId => $ref) {
        exportXmlElement($w, 'TLCRole', null, ['eId' => $eId, 'href' => $ref['href'], 'showAs' => $ref['showAs']]);
    }
    foreach ($tlc['objects'] as $eId => $ref) {
        exportXmlElement($w, 'TLCObject', null, ['eId' => $eId, 'href' => $ref['href'], 'showAs' => $ref['showAs']]);
    }
    $w->endElement(); // references

    $w->endElement(); // meta
}

/** One media item's utterance groups as <speech>/<scene> elements. */
function aknWriteMediaGroups(XMLWriter $w, array $media, array $tlc): void {
    foreach ($media['groups'] as $groupIndex => $group) {
        if ($group['kind'] === 'comment') {
            exportXmlElement($w, 'scene', $group['text']);
            continue;
        }
        $ref = $tlc['speechRefs'][$media['id'] . ':' . $groupIndex] ?? null;
        if ($ref === null) {
            continue;
        }
        // speechType requires at least one block element — skip empty speeches.
        $texts = [];
        foreach ($group['paragraphs'] as $paragraph) {
            $text = aknParagraphText($paragraph);
            if ($text !== '') {
                $texts[] = $text;
            }
        }
        if (empty($texts)) {
            continue;
        }
        exportXmlStart($w, 'speech', [
            'eId' => $ref['eId'],
            'by' => $ref['by'],
            'as' => $ref['as'],
            'refersTo' => $ref['refersTo'],
            'startTime' => $ref['startTime'],
            'endTime' => $ref['endTime'],
        ]);
        if ($group['speaker'] !== '') {
            exportXmlElement($w, 'from', $group['speaker']);
        }
        foreach ($texts as $text) {
            exportXmlElement($w, 'p', $text);
        }
        $w->endElement(); // speech
    }
}

/** Plain text of one paragraph (sentences joined, tags stripped). */
function aknParagraphText(array $paragraph): string {
    $parts = [];
    foreach (($paragraph['sentences'] ?? []) as $sentence) {
        $text = exportPlainText($sentence['text'] ?? '');
        if ($text !== '') {
            $parts[] = $text;
        }
    }
    if (empty($parts)) {
        $fallback = exportPlainText($paragraph['text'] ?? '');
        return $fallback;
    }
    return implode(' ', $parts);
}
