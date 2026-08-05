<?php
/**
 * Playlist CRUD API module.
 *
 * A *playlist* is a user-curated queue for the media player. All playlists live
 * in the platform DB and belong to exactly one user; every endpoint here except
 * `resolve` operates on the caller's own playlists and rejects anonymous callers.
 *
 * `resolve` is the one publicly reachable action: it takes a share token and
 * returns the queue format the player consumes. A playlist is private until its
 * owner sets `shared`, so a token alone is not enough.
 *
 * Routed from api/v1/registry.php: action=playlist,
 * itemType=list|get|resolve|create|update|delete|itemAdd|itemRemove|itemsReorder
 */

require_once(__DIR__ . "/../utilities.php");
require_once(__DIR__ . "/../../../modules/notifications/notifications.php"); // notificationCurrentUserId()
require_once(__DIR__ . "/../../../modules/playlist/storage.php");

/**
 * Gate every endpoint on the feature flag, then on a logged-in user.
 *
 * Unlike the alert module, the flag is enforced here and not only in the UI: an
 * instance with playlists switched off should not have a reachable write API.
 *
 * @return int|array User id, or an error response array
 */
function playlistRequireUser() {
    if (!playlistFeatureEnabled()) {
        return apiErrorResponse(404, 1, "messageErrorNotFoundTitle", "messageErrorNotFoundDetail");
    }

    $userId = notificationCurrentUserId();
    if (!$userId) {
        return apiErrorResponse(401, 1, "messageAuthLoginRequiredTitle", "messageAuthLoginRequiredDetail");
    }

    return $userId;
}

/**
 * Load one of the caller's own playlists by id.
 *
 * @param mixed $id
 * @param int $userId
 * @param object $db
 * @return array|null Row, or null when it does not exist / is not theirs
 */
function playlistOwnedRow($id, $userId, $db) {
    $id = (int)$id;
    if (!$id) {
        return null;
    }

    return $db->getRow(
        "SELECT * FROM ?n WHERE PlaylistID = ?i AND PlaylistUserID = ?i",
        playlistTable("Playlist"), $id, $userId
    ) ?: null;
}

/**
 * Append items to a playlist, respecting PLAYLIST_MAX_ITEMS.
 *
 * Shared by create (initial items) and itemAdd.
 *
 * @param int $playlistId
 * @param array $items  [{mediaID|id, parliament, segments}, …]
 * @param object $db
 * @param int|null $position Explicit position for a single item, or null to append
 * @return int Number of items actually written
 */
function playlistAppendItems($playlistId, $items, $db, $position = null) {
    $existing = playlistItemCount($playlistId, $db);
    $next = $position !== null ? (int)$position : playlistNextPosition($playlistId, $db);
    $written = 0;

    foreach ($items as $item) {
        if ($existing + $written >= PLAYLIST_MAX_ITEMS) {
            break;
        }

        $mediaId = $item["mediaID"] ?? ($item["id"] ?? null);
        if (!is_string($mediaId) || $mediaId === "") {
            continue;
        }

        $parliament = $item["parliament"] ?? "";
        if (!is_string($parliament)) {
            $parliament = "";
        }
        // Media ids are "<PARLIAMENT>-<number>", so the prefix is a reliable
        // fallback when the caller did not send one.
        if ($parliament === "" && strpos($mediaId, "-") > 0) {
            $parliament = substr($mediaId, 0, strpos($mediaId, "-"));
        }

        $segments = playlistNormalizeSegments($item["segments"] ?? null);

        $db->query("INSERT INTO ?n SET ?u", playlistTable("PlaylistItem"), [
            "PlaylistItemPlaylistID" => $playlistId,
            "PlaylistItemParliament" => substr($parliament, 0, 16),
            "PlaylistItemMediaID" => substr($mediaId, 0, 255),
            "PlaylistItemSegments" => playlistEncodeSegments($segments),
            "PlaylistItemPosition" => $next + $written,
        ]);
        $written++;
    }

    return $written;
}

/**
 * Read an items array off a request (JSON string or array).
 *
 * @param array $parameter
 * @return array
 */
function playlistExtractItems($parameter) {
    if (!isset($parameter["items"])) {
        return [];
    }
    $items = is_array($parameter["items"]) ? $parameter["items"] : json_decode((string)$parameter["items"], true);

    return is_array($items) ? $items : [];
}

// -----------------------------------------------------------------------------
// Endpoints
// -----------------------------------------------------------------------------

function playlistList($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $rows = $db->getAll(
        "SELECT p.*, (SELECT COUNT(*) FROM ?n i WHERE i.PlaylistItemPlaylistID = p.PlaylistID) AS ItemCount
         FROM ?n p WHERE p.PlaylistUserID = ?i ORDER BY p.PlaylistLastChanged DESC",
        playlistTable("PlaylistItem"), playlistTable("Playlist"), $userId
    ) ?: [];

    $out = array_map(function ($row) {
        return playlistRowToObject($row, true, $row["ItemCount"]);
    }, $rows);

    return apiSuccessResponse($out, [], null, null, count($out));
}

function playlistGet($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistOwnedRow($parameter["id"] ?? 0, $userId, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    $items = playlistGetItemRows($row["PlaylistID"], $db);
    $object = playlistRowToObject($row, true, count($items));

    // Raw stored items, for the edit UI — resolve is what the player uses.
    $object["items"] = array_map(function ($item) {
        return [
            "id" => (int)$item["PlaylistItemID"],
            "mediaID" => $item["PlaylistItemMediaID"],
            "parliament" => $item["PlaylistItemParliament"],
            "segments" => playlistDecodeSegments($item["PlaylistItemSegments"]),
            "position" => (int)$item["PlaylistItemPosition"],
        ];
    }, $items);

    return apiSuccessResponse($object);
}

/**
 * Resolve a playlist to the player's queue format.
 *
 * The only action reachable without owning the playlist — and the only one that
 * takes a token instead of an id. A non-owner needs PlaylistShared; anything
 * else is reported as "not found" rather than "forbidden", so a token cannot be
 * used to probe for the existence of private playlists.
 */
function playlistResolve($parameter = []) {
    if (!playlistFeatureEnabled()) {
        return apiErrorResponse(404, 1, "messageErrorNotFoundTitle", "messageErrorNotFoundDetail");
    }

    $token = $parameter["token"] ?? null;
    if (!$token) { return apiErrorMissingParameter("token"); }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistGetByToken($token, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    $userId = notificationCurrentUserId();
    $isOwner = $userId && (int)$row["PlaylistUserID"] === (int)$userId;

    if (!$isOwner && !(int)$row["PlaylistShared"]) {
        return apiErrorNotFound("playlist");
    }

    $rows = playlistGetItemRows($row["PlaylistID"], $db);
    $items = playlistResolveItems($rows);

    if ($items === null) {
        return apiErrorResponse(503, 3, "messageErrorSearchGenericTitle", "messageServiceSearchUnavailable");
    }

    $playlist = playlistRowToObject($row, $isOwner, count($items))["attributes"];

    // The owner's own UI mutates through the id-based endpoints, so it needs the
    // id back. Guests never see it — they only ever hold the token.
    if ($isOwner) {
        $playlist["id"] = (int)$row["PlaylistID"];
    }

    return apiSuccessResponse(
        $items,
        [
            "requestStatus" => "success",
            "playlist" => $playlist,
            "results" => ["count" => count($items), "total" => count($items)],
        ]
    );
}

function playlistCreate($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $title = trim((string)($parameter["title"] ?? ""));
    if ($title === "") { return apiErrorMissingParameter("title"); }

    $count = (int)$db->getOne("SELECT COUNT(*) FROM ?n WHERE PlaylistUserID = ?i",
        playlistTable("Playlist"), $userId);
    if ($count >= PLAYLIST_MAX_PER_USER) {
        return apiErrorResponse(429, 1, "messagePlaylistLimitTitle", "messagePlaylistLimitDetail");
    }

    $type = ($parameter["playlistType"] ?? $parameter["type"] ?? "custom") === "live" ? "live" : "custom";

    $db->query("INSERT INTO ?n SET ?u", playlistTable("Playlist"), [
        "PlaylistUserID" => $userId,
        "PlaylistType" => $type,
        "PlaylistTitle" => mb_substr($title, 0, 200),
        "PlaylistDescription" => isset($parameter["description"]) ? (string)$parameter["description"] : null,
        "PlaylistToken" => playlistGenerateToken(),
        "PlaylistShared" => isset($parameter["shared"]) ? (int)(bool)json_decode((string)$parameter["shared"]) : 0,
    ]);
    $newId = $db->insertId();

    $items = playlistExtractItems($parameter);
    $written = empty($items) ? 0 : playlistAppendItems($newId, $items, $db);

    $row = $db->getRow("SELECT * FROM ?n WHERE PlaylistID = ?i", playlistTable("Playlist"), $newId);
    $object = playlistRowToObject($row, true, $written);

    $meta = [];
    if (count($items) > $written) {
        // Say so rather than silently dropping the tail.
        $meta["truncated"] = ["requested" => count($items), "stored" => $written, "limit" => PLAYLIST_MAX_ITEMS];
    }

    return apiSuccessResponse($object, $meta);
}

function playlistUpdate($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistOwnedRow($parameter["id"] ?? 0, $userId, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    $set = [];
    if (isset($parameter["title"])) {
        $title = trim((string)$parameter["title"]);
        if ($title === "") { return apiErrorInvalidParameter("title"); }
        $set["PlaylistTitle"] = mb_substr($title, 0, 200);
    }
    if (isset($parameter["description"])) {
        $set["PlaylistDescription"] = (string)$parameter["description"];
    }
    if (isset($parameter["shared"])) {
        $set["PlaylistShared"] = (int)(bool)json_decode((string)$parameter["shared"]);
    }

    if (empty($set)) { return apiErrorMissingParameter("title"); }

    $db->query("UPDATE ?n SET ?u WHERE PlaylistID = ?i AND PlaylistUserID = ?i",
        playlistTable("Playlist"), $set, $row["PlaylistID"], $userId);

    $row = $db->getRow("SELECT * FROM ?n WHERE PlaylistID = ?i", playlistTable("Playlist"), $row["PlaylistID"]);

    return apiSuccessResponse(playlistRowToObject($row, true, playlistItemCount($row["PlaylistID"], $db)));
}

function playlistDelete($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistOwnedRow($parameter["id"] ?? 0, $userId, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    // No foreign keys in this schema, so the children go explicitly.
    $db->query("DELETE FROM ?n WHERE PlaylistItemPlaylistID = ?i",
        playlistTable("PlaylistItem"), $row["PlaylistID"]);
    $db->query("DELETE FROM ?n WHERE PlaylistID = ?i AND PlaylistUserID = ?i",
        playlistTable("Playlist"), $row["PlaylistID"], $userId);

    return apiSuccessResponse();
}

function playlistItemAdd($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistOwnedRow($parameter["id"] ?? 0, $userId, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    if ($row["PlaylistType"] === "live") {
        // Live playlists derive their items from a query; hand-adding to one
        // would be silently discarded on the next resolve.
        return apiErrorResponse(422, 1, "messageErrorInvalidParameter", "messagePlaylistLiveNotEditable");
    }

    $items = playlistExtractItems($parameter);
    if (empty($items)) {
        $mediaId = $parameter["mediaID"] ?? null;
        if (!$mediaId) { return apiErrorMissingParameter("mediaID"); }
        $items = [[
            "mediaID" => $mediaId,
            "parliament" => $parameter["parliament"] ?? "",
            "segments" => $parameter["segments"] ?? null,
        ]];
    }

    $before = playlistItemCount($row["PlaylistID"], $db);
    if ($before >= PLAYLIST_MAX_ITEMS) {
        return apiErrorResponse(429, 1, "messagePlaylistItemLimitTitle", "messagePlaylistItemLimitDetail");
    }

    $written = playlistAppendItems($row["PlaylistID"], $items, $db);

    // Touch the parent so the manage list re-sorts on real activity.
    $db->query("UPDATE ?n SET PlaylistLastChanged = CURRENT_TIMESTAMP WHERE PlaylistID = ?i",
        playlistTable("Playlist"), $row["PlaylistID"]);

    $row = $db->getRow("SELECT * FROM ?n WHERE PlaylistID = ?i", playlistTable("Playlist"), $row["PlaylistID"]);
    $object = playlistRowToObject($row, true, $before + $written);

    $meta = [];
    if (count($items) > $written) {
        $meta["truncated"] = ["requested" => count($items), "stored" => $written, "limit" => PLAYLIST_MAX_ITEMS];
    }

    return apiSuccessResponse($object, $meta);
}

function playlistItemRemove($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $itemId = (int)($parameter["itemID"] ?? 0);
    if (!$itemId) { return apiErrorMissingParameter("itemID"); }

    // Join back to the parent so ownership is checked in the delete itself.
    $item = $db->getRow(
        "SELECT i.* FROM ?n i JOIN ?n p ON p.PlaylistID = i.PlaylistItemPlaylistID
         WHERE i.PlaylistItemID = ?i AND p.PlaylistUserID = ?i",
        playlistTable("PlaylistItem"), playlistTable("Playlist"), $itemId, $userId
    );
    if (!$item) { return apiErrorNotFound("playlistItem"); }

    $db->query("DELETE FROM ?n WHERE PlaylistItemID = ?i", playlistTable("PlaylistItem"), $itemId);
    $db->query("UPDATE ?n SET PlaylistLastChanged = CURRENT_TIMESTAMP WHERE PlaylistID = ?i",
        playlistTable("Playlist"), $item["PlaylistItemPlaylistID"]);

    return apiSuccessResponse();
}

/**
 * Reorder a playlist: the caller sends the full ordered list of item ids and
 * positions are renumbered from it, so a stale client cannot leave holes.
 */
function playlistItemsReorder($parameter = []) {
    $userId = playlistRequireUser();
    if (is_array($userId)) { return $userId; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) { return $db; }

    $row = playlistOwnedRow($parameter["id"] ?? 0, $userId, $db);
    if (!$row) { return apiErrorNotFound("playlist"); }

    $order = $parameter["order"] ?? null;
    if (!is_array($order)) {
        $order = json_decode((string)$order, true);
    }
    if (!is_array($order) || empty($order)) {
        return apiErrorMissingParameter("order");
    }

    // Only ids that really belong to this playlist may be positioned.
    $owned = $db->getCol(
        "SELECT PlaylistItemID FROM ?n WHERE PlaylistItemPlaylistID = ?i",
        playlistTable("PlaylistItem"), $row["PlaylistID"]
    ) ?: [];
    $owned = array_flip(array_map('intval', $owned));

    $position = 0;
    foreach ($order as $itemId) {
        $itemId = (int)$itemId;
        if (!isset($owned[$itemId])) { continue; }
        $db->query("UPDATE ?n SET PlaylistItemPosition = ?i WHERE PlaylistItemID = ?i",
            playlistTable("PlaylistItem"), $position, $itemId);
        $position++;
    }

    $db->query("UPDATE ?n SET PlaylistLastChanged = CURRENT_TIMESTAMP WHERE PlaylistID = ?i",
        playlistTable("Playlist"), $row["PlaylistID"]);

    return apiSuccessResponse();
}
