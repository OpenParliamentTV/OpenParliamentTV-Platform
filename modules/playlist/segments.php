<?php
/**
 * Playlist segments — the timecode ranges a playlist item plays.
 *
 * A segment list is an ordered array of {start,end} in seconds, or null for
 * "play the whole speech". The same shape reaches storage from three different
 * places — a search hit's finds, a transcript selection, a saved result set —
 * so validation and encoding live here rather than at each call site.
 *
 * Pure functions only: no database, no config, no session.
 */

/**
 * Validate and tidy a segment list.
 *
 * Accepts what the UI sends — a JSON string or an array of {start,end} — and
 * returns an ordered, de-duplicated list of positive ranges, or null for "play
 * the whole speech". Anything unparseable degrades to null rather than throwing:
 * a malformed segment should cost the timestamps, not the item.
 *
 * @param mixed $raw JSON string or array
 * @return array|null Ordered [{start,end},…], or null
 */
function playlistNormalizeSegments($raw) {
    if ($raw === null || $raw === "" || $raw === "null") {
        return null;
    }

    $segments = is_array($raw) ? $raw : json_decode((string)$raw, true);
    if (!is_array($segments) || empty($segments)) {
        return null;
    }

    $clean = [];
    foreach ($segments as $segment) {
        if (!is_array($segment) || !isset($segment["start"])) {
            continue;
        }
        $start = (float)$segment["start"];
        $end = isset($segment["end"]) ? (float)$segment["end"] : 0.0;

        // A zero-length or inverted range would make the player seek nowhere.
        if ($start < 0 || $end <= $start) {
            continue;
        }
        $clean[] = ["start" => round($start, 3), "end" => round($end, 3)];
    }

    if (empty($clean)) {
        return null;
    }

    usort($clean, function ($a, $b) { return $a["start"] <=> $b["start"]; });

    // Identical ranges arrive routinely: search finds are deduplicated on the
    // whole tuple, so the same span can appear under two different snippets.
    $unique = [];
    foreach ($clean as $segment) {
        $last = end($unique);
        if ($last && $last["start"] === $segment["start"] && $last["end"] === $segment["end"]) {
            continue;
        }
        $unique[] = $segment;
    }

    return $unique;
}

/**
 * Encode segments for storage.
 *
 * Written with sprintf rather than json_encode because instances run with
 * serialize_precision=100, which spells 53.6 out as its full 50-digit binary
 * expansion — bloating every stored row and every response built from it.
 * Timecodes are milliseconds; three decimals is the whole story.
 *
 * @param array|null $segments Output of playlistNormalizeSegments()
 * @return string|null JSON array, or null for "whole speech"
 */
function playlistEncodeSegments($segments) {
    if ($segments === null || empty($segments)) {
        return null;
    }

    $parts = [];
    foreach ($segments as $segment) {
        $parts[] = sprintf('{"start":%.3F,"end":%.3F}', $segment["start"], $segment["end"]);
    }

    return '[' . implode(',', $parts) . ']';
}

/**
 * Decode a stored PlaylistItemSegments value.
 *
 * @param string|null $stored
 * @return array|null
 */
function playlistDecodeSegments($stored) {
    if ($stored === null || $stored === "") {
        return null;
    }
    $decoded = json_decode($stored, true);

    return is_array($decoded) && !empty($decoded) ? $decoded : null;
}
