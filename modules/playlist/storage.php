<?php
/**
 * Playlist storage — rows, queries and queue resolution.
 *
 * A *playlist* is a user-curated queue for the media player. Two kinds share one
 * table: `custom` playlists own ordered `playlist_item` rows, `live` playlists
 * own none and carry search criteria resolved at play time.
 *
 * Everything here works on plain arrays and returns plain data — the JSON:API
 * envelope lives in api/v1/modules/playlist.php. Nothing in this file depends on
 * api/v1/, so pages and CLI can use it directly.
 *
 * Timecode handling lives next door in segments.php.
 */

require_once(__DIR__ . "/segments.php");
require_once(__DIR__ . "/criteria.php");
require_once(__DIR__ . "/../database/connections.php");
require_once(__DIR__ . "/../search/opensearch.php");   // searchMediaByIds(), searchSpeeches()
require_once(__DIR__ . "/../entities/media.php");      // mediaQueueNode()
require_once(__DIR__ . "/../utilities/paths.php");     // optvVarPath()

/** How long a resolved live playlist stays cached, in seconds. */
define("PLAYLIST_LIVE_CACHE_TTL", 300);

/** Playlists a single user may own. */
define("PLAYLIST_MAX_PER_USER", 50);

/** Items a playlist may hold — also the ceiling on a resolved live playlist. */
define("PLAYLIST_MAX_ITEMS", 500);

/**
 * Table name for a playlist entity, tolerating a config that predates the
 * feature (instance configs are deployed separately from the code).
 *
 * @param string $key "Playlist" | "PlaylistItem"
 * @return string
 */
function playlistTable($key) {
    global $config;

    $defaults = ["Playlist" => "playlist", "PlaylistItem" => "playlist_item"];

    return $config["platform"]["sql"]["tbl"][$key] ?? $defaults[$key];
}

/**
 * Whether the feature is switched on for this instance.
 *
 * @return bool
 */
function playlistFeatureEnabled() {
    global $config;

    return !empty($config["allow"]["playlists"]);
}

/**
 * A fresh share token: 128 bits, hex-encoded to fit char(32).
 *
 * Tokens are minted at creation rather than on first share, so a playlist's URL
 * never changes and sharing is a pure flag flip.
 *
 * @return string
 */
function playlistGenerateToken() {
    return bin2hex(random_bytes(16));
}

/**
 * Map a `playlist` row to its API object.
 *
 * `owner` is the caller's relationship to the row, not a property of it, so it
 * is passed in rather than derived here.
 *
 * @param array $row       Row from the playlist table
 * @param bool  $isOwner   Whether the current session owns it
 * @param int|null $itemCount Number of items, when the caller has counted them
 * @return array
 */
function playlistRowToObject($row, $isOwner = false, $itemCount = null) {
    global $config;

    $criteria = null;
    if (!empty($row["PlaylistCriteria"])) {
        $decoded = json_decode($row["PlaylistCriteria"], true);
        $criteria = is_array($decoded) ? $decoded : null;
    }

    $object = [
        "type" => "playlist",
        "id" => (int)$row["PlaylistID"],
        "attributes" => [
            "token" => $row["PlaylistToken"],
            "playlistType" => $row["PlaylistType"],
            "title" => $row["PlaylistTitle"],
            "description" => $row["PlaylistDescription"],
            "shared" => (bool)$row["PlaylistShared"],
            "owner" => (bool)$isOwner,
            "created" => $row["PlaylistCreated"],
            "lastChanged" => $row["PlaylistLastChanged"],
        ],
        "links" => [
            "self" => $config["dir"]["root"] . "/playlist/" . $row["PlaylistToken"],
        ],
    ];

    if ($itemCount !== null) {
        $object["attributes"]["itemCount"] = (int)$itemCount;
    }

    // Criteria are the owner's saved query; they are not part of what a guest
    // gets when resolving a shared live playlist.
    if ($isOwner && $criteria !== null) {
        $object["attributes"]["criteria"] = $criteria;
    }

    return $object;
}

/**
 * Fetch a playlist row by its share token.
 *
 * @param string $token
 * @param object $db Platform database connection
 * @return array|null
 */
function playlistGetByToken($token, $db) {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }

    return $db->getRow("SELECT * FROM ?n WHERE PlaylistToken = ?s", playlistTable("Playlist"), $token) ?: null;
}

/**
 * The ordered item rows of a playlist.
 *
 * @param int $playlistId
 * @param object $db Platform database connection
 * @return array
 */
function playlistGetItemRows($playlistId, $db) {
    return $db->getAll(
        "SELECT * FROM ?n WHERE PlaylistItemPlaylistID = ?i ORDER BY PlaylistItemPosition ASC, PlaylistItemID ASC",
        playlistTable("PlaylistItem"),
        $playlistId
    ) ?: [];
}

/**
 * Turn stored item rows into the player's queue items.
 *
 * The display metadata comes from the search index in one batch per parliament
 * (searchMediaByIds), through the same projection the queue panel uses for a
 * search — so a stored playlist and a search render identically.
 *
 * Items whose speech no longer resolves are dropped: the index filters out
 * non-public media, so this is also what keeps a speech that was withdrawn after
 * being added from leaking through a shared playlist.
 *
 * @param array $rows Rows from playlistGetItemRows()
 * @return array Queue items, in stored order
 */
function playlistResolveItems($rows) {
    if (empty($rows)) {
        return [];
    }

    // Group by parliament so each index is queried once.
    $idsByParliament = [];
    foreach ($rows as $row) {
        $parliament = $row["PlaylistItemParliament"] !== "" ? $row["PlaylistItemParliament"] : null;
        $idsByParliament[(string)$parliament][] = $row["PlaylistItemMediaID"];
    }

    $found = [];
    foreach ($idsByParliament as $parliament => $ids) {
        $hits = searchMediaByIds($ids, $parliament !== "" ? $parliament : null);
        if (!is_array($hits)) {
            // Search unavailable — better to serve nothing than a queue that
            // silently lost every item.
            return null;
        }
        $found += $hits;
    }

    $items = [];
    foreach ($rows as $row) {
        $mediaId = $row["PlaylistItemMediaID"];
        if (!isset($found[$mediaId])) {
            continue;
        }

        $node = mediaQueueNode($found[$mediaId]);
        $node["segments"] = playlistDecodeSegments($row["PlaylistItemSegments"]);
        $node["attributes"]["findCount"] = $node["segments"] === null ? 0 : count($node["segments"]);
        $node["playlistItemID"] = (int)$row["PlaylistItemID"];

        $items[] = $node;
    }

    return $items;
}

/**
 * Resolve a live playlist's stored criteria into queue items.
 *
 * One search, not a paging loop: `getAllResults` returns the whole ordered set,
 * and highlighting stays on throughout searchSpeeches(), so the hits come back as
 * segments in the same call. `fields=queue` caps the response at
 * OPTV_QUEUE_MAX_RESULTS; PLAYLIST_MAX_ITEMS is applied here as well so a live and
 * a custom playlist can never differ in length limit.
 *
 * A criteria set with no `q` (pure entity filters) naturally yields no segments,
 * which is the player's "play the whole speech" case — the same as a custom item
 * stored without segments.
 *
 * @param array $criteria Stored criteria
 * @return array|null Queue items, or null when the search service is unavailable
 */
function playlistResolveCriteria($criteria) {
    $params = playlistCriteriaSearchParams($criteria);
    if (empty($params)) {
        return [];
    }

    // Same allow-list the HTTP search goes through, so stored criteria can never
    // reach the query builder with a parameter a request could not have sent
    // (it also strips `public`/`includeAll` for non-admins).
    $params = filterAllowedSearchParams($params, 'media');
    $params["fields"] = "queue";

    // searchSpeeches() + mediaQueueNode() directly rather than mediaSearch():
    // this file must not depend on api/v1/. It is the same pair mediaSearch()
    // uses for its fields=queue branch, so the items are identical.
    $search = searchSpeeches($params, true);

    if ((isset($search["serviceAvailable"]) && $search["serviceAvailable"] === false)
        || !isset($search["hits"]["hits"])) {
        return null;
    }

    $items = [];
    foreach ($search["hits"]["hits"] as $hit) {
        if (count($items) >= PLAYLIST_MAX_ITEMS) {
            break;
        }
        $node = $hit["_source"];
        $node["_finds"] = $hit["finds"] ?? [];
        $items[] = mediaQueueNode($node);
    }

    return $items;
}

/**
 * Cache path for one resolved live playlist.
 *
 * Keyed by criteria hash rather than by playlist id, so editing the criteria
 * invalidates the entry by simply not matching it any more.
 */
function playlistLiveCachePath($playlistId, $criteria) {
    return optvVarFilePath('cache/api', 'playlist-' . (int)$playlistId . '-' . playlistCriteriaHash($criteria) . '.json');
}

/**
 * Resolve a live playlist, through a short-lived cache.
 *
 * A live playlist is re-resolved on every player navigation, so an uncached
 * version would run a full search per skipped speech. Cache failures degrade to
 * resolving directly — a slow queue beats a broken one.
 *
 * @param int   $playlistId
 * @param array $criteria
 * @return array|null Queue items, or null when the search service is unavailable
 */
function playlistResolveLive($playlistId, $criteria) {
    $path = playlistLiveCachePath($playlistId, $criteria);

    if (is_readable($path) && (time() - filemtime($path)) < PLAYLIST_LIVE_CACHE_TTL) {
        $cached = json_decode((string)file_get_contents($path), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $items = playlistResolveCriteria($criteria);
    if ($items === null) {
        return null;
    }

    @file_put_contents($path, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

    return $items;
}

/**
 * Drop any cached resolve for a playlist (after its criteria change, or on delete).
 *
 * @param int $playlistId
 * @return void
 */
function playlistClearLiveCache($playlistId) {
    foreach (glob(optvVarPath('cache/api') . '/playlist-' . (int)$playlistId . '-*.json') ?: [] as $file) {
        @unlink($file);
    }
}

/**
 * The next free position in a playlist (append).
 *
 * @param int $playlistId
 * @param object $db Platform database connection
 * @return int
 */
function playlistNextPosition($playlistId, $db) {
    $max = $db->getOne(
        "SELECT MAX(PlaylistItemPosition) FROM ?n WHERE PlaylistItemPlaylistID = ?i",
        playlistTable("PlaylistItem"),
        $playlistId
    );

    return $max === null ? 0 : ((int)$max + 1);
}

/**
 * Number of items in a playlist.
 *
 * @param int $playlistId
 * @param object $db Platform database connection
 * @return int
 */
function playlistItemCount($playlistId, $db) {
    return (int)$db->getOne(
        "SELECT COUNT(*) FROM ?n WHERE PlaylistItemPlaylistID = ?i",
        playlistTable("PlaylistItem"),
        $playlistId
    );
}
