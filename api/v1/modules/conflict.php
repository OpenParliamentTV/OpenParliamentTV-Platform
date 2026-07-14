<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");

/**
 * Import conflicts are aggregated: one `conflict` row per unique issue,
 * identified by ConflictGroupKey = sha1(type|parliament|entityKey). The
 * affected media are stored one-row-per-occurrence in `conflict_media`
 * (MediaID = platform media ID, or "origin:<originID>/<originMediaID>" for
 * conflicts reported before a media row exists).
 */

// Machine-readable conflict type keys.
const CONFLICT_TYPES = [
    "person-missing-wikidata-id",
    "faction-missing-wikidata-id",
    "person-missing-context",
    "media-import-error",
    "media-validation-error",
    "annotation-import-error",
    "text-import-error",
    "document-missing-source-uri",
    "entity-suggestion-missing-id",
    "legacy-unclassified"
];

// Types produced by the import pipeline: when a media item is re-imported,
// its conflict_media rows for these types are cleared and re-created by the
// conflicts that still fire (auto-clean). "legacy-unclassified" rows come
// from the backlog migration and are never re-reported, so clearing them
// would silently lose them.
const CONFLICT_IMPORT_TYPES = [
    "person-missing-wikidata-id",
    "faction-missing-wikidata-id",
    "person-missing-context",
    "media-import-error",
    "media-validation-error",
    "annotation-import-error",
    "text-import-error",
    "document-missing-source-uri",
    "entity-suggestion-missing-id"
];

// Types whose resolution path is "add the missing entity to the platform"
// (the UI offers the add-entity workflow and cleanup matches by label).
const CONFLICT_ENTITY_ADDABLE_TYPES = [
    "person-missing-wikidata-id",
    "faction-missing-wikidata-id"
];

const CONFLICT_STATUSES = ["open", "ignored", "resolved"];

/**
 * Computes the aggregation key that identifies a unique conflict issue.
 *
 * @param string $type ConflictType machine key.
 * @param string $parliament Parliament code (e.g. "DE"), may be empty.
 * @param string $entityKey Identifying key of the affected entity (wid or normalised label), may be empty.
 * @return string sha1 group key.
 */
function conflictComputeGroupKey($type, $parliament, $entityKey) {
    return sha1($type . "|" . $parliament . "|" . $entityKey);
}

/**
 * Adds a conflict occurrence: upserts the conflict group and registers the
 * affected media. Re-reports merge into the existing group — an "ignored"
 * group stays ignored, a "resolved" group re-opens.
 *
 * @param array $item Conflict fields:
 *                    - ConflictType (string, required, one of CONFLICT_TYPES)
 *                    - ConflictParliament (string, optional)
 *                    - ConflictMediaID (string, optional; media ID or "origin:..." key)
 *                    - ConflictEntityType (string, optional; person|organisation|document|term|media)
 *                    - ConflictEntityLabel (string, optional)
 *                    - ConflictEntityWid (string, optional)
 *                    - ConflictEntityKey (string, optional; overrides the default wid-or-label entity key)
 *                    - ConflictData (array or JSON string, optional; latest sample payload)
 * @param object|false $dbPlatform Optional SafeMySQL platform DB connection.
 * @return array Standard API response array.
 */
function conflictAdd($item, $dbPlatform = false) {
    global $config;

    // Only read the whitelisted Conflict* keys — apiV1 merges $_GET/$_POST
    // into internal requests, so $item may carry unrelated request params.
    $type = $item["ConflictType"] ?? "";
    if (!in_array($type, CONFLICT_TYPES, true)) {
        return createApiErrorInvalidParameter("ConflictType");
    }

    $parliament = trim((string)($item["ConflictParliament"] ?? ""));
    $mediaID = trim((string)($item["ConflictMediaID"] ?? ""));
    $entityType = ($item["ConflictEntityType"] ?? null) !== null ? trim((string)$item["ConflictEntityType"]) : null;
    $entityLabel = ($item["ConflictEntityLabel"] ?? null) !== null ? trim((string)$item["ConflictEntityLabel"]) : null;
    $entityWid = ($item["ConflictEntityWid"] ?? null) !== null ? trim((string)$item["ConflictEntityWid"]) : null;

    $entityKey = $item["ConflictEntityKey"] ?? null;
    if ($entityKey === null || $entityKey === "") {
        $entityKey = ($entityWid !== null && $entityWid !== "") ? $entityWid : mb_strtolower(trim((string)$entityLabel));
    }

    $data = $item["ConflictData"] ?? null;
    if (is_array($data)) {
        $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($data !== null) {
        $data = (string)$data;
    }

    if (!$dbPlatform) {
        $dbPlatform = getApiDatabaseConnection('platform');
        if (is_array($dbPlatform) && isset($dbPlatform["errors"])) {
            return $dbPlatform;
        }
        if (!is_object($dbPlatform)) {
            return createApiErrorDatabaseConnection();
        }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];
    $tblMedia = $config["platform"]["sql"]["tbl"]["ConflictMedia"];
    $groupKey = conflictComputeGroupKey($type, $parliament, (string)$entityKey);
    $now = time();

    try {
        $existing = $dbPlatform->getRow("SELECT ConflictID, ConflictStatus, ConflictEntityLabel, ConflictEntityWid FROM ?n WHERE ConflictGroupKey = ?s", $tbl, $groupKey);

        if (!$existing) {
            try {
                $dbPlatform->query("INSERT INTO ?n SET
                        ConflictType = ?s,
                        ConflictGroupKey = ?s,
                        ConflictEntityType = ?s,
                        ConflictEntityLabel = ?s,
                        ConflictEntityWid = ?s,
                        ConflictParliament = ?s,
                        ConflictData = ?s,
                        ConflictStatus = 'open',
                        ConflictFirstSeen = ?i,
                        ConflictLastSeen = ?i",
                    $tbl, $type, $groupKey, $entityType, $entityLabel, $entityWid, $parliament, $data, $now, $now);
                $conflictID = $dbPlatform->insertId();
                $status = "created";
            } catch (Exception $e) {
                // Duplicate-key race with a concurrent report: fall through to the update path.
                $existing = $dbPlatform->getRow("SELECT ConflictID, ConflictStatus, ConflictEntityLabel, ConflictEntityWid FROM ?n WHERE ConflictGroupKey = ?s", $tbl, $groupKey);
                if (!$existing) {
                    throw $e;
                }
            }
        }

        if ($existing) {
            $updateParts = [
                $dbPlatform->parse("ConflictData = ?s", $data),
                $dbPlatform->parse("ConflictLastSeen = ?i", $now)
            ];
            // Backfill identifying fields a later report may know better.
            if ($entityLabel !== null && $entityLabel !== "" && empty($existing["ConflictEntityLabel"])) {
                $updateParts[] = $dbPlatform->parse("ConflictEntityLabel = ?s", $entityLabel);
            }
            if ($entityWid !== null && $entityWid !== "" && empty($existing["ConflictEntityWid"])) {
                $updateParts[] = $dbPlatform->parse("ConflictEntityWid = ?s", $entityWid);
            }
            // A resolved group that fires again was not actually fixed — re-open
            // it. An ignored group stays ignored (explicit admin judgement).
            if ($existing["ConflictStatus"] === "resolved") {
                $updateParts[] = "ConflictStatus = 'open'";
                $updateParts[] = "ConflictResolvedDate = NULL";
            }
            $dbPlatform->query("UPDATE ?n SET ?p WHERE ConflictID = ?i", $tbl, implode(", ", $updateParts), $existing["ConflictID"]);
            $conflictID = (int)$existing["ConflictID"];
            $status = "updated";
        }

        if ($mediaID !== "") {
            $dbPlatform->query("INSERT IGNORE INTO ?n SET ConflictID = ?i, MediaID = ?s", $tblMedia, $conflictID, $mediaID);
        }

        return createApiSuccessResponse(["ConflictID" => $conflictID, "status" => $status]);
    } catch (Exception $e) {
        error_log("Error in conflictAdd: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Retrieves conflict groups (or per-type statistics) from the database.
 *
 * @param string|int $id ConflictID of a specific group, or "all".
 * @param int $limit Maximum number of items to return.
 * @param int $offset Number of items to skip for pagination.
 * @param string|false $search Term matched against entity label/wid, type, and affected media IDs.
 * @param string|false $sort Column to sort by (whitelisted; "ConflictCount" sorts by affected-media count).
 * @param string|false $order Sort order ("ASC" or "DESC").
 * @param string $status Status filter: "open" (default), "ignored", "resolved" or "all".
 * @param string|false $type ConflictType filter.
 * @param bool $getStats If true, returns per type x status group/media counts instead of rows.
 * @return array An array containing 'total' and 'data'.
 */
function conflictGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $status = "open", $type = false, $getStats = false) {
    global $config;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        // Consistent with other *GetItemsFromDB methods, return empty on DB connection error
        return array("total" => 0, "data" => array());
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];
    $tblMedia = $config["platform"]["sql"]["tbl"]["ConflictMedia"];

    try {
        if ($getStats) {
            // One cell per type x status; the UI matrix needs all statuses.
            $statsData = $db->getAll(
                "SELECT c.ConflictType, c.ConflictStatus,
                        COUNT(DISTINCT c.ConflictID) as ConflictCount,
                        COUNT(m.MediaID) as ConflictSpeechCount
                 FROM ?n c LEFT JOIN ?n m ON m.ConflictID = c.ConflictID
                 GROUP BY c.ConflictType, c.ConflictStatus
                 ORDER BY c.ConflictType ASC, c.ConflictStatus ASC",
                $tbl, $tblMedia
            );
            foreach ($statsData as $k => $row) {
                $statsData[$k]["ConflictCount"] = (int)$row["ConflictCount"];
                $statsData[$k]["ConflictSpeechCount"] = (int)$row["ConflictSpeechCount"];
            }
            return array(
                "total" => count($statsData),
                "data" => $statsData
            );
        }

        $whereParts = [];

        if ($id !== "all" && $id !== null && $id !== '') {
            $whereParts[] = $db->parse("c.ConflictID = ?i", (int)$id);
        }

        if (!empty($search)) {
            $search_term = "%" . $search . "%";
            $whereParts[] = $db->parse(
                "(c.ConflictEntityLabel LIKE ?s OR c.ConflictEntityWid LIKE ?s OR c.ConflictType LIKE ?s
                  OR EXISTS (SELECT 1 FROM ?n m WHERE m.ConflictID = c.ConflictID AND m.MediaID LIKE ?s))",
                $search_term, $search_term, $search_term, $tblMedia, $search_term
            );
        }

        if ($status !== "all") {
            $statusFilter = in_array($status, CONFLICT_STATUSES, true) ? $status : "open";
            $whereParts[] = $db->parse("c.ConflictStatus = ?s", $statusFilter);
        }

        if (!empty($type) && in_array($type, CONFLICT_TYPES, true)) {
            $whereParts[] = $db->parse("c.ConflictType = ?s", $type);
        }

        $whereClause = empty($whereParts) ? "1" : implode(" AND ", $whereParts);

        $totalCount = $db->getOne(
            "SELECT COUNT(c.ConflictID) as count FROM ?n c WHERE ?p",
            $tbl, $whereClause
        );

        $orderByClause = "";
        if (!empty($sort)) {
            // Whitelist valid sort columns to prevent SQL injection
            $validSortColumns = ['ConflictID', 'ConflictType', 'ConflictEntityLabel', 'ConflictParliament', 'ConflictCount', 'ConflictFirstSeen', 'ConflictLastSeen', 'ConflictStatus'];
            if (in_array($sort, $validSortColumns)) {
                $orderDirection = (strtoupper($order ?? '') === 'DESC') ? 'DESC' : 'ASC';
                if ($sort === 'ConflictCount') {
                    $orderByClause = " ORDER BY ConflictCount " . $orderDirection;
                } else {
                    $orderByClause = " ORDER BY c." . $db->parse("?n", $sort) . " " . $orderDirection;
                }
            }
        }

        $limitClause = "";
        if ($limit > 0) {
            $limitClause = $db->parse(" LIMIT ?i, ?i", (int)$offset, (int)$limit);
        }

        $items = $db->getAll(
            "SELECT c.*, (SELECT COUNT(*) FROM ?n m WHERE m.ConflictID = c.ConflictID) as ConflictCount
             FROM ?n c WHERE ?p" . $orderByClause . $limitClause,
            $tblMedia, $tbl, $whereClause
        );

        foreach ($items as $key => $item) {
            $items[$key]["ConflictCount"] = (int)$item["ConflictCount"];
            if (isset($item["ConflictData"]) && is_string($item["ConflictData"])) {
                $decodedData = json_decode($item["ConflictData"], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $items[$key]["ConflictData"] = $decodedData;
                }
            }
        }

        // Single-group fetch: attach the first affected media (the detail view
        // must never load a 78k-row list) plus the exact total.
        if ($id !== "all" && $id !== null && $id !== '' && !empty($items)) {
            $items[0]["ConflictMedia"] = $db->getCol(
                "SELECT MediaID FROM ?n WHERE ConflictID = ?i ORDER BY MediaID ASC LIMIT 100",
                $tblMedia, (int)$id
            );
        }

        return array(
            "total" => (int)$totalCount,
            "data" => $items
        );

    } catch (Exception $e) {
        error_log("Error in conflictGetItemsFromDB: " . $e->getMessage());
        return array("total" => 0, "data" => array());
    }
}

/**
 * Resolves the selector of a change/delete request into conflict IDs.
 *
 * @param array $api_request Request with either "id" (int, comma-separated list
 *                           or array) or "type" (+ optional "parliament", "status").
 * @param object $db Platform DB connection.
 * @return array|false Array of int ConflictIDs (may be empty), or false on invalid selector.
 */
function conflictResolveSelector($api_request, $db) {
    global $config;

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];

    if (!empty($api_request["id"])) {
        $rawIds = is_array($api_request["id"]) ? $api_request["id"] : explode(",", (string)$api_request["id"]);
        $ids = [];
        foreach ($rawIds as $rawId) {
            $rawId = trim((string)$rawId);
            if ($rawId !== "" && ctype_digit($rawId)) {
                $ids[] = (int)$rawId;
            }
        }
        return empty($ids) ? false : array_values(array_unique($ids));
    }

    if (!empty($api_request["type"]) && in_array($api_request["type"], CONFLICT_TYPES, true)) {
        $whereParts = [$db->parse("ConflictType = ?s", $api_request["type"])];
        if (!empty($api_request["parliament"])) {
            $whereParts[] = $db->parse("ConflictParliament = ?s", $api_request["parliament"]);
        }
        if (!empty($api_request["status"]) && in_array($api_request["status"], CONFLICT_STATUSES, true)) {
            $whereParts[] = $db->parse("ConflictStatus = ?s", $api_request["status"]);
        }
        $ids = $db->getCol("SELECT ConflictID FROM ?n WHERE ?p", $tbl, implode(" AND ", $whereParts));
        return array_map('intval', $ids);
    }

    return false;
}

/**
 * Changes the status of one or more conflict groups.
 *
 * @param array $api_request Request with "status" (target: open|ignored|resolved)
 *                           and a selector: "id" (int/comma-list/array) or "type"
 *                           (+ optional "parliament"). A by-type transition to
 *                           "ignored" or "resolved" only affects open groups.
 * @param object|false $db Optional platform DB connection.
 * @return array Standard API response array with "updatedCount".
 */
function conflictChange($api_request, $db = false) {
    global $config;

    $targetStatus = $api_request["status"] ?? "";
    if (!in_array($targetStatus, CONFLICT_STATUSES, true)) {
        return createApiErrorInvalidParameter("status");
    }

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (is_array($db) && isset($db["errors"])) {
            return $db;
        }
        if (!is_object($db)) {
            return createApiErrorDatabaseConnection();
        }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];

    try {
        // By-type bulk transitions away from "open" must not flip groups an
        // admin already classified — restrict them to open groups.
        $selectorRequest = $api_request;
        unset($selectorRequest["status"]);
        if (empty($api_request["id"]) && $targetStatus !== "open") {
            $selectorRequest["status"] = "open";
        }

        $ids = conflictResolveSelector($selectorRequest, $db);
        if ($ids === false) {
            return createApiErrorInvalidParameter("id");
        }
        if (empty($ids)) {
            return createApiSuccessResponse(["updatedCount" => 0]);
        }

        if ($targetStatus === "resolved") {
            $db->query("UPDATE ?n SET ConflictStatus = ?s, ConflictResolvedDate = NOW() WHERE ConflictID IN (?a) AND ConflictStatus != ?s", $tbl, $targetStatus, $ids, $targetStatus);
        } else {
            $db->query("UPDATE ?n SET ConflictStatus = ?s, ConflictResolvedDate = NULL WHERE ConflictID IN (?a) AND ConflictStatus != ?s", $tbl, $targetStatus, $ids, $targetStatus);
        }
        $updatedCount = $db->affectedRows();

        return createApiSuccessResponse(["updatedCount" => $updatedCount]);
    } catch (Exception $e) {
        error_log("Error in conflictChange: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Deletes one or more conflict groups including their affected-media rows.
 *
 * @param array $api_request Request with a selector: "id" (int/comma-list/array)
 *                           or "type" (+ optional "parliament", "status").
 * @param object|false $db Optional platform DB connection.
 * @return array Standard API response array with "deletedCount".
 */
function conflictDelete($api_request, $db = false) {
    global $config;

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (is_array($db) && isset($db["errors"])) {
            return $db;
        }
        if (!is_object($db)) {
            return createApiErrorDatabaseConnection();
        }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];
    $tblMedia = $config["platform"]["sql"]["tbl"]["ConflictMedia"];

    try {
        $ids = conflictResolveSelector($api_request, $db);
        if ($ids === false) {
            return createApiErrorInvalidParameter("id");
        }
        if (empty($ids)) {
            return createApiSuccessResponse(["deletedCount" => 0]);
        }

        $db->query("DELETE FROM ?n WHERE ConflictID IN (?a)", $tblMedia, $ids);
        $db->query("DELETE FROM ?n WHERE ConflictID IN (?a)", $tbl, $ids);
        $deletedCount = $db->affectedRows();

        return createApiSuccessResponse(["deletedCount" => $deletedCount]);
    } catch (Exception $e) {
        error_log("Error in conflictDelete: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Marks open missing-WikidataID conflict groups as resolved when a platform
 * entity with a matching label exists (the admin added the missing entity).
 * The groups are resolved, not deleted: the conflict_media rows disappear via
 * auto-clean once the corrected source data is actually re-imported.
 *
 * @param object|false $db Optional platform DB connection.
 * @return array Standard API response array with "cleanedCount".
 */
function conflictCleanup($db = false) {
    global $config;

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (is_array($db) && isset($db["errors"])) {
            return $db;
        }
        if (!is_object($db)) {
            return createApiErrorDatabaseConnection();
        }
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];

    try {
        $groups = $db->getAll(
            "SELECT ConflictID, ConflictType, ConflictEntityLabel FROM ?n WHERE ConflictStatus = 'open' AND ConflictType IN (?a) AND ConflictEntityLabel IS NOT NULL AND ConflictEntityLabel != ''",
            $tbl, CONFLICT_ENTITY_ADDABLE_TYPES
        );

        $cleanedCount = 0;
        foreach ($groups as $group) {
            $label = $group["ConflictEntityLabel"];
            $labelLike = '%"' . $label . '"%';

            if ($group["ConflictType"] === "person-missing-wikidata-id") {
                $exists = $db->getOne(
                    "SELECT PersonID FROM ?n WHERE PersonLabel = ?s OR PersonLabelAlternative LIKE ?s LIMIT 1",
                    $config["platform"]["sql"]["tbl"]["Person"], $label, $labelLike
                );
            } else {
                $exists = $db->getOne(
                    "SELECT OrganisationID FROM ?n WHERE OrganisationLabel = ?s OR OrganisationLabelAlternative LIKE ?s LIMIT 1",
                    $config["platform"]["sql"]["tbl"]["Organisation"], $label, $labelLike
                );
            }

            if ($exists) {
                $db->query("UPDATE ?n SET ConflictStatus = 'resolved', ConflictResolvedDate = NOW() WHERE ConflictID = ?i", $tbl, $group["ConflictID"]);
                $cleanedCount++;
            }
        }

        return createApiSuccessResponse(["cleanedCount" => $cleanedCount, "status" => "Cleanup completed."]);
    } catch (Exception $e) {
        error_log("Error in conflictCleanup: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Auto-clean hook for the import pipeline: removes the given media keys from
 * all import-generated conflict groups. mediaAdd calls this right after the
 * media row is created; conflicts that still apply re-add the media while the
 * item is processed. Groups left without any affected media are deleted —
 * if such a conflict still existed, the same import run would recreate it.
 *
 * Never throws: a failure here must not break the import.
 *
 * @param array $mediaKeys Media IDs / "origin:..." keys to clear.
 * @param object $db Platform DB connection.
 */
function conflictClearMedia($mediaKeys, $db) {
    global $config;

    $mediaKeys = array_values(array_filter(array_map('strval', (array)$mediaKeys), function ($key) {
        return $key !== "" && $key !== "origin:/";
    }));
    if (empty($mediaKeys) || !is_object($db)) {
        return;
    }

    $tbl = $config["platform"]["sql"]["tbl"]["Conflict"];
    $tblMedia = $config["platform"]["sql"]["tbl"]["ConflictMedia"];

    try {
        $affectedIDs = $db->getCol("SELECT DISTINCT ConflictID FROM ?n WHERE MediaID IN (?a)", $tblMedia, $mediaKeys);
        if (empty($affectedIDs)) {
            return;
        }

        $db->query("DELETE FROM ?n WHERE MediaID IN (?a)", $tblMedia, $mediaKeys);

        $db->query(
            "DELETE c FROM ?n c LEFT JOIN ?n m ON m.ConflictID = c.ConflictID
             WHERE c.ConflictID IN (?a) AND m.ConflictID IS NULL AND c.ConflictType IN (?a)",
            $tbl, $tblMedia, $affectedIDs, CONFLICT_IMPORT_TYPES
        );
    } catch (Exception $e) {
        error_log("Error in conflictClearMedia: " . $e->getMessage());
    }
}

?>
