<?php

/**
 * Read side of the statistics index (optv_statistics_<parliament>).
 *
 * Every query here filters `procedural: false` by default, so chair procedural
 * text ("Ich rufe auf den Tagesordnungspunkt …") never counts towards anyone's
 * vocabulary. It is indexed and reachable, just never the default.
 *
 * Two things are worth knowing about the document model these read:
 *
 *  - One document per (speech x word), so a term bucket's `doc_count` IS the
 *    number of speeches containing that word. The old model stored a running
 *    `speech_count` that had to be summed, and that inflated whenever a speech
 *    was re-indexed.
 *  - `stem` groups word forms, `word` keeps the surface form. Trend queries
 *    filter on one and report the other, which is what lets a chart say
 *    "flüchtlinge — includes flüchtling, flüchtlingen".
 *
 * Plain data in, plain data out: the JSON:API envelope belongs to
 * api/v1/modules/statistics.php.
 *
 * @see modules/statistics-index/aggregation.php for the write side.
 */

require_once(__DIR__ . '/../search/opensearch.php');
require_once(__DIR__ . '/../statistics-index/stemming.php');
require_once(__DIR__ . '/../statistics-index/text-processing.php'); // statsIndexGetStopwords()
require_once(__DIR__ . '/../../api/v1/utilities.php');

/**
 * Bucket ceiling for per-faction aggregations. Parliaments have single-digit
 * faction counts; this only needs to be comfortably above any of them.
 */
const STATS_MAX_FACTIONS = 50;

/** Bucket ceiling for surface forms reported per stem group. */
const STATS_MAX_WORD_FORMS = 5;

/**
 * Filter clauses shared by every statistics-index query.
 *
 * @param string $aggregationType 'speech_word' or 'speech_totals'.
 * @param array  $options         scope filters; see statsQueryScopeFilters().
 * @return array OpenSearch filter clauses.
 */
function statsQueryBaseFilters($aggregationType, array $options = [])
{
    $filters = [
        ['term' => ['aggregation_type' => $aggregationType]],
        ['term' => ['procedural' => !empty($options['procedural'])]],
    ];

    return array_merge($filters, statsQueryScopeFilters($options));
}

/**
 * Scope filters shared by the statistics index and the speech index.
 *
 * Parameter names deliberately mirror the media-search ones, so a caller can
 * pass the same scope to either lane.
 *
 * @param array $options sessionID, agendaItemID, electoralPeriodID, factionID,
 *                       personID, dateFrom, dateTo, factions.
 * @return array OpenSearch filter clauses.
 */
function statsQueryScopeFilters(array $options)
{
    $filters = [];

    if (!empty($options['sessionID'])) {
        $filters[] = ['term' => ['session_id' => $options['sessionID']]];
    }
    if (!empty($options['agendaItemID'])) {
        // Stored bare ("3305") because that is what the media node carries, while
        // callers hold the page form ("DE-3305"). Same normalisation the speech
        // lane applies.
        $parts = explode('-', (string) $options['agendaItemID']);
        $filters[] = ['term' => ['agenda_item_id' => array_pop($parts)]];
    }
    if (!empty($options['electoralPeriodID'])) {
        $filters[] = ['term' => ['electoral_period_id' => $options['electoralPeriodID']]];
    }
    if (!empty($options['personID'])) {
        $filters[] = ['term' => ['speaker_id' => $options['personID']]];
    }
    if (!empty($options['factionID'])) {
        $filters[] = ['term' => ['faction_id' => $options['factionID']]];
    }
    if (!empty($options['factions']) && is_array($options['factions'])) {
        $filters[] = ['terms' => ['faction_id' => array_values($options['factions'])]];
    }

    $range = [];
    if (!empty($options['dateFrom'])) {
        $range['gte'] = is_numeric($options['dateFrom'])
            ? (int) $options['dateFrom'] : strtotime($options['dateFrom']);
    }
    if (!empty($options['dateTo'])) {
        $range['lte'] = is_numeric($options['dateTo'])
            ? (int) $options['dateTo'] : strtotime($options['dateTo']);
    }
    if (!empty($range)) {
        $filters[] = ['range' => ['date' => $range]];
    }

    // Anything the caller meant as a scope but this lane cannot express has to be
    // refused rather than dropped. Silently ignoring `agendaItemID` here is what
    // made an agenda item's vocabulary figure report the whole corpus: the filter
    // vanished and the query ran unscoped, so the number looked plausible and was
    // wrong by four orders of magnitude.
    $unsupported = array_diff(
        array_keys(array_filter($options, fn($v) => $v !== null && $v !== '' && $v !== [])),
        STATS_QUERY_KNOWN_OPTIONS
    );
    if (!empty($unsupported)) {
        throw new InvalidArgumentException(
            'Unsupported statistics scope: ' . implode(', ', $unsupported));
    }

    return $filters;
}

/** Option keys statsQueryScopeFilters() knows how to honour or ignore safely. */
const STATS_QUERY_KNOWN_OPTIONS = [
    'sessionID', 'agendaItemID', 'electoralPeriodID', 'personID', 'factionID',
    'factions', 'dateFrom', 'dateTo',
    // Not scope filters, but legitimately present in the same options array.
    'parliament', 'procedural', 'interval', 'size', 'byFaction', 'match',
    'separateByFaction',
];

/**
 * The same scope, expressed against the SPEECH index.
 *
 * The two indices name these things differently — the statistics index carries
 * flat `session_id` / `faction_id` keywords, while the speech index keeps them
 * under `relationships.*` and puts entity references in a nested
 * `annotations.data` — so the same scope has to be built twice. Callers pass one
 * set of parameters and get the right query for whichever lane they are in.
 *
 * @param array $options Same keys as statsQueryScopeFilters().
 * @return array OpenSearch filter clauses.
 */
function statsQuerySpeechScopeFilters(array $options)
{
    $filters = [];

    if (!empty($options['sessionID'])) {
        $filters[] = ['term' => ['relationships.session.data.id' => $options['sessionID']]];
    }

    if (!empty($options['electoralPeriodID'])) {
        $filters[] = ['term' => ['relationships.electoralPeriod.data.id' => $options['electoralPeriodID']]];
    }

    if (!empty($options['agendaItemID'])) {
        // Agenda items are indexed under their bare numeric id ("9314") while
        // callers may hold a compound form; the search layer takes the last
        // dash-separated segment and so do we.
        $parts = explode('-', (string) $options['agendaItemID']);
        $filters[] = ['term' => ['relationships.agendaItem.data.id' => array_pop($parts)]];
    }

    if (!empty($options['personID'])) {
        $filters[] = statsQuerySpeechAnnotationFilter($options['personID'], 'person', 'main-speaker');
    }

    if (!empty($options['factionID'])) {
        $filters[] = statsQuerySpeechAnnotationFilter($options['factionID'], 'organisation', 'main-speaker-faction');
    }

    $range = [];
    if (!empty($options['dateFrom'])) {
        $range['gte'] = $options['dateFrom'];
    }
    if (!empty($options['dateTo'])) {
        $range['lte'] = $options['dateTo'];
    }
    if (!empty($range)) {
        $filters[] = ['range' => ['attributes.dateStart' => $range]];
    }

    return $filters;
}

/** One nested annotation clause on the speech index. */
function statsQuerySpeechAnnotationFilter($id, $type, $context)
{
    return ['nested' => [
        'path'  => 'annotations.data',
        'query' => ['bool' => ['must' => [
            ['term' => ['annotations.data.id' => $id]],
            ['term' => ['annotations.data.type' => $type]],
            ['match_phrase' => ['annotations.data.attributes.context' => $context]],
        ]]],
    ]];
}

/**
 * Run a search against the statistics index.
 *
 * @return array|null Aggregations, or null when the query failed.
 */
function statsQueryRun(array $body, $parliament = null)
{
    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        error_log('statsQuery: OpenSearch client unavailable');
        return null;
    }

    try {
        $response = $client->search([
            'index' => getStatisticsIndexName($parliament),
            'body'  => $body,
        ]);
    } catch (Exception $e) {
        error_log('statsQuery: ' . $e->getMessage());
        return null;
    }

    return $response['aggregations'] ?? [];
}

/**
 * Word trends over time.
 *
 * @param array $words   Words as the user typed them.
 * @param array $options parliament, startDate, endDate, factions,
 *                       separateByFaction, match ('stem'|'exact'), interval.
 * @return array|null ['trends' => [...], 'match' => string] or null on failure.
 */
function statsQueryWordTrends(array $words, array $options = [])
{
    $parliament = $options['parliament'] ?? null;
    $matchMode  = ($options['match'] ?? 'stem') === 'exact' ? 'exact' : 'stem';
    $interval   = $options['interval'] ?? '1M';
    $separate   = !empty($options['separateByFaction']);

    $words = array_values(array_filter(array_map(
        fn($w) => mb_strtolower(trim($w), 'UTF-8'),
        $words
    )));

    if (empty($words)) {
        return ['trends' => [], 'match' => $matchMode];
    }

    // In stem mode the query filters on the stem of what the user typed, so
    // "Flüchtlinge" also finds "Flüchtling". Keep the mapping so each result can
    // be reported back under the word the user actually asked for.
    if ($matchMode === 'stem') {
        $stemByWord = statsIndexStemWords($words, $parliament);
        $field = 'stem';
        $terms = array_values(array_unique(array_values($stemByWord)));
    } else {
        $stemByWord = array_combine($words, $words);
        $field = 'word';
        $terms = $words;
    }

    $timeSeries = [
        'date_histogram' => [
            'field'            => 'date_string',
            'calendar_interval' => $interval,
            'format'           => 'yyyy-MM',
        ],
        'aggs' => [
            'total_count' => ['sum' => ['field' => 'count']],
        ],
    ];

    $perTerm = [
        'time_series' => $timeSeries,
        // Surface forms behind a stem group, so the UI can label honestly.
        'forms'       => [
            'terms' => ['field' => 'word', 'size' => STATS_MAX_WORD_FORMS,
                        'order' => ['form_count' => 'desc']],
            'aggs'  => ['form_count' => ['sum' => ['field' => 'count']]],
        ],
        'first_use'   => ['min' => ['field' => 'date']],
        'total'       => ['sum' => ['field' => 'count']],
    ];

    if ($separate) {
        $perTerm = [
            'factions' => [
                'terms' => ['field' => 'faction_id', 'size' => STATS_MAX_FACTIONS],
                'aggs'  => ['time_series' => $timeSeries],
            ],
            'forms'     => $perTerm['forms'],
            'first_use' => $perTerm['first_use'],
            'total'     => $perTerm['total'],
        ];
    }

    $body = [
        'size'  => 0,
        'query' => ['bool' => ['filter' => array_merge(
            statsQueryBaseFilters('speech_word', $options),
            [['terms' => [$field => $terms]]]
        )]],
        'aggs'  => [
            'by_term' => [
                'terms' => ['field' => $field, 'size' => count($terms)],
                'aggs'  => $perTerm,
            ],
        ],
    ];

    $aggregations = statsQueryRun($body, $parliament);
    if ($aggregations === null) {
        return null;
    }

    $byTerm = [];
    foreach ($aggregations['by_term']['buckets'] ?? [] as $bucket) {
        $byTerm[$bucket['key']] = $bucket;
    }

    $trends = [];
    foreach ($words as $word) {
        $term   = $stemByWord[$word] ?? $word;
        $bucket = $byTerm[$term] ?? null;

        $entry = ['word' => $word];

        if ($bucket === null) {
            $entry += $separate ? ['factionBreakdown' => []] : ['timeline' => []];
            $entry['total'] = 0;
            $trends[] = $entry;
            continue;
        }

        if ($separate) {
            $breakdown = [];
            foreach ($bucket['factions']['buckets'] ?? [] as $factionBucket) {
                $breakdown[] = [
                    'id'       => $factionBucket['key'],
                    'timeline' => statsQueryTimeline($factionBucket['time_series']['buckets'] ?? []),
                ];
            }
            $entry['factionBreakdown'] = $breakdown;
        } else {
            $entry['timeline'] = statsQueryTimeline($bucket['time_series']['buckets'] ?? []);
        }

        $entry['total'] = (int) ($bucket['total']['value'] ?? 0);

        // Which surface forms this line actually covers.
        $forms = [];
        foreach ($bucket['forms']['buckets'] ?? [] as $formBucket) {
            $forms[] = ['word' => $formBucket['key'], 'count' => (int) $formBucket['form_count']['value']];
        }
        $entry['forms'] = $forms;

        $firstUse = $bucket['first_use']['value'] ?? null;
        if ($firstUse !== null) {
            // `date` is epoch seconds (a long, not a date field), so no /1000 here.
            $entry['firstUse'] = [
                'date'        => date('Y-m-d', (int) $firstUse),
                'surfaceForm' => $forms[0]['word'] ?? $word,
            ];
        }

        $trends[] = $entry;
    }

    return ['trends' => $trends, 'match' => $matchMode];
}

/**
 * Shape one date_histogram into timeline rows.
 *
 * `speechCount` is the bucket's doc_count: with one document per (speech x word)
 * that is exactly the number of speeches using the word in that month.
 */
function statsQueryTimeline(array $buckets)
{
    $timeline = [];

    foreach ($buckets as $bucket) {
        $timeline[] = [
            'date'        => $bucket['key_as_string'],
            'totalCount'  => (int) ($bucket['total_count']['value'] ?? 0),
            'speechCount' => (int) ($bucket['doc_count'] ?? 0),
        ];
    }

    return $timeline;
}

/**
 * Token totals per time bucket — the denominator for "per million words".
 *
 * Cached separately from the word query it normalizes, because the same
 * denominator serves every word asked about the same scope.
 *
 * @return array|null date => token count.
 */
function statsQueryTotalsHistogram(array $options = [])
{
    $body = [
        'size'  => 0,
        'query' => ['bool' => ['filter' => statsQueryBaseFilters('speech_totals', $options)]],
        'aggs'  => [
            'over_time' => [
                'date_histogram' => [
                    'field'             => 'date_string',
                    'calendar_interval' => $options['interval'] ?? '1M',
                    'format'            => 'yyyy-MM',
                ],
                'aggs' => ['tokens' => ['sum' => ['field' => 'token_count']]],
            ],
        ],
    ];

    $aggregations = statsQueryRun($body, $options['parliament'] ?? null);
    if ($aggregations === null) {
        return null;
    }

    $totals = [];
    foreach ($aggregations['over_time']['buckets'] ?? [] as $bucket) {
        $totals[$bucket['key_as_string']] = (int) ($bucket['tokens']['value'] ?? 0);
    }

    return $totals;
}

/**
 * Attach the token denominator to every timeline bucket of a trends result.
 *
 * Occurrences alone say as much about how busy a month was as about the word,
 * so relative mode needs "out of how many words". This returns the denominator
 * itself rather than a precomputed rate, for two reasons: a rate is a float, and
 * PHP serialises floats at whatever `serialize_precision` the host is set to
 * (100 on XAMPP, which turns 270.22 into 270.2200000000000272848…); and a client
 * holding both numbers can switch between absolute and relative display without
 * asking for the data again.
 *
 * perMillion = totalCount / tokenCount * 1e6
 */
function statsQueryAttachTotals(array &$trends, array $totals)
{
    foreach ($trends as &$entry) {
        if (isset($entry['timeline'])) {
            statsQueryAttachTimelineTotals($entry['timeline'], $totals);
        }
        foreach ($entry['factionBreakdown'] ?? [] as &$faction) {
            statsQueryAttachTimelineTotals($faction['timeline'], $totals);
        }
        unset($faction);
    }
    unset($entry);
}

/** @param array $timeline Modified in place. */
function statsQueryAttachTimelineTotals(array &$timeline, array $totals)
{
    foreach ($timeline as &$bucket) {
        $bucket['tokenCount'] = (int) ($totals[$bucket['date']] ?? 0);
    }
    unset($bucket);
}

/**
 * A speaker's most-used words.
 *
 * @return array|null ['totalWords','uniqueWords','topWords'] or null on failure.
 */
function statsQuerySpeakerVocabulary($speakerId, $limit = 50, $parliament = null)
{
    $body = [
        'size'  => 0,
        'query' => ['bool' => ['filter' => statsQueryBaseFilters('speech_word', [
            'personID' => $speakerId,
        ])]],
        'aggs'  => [
            'top_words' => [
                'terms' => ['field' => 'word', 'size' => (int) $limit,
                            'order' => ['frequency' => 'desc']],
                'aggs'  => [
                    'frequency' => ['sum' => ['field' => 'count']],
                    'first_used' => ['min' => ['field' => 'date']],
                    'last_used'  => ['max' => ['field' => 'date']],
                ],
            ],
            'total_words'  => ['sum' => ['field' => 'count']],
            'unique_words' => ['cardinality' => ['field' => 'word']],
        ],
    ];

    $aggregations = statsQueryRun($body, $parliament);
    if ($aggregations === null) {
        return null;
    }

    $topWords = [];
    foreach ($aggregations['top_words']['buckets'] ?? [] as $bucket) {
        $topWords[] = [
            'word'        => $bucket['key'],
            'frequency'   => (int) $bucket['frequency']['value'],
            'speechCount' => (int) $bucket['doc_count'],
            'firstUsed'   => isset($bucket['first_used']['value'])
                ? date('Y-m-d', (int) $bucket['first_used']['value']) : null,
            'lastUsed'    => isset($bucket['last_used']['value'])
                ? date('Y-m-d', (int) $bucket['last_used']['value']) : null,
        ];
    }

    return [
        'totalWords'  => (int) ($aggregations['total_words']['value'] ?? 0),
        'uniqueWords' => (int) ($aggregations['unique_words']['value'] ?? 0),
        'topWords'    => $topWords,
    ];
}

/**
 * Most-used words in a scope, overall and per faction.
 *
 * Corpus-wide when unscoped, which is why the general-statistics cache warms it
 * rather than letting a page request run it cold.
 *
 * @return array|null ['totalUniqueWords','topWords','byFaction'] or null.
 */
function statsQueryTopWords(array $options = [])
{
    $size        = $options['size'] ?? 20;
    $withFaction = $options['byFaction'] ?? true;

    $wordAgg = [
        'terms' => ['field' => 'word', 'size' => (int) $size, 'order' => ['frequency' => 'desc']],
        'aggs'  => ['frequency' => ['sum' => ['field' => 'count']]],
    ];

    $aggs = [
        'top_words'         => $wordAgg,
        'total_unique_words' => ['cardinality' => ['field' => 'word']],
    ];

    if ($withFaction) {
        $aggs['by_faction'] = [
            'terms' => ['field' => 'faction_id', 'size' => STATS_MAX_FACTIONS],
            'aggs'  => ['top_words' => $wordAgg],
        ];
    }

    $body = [
        'size'  => 0,
        'query' => ['bool' => ['filter' => statsQueryBaseFilters('speech_word', $options)]],
        'aggs'  => $aggs,
    ];

    $aggregations = statsQueryRun($body, $options['parliament'] ?? null);
    if ($aggregations === null) {
        return null;
    }

    $result = [
        'totalUniqueWords' => (int) ($aggregations['total_unique_words']['value'] ?? 0),
        'topWords'         => statsQueryWordBuckets($aggregations['top_words']['buckets'] ?? []),
    ];

    if ($withFaction) {
        $byFaction = [];
        foreach ($aggregations['by_faction']['buckets'] ?? [] as $bucket) {
            $byFaction[] = [
                'id'       => $bucket['key'],
                'topWords' => statsQueryWordBuckets($bucket['top_words']['buckets'] ?? []),
            ];
        }
        $result['byFaction'] = $byFaction;
    }

    return $result;
}

/** Shape word term buckets into rows. */
function statsQueryWordBuckets(array $buckets)
{
    $words = [];

    foreach ($buckets as $bucket) {
        $words[] = [
            'word'        => $bucket['key'],
            'frequency'   => (int) ($bucket['frequency']['value'] ?? 0),
            'speechCount' => (int) ($bucket['doc_count'] ?? 0),
        ];
    }

    return $words;
}

/**
 * How many documents the significance sampler looks at per shard.
 *
 * significant_text re-analyses text from _source at query time, so this is what
 * bounds the cost: an electoral-period-wide scope costs the same as this many
 * speeches, not as many as it contains.
 */
const STATS_SIGNIFICANT_SAMPLE = 500;

/**
 * Words that characterise a scope, against the rest of the corpus.
 *
 * Runs over `cleanText`, so applause formulas and agenda announcements cannot
 * be mistaken for a scope's distinctive vocabulary.
 *
 * The words come back stemmed, because the search index analyses text with
 * light_german — "Migrationswende" is indexed as "migrationswend" and, worse for
 * a reader, "Frühjahrsdürre" as "fruhjahrsdurr". They are mapped back to their
 * most frequent surface form through the statistics index, which stores both
 * `word` and `stem` under the same stemmer. One extra round trip for the whole
 * result set.
 *
 * @param array $options Scope filters plus size, parliament.
 * @return array|null Rows of ['word','score','speechCount'] or null on failure.
 */
function statsQuerySignificantWords(array $options = [])
{
    $parliament = $options['parliament'] ?? null;
    $size = min(max((int) ($options['size'] ?? 20), 1), 50);

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return null;
    }

    $filters = statsQuerySpeechScopeFilters($options);
    if (empty($filters)) {
        return null;
    }

    // Keep stopwords out of the SCORING, not just out of the results. Over a
    // large foreground the tiny differences in how often one group says "und" or
    // "wir" are enough to register as significant, and they crowd out everything
    // that matters: asked for twenty words about one faction, significant_text
    // returned forty buckets of which thirty-seven were function words, leaving
    // three real ones after filtering.
    $stopwords = statsQueryStopwordStems($parliament);

    try {
        $response = $client->search([
            'index' => getSearchIndexPattern($parliament),
            'body'  => [
                'size'  => 0,
                'query' => ['bool' => ['filter' => $filters]],
                'aggs'  => ['sample' => [
                    'sampler' => ['shard_size' => STATS_SIGNIFICANT_SAMPLE],
                    'aggs'    => ['words' => ['significant_text' => [
                        'field'                => OPTV_INDEX_ONLY_FIELD,
                        // Over-fetch, because the stopword and numeric filters
                        // below discard some of what comes back.
                        'size'                 => $size * 2,
                        'filter_duplicate_text' => true,
                        'exclude'              => array_values($stopwords),
                        // Compare the scope against everything OUTSIDE it. By
                        // default the background is the whole index, foreground
                        // included — so a scope covering a third of the corpus is
                        // measured largely against itself and almost nothing comes
                        // out as distinctive. An electoral period spanning four
                        // years returned exactly one word before this.
                        'background_filter'    => ['bool' => ['must_not' => $filters]],
                    ]]],
                ]],
            ],
        ]);
    } catch (Exception $e) {
        error_log('statsQuerySignificantWords: ' . $e->getMessage());
        return null;
    }

    $buckets = $response['aggregations']['sample']['words']['buckets'] ?? [];
    if (empty($buckets)) {
        return [];
    }

    // Backstop only, now that the scoring itself excludes stopwords: bare
    // numbers and anything shorter than a word still get dropped here.
    $config    = $GLOBALS['config'] ?? [];
    $minLength = $config['statistics']['minWordLength'] ?? 3;

    $candidates = [];
    foreach ($buckets as $bucket) {
        $key = (string) $bucket['key'];
        if (is_numeric($key) || mb_strlen($key, 'UTF-8') < $minLength) {
            continue;
        }
        if (in_array($key, $stopwords, true)) {
            continue;
        }
        $candidates[$key] = [
            'stem'        => $key,
            'score'       => round($bucket['score'], 4),
            'speechCount' => (int) $bucket['doc_count'],
        ];
    }

    if (empty($candidates)) {
        return [];
    }

    $surfaceForms = statsQuerySurfaceForms(array_keys($candidates), $parliament);

    $words = [];
    foreach ($candidates as $stem => $row) {
        $surface = $surfaceForms[$stem] ?? $stem;

        // Check the SURFACE form against the stopword list as well. The buckets
        // above are stems, and the list is written in surface forms, so a stemmed
        // function word slips past the first check: "für" stems to "fur" and
        // "werden" to "werd", neither of which is on any list.
        if (in_array($surface, $stopwords, true)) {
            continue;
        }

        $words[] = [
            'word'        => $surface,
            'stem'        => $stem,
            'score'       => $row['score'],
            'speechCount' => $row['speechCount'],
        ];
        if (count($words) >= $size) {
            break;
        }
    }

    return $words;
}

/**
 * The stopword list, plus the stem of every entry.
 *
 * significant_text reports stems, because the search index analyses text with
 * light_german — and a stemmed function word matches nothing on a list written
 * in surface forms. "für" stems to "fur", "werden" to "werd"; neither appears in
 * any list, so both used to survive into the results.
 *
 * Resolving the stem back through the statistics index does not help here: the
 * words being caught are stopwords, so they were never indexed, and there is no
 * surface form to look up. Stemming the list is the only way round.
 *
 * @return array Surface forms and their stems, deduplicated.
 */
function statsQueryStopwordStems($parliament = null)
{
    static $cache = [];

    $key = (string) resolveParliamentKey($parliament);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $config = $GLOBALS['config'] ?? [];
    $stopwords = statsIndexGetStopwords($config, statsQueryParliamentLanguage($parliament));

    if (empty($stopwords)) {
        $cache[$key] = [];
        return $cache[$key];
    }

    $stems = statsIndexStemWords(array_values($stopwords), $parliament, $config);

    $cache[$key] = array_values(array_unique(array_merge($stopwords, array_values($stems))));

    return $cache[$key];
}
/**
 * The most frequently written form of each stem.
 *
 * @return array stem => surface form, for whichever stems were found.
 */
function statsQuerySurfaceForms(array $stems, $parliament = null)
{
    if (empty($stems)) {
        return [];
    }

    $body = [
        'size'  => 0,
        'query' => ['bool' => ['filter' => array_merge(
            statsQueryBaseFilters('speech_word', []),
            [['terms' => ['stem' => array_values($stems)]]]
        )]],
        'aggs'  => ['stems' => [
            'terms' => ['field' => 'stem', 'size' => count($stems)],
            'aggs'  => ['top_form' => [
                'terms' => ['field' => 'word', 'size' => 1, 'order' => ['n' => 'desc']],
                'aggs'  => ['n' => ['sum' => ['field' => 'count']]],
            ]],
        ]],
    ];

    $aggregations = statsQueryRun($body, $parliament);
    if ($aggregations === null) {
        return [];
    }

    $forms = [];
    foreach ($aggregations['stems']['buckets'] ?? [] as $bucket) {
        $top = $bucket['top_form']['buckets'][0]['key'] ?? null;
        if ($top !== null) {
            $forms[$bucket['key']] = $top;
        }
    }

    return $forms;
}

/** Configured transcript language for a parliament, for stopword lookup. */
function statsQueryParliamentLanguage($parliament = null)
{
    $config = $GLOBALS['config'] ?? [];
    $key = resolveParliamentKey($parliament);

    return $config['parliament'][$key]['statistics']['language'] ?? null;
}

/**
 * Annotation contexts that mean "this entity was talked about", per type.
 *
 * Everything NOT listed here is structural — it records who was speaking, not
 * who was mentioned. Without that distinction a person's own speeches would
 * count as mentions of them, which is the single easiest way to make a mention
 * timeline meaningless.
 *
 * Enumerated from the live index rather than from entityContextRoles(), which
 * is out of step with the data in three ways: organisations actually carry
 * `speaker-faction`, `vice-president-faction` and `president-faction` (all
 * structural, none listed there); `main-speaker-party` does not occur at all;
 * and terms never carry `proceedingsReference`, only documents do.
 *
 * Documents are the one type where two different kinds of reference exist and
 * are worth separating: `proceedingsReference` is the document being formally
 * on the agenda, `NER` is someone naming it mid-speech. The two are reported as
 * separate series, though in the German corpus they turn out to be disjoint
 * populations — 12,270 documents carry only the former and 34 only the latter,
 * with no overlap — so a document normally yields one series and an empty one.
 */
const STATS_MENTION_CONTEXTS = [
    'person'       => ['mentioned' => ['NER']],
    'organisation' => ['mentioned' => ['NER']],
    'term'         => ['mentioned' => ['NER']],
    'document'     => ['mentioned' => ['NER'], 'linked' => ['proceedingsReference']],
];

/**
 * How often an entity was mentioned over time.
 *
 * @param string $entityType person|organisation|document|term
 * @param string $entityID   Platform entity id.
 * @param array  $options    parliament, dateFrom, dateTo, interval.
 * @return array|null [series => [date => count]] or null on failure.
 */
function statsQueryEntityMentions($entityType, $entityID, array $options = [])
{
    $seriesContexts = STATS_MENTION_CONTEXTS[$entityType] ?? null;
    if ($seriesContexts === null) {
        return null;
    }

    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client['errors'])) {
        return null;
    }

    $histogram = [
        'date_histogram' => [
            'field'             => 'attributes.dateStart',
            'calendar_interval' => $options['interval'] ?? '1M',
            'format'            => 'yyyy-MM',
            'min_doc_count'     => 0,
        ],
    ];

    $aggs = [];
    foreach ($seriesContexts as $series => $contexts) {
        $aggs[$series] = [
            'filter' => ['bool' => ['should' => array_map(
                fn($context) => statsQuerySpeechAnnotationFilter($entityID, $entityType, $context),
                $contexts
            ), 'minimum_should_match' => 1]],
            'aggs'   => ['over_time' => $histogram],
        ];
    }

    $filters = statsQuerySpeechScopeFilters($options);
    $filters[] = ['nested' => [
        'path'  => 'annotations.data',
        'query' => ['bool' => ['must' => [
            ['term' => ['annotations.data.id' => $entityID]],
            ['term' => ['annotations.data.type' => $entityType]],
        ]]],
    ]];

    try {
        $response = $client->search([
            'index' => getSearchIndexPattern($options['parliament'] ?? null),
            'body'  => [
                'size'  => 0,
                'query' => ['bool' => ['filter' => $filters]],
                'aggs'  => $aggs,
            ],
        ]);
    } catch (Exception $e) {
        error_log('statsQueryEntityMentions: ' . $e->getMessage());
        return null;
    }

    $result = [];
    foreach (array_keys($seriesContexts) as $series) {
        $timeline = [];
        foreach ($response['aggregations'][$series]['over_time']['buckets'] ?? [] as $bucket) {
            $timeline[] = [
                'date'        => $bucket['key_as_string'],
                'speechCount' => (int) $bucket['doc_count'],
            ];
        }
        $result[$series] = $timeline;
    }

    return $result;
}

/**
 * Word suggestions for the search box.
 *
 * Runs over the `word.text` edge-ngram subfield, which only started existing
 * with the current mapping: the previous live index had `word` as a plain
 * keyword, so prefix suggestions were a wildcard scan.
 *
 * @return array Suggestion rows; empty on failure.
 */
function statsQueryAutocomplete($query, $maxResults = 10, $factionFilter = null, $parliament = null)
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    if ($query === '') {
        return [];
    }

    $options = [];
    if ($factionFilter) {
        $options['factionID'] = $factionFilter;
    }

    $body = [
        'size'  => 0,
        'query' => ['bool' => [
            'filter' => statsQueryBaseFilters('speech_word', $options),
            // The `keyword` analyzer on the query side is what makes this a
            // prefix search. word.text is indexed as edge-ngrams, and analyzing
            // the QUERY the same way would break "klim" into k/kl/kli/klim —
            // whose "k" then matches every word starting with k. One token in,
            // matched against the indexed ngrams. The input is already
            // lowercased above, which is why `keyword` alone suffices.
            'must'   => [['match' => ['word.text' => [
                'query'    => $query,
                'analyzer' => 'keyword',
            ]]]],
        ]],
        'aggs' => [
            'words' => [
                'terms' => ['field' => 'word', 'size' => (int) $maxResults,
                            'order' => ['frequency' => 'desc']],
                'aggs'  => [
                    'frequency' => ['sum' => ['field' => 'count']],
                    'last_used' => ['max' => ['field' => 'date']],
                ],
            ],
        ],
    ];

    $aggregations = statsQueryRun($body, $parliament);
    if ($aggregations === null) {
        return [];
    }

    $suggestions = [];
    foreach ($aggregations['words']['buckets'] ?? [] as $bucket) {
        $suggestions[] = [
            'text'          => $bucket['key'],
            'frequency'     => (int) $bucket['frequency']['value'],
            'speech_count'  => (int) $bucket['doc_count'],
            'last_used'     => isset($bucket['last_used']['value']) ? (int) $bucket['last_used']['value'] : null,
            'last_used_string' => isset($bucket['last_used']['value'])
                ? date('Y-m-d', (int) $bucket['last_used']['value']) : null,
            'type'          => strpos($bucket['key'], $query) === 0 ? 'prefix' : 'substring',
        ];
    }

    return $suggestions;
}
