<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/i18n/language.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/media.php");

/**
 * Standalone WebVTT transcript generation for media items.
 *
 * Independent of IIIF: powers a plain `/api/v1/media/{id}/transcript.vtt` endpoint
 * (download buttons, accessibility, external tools) and is reused by the IIIF module
 * to build `supplementing` annotations.
 *
 * A media item can carry multiple transcripts in `textContents`, keyed by
 * TextType (e.g. "proceedings", "generated") and TextLanguage (BCP 47).
 */

/**
 * Normalize a language value to its primary BCP 47 subtag (lowercase).
 * Handles "de", "de-DE" and the legacy parliament-prefixed "DE-de" form,
 * all of which resolve to "de".
 *
 * @param string $lang
 * @return string e.g. "de" (empty string if input is empty)
 */
function transcriptNormalizeLang(string $lang): string {
    $lang = trim($lang);
    if ($lang === '') {
        return '';
    }
    $parts = preg_split('/[-_]/', $lang);
    // Prefer an already-lowercase 2–3 letter part (the language subtag).
    foreach ($parts as $part) {
        if (preg_match('/^[a-z]{2,3}$/', $part)) {
            return $part;
        }
    }
    // Otherwise take the first alphabetic part, lowercased.
    foreach ($parts as $part) {
        if (preg_match('/^[A-Za-z]{2,3}$/', $part)) {
            return strtolower($part);
        }
    }
    return strtolower($parts[0]);
}

/**
 * Format seconds as a WebVTT timestamp (HH:MM:SS.mmm).
 *
 * @param float $seconds
 * @return string e.g. "00:05:23.400"
 */
function transcriptFormatVTTTime(float $seconds): string {
    if ($seconds < 0) {
        $seconds = 0.0;
    }
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds - $hours * 3600) / 60);
    $secs = $seconds - $hours * 3600 - $minutes * 60;
    return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
}

/**
 * Localized label for a TextType value (reuses existing lang keys).
 *
 * @param string $textType e.g. "proceedings", "generated"
 * @return string
 */
function transcriptTypeLabel(string $textType): string {
    switch (strtolower($textType)) {
        case 'proceedings':
            return L::proceedings();
        default:
            return ucfirst($textType);
    }
}

/**
 * Generate a WebVTT document from a single textContents entry.
 * Sentences without usable time codes are skipped; HTML is stripped.
 *
 * @param array $textContent One entry from a media item's textContents array
 * @return string WebVTT content
 */
function transcriptGenerateVTT(array $textContent): string {
    $out = "WEBVTT\n\n";

    $textBody = $textContent['textBody'] ?? [];
    if (!is_array($textBody)) {
        return $out;
    }

    foreach ($textBody as $paragraph) {
        if (empty($paragraph['sentences']) || !is_array($paragraph['sentences'])) {
            continue;
        }
        foreach ($paragraph['sentences'] as $sentence) {
            if (!isset($sentence['timeStart'], $sentence['timeEnd'])) {
                continue;
            }
            $start = $sentence['timeStart'];
            $end = $sentence['timeEnd'];
            if ($start === '' || $start === null || $end === '' || $end === null) {
                continue;
            }
            $startF = (float) $start;
            $endF = (float) $end;
            if ($endF <= $startF) {
                continue; // WebVTT requires end > start
            }

            $text = isset($sentence['text'])
                ? trim(html_entity_decode(strip_tags($sentence['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : '';
            if ($text === '') {
                continue;
            }

            $out .= transcriptFormatVTTTime($startF) . ' --> ' . transcriptFormatVTTTime($endF) . "\n";
            $out .= $text . "\n\n";
        }
    }

    return $out;
}

/**
 * Resolve which textContents entry to serve given optional type/lang params.
 *
 * Fallback logic:
 *  - type + lang  -> that exact transcript, or null (caller returns 404)
 *  - lang only    -> preferred type (proceedings > generated) in that language
 *  - type only    -> that type in the default language, else that type in any language
 *  - neither      -> best available: default language + preferred type, else any
 *
 * @param array       $textContents Full textContents array from the media item
 * @param string|null $type         Requested TextType (proceedings|generated|...)
 * @param string|null $lang         Requested language (any BCP 47 / legacy form)
 * @param string      $defaultLang  Instance default language (e.g. from getDefaultLang())
 * @return array|null The matching textContents entry, or null if none
 */
function transcriptResolve(array $textContents, ?string $type, ?string $lang, string $defaultLang): ?array {
    if (empty($textContents)) {
        return null;
    }

    $typePreference = ['proceedings', 'generated'];

    $wantType = ($type !== null && $type !== '') ? strtolower($type) : null;
    $wantLang = ($lang !== null && $lang !== '') ? transcriptNormalizeLang($lang) : null;
    $defaultLangNorm = transcriptNormalizeLang($defaultLang);

    // Annotate candidates with normalized type/language.
    $candidates = [];
    foreach ($textContents as $tc) {
        $candidates[] = [
            'tc' => $tc,
            'type' => strtolower($tc['type'] ?? ''),
            'lang' => transcriptNormalizeLang($tc['language'] ?? ''),
        ];
    }

    // Find a transcript of (optional) type within a specific language.
    $findInLang = function (?string $t, string $l) use ($candidates, $typePreference) {
        if ($t !== null) {
            foreach ($candidates as $c) {
                if ($c['type'] === $t && $c['lang'] === $l) {
                    return $c['tc'];
                }
            }
            return null;
        }
        foreach ($typePreference as $pt) {
            foreach ($candidates as $c) {
                if ($c['type'] === $pt && $c['lang'] === $l) {
                    return $c['tc'];
                }
            }
        }
        foreach ($candidates as $c) {
            if ($c['lang'] === $l) {
                return $c['tc'];
            }
        }
        return null;
    };

    // Explicit language: exact behavior, no cross-language fallback.
    if ($wantLang !== null) {
        return $findInLang($wantType, $wantLang);
    }

    // No language: try default language first.
    $result = $findInLang($wantType, $defaultLangNorm);
    if ($result !== null) {
        return $result;
    }

    // Then fall back to preferred type (or requested type) in any language.
    $orderedTypes = ($wantType !== null) ? [$wantType] : $typePreference;
    foreach ($orderedTypes as $pt) {
        foreach ($candidates as $c) {
            if ($c['type'] === $pt) {
                return $c['tc'];
            }
        }
    }

    // Absolute fallback: the first available transcript.
    return $textContents[0] ?? null;
}

/**
 * Send a plain-text 404 and terminate. Used for missing transcripts/media.
 */
function transcriptSend404(): void {
    header('Content-Type: text/plain; charset=utf-8', true, 404);
    echo "404 Not Found";
    exit;
}

/**
 * Serve a WebVTT transcript for a media item directly to the client.
 * Sets text/vtt headers, echoes the VTT and exits (bypassing the JSON encoder
 * in api/v1/index.php).
 *
 * @param string      $mediaID e.g. "DE-0190061003"
 * @param string|null $type    Requested TextType
 * @param string|null $lang    Requested language
 * @return void  (always exits)
 */
function transcriptServeVTT(string $mediaID, ?string $type = null, ?string $lang = null): void {
    if ($mediaID === '') {
        transcriptSend404();
    }

    $resp = mediaGetByID($mediaID);

    $isSuccess = isset($resp['meta']['requestStatus']) && $resp['meta']['requestStatus'] === 'success';
    if (!$isSuccess || empty($resp['data']['attributes'])) {
        // Covers not-found and non-public media (mediaGetByID enforces access).
        transcriptSend404();
    }

    $textContents = $resp['data']['attributes']['textContents'] ?? [];
    $defaultLang = LanguageManager::getDefaultLang();

    $textContent = transcriptResolve($textContents, $type, $lang, $defaultLang);
    if ($textContent === null) {
        transcriptSend404();
    }

    $vtt = transcriptGenerateVTT($textContent);

    // Discard any buffered output (stray whitespace from included files) so the
    // response body starts exactly with "WEBVTT" as the format requires.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/vtt; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *'); // IIIF viewers fetch cross-origin
    echo $vtt;
    exit;
}
