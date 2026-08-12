<?php

/**
 * Metadata extraction from a media node for the statistics index.
 *
 * Everything here reads the flat `annotations.data` array rather than the
 * `relationships.*` copies: the relationships are for display and carry a null
 * context in the indexed document, while annotations carry the real one.
 */

/**
 * The credited main speaker's Wikidata id.
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractSpeakerId($speechData)
{
    foreach (($speechData['annotations']['data'] ?? []) as $annotation) {
        if (($annotation['type'] ?? null) === 'person'
            && ($annotation['attributes']['context'] ?? null) === 'main-speaker') {
            return $annotation['id'] ?? null;
        }
    }

    return null;
}

/**
 * The credited main speaker's faction id.
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractFactionId($speechData)
{
    foreach (($speechData['annotations']['data'] ?? []) as $annotation) {
        if (($annotation['type'] ?? null) === 'organisation'
            && ($annotation['attributes']['context'] ?? null) === 'main-speaker-faction') {
            return $annotation['id'] ?? null;
        }
    }

    return null;
}

/**
 * Platform session id, e.g. "DE-0210010".
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractSessionId($speechData)
{
    return $speechData['relationships']['session']['data']['id'] ?? null;
}

/**
 * Platform agenda item id, e.g. "DE-3305".
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractAgendaItemId($speechData)
{
    return $speechData['relationships']['agendaItem']['data']['id'] ?? null;
}
/**
 * Platform electoral period id, e.g. "DE-021".
 *
 * Stored on every word document so per-EP scoping is a plain filter rather than
 * a date-range approximation.
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractElectoralPeriodId($speechData)
{
    return $speechData['relationships']['electoralPeriod']['data']['id'] ?? null;
}

/**
 * Speech start as a Unix timestamp.
 *
 * @param array $speechData A media node.
 * @return int|null
 */
function statsIndexExtractDate($speechData)
{
    if (isset($speechData['attributes']['dateStartTimestamp'])
        && is_numeric($speechData['attributes']['dateStartTimestamp'])) {
        return (int) $speechData['attributes']['dateStartTimestamp'];
    }

    $candidates = [
        $speechData['attributes']['dateStart'] ?? null,
        $speechData['attributes']['date'] ?? null,
        $speechData['attributes']['dateCreated'] ?? null,
        $speechData['relationships']['session']['data']['attributes']['date'] ?? null,
    ];

    foreach ($candidates as $value) {
        if (empty($value)) {
            continue;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    return null;
}

/**
 * Speech start as YYYY-MM-DD.
 *
 * @param array $speechData A media node.
 * @return string|null
 */
function statsIndexExtractDateString($speechData)
{
    $timestamp = statsIndexExtractDate($speechData);

    return $timestamp === null ? null : date('Y-m-d', $timestamp);
}
