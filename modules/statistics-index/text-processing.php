<?php

/**
 * Word extraction for the statistics index.
 *
 * Tokenization is deliberately language-agnostic: it keeps anything Unicode
 * considers a letter or number, plus the three characters that carry meaning
 * *inside* a word in parliamentary German and its neighbours -- the hyphen of
 * "Riester-Rente", the slash of "CDU/CSU" and "90/DIE", and the apostrophe of
 * "O'Brien's". Everything else is a separator. Which words are then discarded is
 * a per-language question, answered by the configured stopword list.
 *
 * There is exactly ONE minimum-length rule, applied in one place
 * (statsIndexNormalizeWord), and it counts characters rather than bytes.
 *
 * @see segments.php for which parts of a transcript are fed in here at all.
 */

require_once(__DIR__ . '/segments.php');

/** Fallback minimum word length, in characters, when nothing is configured. */
const STATS_MIN_WORD_LENGTH = 3;

/**
 * Characters that stay attached inside a word: hyphen, slash, apostrophe.
 * Used as an exception set in the tokenizer's separator patterns.
 */
const STATS_WORD_INNER = '\'\/-';

/**
 * Resolve the stopword list for a language.
 *
 * Lookup order: the per-language list, then the legacy flat list (so instances
 * that have not migrated their config keep working), then nothing. A language
 * with no configured list simply has no stopwords -- that is a setup task per
 * parliament, not an error.
 *
 * @param array|null  $config Platform config; the global one when omitted.
 * @param string|null $lang   Two-letter language code of the transcript.
 * @return array Lowercased stopwords.
 */
function statsIndexGetStopwords($config = null, $lang = null)
{
    if ($config === null) {
        $config = $GLOBALS["config"] ?? [];
    }

    if ($lang !== null && isset($config['statistics']['stopwords'][$lang])
        && is_array($config['statistics']['stopwords'][$lang])) {
        return $config['statistics']['stopwords'][$lang];
    }

    // Pre-1.0 flat list, kept as a fallback so unmigrated instances keep working.
    if (isset($config['excludedStopwords']) && is_array($config['excludedStopwords'])) {
        return $config['excludedStopwords'];
    }

    return [];
}

/**
 * Language of a speech's transcript.
 *
 * Falls back to the parliament's configured statistics language, then to the
 * platform default. Never guesses from the text.
 *
 * @param array       $speechData A media node.
 * @param string|null $parliament Parliament config key.
 * @param array|null  $config     Platform config; the global one when omitted.
 * @return string|null
 */
function statsIndexResolveLanguage($speechData, $parliament = null, $config = null)
{
    if ($config === null) {
        $config = $GLOBALS["config"] ?? [];
    }

    foreach (($speechData['attributes']['textContents'] ?? []) as $textContent) {
        if (!empty($textContent['language'])) {
            return strtolower(substr($textContent['language'], 0, 2));
        }
    }

    if ($parliament !== null && !empty($config['parliament'][$parliament]['statistics']['language'])) {
        return strtolower($config['parliament'][$parliament]['statistics']['language']);
    }

    return null;
}

/**
 * Is this word on the stopword list?
 *
 * A pure list lookup. Length filtering happens in statsIndexNormalizeWord(); it
 * used to happen here too, with a different threshold, measured in bytes.
 *
 * @param string $word      An already-normalized (lowercased) word.
 * @param array  $stopwords Resolved list from statsIndexGetStopwords().
 * @return bool
 */
function statsIndexIsStopword($word, array $stopwords)
{
    return $word === '' || in_array($word, $stopwords, true);
}

/**
 * Split text into raw word candidates.
 *
 * Splits on whitespace, symbols and separators, and on punctuation -- except
 * where a hyphen, slash or apostrophe sits between two alphanumerics, which is
 * what keeps "CDU/CSU-Fraktion" and "O'Brien's" in one piece.
 *
 * @param string $text
 * @return array Raw tokens, still to be normalized.
 */
function statsIndexTokenize($text)
{
    $text = strip_tags($text);

    $splitPattern = '/[\s\p{S}\p{Z}]'                                   // whitespace, symbols, separators
        . '|(?:(?![' . STATS_WORD_INNER . '])\p{P})'                    // punctuation, except the inner three
        . '|(?<![\p{L}\p{N}])[' . STATS_WORD_INNER . ']+'               // inner chars not preceded by alphanumeric
        . '|[' . STATS_WORD_INNER . ']+(?![\p{L}\p{N}])/u';             // ... or not followed by one

    $tokens = preg_split($splitPattern, mb_strtolower(trim($text), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);

    return $tokens === false ? [] : $tokens;
}

/**
 * Normalize one token, or reject it.
 *
 * This is the single place the minimum-length rule lives.
 *
 * @param string   $word
 * @param int|null $minLength Characters; STATS_MIN_WORD_LENGTH when omitted.
 * @return string The normalized word, or '' when it does not count.
 */
function statsIndexNormalizeWord($word, $minLength = null)
{
    if ($minLength === null) {
        $minLength = STATS_MIN_WORD_LENGTH;
    }

    $normalized = mb_strtolower(trim($word), 'UTF-8');

    // Strip leading/trailing punctuation, symbols and separators, keeping the
    // inner three so "riester-rente" survives but "-rente" loses its dash.
    $edgePattern = '/^(?:(?![' . STATS_WORD_INNER . '])[\p{P}\p{S}\p{Z}])+'
        . '|(?:(?![' . STATS_WORD_INNER . '])[\p{P}\p{S}\p{Z}])+$/u';
    $normalized = preg_replace($edgePattern, '', $normalized);

    // Keep letters and numbers of any script, plus the inner three.
    $normalized = preg_replace('/[^\p{L}\p{N}' . STATS_WORD_INNER . ']/u', '', $normalized);

    $normalized = trim($normalized, "'-/");

    if (mb_strlen($normalized, 'UTF-8') < $minLength) {
        return '';
    }

    // Must contain at least one letter, and must not be a bare number.
    if (is_numeric($normalized) || !preg_match('/\p{L}/u', $normalized)) {
        return '';
    }

    return $normalized;
}

/**
 * Word frequencies of a speech, split by segment class.
 *
 * Returns one entry per class that actually has text, each carrying both the
 * counted words and the two totals that normalization needs:
 *
 *   tokenCount        every token that survived tokenization + length rules,
 *                     INCLUDING stopwords -- the denominator for "per million
 *                     words" figures
 *   countedWordCount  the subset that produced word documents
 *
 * @param array       $speechData A media node.
 * @param array|null  $config     Platform config; the global one when omitted.
 * @param string|null $parliament Parliament config key, for language fallback.
 * @return array [class => ['words' => [word => count], 'tokenCount' => int,
 *               'countedWordCount' => int]]
 */
function statsIndexGetWordFrequencies($speechData, $config = null, $parliament = null)
{
    if ($config === null) {
        $config = $GLOBALS["config"] ?? [];
    }

    $minLength = $config['statistics']['minWordLength'] ?? STATS_MIN_WORD_LENGTH;
    $lang      = statsIndexResolveLanguage($speechData, $parliament, $config);
    $stopwords = statsIndexGetStopwords($config, $lang);

    $result = [];

    foreach (statsIndexGetSegments($speechData) as $segment) {
        $class = $segment['class'];

        if (!isset($result[$class])) {
            $result[$class] = ['words' => [], 'tokenCount' => 0, 'countedWordCount' => 0];
        }

        foreach (statsIndexTokenize($segment['text']) as $token) {
            $normalized = statsIndexNormalizeWord($token, $minLength);
            if ($normalized === '') {
                continue;
            }

            $result[$class]['tokenCount']++;

            if (statsIndexIsStopword($normalized, $stopwords)) {
                continue;
            }

            $result[$class]['countedWordCount']++;
            $result[$class]['words'][$normalized] = ($result[$class]['words'][$normalized] ?? 0) + 1;
        }
    }

    return $result;
}
