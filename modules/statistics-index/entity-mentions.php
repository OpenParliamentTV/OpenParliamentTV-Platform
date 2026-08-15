<?php

/**
 * Entity mentions per segment class, for the statistics index.
 *
 * The companion to text-processing.php: that module answers "which words did the
 * credited speaker say", this one answers "which entities did they name".
 *
 * The split matters more here than it does for words. Measured over a 400-speech
 * random sample of the DE corpus, 71% of all `context=NER` annotations sit in
 * `type=comment` segments -- applause formulas and heckles, which the Bundestag
 * protocol records verbatim:
 *
 *     (Beifall bei der AfD)
 *     (Michael Grosse-Brömer [CDU/CSU]: Phänomenaler Auftritt war das!)
 *
 * NER annotates the names in those, so an unfiltered "most mentioned people in
 * this faction's speeches" ranks the people who HECKLE that faction. Per type the
 * comment share is 72.6% for person and 74.2% for organisation (terms are barely
 * affected at 1.9%, documents not at all) -- so without this module the two
 * biggest entity types are unusable.
 *
 * Recovering the class needs a lookup, because the importer does not record it:
 * importWriteTextContent() (modules/import/media-writer.php) loops over textBody
 * items -- and therefore knows the segment -- but writes only the sentence's
 * timeStart/timeEnd onto the annotation row. Those same two values identify the
 * sentence inside `textBody[].sentences[]`, which is what this module matches on.
 * 99.6% of annotations find their sentence that way.
 *
 * @see segments.php for the main/procedural split this reuses.
 */

require_once(__DIR__ . '/segments.php');

/** Decimals the timecode pair is rounded to before matching. */
const STATS_MENTION_TIME_PRECISION = 2;

/**
 * Map every sentence timecode pair in a media node to its segment class.
 *
 * Comments are tracked here even though they never produce a statistic: a pair
 * that a comment also claims has to be recognisable so it can be dropped.
 *
 * @param array $speechData A media node as returned by mediaGetNode().
 * @return array [ "<start>|<end>" => ['main' => true, 'comment' => true, ...] ]
 */
function statsIndexMentionSegmentMap($speechData)
{
    $map = [];

    foreach (($speechData['attributes']['textContents'] ?? []) as $textContent) {
        if (!isset($textContent['textBody']) || !is_array($textContent['textBody'])) {
            continue;
        }

        foreach ($textContent['textBody'] as $textBodyItem) {
            if (!is_array($textBodyItem)) {
                continue;
            }

            if (($textBodyItem['type'] ?? null) === 'comment') {
                $class = 'comment';
            } else {
                $class = statsIndexSegmentClass($textBodyItem);
                if ($class === null) {
                    // Another MP's Zwischenfrage, or an unrecognised segment.
                    // Not the credited speaker, and not a comment we need to
                    // defend against either -- it simply does not count.
                    continue;
                }
            }

            foreach (($textBodyItem['sentences'] ?? []) as $sentence) {
                $key = statsIndexMentionTimeKey(
                    $sentence['timeStart'] ?? null,
                    $sentence['timeEnd'] ?? null
                );
                if ($key !== null) {
                    $map[$key][$class] = true;
                }
            }
        }
    }

    return $map;
}

/**
 * Normalise a sentence's timecode pair into a lookup key.
 *
 * The two sides of the comparison are formatted differently -- transcripts carry
 * "19.920", annotations "19.92" -- so both go through the same rounding.
 *
 * @param mixed $start
 * @param mixed $end
 * @return string|null Null when either side is missing or non-numeric.
 */
function statsIndexMentionTimeKey($start, $end)
{
    if (!is_numeric($start) || !is_numeric($end)) {
        return null;
    }

    return round((float) $start, STATS_MENTION_TIME_PRECISION)
        . '|' . round((float) $end, STATS_MENTION_TIME_PRECISION);
}

/**
 * The entities named in a media node, split by segment class.
 *
 * Only `context=NER` annotations count. The speaker/faction contexts describe who
 * is speaking rather than who was named, and `proceedingsReference` is the agenda
 * item's own Drucksache, attached to every speech under it.
 *
 * A pair that a comment also claims is dropped rather than credited: comment
 * spans run from the start of the preceding sentence to the end of the following
 * one, so they collide with a real sentence pair in about 1.8% of cases, and
 * crediting a heckle to the speaker is the failure that matters here.
 *
 * @param array $speechData A media node as returned by mediaGetNode().
 * @return array [ <class> => [ "<type>|<id>" => ['id','type','count'] ] ], keyed
 *               by STATS_SEGMENT_MAIN / STATS_SEGMENT_PROCEDURAL. Empty when the
 *               node names nothing.
 */
function statsIndexGetEntityMentions($speechData)
{
    $annotations = $speechData['annotations']['data'] ?? null;
    if (!is_array($annotations) || empty($annotations)) {
        return [];
    }

    $segmentMap = statsIndexMentionSegmentMap($speechData);
    if (empty($segmentMap)) {
        return [];
    }

    $mentions = [];

    foreach ($annotations as $annotation) {
        if (($annotation['attributes']['context'] ?? null) !== 'NER') {
            continue;
        }

        $entityId = $annotation['id'] ?? null;
        $entityType = $annotation['type'] ?? null;
        if (empty($entityId) || empty($entityType)) {
            continue;
        }

        $key = statsIndexMentionTimeKey(
            $annotation['attributes']['timeStart'] ?? null,
            $annotation['attributes']['timeEnd'] ?? null
        );
        if ($key === null || !isset($segmentMap[$key])) {
            continue;
        }

        $classes = $segmentMap[$key];
        if (isset($classes['comment']) || count($classes) !== 1) {
            continue;
        }

        $class = array_key_first($classes);
        $entityKey = $entityType . '|' . $entityId;

        if (!isset($mentions[$class][$entityKey])) {
            $mentions[$class][$entityKey] = [
                'id'    => $entityId,
                'type'  => $entityType,
                'count' => 0,
            ];
        }

        $mentions[$class][$entityKey]['count']++;
    }

    return $mentions;
}
