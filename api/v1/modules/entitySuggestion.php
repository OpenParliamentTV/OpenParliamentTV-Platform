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
        if (isset($db["errors"])) { // getApiDatabaseConnection returns an error array on failure
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
 * Reimports specified session files for an entity suggestion.
 *
 * @param array $request_params An array containing:
 *                              - files (array, required): An associative array where keys are parliament codes
 *                                and values are arrays of session filenames to re-import.
 *                                e.g., ["DE" => ["21001-session.json", "21002-session.json"]]
 * @param object|false $db Optional platform database connection object.
 * @return array Standard API response array.
 */
function entitySuggestionReimportSessions($request_params, $db = false) {
    global $config; 
    
    if (empty($request_params['files']) || !is_array($request_params['files'])) {
        return createApiErrorMissingParameter('files');
    }

    $filesToReimport = $request_params['files'];
    $results = [
        'copied' => [],
        'failed' => [],
        'skipped' => []
    ];
    $projectRoot = realpath(__DIR__ . "/../../../"); // project_root

    if (!$projectRoot) {
        error_log("Critical error: Project root could not be determined in entitySuggestionReimportSessions.");
        return createApiErrorResponse(500, 'PROJECT_ROOT_ERROR', 'messageErrorInternal', 'Could not determine project root directory.');
    }

    foreach ($filesToReimport as $parliament => $sessionFiles) {
        if (!is_array($sessionFiles)) {
            $results['failed'][] = ['parliament' => $parliament, 'file' => 'N/A', 'reason' => 'Invalid file list format for parliament.'];
            continue;
        }
        foreach ($sessionFiles as $file) {
            $sourcePath = $projectRoot . "/data/repos/" . $parliament . "/processed/" . $file;
            $destinationPath = $projectRoot . "/data/input/" . $file;

            if (!is_file($sourcePath)) {
                $results['skipped'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Source file not found or is not a file.'];
                error_log("Reimport: Source file not found or not a file: " . $sourcePath);
                continue;
            }
            if (!is_readable($sourcePath)) {
                $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Source file not readable.'];
                error_log("Reimport: Source file not readable: " . $sourcePath);
                continue;
            }

            // Ensure destination directory exists or can be created (though /data/input/ should exist)
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir)) {
                // Attempt to create if it doesn't exist, though this should ideally already be set up.
                if (!mkdir($destinationDir, 0775, true)) {
                    $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Destination directory does not exist and could not be created.'];
                    error_log("Reimport: Destination directory could not be created: " . $destinationDir);
                    continue;
                }
            }

            if (copy($sourcePath, $destinationPath)) {
                $results['copied'][] = ['parliament' => $parliament, 'file' => $file, 'source' => $sourcePath, 'destination' => $destinationPath];
            } else {
                $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Copy operation failed.'];
                error_log("Reimport: Failed to copy " . $sourcePath . " to " . $destinationPath);
            }
        }
    }

    return createApiSuccessResponse($results, ['summary' => count($results['copied']) . ' file(s) re-imported, ' . count($results['failed']) . ' failed, ' . count($results['skipped']) . ' skipped.']);
}

?> 