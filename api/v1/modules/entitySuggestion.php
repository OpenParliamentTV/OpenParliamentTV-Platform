<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
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

?> 