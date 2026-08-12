<?php

/**
 * Segment classification for the statistics index.
 *
 * A media node's transcript (`attributes.textContents[].textBody[]`) interleaves
 * several kinds of text, and only one of them is the credited speaker talking:
 *
 *   type=speech,  speakerstatus=main-speaker        the speech itself
 *   type=speech,  speakerstatus=president|          the session chair -- agenda
 *                 vice-president|interim-president  announcements, transitions,
 *                                                   admonishments
 *   type=speech,  speakerstatus=speaker             a different MP's Zwischenfrage
 *   type=comment  (no speakerstatus)                "(Beifall bei der SPD)",
 *                                                   "(Zuruf des Abg. X: ...)"
 *
 * Measured over a 400-speech sample, only 72.4% of the words in a media node are
 * main-speaker speech; 12.4% is chair, 11.2% comments, 3.9% other speakers. All
 * of it used to be credited to the main speaker and their faction, which is what
 * made heckles count as the heckled person's vocabulary.
 *
 * This module is the single source of truth for that split. It is consumed by
 * the statistics writer (aggregation.php) and by the search-index writer, which
 * uses it to populate the `cleanText` field that significant_text runs on.
 *
 * Chair text is kept but classified separately, so it can be excluded from every
 * default query while remaining available for a future analysis of procedural
 * language. Comments and other speakers are dropped outright: they are not the
 * credited speaker's words and there is no reliable id to credit them to.
 */

/** The credited speaker actually speaking. */
const STATS_SEGMENT_MAIN = 'main';

/** Session-chair text inside a media item (agenda calls, transitions, results). */
const STATS_SEGMENT_PROCEDURAL = 'procedural';

/** speakerstatus values that mark a segment as session-chair text. */
const STATS_CHAIR_STATUS = ['president', 'vice-president', 'interim-president'];

/**
 * Classify one textBody item.
 *
 * @param array $textBodyItem One entry of textContents[].textBody[].
 * @return string|null STATS_SEGMENT_MAIN, STATS_SEGMENT_PROCEDURAL, or null when
 *                     the segment does not count towards statistics at all.
 */
function statsIndexSegmentClass($textBodyItem)
{
    if (!is_array($textBodyItem) || ($textBodyItem['type'] ?? null) !== 'speech') {
        // Comments (applause, heckles) and anything unrecognised.
        return null;
    }

    $status = $textBodyItem['speakerstatus'] ?? null;

    if ($status === 'main-speaker') {
        return STATS_SEGMENT_MAIN;
    }

    if (in_array($status, STATS_CHAIR_STATUS, true)) {
        return STATS_SEGMENT_PROCEDURAL;
    }

    // speakerstatus 'speaker' -- another MP's intervention inside this item.
    return null;
}

/**
 * Split a media node into classified text segments.
 *
 * Note that roughly one media item in five is chair-attributed: the chair is the
 * credited main speaker and *every* one of its segments is procedural, so it
 * yields no STATS_SEGMENT_MAIN text at all. That is intentional -- those items
 * are procedural boilerplate and contribute nothing to anyone's vocabulary.
 *
 * @param array $speechData A media node as returned by mediaGetNode().
 * @return array List of ['class' => string, 'text' => string], one per textBody
 *               item that counts, in document order. Empty when there is none.
 */
function statsIndexGetSegments($speechData)
{
    $segments = [];

    $textContents = $speechData['attributes']['textContents'] ?? null;
    if (!is_array($textContents) || empty($textContents)) {
        return $segments;
    }

    if (isset($speechData['attributes']['textContentsCount'])
        && $speechData['attributes']['textContentsCount'] == 0) {
        return $segments;
    }

    foreach ($textContents as $textContent) {
        if (!isset($textContent['textBody']) || !is_array($textContent['textBody'])) {
            continue;
        }

        foreach ($textContent['textBody'] as $textBodyItem) {
            $class = statsIndexSegmentClass($textBodyItem);
            if ($class === null) {
                continue;
            }

            if (!isset($textBodyItem['sentences']) || !is_array($textBodyItem['sentences'])) {
                continue;
            }

            $parts = [];
            foreach ($textBodyItem['sentences'] as $sentence) {
                if (isset($sentence['text']) && $sentence['text'] !== '') {
                    $parts[] = $sentence['text'];
                }
            }

            if (empty($parts)) {
                continue;
            }

            $segments[] = ['class' => $class, 'text' => implode(' ', $parts)];
        }
    }

    return $segments;
}

/**
 * Concatenated plain text of one segment class.
 *
 * This is what populates the search index's `cleanText` field, so that
 * significant_text ranks distinctive words over real speech rather than over
 * applause formulas and agenda announcements.
 *
 * @param array  $speechData A media node as returned by mediaGetNode().
 * @param string $class      Which class to return; defaults to the speech itself.
 * @return string Empty string when the node has no text of that class.
 */
function statsIndexGetCleanText($speechData, $class = STATS_SEGMENT_MAIN)
{
    $parts = [];

    foreach (statsIndexGetSegments($speechData) as $segment) {
        if ($segment['class'] === $class) {
            $parts[] = $segment['text'];
        }
    }

    return implode(' ', $parts);
}
