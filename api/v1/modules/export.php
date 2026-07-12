<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.entities.php");
require_once (__DIR__."/../../../modules/i18n/language.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/media.php");
require_once (__DIR__."/session.php");
require_once (__DIR__."/transcript.php");
require_once (__DIR__."/akomantoso.php");
require_once (__DIR__."/parlamint.php");

/**
 * XML exchange-format exports for media items and sessions.
 *
 * Two formats are served from the existing media/session URIs via content
 * negotiation, mirroring the IIIF module:
 *  - Akoma Ntoso 1.0 (OASIS LegalDocML) <debate> documents
 *      ?format=akomantoso (alias: akn)   /  Accept: application/akn+xml
 *  - TEI following the ParlaMint conventions (Parla-CLARIN-aligned)
 *      ?format=parlamint (alias: tei)    /  Accept: application/tei+xml
 *
 * Optional ?type= / &lang= select among multiple transcripts (same semantics
 * as the WebVTT endpoint, via transcriptResolve()).
 *
 * This file carries the request dispatch and the format-independent data
 * shaping (utterance grouping, speaker resolution, time helpers); the actual
 * document generators live in akomantoso.php / parlamint.php.
 */

// ---------------------------------------------------------------------------
// Request detection / dispatch
// ---------------------------------------------------------------------------

/**
 * Which export format the request asks for: "akn", "tei" or null.
 * An explicit ?format= param wins; otherwise the Accept header decides.
 */
function exportRequestedFormat(array $req): ?string {
    $format = strtolower(trim((string) ($req['format'] ?? '')));
    if ($format === 'akomantoso' || $format === 'akn') {
        return 'akn';
    }
    if ($format === 'parlamint' || $format === 'tei') {
        return 'tei';
    }
    if ($format !== '') {
        return null; // some other format (e.g. iiif) — not ours
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/akn+xml') !== false) {
        return 'akn';
    }
    if (stripos($accept, 'application/tei+xml') !== false) {
        return 'tei';
    }
    return null;
}

/**
 * If the request targets a resource we can express in the requested format,
 * serve the XML directly and exit. Otherwise return, so the caller falls back
 * to normal OPTV routing (which also produces the correct error responses for
 * missing/non-public items).
 */
function handleExportRequest(array $req): void {
    $format = exportRequestedFormat($req);
    if ($format === null || ($req['action'] ?? '') !== 'getItem') {
        return;
    }
    $id = $req['id'] ?? '';
    if ($id === '') {
        return;
    }
    $type = isset($req['type']) && $req['type'] !== '' ? (string) $req['type'] : null;
    $lang = isset($req['lang']) && $req['lang'] !== '' ? (string) $req['lang'] : null;

    switch ($req['itemType'] ?? '') {
        case 'media':
            $xml = ($format === 'akn')
                ? aknGenerateMedia($id, $type, $lang)
                : parlamintGenerateMedia($id, $type, $lang);
            break;
        case 'session':
            $xml = ($format === 'akn')
                ? aknGenerateSession($id, $type, $lang)
                : parlamintGenerateSession($id, $type, $lang);
            break;
        default:
            return;
    }

    if ($xml === null) {
        return; // fall through to normal routing
    }
    exportServeXML($xml, $format, $id);
}

/**
 * Send an XML document with the right headers and terminate (bypasses the
 * JSON encoder in api/v1/index.php, like transcriptServeVTT()).
 *
 * Browsers don't know the registered akn+xml/tei+xml media types and would
 * offer a download, so those are only sent when the client explicitly asked
 * for them via Accept; everyone else (browser address bar, plain curl) gets
 * application/xml, which renders inline as an XML tree.
 */
function exportServeXML(string $xml, string $format, string $id): void {
    // Discard any buffered output so the response starts with the XML declaration.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $specificType = ($format === 'akn') ? 'application/akn+xml' : 'application/tei+xml';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = (stripos($accept, $specificType) !== false) ? $specificType : 'application/xml';
    $suffix = ($format === 'akn') ? '.akn.xml' : '.tei.xml';
    header('Content-Type: ' . $contentType . '; charset=utf-8');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $id) . $suffix . '"');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *');
    echo $xml;
    exit;
}

// ---------------------------------------------------------------------------
// Data shaping (shared by both generators)
// ---------------------------------------------------------------------------

/**
 * Load one media item and shape everything the generators need.
 * Returns null when the item is unavailable (not found / non-public).
 *
 * The returned array:
 *  - id, attr, rel, annotations: straight from mediaGetByID
 *  - textContent: the transcript entry selected via transcriptResolve(), or null
 *  - lang: primary language subtag of the transcript (fallback: instance default)
 *  - groups: see exportBuildUtteranceGroups(), each utterance annotated with
 *            the resolved 'person' (or null)
 *  - mainSpeaker / mainFaction: for titles and metadata
 */
function exportPrepareMedia(string $mediaID, ?string $type = null, ?string $lang = null): ?array {
    $resp = mediaGetByID($mediaID);
    if (!is_array($resp) || ($resp['meta']['requestStatus'] ?? '') !== 'success' || empty($resp['data'])) {
        return null;
    }
    $speech = $resp['data'];
    $attr = $speech['attributes'] ?? [];
    $rel = $speech['relationships'] ?? [];
    $annotations = $speech['annotations']['data'] ?? [];

    $tc = transcriptResolve($attr['textContents'] ?? [], $type, $lang, LanguageManager::getDefaultLang());
    $groups = exportBuildUtteranceGroups(is_array($tc) ? ($tc['textBody'] ?? []) : []);
    foreach ($groups as &$group) {
        if ($group['kind'] === 'utterance') {
            $group['person'] = exportResolveSpeakerPerson($group['speaker'], $group['speakerstatus'], $rel, $annotations);
        }
    }
    unset($group);

    $langCode = transcriptNormalizeLang(is_array($tc) ? ($tc['language'] ?? '') : '');
    if ($langCode === '') {
        $langCode = transcriptNormalizeLang(LanguageManager::getDefaultLang());
    }

    return [
        'id' => $speech['id'] ?? $mediaID,
        'attr' => $attr,
        'rel' => $rel,
        'annotations' => $annotations,
        'textContent' => is_array($tc) ? $tc : null,
        'lang' => $langCode,
        'groups' => $groups,
        'mainSpeaker' => getMainSpeakerFromPeopleArray($annotations, $rel['people']['data'] ?? []),
        'mainFaction' => getMainFactionFromOrganisationsArray($annotations, $rel['organisations']['data'] ?? []),
    ];
}

/**
 * Load a session and all its public speeches in agenda order (same listing as
 * the IIIF session Manifest). Returns null when the session is unavailable or
 * has no exportable speeches.
 *
 * The returned array:
 *  - id, session (sessionGetByID data), parliament, parliamentLabel, lang
 *  - sections: agenda-item groups, each
 *      ['agendaItemID', 'order', 'title', 'items' => [preparedMedia, ...]]
 */
function exportPrepareSession(string $sessionID, ?string $type = null, ?string $lang = null): ?array {
    global $config;

    $resp = sessionGetByID($sessionID);
    if (!is_array($resp) || ($resp['meta']['requestStatus'] ?? '') !== 'success' || empty($resp['data'])) {
        return null;
    }
    $session = $resp['data'];

    $idInfo = getInfosFromStringID($sessionID);
    if (!$idInfo || !array_key_exists($idInfo['parliament'], $config['parliament'])) {
        return null;
    }
    $parliament = $idInfo['parliament'];

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

    $sections = [];
    $docLang = '';
    foreach ($rows as $row) {
        $prepared = exportPrepareMedia($row['MediaID'], $type, $lang);
        if ($prepared === null) {
            continue;
        }
        if ($docLang === '') {
            $docLang = $prepared['lang'];
        }
        $aiID = $row['MediaAgendaItemID'];
        if (!isset($sections[$aiID])) {
            $sections[$aiID] = [
                'agendaItemID' => $aiID,
                'order' => (int) $row['AgendaItemOrder'],
                'title' => $row['AgendaItemTitle'] ?: ($config['parliament'][$parliament]['label'] ?? $parliament),
                'items' => [],
            ];
        }
        $sections[$aiID]['items'][] = $prepared;
    }
    if (empty($sections)) {
        return null;
    }

    return [
        'id' => $session['id'] ?? $sessionID,
        'session' => $session,
        'parliament' => $parliament,
        'parliamentLabel' => $config['parliament'][$parliament]['label'] ?? $parliament,
        'lang' => $docLang !== '' ? $docLang : transcriptNormalizeLang(LanguageManager::getDefaultLang()),
        'sections' => array_values($sections),
    ];
}

/**
 * Group textBody paragraphs into a flat sequence of rendering items:
 *  - ['kind' => 'utterance', 'speaker' => label, 'speakerstatus' => status,
 *     'paragraphs' => [...], 'timeStart' => float|null, 'timeEnd' => float|null]
 *    (consecutive speech paragraphs by the same speaker are one utterance)
 *  - ['kind' => 'comment', 'text' => "(Beifall ...)"]
 */
function exportBuildUtteranceGroups(array $textBody): array {
    $groups = [];
    foreach ($textBody as $paragraph) {
        if (!is_array($paragraph)) {
            continue;
        }
        if (($paragraph['type'] ?? '') === 'speech') {
            $speaker = (string) ($paragraph['speaker'] ?? '');
            $status = (string) ($paragraph['speakerstatus'] ?? '');
            $lastIdx = count($groups) - 1;
            if ($lastIdx >= 0
                && $groups[$lastIdx]['kind'] === 'utterance'
                && $groups[$lastIdx]['speaker'] === $speaker
                && $groups[$lastIdx]['speakerstatus'] === $status) {
                $groups[$lastIdx]['paragraphs'][] = $paragraph;
            } else {
                $groups[] = [
                    'kind' => 'utterance',
                    'speaker' => $speaker,
                    'speakerstatus' => $status,
                    'paragraphs' => [$paragraph],
                ];
            }
        } else {
            // Comments (applause, interjections, stage directions)
            $text = trim((string) ($paragraph['text'] ?? ''));
            if ($text === '' && !empty($paragraph['sentences']) && is_array($paragraph['sentences'])) {
                $text = trim(implode(' ', array_map(function ($s) {
                    return $s['text'] ?? '';
                }, $paragraph['sentences'])));
            }
            $text = exportPlainText($text);
            if ($text !== '') {
                $groups[] = ['kind' => 'comment', 'text' => $text];
            }
        }
    }

    foreach ($groups as &$group) {
        if ($group['kind'] !== 'utterance') {
            continue;
        }
        $start = null;
        $end = null;
        foreach ($group['paragraphs'] as $paragraph) {
            foreach (($paragraph['sentences'] ?? []) as $sentence) {
                $s = $sentence['timeStart'] ?? null;
                $e = $sentence['timeEnd'] ?? null;
                if ($s !== null && $s !== '' && ($start === null || (float) $s < $start)) {
                    $start = (float) $s;
                }
                if ($e !== null && $e !== '' && ($end === null || (float) $e > $end)) {
                    $end = (float) $e;
                }
            }
        }
        $group['timeStart'] = $start;
        $group['timeEnd'] = $end;
    }
    unset($group);

    return $groups;
}

/**
 * Find the person entity behind a paragraph's speaker label. Matches the
 * label (then labelAlternative) against the media item's people, then falls
 * back to the person annotation whose context equals the speakerstatus
 * (e.g. "main-speaker", "president"). Returns the person array or null.
 */
function exportResolveSpeakerPerson(?string $speakerLabel, ?string $speakerstatus, array $rel, array $annotations): ?array {
    $people = $rel['people']['data'] ?? [];
    if ($speakerLabel !== null && $speakerLabel !== '') {
        foreach ($people as $person) {
            if (($person['attributes']['label'] ?? '') === $speakerLabel) {
                return $person;
            }
        }
        foreach ($people as $person) {
            $alternatives = $person['attributes']['labelAlternative'] ?? [];
            if (is_array($alternatives) && in_array($speakerLabel, $alternatives, true)) {
                return $person;
            }
        }
    }
    if ($speakerstatus !== null && $speakerstatus !== '') {
        foreach ($annotations as $annotation) {
            if (($annotation['type'] ?? '') === 'person'
                && ($annotation['attributes']['context'] ?? '') === $speakerstatus) {
                foreach ($people as $person) {
                    if (($person['id'] ?? null) === ($annotation['id'] ?? '')) {
                        return $person;
                    }
                }
            }
        }
    }
    return null;
}

/** Whether a speakerstatus denotes the chair of the sitting. */
function exportIsChairStatus(?string $status): bool {
    return in_array($status, ['president', 'vice-president', 'interim-president'], true);
}

// ---------------------------------------------------------------------------
// Small value helpers
// ---------------------------------------------------------------------------

/** Decode entities, strip tags, collapse whitespace. */
function exportPlainText(?string $html): string {
    if ($html === null || $html === '') {
        return '';
    }
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    return trim(preg_replace('/\s+/u', ' ', $text));
}

/** First href in a (possibly entity-encoded) HTML fragment, or null. */
function exportExtractHref(?string $html): ?string {
    if ($html === null || $html === '') {
        return null;
    }
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $decoded, $m)) {
        return $m[1];
    }
    return null;
}

/** ISO 639-1 → ISO 639-2/B code (for Akoma Ntoso FRBRlanguage). */
function exportLang3(string $lang2): string {
    $map = ['de' => 'deu', 'en' => 'eng', 'fr' => 'fra', 'tr' => 'tur'];
    return $map[strtolower($lang2)] ?? strtolower($lang2);
}

/**
 * Absolute ISO 8601 datetime for "media start + offset seconds", or null when
 * no usable base datetime exists.
 */
function exportAbsoluteTime(?string $dateStart, $offsetSeconds): ?string {
    if (empty($dateStart)) {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($dateStart);
    } catch (Exception $e) {
        return null;
    }
    $seconds = (int) round((float) $offsetSeconds);
    return $dt->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds')->format('Y-m-d\TH:i:sP');
}

/** "Y-m-d" date of a media/session dateStart, or null. */
function exportDateOnly(?string $dateStart): ?string {
    if (empty($dateStart)) {
        return null;
    }
    $ts = strtotime($dateStart);
    return $ts === false ? null : date('Y-m-d', $ts);
}

/**
 * Canonical seconds string for xml:ids and media fragments:
 * "1.000" → "1", "5.260" → "5.26", "244.48" → "244.48".
 */
function exportFormatSeconds($seconds): string {
    $formatted = rtrim(rtrim(number_format((float) $seconds, 3, '.', ''), '0'), '.');
    return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
}

/** Sanitize a string into an NCName-safe id fragment. */
function exportIdToken(string $value): string {
    $token = preg_replace('/[^A-Za-z0-9._-]+/u', '_', $value);
    $token = trim($token, '_');
    if ($token === '' || !preg_match('/^[A-Za-z]/', $token)) {
        $token = 'x' . $token;
    }
    return $token;
}

/**
 * Reference URI for an entity: Wikidata for Q-ids, the OPTV entity page
 * otherwise ($entityType e.g. "person", "organisation").
 */
function exportEntityURI(string $entityID, string $entityType): string {
    global $config;
    if (preg_match('/^Q\d+$/', $entityID)) {
        return 'https://www.wikidata.org/entity/' . $entityID;
    }
    return $config['dir']['root'] . '/' . rawurlencode($entityType) . '/' . rawurlencode($entityID);
}

// ---------------------------------------------------------------------------
// XMLWriter convenience
// ---------------------------------------------------------------------------

/** Start an element and write its non-empty attributes. */
function exportXmlStart(XMLWriter $w, string $name, array $attrs = []): void {
    $w->startElement($name);
    foreach ($attrs as $key => $value) {
        if ($value !== null && $value !== '') {
            $w->writeAttribute($key, (string) $value);
        }
    }
}

/** Write a complete element with optional text content and attributes. */
function exportXmlElement(XMLWriter $w, string $name, ?string $text = null, array $attrs = []): void {
    exportXmlStart($w, $name, $attrs);
    if ($text !== null && $text !== '') {
        $w->text($text);
    }
    $w->endElement();
}
