<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php"); // For general utility functions if needed
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php"); // For getApiDatabaseConnection, response formatting

/**
 * Retrieves a list of entity suggestions from the database.
 *
 * @param string|int $id EntitysuggestionExternalID or "all" to retrieve all suggestions.
 * @param int $limit Maximum number of items to return.
 * @param int $offset Number of items to skip for pagination.
 * @param string|false $search Search term to filter entity suggestions (by Label or ExternalID).
 * @param string|false $sort Column to sort by.
 * @param string|false $order Sort order ("ASC" or "DESC").
 * @param string $idType The type of ID being provided when $id is not "all" ("internal" or "external"). Defaults to "external".
 * @return array An array containing 'total' (total number of matching records) and 'data' (the entity suggestion records).
 */
function entitySuggestionGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $idType = "external") {
    global $config;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return array("total" => 0, "data" => array()); // Consistent error return
    }

    try {
        $whereParts = [];

        if ($id !== "all" && $id !== null && $id !== '') {
            if ($idType === "internal") {
                $whereParts[] = $db->parse("EntitysuggestionID=?i", $id);
            } else { // Default to external ID
                $whereParts[] = $db->parse("EntitysuggestionExternalID=?s", $id);
            }
        } else {
            $whereParts[] = "1"; // Base condition if $id is "all"
        }

        if (!empty($search)) {
            $search_term = "%" . $search . "%";
            $whereParts[] = $db->parse("(EntitysuggestionLabel LIKE ?s OR EntitysuggestionExternalID LIKE ?s)", $search_term, $search_term);
        }
        
        $whereClause = implode(" AND ", $whereParts);

        $totalCountQuery = "SELECT COUNT(EntitysuggestionID) as count FROM " . $config["platform"]["sql"]["tbl"]["Entitysuggestion"] . " WHERE " . $whereClause;
        $totalCount = $db->getOne($totalCountQuery);

        $orderByClause = "";
        if (!empty($sort)) {
            // Whitelist valid sort columns to prevent SQL injection
            $validSortColumns = ['EntitysuggestionID', 'EntitysuggestionExternalID', 'EntitysuggestionType', 'EntitysuggestionLabel', 'EntitysuggestionCount']; 
            if (in_array($sort, $validSortColumns)) {
                $orderDirection = (strtoupper($order ?? '') === 'DESC') ? 'DESC' : 'ASC';
                if ($sort === 'EntitysuggestionCount') { // Special handling if sorting by a calculated/aliased field
                     $orderByClause = " ORDER BY JSON_LENGTH(EntitysuggestionContext) " . $orderDirection;
                } else {
                    $orderByClause = " ORDER BY " . $db->parse("?n", $sort) . " " . $orderDirection;
                }
            } 
        }

        $limitClause = "";
        if ($limit > 0) {
            $limitClause = $db->parse(" LIMIT ?i, ?i", (int)$offset, (int)$limit);
        }

        // SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount FROM ...
        $itemsQuery = "SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount FROM " . 
                      $config["platform"]["sql"]["tbl"]["Entitysuggestion"] . 
                      " WHERE " . $whereClause . $orderByClause . $limitClause;
        
        $items = $db->getAll($itemsQuery);

        // Decode EntitysuggestionContext for each item
        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (isset($item['EntitysuggestionContext']) && is_string($item['EntitysuggestionContext'])) {
                    $decodedContext = json_decode($item['EntitysuggestionContext'], true);
                    // Check if json_decode was successful or if it was already an array (though type hint says string)
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $items[$key]['EntitysuggestionContext'] = $decodedContext;
                    } else {
                        // Optionally handle/log error if context is not valid JSON
                        error_log("Failed to decode EntitysuggestionContext for ID: " . ($item['EntitysuggestionID'] ?? 'N/A'));
                        // Keep it as is or set to empty array if preferred on error
                        // $items[$key]['EntitysuggestionContext'] = []; 
                    }
                }
            }
        }

        return array(
            "total" => (int)$totalCount,
            "data" => $items
        );

    } catch (Exception $e) {
        error_log("Error in entitySuggestionGetItemsFromDB: " . $e->getMessage());
        return array("total" => 0, "data" => array());
    }
}

/**
 * Adds or updates an entity suggestion in the database.
 *
 * @param array $api_request An array containing the entity suggestion data:
 *                           - EntitysuggestionExternalID (string, required if no conflict to report)
 *                           - EntitysuggestionType (string, required)
 *                           - EntitysuggestionLabel (string, required)
 *                           - EntitysuggestionContent (string, required, JSON string)
 *                           - EntitysuggestionContext (string, required, key for context array)
 * @param object|false $db Optional database connection object.
 * @return array Standard API response array.
 */
function entitySuggestionAdd($api_request, $db = false) {
    global $config;

    // Validate required parameters
    $requiredFields = ['EntitysuggestionType', 'EntitysuggestionLabel', 'EntitysuggestionContent', 'EntitysuggestionContext'];
    foreach ($requiredFields as $field) {
        if (empty($api_request[$field])) {
            return createApiErrorMissingParameter($field);
        }
    }

    $entitysuggestionExternalID = $api_request['EntitysuggestionExternalID'] ?? null;
    $entitysuggestionType = $api_request['EntitysuggestionType'];
    $entitysuggestionLabel = $api_request['EntitysuggestionLabel'];
    $entitysuggestionContent = $api_request['EntitysuggestionContent']; // Expected to be a JSON string from the client or other modules
    $singleContextEntry = $api_request['EntitysuggestionContext']; // This is the single context string to add

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        // On failure, getApiDatabaseConnection returns an error array. On success, it's an object.
        if (is_array($db) && isset($db["errors"])) {
            return $db; 
        }
    }

    if (empty($entitysuggestionExternalID)) {
        $reportArray = [
            "type" => $entitysuggestionType,
            "label" => $entitysuggestionLabel,
            "content" => $entitysuggestionContent,
            "context" => $singleContextEntry // Original function passed the single context here
        ];
        return reportConflict(
            "Entitysuggestion", 
            "Suggestion had no ID", 
            "", 
            "", 
            json_encode($reportArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $db // Pass the DB connection to reportConflict helper which then passes to apiV1
        );
    }

    try {
        $exists = $db->getRow("SELECT * FROM ?n WHERE EntitysuggestionExternalID = ?s", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitysuggestionExternalID);

        if ($exists) {
            $currentContextArray = json_decode($exists["EntitysuggestionContext"], true);
            if (json_last_error() !== JSON_ERROR_NONE) { // Handle cases where existing context might be malformed
                $currentContextArray = []; // Default to empty array if decode fails
                error_log("Malformed JSON in EntitysuggestionContext for EntitysuggestionExternalID: " . $entitysuggestionExternalID);
            }
            $currentContextArray[$singleContextEntry] = $singleContextEntry; // Add/update the specific context
            $newContextJson = json_encode($currentContextArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $db->query("UPDATE ?n SET EntitysuggestionContext = ?s WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $newContextJson, $exists["EntitysuggestionID"]);
            return createApiSuccessResponse(["EntitysuggestionID" => $exists["EntitysuggestionID"], "status" => "updated"]);
        } else {
            $newContextArray = [$singleContextEntry => $singleContextEntry];
            $newContextJson = json_encode($newContextArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $db->query("INSERT INTO ?n SET
                                EntitysuggestionExternalID = ?s,
                                EntitysuggestionType = ?s,
                                EntitysuggestionLabel = ?s,
                                EntitysuggestionContent = ?s,
                                EntitysuggestionContext = ?s",
                $config["platform"]["sql"]["tbl"]["Entitysuggestion"],
                $entitysuggestionExternalID,
                $entitysuggestionType,
                $entitysuggestionLabel,
                $entitysuggestionContent, // Store EntitysuggestionContent as is (JSON string)
                $newContextJson);
            
            $insertedId = $db->insertId();
            return createApiSuccessResponse(["EntitysuggestionID" => $insertedId, "status" => "created"]);
        }
    } catch (Exception $e) {
        error_log("Error in entitySuggestionAdd: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Deletes an entity suggestion from the database.
 *
 * @param int $entitySuggestionID The internal ID of the entity suggestion to delete.
 * @param object|false $db Optional database connection object.
 * @return array Standard API response array.
 */
function entitySuggestionDelete($entitySuggestionID, $db = false) {
    global $config;

    if (empty($entitySuggestionID)) {
        return createApiErrorMissingParameter('EntitysuggestionID');
    }

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        // On failure, getApiDatabaseConnection returns an error array. On success, it's an object.
        if (is_array($db) && isset($db["errors"])) {
            return $db;
        }
    }

    try {
        // Check if the suggestion exists before attempting to delete
        $exists = $db->getOne("SELECT EntitysuggestionID FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitySuggestionID);

        if (!$exists) {
            return createApiErrorNotFound('EntitySuggestion');
        }

        $db->query("DELETE FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitySuggestionID);
        
        // Check if the row was actually deleted
        $stillExists = $db->getOne("SELECT EntitysuggestionID FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitySuggestionID);
        if ($stillExists) {
            // This case should ideally not happen if the query was successful and no error was thrown
            error_log("Error in entitySuggestionDelete: Failed to delete EntitysuggestionID: " . $entitySuggestionID . " despite no DB exception.");
            return createApiErrorResponse(500, 1, "messageErrorItemDeletionFailedTitle", "messageErrorItemDeletionFailedDetail", ["itemType" => "EntitySuggestion"]);
        }

        return createApiSuccessResponse(["EntitysuggestionID" => $entitySuggestionID, "status" => "deleted"]);

    } catch (Exception $e) {
        error_log("Error in entitySuggestionDelete: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

?> 