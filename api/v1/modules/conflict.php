<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * Retrieves a list of conflicts from the database.
 *
 * @param string|int $id ID of a specific conflict, or "all" to retrieve all conflicts.
 * @param int $limit Maximum number of items to return.
 * @param int $offset Number of items to skip for pagination.
 * @param string|false $search Search term to filter conflicts.
 * @param string|false $sort Column to sort by.
 * @param string|false $order Sort order ("ASC" or "DESC").
 * @param bool $includeResolved Whether to include resolved conflicts. Defaults to false (only unresolved).
 * @param bool $getStats If true, returns aggregated statistics instead of individual rows.
 * @return array An array containing 'total' (total number of matching records or groups) and 'data' (the conflict records or stats).
 */
function conflictGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $includeResolved = false, $getStats = false) {
    global $config;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        // Consistent with other *GetItemsFromDB methods, return empty on DB connection error
        return array("total" => 0, "data" => array());
    }

    try {
        if ($getStats) {
            // Query for statistics: Count conflicts per subject
            // Only include unresolved conflicts for stats by default, matching the main table view.
            $statsWhere = $includeResolved ? "1" : "ConflictResolved=0";
            $statsData = $db->getAll(
                "SELECT ConflictSubject, COUNT(ConflictID) as ConflictCount FROM ?n WHERE ?p GROUP BY ConflictSubject ORDER BY ConflictSubject ASC",
                $config["platform"]["sql"]["tbl"]["Conflict"],
                $statsWhere
            );
            return array(
                "total" => count($statsData), // Total number of subjects with conflicts
                "data" => $statsData
            );
        }

        $whereParts = [];

        if ($id !== "all" && $id !== null && $id !== '') {
            $whereParts[] = $db->parse("ConflictID = ?i", (int)$id);
        }

        if (!empty($search)) {
            $search_term = "%" . $search . "%";
            $whereParts[] = $db->parse(
                "(ConflictSubject LIKE ?s OR ConflictDescription LIKE ?s OR ConflictEntity LIKE ?s OR ConflictIdentifier LIKE ?s OR ConflictRival LIKE ?s)",
                $search_term, $search_term, $search_term, $search_term, $search_term
            );
        }

        // If $includeResolved is false (default), only show unresolved conflicts.
        // If $includeResolved is true, show both resolved and unresolved (no condition on ConflictResolved).
        if ($includeResolved === false) { // Explicitly check for false
            $whereParts[] = "ConflictResolved=0";
        }


        $whereClause = empty($whereParts) ? "1" : implode(" AND ", $whereParts);

        $totalCount = $db->getOne(
            "SELECT COUNT(ConflictID) as count FROM ?n WHERE ?p",
            $config["platform"]["sql"]["tbl"]["Conflict"],
            $whereClause
        );

        $orderByClause = "";
        if (!empty($sort)) {
            // Whitelist valid sort columns to prevent SQL injection
            $validSortColumns = ['ConflictID', 'ConflictEntity', 'ConflictIdentifier', 'ConflictRival', 'ConflictSubject', 'ConflictDate', 'ConflictResolved', 'ConflictTimestamp'];
            if (in_array($sort, $validSortColumns)) {
                $orderDirection = (strtoupper($order ?? '') === 'DESC') ? 'DESC' : 'ASC';
                $orderByClause = " ORDER BY " . $db->parse("?n", $sort) . " " . $orderDirection;
            }
            // Silently ignore invalid sort columns or log an error if preferred
        }

        $limitClause = "";
        if ($limit > 0) {
            $limitClause = $db->parse(" LIMIT ?i, ?i", (int)$offset, (int)$limit);
        }

        $items = $db->getAll(
            "SELECT * FROM ?n WHERE ?p" . $orderByClause . $limitClause,
            $config["platform"]["sql"]["tbl"]["Conflict"],
            $whereClause
        );

        return array(
            "total" => (int)$totalCount,
            "data" => $items
        );

    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Error in conflictGetItemsFromDB: " . $e->getMessage());
        return array("total" => 0, "data" => array());
    }
}

/**
 * Reports (adds) a new conflict item to the database.
 *
 * @param array $item An associative array containing the conflict details.
 * Expected keys: ConflictEntity, ConflictSubject, ConflictIdentifier (optional),
 * ConflictRival (optional), ConflictDescription (optional).
 * @param object|false $dbPlatform Optional SafeMySQL database object for the platform database.
 * If not provided, a new connection will be established.
 * @return array Standard API response array (success or error).
 */
function conflictAdd($item, $dbPlatform = false) {
    global $config;

    // Validate required fields
    if (empty($item["ConflictEntity"])) {
        return createApiErrorMissingParameter("ConflictEntity");
    }
    if (empty($item["ConflictSubject"])) {
        return createApiErrorMissingParameter("ConflictSubject");
    }

    // Provide default values for optional fields if they are not set
    $identifier = $item["ConflictIdentifier"] ?? "";
    $rival = $item["ConflictRival"] ?? "";
    $description = $item["ConflictDescription"] ?? "";
    $entity = $item["ConflictEntity"];
    $subject = $item["ConflictSubject"];

    if (!$dbPlatform) {
        $dbPlatform = getApiDatabaseConnection('platform');
        if (!is_object($dbPlatform)) {
            return createApiErrorDatabaseConnection(); 
        }
    }

    try {
        $dbPlatform->query(
            "INSERT INTO " . $config["platform"]["sql"]["tbl"]["Conflict"] . 
            " SET ConflictEntity = ?s, ConflictIdentifier=?s, ConflictRival=?s, ConflictSubject=?s, ConflictDescription=?s, ConflictDate=?s, ConflictTimestamp=?i", 
            $entity, 
            $identifier, 
            $rival, 
            $subject, 
            $description, 
            date("Ymd H:i:s"), 
            time()
        );
        $insertedId = $dbPlatform->insertId();
        if ($insertedId) {
            return createApiSuccessResponse(["id" => $insertedId, "message" => "Conflict reported successfully."]);
        } else {
            return createApiErrorResponse(500, 1, "Database Error", "Failed to report conflict. No ID returned.");
        }
    } catch (Exception $e) {
        error_log("Error in conflictAdd: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

?> 