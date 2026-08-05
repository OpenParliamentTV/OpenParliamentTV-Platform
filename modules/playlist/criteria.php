<?php
/**
 * Live-playlist criteria — the saved search a live playlist resolves at play time.
 *
 * Criteria use the search API's own parameter names, exactly as alerts do, so the
 * round trip criteria → search URL → same result set holds for both. This file is
 * a thin wrapper around the alert helpers in modules/notifications/notifications.php,
 * **not** a fork of them: the entity and scalar keys they model are normalized by
 * their code, and only the keys alerts do not model are added on top.
 *
 * Those extra keys are the ones a playlist needs and an alert does not:
 *
 *  - `sort`, because a playlist's playback order *is* the saved search's order,
 *    whereas an alert only ever asks "did anything new match?".
 *  - the media-only filters a search page can have active (`dateFrom`, `dateTo`,
 *    `sessionID`, `agendaItemID`, `aligned`), which an alert would ignore.
 *
 * Pure functions only: no database, no session.
 */

require_once(__DIR__ . "/../notifications/notifications.php");

/**
 * Sort values the search backend understands (determineSorting() in
 * modules/search/opensearch.php). Anything else is dropped rather than stored,
 * so a playlist can never resolve into an order the backend will not reproduce.
 */
function playlistCriteriaSortValues() {
    return [
        "relevance",
        "date-asc", "date-desc",
        "topic-asc", "topic-desc",
        "duration-asc", "duration-desc",
        "changed-asc", "changed-desc",
    ];
}

/**
 * Scalar criteria keys a playlist stores beyond the alert-supported set.
 *
 * @return array
 */
function playlistCriteriaExtraKeys() {
    return ["sort", "dateFrom", "dateTo", "sessionID", "agendaItemID", "aligned"];
}

/**
 * Normalize raw criteria for a live playlist.
 *
 * The alert-compatible keys go through normalizeAlertCriteria() untouched; the
 * playlist-only keys are appended as trimmed scalars. Returns an empty array when
 * nothing usable remains, which callers treat as "not a valid live playlist".
 *
 * @param mixed $raw Criteria array (or JSON string)
 * @return array Canonical criteria
 */
function playlistNormalizeCriteria($raw) {
    if (!is_array($raw)) {
        $raw = json_decode((string)$raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }

    $criteria = normalizeAlertCriteria($raw);

    foreach (playlistCriteriaExtraKeys() as $key) {
        if (!isset($raw[$key]) || !is_scalar($raw[$key])) {
            continue;
        }
        $value = trim((string)$raw[$key]);
        if ($value === "") {
            continue;
        }
        if ($key === "sort" && !in_array($value, playlistCriteriaSortValues(), true)) {
            continue;
        }
        $criteria[$key] = $value;
    }

    ksort($criteria);

    return $criteria;
}

/**
 * Storage form: canonical criteria plus the cleaned `_labels` snapshot, so the
 * playlist page can render entity names without live lookups — the same sidecar
 * alerts persist.
 *
 * @param mixed $raw
 * @return array
 */
function playlistCriteriaForStorage($raw) {
    if (!is_array($raw)) {
        $raw = json_decode((string)$raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }

    $criteria = playlistNormalizeCriteria($raw);
    if (empty($criteria)) {
        return [];
    }

    $labels = alertCriteriaCleanLabels($raw, $criteria);
    if (!empty($labels)) {
        $criteria["_labels"] = $labels;
    }

    return $criteria;
}

/**
 * Storage JSON for PlaylistCriteria.
 *
 * @param mixed $raw
 * @return string|null
 */
function playlistCriteriaJson($raw) {
    $criteria = playlistCriteriaForStorage($raw);

    return empty($criteria)
        ? null
        : json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Criteria as search-API request parameters.
 *
 * Drops the presentation-only `_labels` sidecar; everything else already uses the
 * search parameter names, which is what makes the round trip work.
 *
 * @param array $criteria
 * @return array
 */
function playlistCriteriaSearchParams($criteria) {
    if (!is_array($criteria)) {
        return [];
    }
    unset($criteria["_labels"]);

    return $criteria;
}

/**
 * The subset of criteria an alert can represent.
 *
 * Alerts answer "did anything new match?", so the ordering and the media-only
 * filters are meaningless to them and are dropped. What remains is exactly what
 * normalizeAlertCriteria() models, which keeps the created alert equal to one the
 * user would have made from the same search.
 *
 * @param array $criteria
 * @return array Alert criteria (may be empty)
 */
function playlistCriteriaForAlert($criteria) {
    if (!is_array($criteria)) {
        return [];
    }

    $alert = normalizeAlertCriteria($criteria);

    // Carry the label snapshot across so the alert list renders names, not ids.
    if (!empty($criteria["_labels"]) && is_array($criteria["_labels"]) && !empty($alert)) {
        $labels = alertCriteriaCleanLabels(["_labels" => $criteria["_labels"]], $alert);
        if (!empty($labels)) {
            $alert["_labels"] = $labels;
        }
    }

    return $alert;
}

/**
 * A stable fingerprint of a criteria set, for cache keys.
 *
 * @param array $criteria
 * @return string
 */
function playlistCriteriaHash($criteria) {
    return sha1(json_encode(playlistNormalizeCriteria(playlistCriteriaSearchParams($criteria)),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
