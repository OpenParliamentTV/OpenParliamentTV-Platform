<?php

/**
 * Stem computation for the statistics index.
 *
 * The statistics index stores two word fields: `word` (the surface form, for
 * display and autocomplete) and `stem` (for grouping). Without a stem field
 * "Flüchtling" and "Flüchtlinge" are two unrelated trend lines, and statistics
 * counts never match search-result counts for the same term -- the search index
 * stems at query time, the statistics index did not stem at all.
 *
 * Stems come from OpenSearch rather than a PHP stemmer, so grouping is by
 * construction the same operation the search index performs.
 *
 * Configuration is per parliament:
 *   $config['parliament'][<key>]['statistics']['stemFilter'] = 'light_german'
 * With nothing configured, stem === word. Grouping then degrades to exact
 * matching and every query keeps working unchanged -- which is what lets a new
 * parliament come online before anyone has picked a stemmer for its language.
 */

require_once(__DIR__ . '/../../api/v1/utilities.php');

/** Words per _analyze request. Keeps the request body well clear of any limit. */
const STATS_STEM_BATCH_SIZE = 1000;

/**
 * The stemmer configured for a parliament, if any.
 *
 * @param string|null $parliament Parliament config key.
 * @param array|null  $config     Platform config; the global one when omitted.
 * @return string|null OpenSearch stemmer name, or null for no stemming.
 */
function statsIndexStemFilter($parliament = null, $config = null)
{
    if ($config === null) {
        $config = $GLOBALS['config'] ?? [];
    }

    if ($parliament === null) {
        return null;
    }

    $filter = $config['parliament'][$parliament]['statistics']['stemFilter'] ?? null;

    return (is_string($filter) && $filter !== '') ? $filter : null;
}

/**
 * Map words to their stems.
 *
 * Sends all unseen words in one _analyze call per batch, using the `whitespace`
 * tokenizer so tokens map 1:1 back onto the input -- `standard` would split
 * "cdu/csu" and "riester-rente" and destroy the alignment. Tokens are matched by
 * their reported position rather than by array order.
 *
 * Results are memoised for the life of the process. Parliamentary vocabulary is
 * Zipfian, so across a full rebuild the hit rate is very high and the number of
 * actual HTTP calls stays small.
 *
 * On any failure the words map to themselves: stemming is an enhancement, and
 * losing it must never abort an indexing run.
 *
 * @param array       $words      Normalized, lowercased words.
 * @param string|null $parliament Parliament config key.
 * @param array|null  $config     Platform config; the global one when omitted.
 * @return array word => stem, covering every input word.
 */
function statsIndexStemWords(array $words, $parliament = null, $config = null)
{
    static $memo = [];
    static $failed = [];

    $filter = statsIndexStemFilter($parliament, $config);
    if ($filter === null) {
        return array_combine($words, $words);
    }

    $cacheKey = $parliament . '|' . $filter;
    if (!isset($memo[$cacheKey])) {
        $memo[$cacheKey] = [];
    }

    $unknown = [];
    foreach ($words as $word) {
        if (!isset($memo[$cacheKey][$word])) {
            $unknown[$word] = true;
        }
    }
    $unknown = array_keys($unknown);

    if (!empty($unknown) && empty($failed[$cacheKey])) {
        try {
            $client = getApiOpenSearchClient();
        } catch (Throwable $e) {
            // Throwable, not Exception: a missing OpenSearch client class raises
            // an Error, and stemming must never be the thing that kills a run.
            error_log('statsIndexStemWords: could not build OpenSearch client (' . $e->getMessage()
                . '), falling back to unstemmed words');
            $client = null;
        }

        if ($client === null || (is_array($client) && isset($client['errors']))) {
            $failed[$cacheKey] = true;
        } else {
            foreach (array_chunk($unknown, STATS_STEM_BATCH_SIZE) as $chunk) {
                $stems = statsIndexAnalyzeBatch($client, $chunk, $filter);
                if ($stems === null) {
                    $failed[$cacheKey] = true;
                    break;
                }
                $memo[$cacheKey] += $stems;
            }
        }
    }

    $result = [];
    foreach ($words as $word) {
        $result[$word] = $memo[$cacheKey][$word] ?? $word;
    }

    return $result;
}

/**
 * One _analyze round-trip for a batch of words.
 *
 * @return array|null word => stem, or null when the call failed.
 */
function statsIndexAnalyzeBatch($client, array $words, $filter)
{
    try {
        $response = $client->indices()->analyze([
            'body' => [
                'tokenizer' => 'whitespace',
                'filter'    => ['lowercase', ['type' => 'stemmer', 'name' => $filter]],
                'text'      => implode(' ', $words),
            ],
        ]);
    } catch (Exception $e) {
        error_log('statsIndexStemWords: _analyze failed (' . $e->getMessage() . '), falling back to unstemmed words');
        return null;
    }

    if (!isset($response['tokens']) || !is_array($response['tokens'])) {
        error_log('statsIndexStemWords: _analyze returned no tokens, falling back to unstemmed words');
        return null;
    }

    $stems = [];
    foreach ($response['tokens'] as $token) {
        // Position indexes back into the input array; the whitespace tokenizer
        // emits exactly one token per input word, and no filter drops tokens.
        $position = $token['position'] ?? null;
        if ($position === null || !isset($words[$position])) {
            continue;
        }
        $stems[$words[$position]] = $token['token'];
    }

    // A partial answer is worse than none: it would silently group some forms
    // and not others.
    if (count($stems) !== count($words)) {
        error_log('statsIndexStemWords: _analyze returned ' . count($stems) . ' of ' . count($words)
            . ' tokens, falling back to unstemmed words');
        return null;
    }

    return $stems;
}
