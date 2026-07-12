<?php

require_once (__DIR__."/export.php");

/**
 * TEI export following the ParlaMint conventions (Parla-CLARIN-aligned).
 *
 * Encoding mirrors what the OPTV Tools ParlaMint-DE importer reads:
 * <u who="#Qid" ana="#chair|#regular"> with <seg> per paragraph inside
 * <div type="debateSection">, comments as kinesic/vocal/incident with <desc>,
 * <meeting ana="#parla.term|#parla.sitting">, listPerson/listOrg with
 * Wikidata idno entries. Standalone documents inline a minimal classDecl
 * taxonomy instead of the corpus-level ParlaMint taxonomy files.
 *
 * On top of plain ParlaMint (whose released corpora are text-only), the
 * export adds the audio/video layer recommended by Parla-CLARIN:
 * recordingStmt/recording/media for the AV resources, one <timeline> per
 * media item, @start/@end on <u> and sentence-level <s @synch> alignment.
 */

const TEI_NS = 'http://www.tei-c.org/ns/1.0';

function parlamintGenerateMedia(string $mediaID, ?string $type = null, ?string $lang = null): ?string {
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

    return parlamintBuildTEI([
        'docID' => $media['id'],
        'lang' => $media['lang'],
        'title' => $title,
        'parliamentLabel' => $attr['parliamentLabel'] ?? '',
        'epNumber' => $rel['electoralPeriod']['data']['attributes']['number'] ?? '',
        'sessionNumber' => $rel['session']['data']['attributes']['number'] ?? '',
        'date' => $date,
        'pageURL' => $config['dir']['root'] . '/media/' . rawurlencode($media['id']),
        'apiURL' => $config['dir']['api'] . '/media/' . rawurlencode($media['id']) . '?format=parlamint',
        'creator' => exportPlainText($attr['creator'] ?? ''),
        'licenseText' => exportPlainText($attr['license'] ?? ''),
        'licenseURL' => exportExtractHref($attr['license'] ?? ''),
        'sourcePage' => $attr['sourcePage'] ?? '',
    ], [[
        'title' => $agendaTitle !== '' ? $agendaTitle : ($attr['parliamentLabel'] ?? $media['id']),
        'items' => [$media],
    ]]);
}

function parlamintGenerateSession(string $sessionID, ?string $type = null, ?string $lang = null): ?string {
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

    return parlamintBuildTEI([
        'docID' => $prepared['id'],
        'lang' => $prepared['lang'],
        'title' => $title,
        'parliamentLabel' => $prepared['parliamentLabel'],
        'epNumber' => $epNumber,
        'sessionNumber' => $sessionNumber,
        'date' => $date,
        'pageURL' => $config['dir']['root'] . '/session/' . rawurlencode($prepared['id']),
        'apiURL' => $config['dir']['api'] . '/session/' . rawurlencode($prepared['id']) . '?format=parlamint',
        'creator' => exportPlainText($firstAttr['creator'] ?? ''),
        'licenseText' => exportPlainText($firstAttr['license'] ?? ''),
        'licenseURL' => exportExtractHref($firstAttr['license'] ?? ''),
        'sourcePage' => '',
    ], $sections);
}

// ---------------------------------------------------------------------------
// Document assembly
// ---------------------------------------------------------------------------

/**
 * Build the full TEI document.
 *
 * $ctx: docID, lang, title, parliamentLabel, epNumber, sessionNumber,
 *       date (Y-m-d|null), pageURL, apiURL, creator, licenseText, licenseURL,
 *       sourcePage
 * $sections: [ ['title' => ..., 'items' => [preparedMedia, ...]], ... ]
 */
function parlamintBuildTEI(array $ctx, array $sections): string {
    $participants = parlamintCollectParticipants($sections);

    $w = new XMLWriter();
    $w->openMemory();
    $w->setIndent(true);
    $w->setIndentString('  ');
    $w->startDocument('1.0', 'UTF-8');

    exportXmlStart($w, 'TEI', [
        'xmlns' => TEI_NS,
        'xml:id' => exportIdToken($ctx['docID']),
        'xml:lang' => $ctx['lang'],
    ]);

    parlamintWriteHeader($w, $ctx, $sections, $participants);

    $w->startElement('text');
    $w->startElement('body');
    foreach ($sections as $section) {
        exportXmlStart($w, 'div', ['type' => 'debateSection']);
        exportXmlElement($w, 'head', $section['title']);
        foreach ($section['items'] as $media) {
            parlamintWriteTimeline($w, $media);
            parlamintWriteMediaGroups($w, $media, $participants);
        }
        $w->endElement(); // div
    }
    $w->endElement(); // body
    $w->endElement(); // text

    $w->endElement(); // TEI
    $w->endDocument();

    return $w->outputMemory();
}

/**
 * Collect the persons and organisations referenced by any utterance group.
 *
 * Returns:
 *  - persons: xml:id => ['label', 'uri' (Wikidata, or null), 'affiliation' (org xml:id or null)]
 *  - orgs:    xml:id => ['label', 'uri' (or null), 'role']
 *  - speakerIds: "{mediaID}:{groupIndex}" => person xml:id
 */
function parlamintCollectParticipants(array $sections): array {
    $persons = [];
    $orgs = [];
    $speakerIds = [];

    foreach ($sections as $section) {
        foreach ($section['items'] as $media) {
            foreach ($media['groups'] as $groupIndex => $group) {
                if ($group['kind'] !== 'utterance') {
                    continue;
                }
                $person = $group['person'];
                if ($person !== null) {
                    $personId = exportIdToken($person['id']);
                    $label = $person['attributes']['label'] ?? $group['speaker'];
                    $uri = preg_match('/^Q\d+$/', $person['id'])
                        ? 'https://www.wikidata.org/entity/' . $person['id'] : null;
                    $affiliation = null;
                    $faction = $person['attributes']['faction'] ?? null;
                    if (is_array($faction) && !empty($faction['id'])) {
                        $orgId = exportIdToken($faction['id']);
                        if (!isset($orgs[$orgId])) {
                            $orgs[$orgId] = [
                                'label' => $faction['label'] ?? $faction['id'],
                                'uri' => preg_match('/^Q\d+$/', $faction['id'])
                                    ? 'https://www.wikidata.org/entity/' . $faction['id'] : null,
                                'role' => 'parliamentaryGroup',
                            ];
                        }
                        $affiliation = $orgId;
                    }
                } else {
                    $label = $group['speaker'] !== '' ? $group['speaker'] : 'Unknown';
                    $personId = exportIdToken($label);
                    $uri = null;
                    $affiliation = null;
                }
                if (!isset($persons[$personId])) {
                    $persons[$personId] = ['label' => $label, 'uri' => $uri, 'affiliation' => $affiliation];
                }
                $speakerIds[$media['id'] . ':' . $groupIndex] = $personId;
            }
        }
    }

    return ['persons' => $persons, 'orgs' => $orgs, 'speakerIds' => $speakerIds];
}

function parlamintWriteHeader(XMLWriter $w, array $ctx, array $sections, array $participants): void {
    global $config;

    $w->startElement('teiHeader');

    // ----- fileDesc -----
    $w->startElement('fileDesc');
    $w->startElement('titleStmt');
    exportXmlElement($w, 'title', $ctx['title'], ['type' => 'main', 'xml:lang' => $ctx['lang']]);
    if ($ctx['epNumber'] !== '') {
        exportXmlElement($w, 'meeting', L::electoralPeriod() . ' ' . $ctx['epNumber'], [
            'ana' => '#parla.term',
            'n' => $ctx['epNumber'],
        ]);
    }
    if ($ctx['sessionNumber'] !== '') {
        exportXmlElement($w, 'meeting', L::session() . ' ' . $ctx['sessionNumber'], [
            'ana' => '#parla.sitting',
            'n' => $ctx['sessionNumber'],
        ]);
    }
    $w->endElement(); // titleStmt

    $w->startElement('publicationStmt');
    exportXmlElement($w, 'publisher', L::brand());
    exportXmlElement($w, 'idno', $ctx['pageURL'], ['type' => 'URI']);
    exportXmlElement($w, 'idno', $ctx['apiURL'], ['type' => 'URI', 'subtype' => 'api']);
    $w->startElement('availability');
    $w->writeAttribute('status', 'unknown');
    if ($ctx['licenseText'] !== '') {
        exportXmlElement($w, 'licence', $ctx['licenseText'], ['target' => $ctx['licenseURL']]);
    }
    exportXmlElement($w, 'p', $ctx['creator'] !== '' ? $ctx['creator'] : $ctx['parliamentLabel']);
    $w->endElement(); // availability
    exportXmlElement($w, 'date', date('Y-m-d'), ['when' => date('Y-m-d')]);
    $w->endElement(); // publicationStmt

    $w->startElement('sourceDesc');
    $w->startElement('bibl');
    exportXmlElement($w, 'title', $ctx['parliamentLabel'], ['type' => 'main']);
    if ($ctx['creator'] !== '') {
        exportXmlElement($w, 'publisher', $ctx['creator']);
    }
    if ($ctx['sourcePage'] !== '') {
        exportXmlElement($w, 'idno', $ctx['sourcePage'], ['type' => 'URI']);
    }
    if ($ctx['date'] !== null) {
        exportXmlElement($w, 'date', $ctx['date'], ['when' => $ctx['date']]);
    }
    $w->endElement(); // bibl

    // AV recordings of all contained media items (Parla-CLARIN AV layer).
    $recordings = [];
    foreach ($sections as $section) {
        foreach ($section['items'] as $media) {
            $recordings[$media['id']] = $media;
        }
    }
    $hasRecording = false;
    foreach ($recordings as $media) {
        if (!empty($media['attr']['videoFileURI']) || !empty($media['attr']['audioFileURI'])) {
            $hasRecording = true;
            break;
        }
    }
    if ($hasRecording) {
        $w->startElement('recordingStmt');
        foreach ($recordings as $media) {
            $mAttr = $media['attr'];
            $durISO = !empty($mAttr['duration']) ? 'PT' . (int) $mAttr['duration'] . 'S' : null;
            if (!empty($mAttr['videoFileURI'])) {
                exportXmlStart($w, 'recording', [
                    'xml:id' => 'rec.' . exportIdToken($media['id']) . '.video',
                    'type' => 'video',
                    'dur-iso' => $durISO,
                ]);
                exportXmlElement($w, 'media', null, ['mimeType' => 'video/mp4', 'url' => $mAttr['videoFileURI']]);
                $w->endElement();
            }
            if (!empty($mAttr['audioFileURI'])) {
                exportXmlStart($w, 'recording', [
                    'xml:id' => 'rec.' . exportIdToken($media['id']) . '.audio',
                    'type' => 'audio',
                    'dur-iso' => $durISO,
                ]);
                exportXmlElement($w, 'media', null, ['mimeType' => 'audio/mpeg', 'url' => $mAttr['audioFileURI']]);
                $w->endElement();
            }
        }
        $w->endElement(); // recordingStmt
    }
    $w->endElement(); // sourceDesc
    $w->endElement(); // fileDesc

    // ----- encodingDesc -----
    $w->startElement('encodingDesc');
    $w->startElement('projectDesc');
    exportXmlElement($w, 'p', 'Exported from ' . L::brand() . ' (' . $config['dir']['root'] . '). '
        . 'Encoded following the ParlaMint conventions (Parla-CLARIN TEI). '
        . 'Sentence-level audio/video alignment is provided via timeline/@synch '
        . 'as recommended by Parla-CLARIN for spoken corpora.', ['xml:lang' => 'en']);
    $w->endElement(); // projectDesc
    $w->startElement('classDecl');
    exportXmlStart($w, 'taxonomy', ['xml:id' => 'speaker_types']);
    exportXmlElement($w, 'desc', 'Types of speakers', ['xml:lang' => 'en']);
    exportXmlStart($w, 'category', ['xml:id' => 'chair']);
    exportXmlElement($w, 'catDesc', 'Chairperson: speaker chairing the meeting', ['xml:lang' => 'en']);
    $w->endElement();
    exportXmlStart($w, 'category', ['xml:id' => 'regular']);
    exportXmlElement($w, 'catDesc', 'Regular: a regular speaker at the meeting', ['xml:lang' => 'en']);
    $w->endElement();
    $w->endElement(); // taxonomy speaker_types
    exportXmlStart($w, 'taxonomy', ['xml:id' => 'parla.legislature']);
    exportXmlElement($w, 'desc', 'Legislature', ['xml:lang' => 'en']);
    exportXmlStart($w, 'category', ['xml:id' => 'parla.term']);
    exportXmlElement($w, 'catDesc', 'Legislative period', ['xml:lang' => 'en']);
    $w->endElement();
    exportXmlStart($w, 'category', ['xml:id' => 'parla.sitting']);
    exportXmlElement($w, 'catDesc', 'Sitting: a session on a given day', ['xml:lang' => 'en']);
    $w->endElement();
    $w->endElement(); // taxonomy parla.legislature
    $w->endElement(); // classDecl
    $w->endElement(); // encodingDesc

    // ----- profileDesc -----
    $w->startElement('profileDesc');
    $w->startElement('settingDesc');
    $w->startElement('setting');
    exportXmlElement($w, 'name', $ctx['parliamentLabel'], ['type' => 'org']);
    if ($ctx['date'] !== null) {
        exportXmlElement($w, 'date', $ctx['date'], ['when' => $ctx['date'], 'ana' => '#parla.sitting']);
    }
    $w->endElement(); // setting
    $w->endElement(); // settingDesc

    $w->startElement('particDesc');
    $w->startElement('listOrg');
    exportXmlStart($w, 'org', ['xml:id' => 'parliament', 'role' => 'parliament']);
    exportXmlElement($w, 'orgName', $ctx['parliamentLabel'], ['full' => 'yes']);
    $w->endElement();
    foreach ($participants['orgs'] as $orgId => $org) {
        exportXmlStart($w, 'org', ['xml:id' => $orgId, 'role' => $org['role']]);
        exportXmlElement($w, 'orgName', $org['label'], ['full' => 'yes']);
        if ($org['uri'] !== null) {
            exportXmlElement($w, 'idno', $org['uri'], ['type' => 'URI', 'subtype' => 'wikimedia']);
        }
        $w->endElement();
    }
    $w->endElement(); // listOrg
    if (!empty($participants['persons'])) { // an empty listPerson is invalid TEI
        $w->startElement('listPerson');
        foreach ($participants['persons'] as $personId => $person) {
            exportXmlStart($w, 'person', ['xml:id' => $personId]);
            exportXmlElement($w, 'persName', $person['label']);
            if ($person['uri'] !== null) {
                exportXmlElement($w, 'idno', $person['uri'], ['type' => 'URI', 'subtype' => 'wikimedia']);
            }
            if ($person['affiliation'] !== null) {
                exportXmlElement($w, 'affiliation', null, ['ref' => '#' . $person['affiliation'], 'role' => 'member']);
            }
            $w->endElement();
        }
        $w->endElement(); // listPerson
    }
    $w->endElement(); // particDesc

    $w->startElement('langUsage');
    exportXmlElement($w, 'language', $ctx['lang'], ['ident' => $ctx['lang']]);
    $w->endElement();
    $w->endElement(); // profileDesc

    $w->endElement(); // teiHeader
}

// ---------------------------------------------------------------------------
// Timeline + utterances
// ---------------------------------------------------------------------------

/** All distinct sentence boundary offsets of a media item, numerically sorted. */
function parlamintTimePoints(array $media): array {
    $points = [];
    foreach ($media['groups'] as $group) {
        if ($group['kind'] !== 'utterance') {
            continue;
        }
        foreach ($group['paragraphs'] as $paragraph) {
            foreach (($paragraph['sentences'] ?? []) as $sentence) {
                foreach (['timeStart', 'timeEnd'] as $key) {
                    $value = $sentence[$key] ?? null;
                    if ($value !== null && $value !== '') {
                        $points[exportFormatSeconds($value)] = (float) $value;
                    }
                }
            }
        }
    }
    asort($points);
    return $points; // formatted string => float
}

/** <when> id for an offset within a media item's timeline. */
function parlamintWhenId(string $mediaID, string $formattedSeconds): string {
    return exportIdToken($mediaID) . '.T' . $formattedSeconds;
}

/** One <timeline> per media item (skipped when it has no timed sentences). */
function parlamintWriteTimeline(XMLWriter $w, array $media): void {
    $points = parlamintTimePoints($media);
    if (empty($points)) {
        return;
    }
    $originId = exportIdToken($media['id']) . '.origin';
    exportXmlStart($w, 'timeline', [
        'unit' => 's',
        'origin' => '#' . $originId,
        'corresp' => !empty($media['attr']['videoFileURI'])
            ? '#rec.' . exportIdToken($media['id']) . '.video' : null,
    ]);
    exportXmlElement($w, 'when', null, [
        'xml:id' => $originId,
        'absolute' => !empty($media['attr']['dateStart'])
            ? (new DateTimeImmutable($media['attr']['dateStart']))->format('Y-m-d\TH:i:sP') : null,
    ]);
    foreach ($points as $formatted => $seconds) {
        exportXmlElement($w, 'when', null, [
            'xml:id' => parlamintWhenId($media['id'], $formatted),
            'interval' => $formatted,
            'since' => '#' . $originId,
        ]);
    }
    $w->endElement(); // timeline
}

/** One media item's utterance groups as <u>/<kinesic>/<vocal>/<incident>. */
function parlamintWriteMediaGroups(XMLWriter $w, array $media, array $participants): void {
    $mediaToken = exportIdToken($media['id']);
    $utteranceCount = 0;

    foreach ($media['groups'] as $groupIndex => $group) {
        if ($group['kind'] === 'comment') {
            parlamintWriteComment($w, $group['text']);
            continue;
        }

        $utteranceCount++;
        $uId = $mediaToken . '.u' . $utteranceCount;
        $personId = $participants['speakerIds'][$media['id'] . ':' . $groupIndex] ?? null;

        exportXmlStart($w, 'u', [
            'xml:id' => $uId,
            'who' => $personId !== null ? '#' . $personId : null,
            'ana' => exportIsChairStatus($group['speakerstatus']) ? '#chair' : '#regular',
            'start' => $group['timeStart'] !== null
                ? '#' . parlamintWhenId($media['id'], exportFormatSeconds($group['timeStart'])) : null,
            'end' => $group['timeEnd'] !== null
                ? '#' . parlamintWhenId($media['id'], exportFormatSeconds($group['timeEnd'])) : null,
        ]);

        $segCount = 0;
        foreach ($group['paragraphs'] as $paragraph) {
            $sentences = [];
            foreach (($paragraph['sentences'] ?? []) as $sentence) {
                $text = exportPlainText($sentence['text'] ?? '');
                if ($text !== '') {
                    $sentences[] = ['text' => $text,
                        'timeStart' => $sentence['timeStart'] ?? null,
                        'timeEnd' => $sentence['timeEnd'] ?? null];
                }
            }
            if (empty($sentences)) {
                continue;
            }
            $segCount++;
            $segId = $uId . '.seg' . $segCount;
            exportXmlStart($w, 'seg', ['xml:id' => $segId]);
            $sentenceCount = 0;
            foreach ($sentences as $sentence) {
                $sentenceCount++;
                $synch = null;
                if ($sentence['timeStart'] !== null && $sentence['timeStart'] !== ''
                    && $sentence['timeEnd'] !== null && $sentence['timeEnd'] !== '') {
                    $synch = '#' . parlamintWhenId($media['id'], exportFormatSeconds($sentence['timeStart']))
                        . ' #' . parlamintWhenId($media['id'], exportFormatSeconds($sentence['timeEnd']));
                }
                exportXmlElement($w, 's', $sentence['text'], [
                    'xml:id' => $segId . '.s' . $sentenceCount,
                    'synch' => $synch,
                ]);
            }
            $w->endElement(); // seg
        }
        $w->endElement(); // u
    }
}

/**
 * A transcript comment as kinesic/vocal/incident with <desc> (outer
 * parentheses stripped; re-added by consumers, cf. parlamint2json.py).
 */
function parlamintWriteComment(XMLWriter $w, string $text): void {
    $inner = trim($text);
    if (preg_match('/^\((.*)\)$/su', $inner, $m)) {
        $inner = trim($m[1]);
    }
    if ($inner === '') {
        return;
    }
    if (preg_match('/^(Beifall|Applaus|Applause)/iu', $inner)) {
        $element = 'kinesic';
        $type = 'applause';
    } elseif (preg_match('/^(Heiterkeit|Lachen|Laughter)/iu', $inner)) {
        $element = 'kinesic';
        $type = 'laughter';
    } elseif (preg_match('/^(Zurufe?|Gegenrufe?|Widerspruch)/iu', $inner)) {
        $element = 'vocal';
        $type = 'interruption';
    } else {
        $element = 'incident';
        $type = null;
    }
    exportXmlStart($w, $element, ['type' => $type]);
    exportXmlElement($w, 'desc', $inner);
    $w->endElement();
}
