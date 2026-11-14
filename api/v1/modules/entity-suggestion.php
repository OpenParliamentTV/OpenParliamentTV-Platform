<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php"); // For general utility functions if needed
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php"); // For getApiDatabaseConnection, response formatting

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
            return apiErrorMissingParameter($field);
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
        $itemInfos = optvParseStringId($singleContextEntry);
        return reportConflict([
            "type" => "entity-suggestion-missing-id",
            "parliament" => ($itemInfos && !empty($itemInfos["parliament"])) ? $itemInfos["parliament"] : "",
            "mediaID" => $singleContextEntry,
            "entityType" => mb_strtolower((string)$entitysuggestionType),
            "entityLabel" => $entitysuggestionLabel,
            "data" => $reportArray
        ], $db);
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
            return apiSuccessResponse(["EntitysuggestionID" => $exists["EntitysuggestionID"], "status" => "updated"]);
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
            return apiSuccessResponse(["EntitysuggestionID" => $insertedId, "status" => "created"]);
        }
    } catch (Exception $e) {
        error_log("Error in entitySuggestionAdd: " . $e->getMessage());
        return apiErrorDatabaseError($e->getMessage());
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
        return apiErrorMissingParameter('EntitysuggestionID');
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
            return apiErrorNotFound('EntitySuggestion');
        }

        $db->query("DELETE FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitySuggestionID);
        
        // Check if the row was actually deleted
        $stillExists = $db->getOne("SELECT EntitysuggestionID FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitySuggestionID);
        if ($stillExists) {
            // This case should ideally not happen if the query was successful and no error was thrown
            error_log("Error in entitySuggestionDelete: Failed to delete EntitysuggestionID: " . $entitySuggestionID . " despite no DB exception.");
            return apiErrorResponse(500, 1, "messageErrorItemDeletionFailedTitle", "messageErrorItemDeletionFailedDetail", ["itemType" => "EntitySuggestion"]);
        }

        return apiSuccessResponse(["EntitysuggestionID" => $entitySuggestionID, "status" => "deleted"]);

    } catch (Exception $e) {
        error_log("Error in entitySuggestionDelete: " . $e->getMessage());
        return apiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Cleans up entity suggestions by removing those that already exist as entities.
 *
 * @param object|false $db Optional database connection object.
 * @return array Standard API response array with a count of cleaned items.
 */
function entitySuggestionCleanup($db = false) {
    global $config;

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (is_array($db) && isset($db["errors"])) {
            return $db;
        }
    }

    try {
        $suggestions = $db->getAll("SELECT EntitysuggestionID, EntitysuggestionExternalID FROM ?n", $config["platform"]["sql"]["tbl"]["Entitysuggestion"]);
        
        $cleanedCount = 0;
        
        if (empty($suggestions)) {
            return apiSuccessResponse(["cleanedCount" => 0, "status" => "No suggestions to clean."]);
        }
        
        foreach ($suggestions as $suggestion) {
            $externalId = $suggestion['EntitysuggestionExternalID'];
            $suggestionId = $suggestion['EntitysuggestionID'];

            $exists = false;
            
            // Check in persons table
            if (!$exists) {
                $personExists = $db->getOne("SELECT PersonID FROM ?n WHERE PersonID = ?s", $config["platform"]["sql"]["tbl"]["Person"], $externalId);
                if ($personExists) $exists = true;
            }

            // Check in organisations table
            if (!$exists) {
                $orgExists = $db->getOne("SELECT OrganisationID FROM ?n WHERE OrganisationID = ?s", $config["platform"]["sql"]["tbl"]["Organisation"], $externalId);
                if ($orgExists) $exists = true;
            }

            // Check in terms table
            if (!$exists) {
                $termExists = $db->getOne("SELECT TermID FROM ?n WHERE TermID = ?s", $config["platform"]["sql"]["tbl"]["Term"], $externalId);
                if ($termExists) $exists = true;
            }

            // Check in documents table
            if (!$exists) {
                $docExists = $db->getOne("SELECT DocumentID FROM ?n WHERE DocumentWikidataID = ?s", $config["platform"]["sql"]["tbl"]["Document"], $externalId);
                if ($docExists) $exists = true;
            }

            if ($exists) {
                $deleteResult = entitySuggestionDelete($suggestionId, $db);
                if (isset($deleteResult["meta"]["requestStatus"]) && $deleteResult["meta"]["requestStatus"] === 'success') {
                    $cleanedCount++;
                }
            }
        }

        return apiSuccessResponse(["cleanedCount" => $cleanedCount, "status" => "Cleanup completed."]);

    } catch (Exception $e) {
        error_log("Error in entitySuggestionCleanup: " . $e->getMessage());
        return apiErrorDatabaseError($e->getMessage());
    }
}

/**
 * The apiV1() calls below stay as they are: their targets (entitySuggestionGetItemsFromDB, reimportSessions, entitySuggestionDelete) are all still envelope-returning API functions, not extracted plain-data modules, so calling them directly would mean reimplementing api.php's envelope handling here for no gain.
 */
/**
 * Settles the entity suggestion after an entity has been added: re-imports the sessions it was suggested in (when the request asks for it) and deletes it.
 *
 * The suggestion is the one passed as sourceEntitySuggestionID, otherwise the one whose external ID is the added entity's Wikidata ID. Finding none is the normal case for an entity added directly, not an error. Once the entity exists the suggestion is obsolete, so it is deleted whether or not a re-import was requested — unless the re-import failed, in which case it is kept so the sessions can still be found.
 *
 * @param array $api_request The addItem request; reads reimportAffectedSessions and sourceEntitySuggestionID.
 * @param string|null $externalId The added entity's Wikidata ID.
 * @param object $db The platform database connection.
 * @return array [
 *                 'reimportStatus' => string ("success", "error", "not_attempted"),
 *                 'reimportSummary' => string|null,
 *                 'affectedSessions' => array|null (session files scheduled for re-import),
 *                 'affectedSpeeches' => array|null (speech IDs from EntitysuggestionContext),
 *                 'suggestionDeleteStatus' => string ("success", "error", "not_attempted")
 *               ]
 */
function entitySuggestionPostProcess(array $api_request, $externalId, $db) {
    // apiV1() for the calls below, functions.php for optvParseStringId()
    require_once (__DIR__."/../api.php");

    $postProcessingResult = [
        'reimportStatus' => 'not_attempted',
        'reimportSummary' => null,
        'affectedSessions' => null,
        'affectedSpeeches' => null,
        'suggestionDeleteStatus' => 'not_attempted'
    ];

    // The form posts the checkbox state as the string "true"/"false"; a plain (bool) cast would read "false" as true.
    $reimportRequested = filter_var($api_request['reimportAffectedSessions'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $sourceEntitySuggestionID = $api_request['sourceEntitySuggestionID'] ?? null;

    // 1. Resolve the suggestion
    if (empty($sourceEntitySuggestionID) && !empty($externalId)) {
        $lookupResponse = apiV1([
            "action" => "getItemsFromDB",
            "itemType" => "entitySuggestion",
            "id" => $externalId,
            "idType" => "external",
            "limit" => 1
        ], $db);

        if (($lookupResponse["meta"]["requestStatus"] ?? null) === "success" && !empty($lookupResponse["data"]["EntitysuggestionID"])) {
            $sourceEntitySuggestionID = $lookupResponse["data"]["EntitysuggestionID"];
        }
    }

    if (empty($sourceEntitySuggestionID)) {
        return $postProcessingResult;
    }

    $suggestionApiResponse = apiV1([
        "action" => "getItemsFromDB",
        "itemType" => "entitySuggestion",
        "id" => $sourceEntitySuggestionID,
        "idType" => "internal"
    ], $db);

    if (($suggestionApiResponse["meta"]["requestStatus"] ?? null) !== "success" || empty($suggestionApiResponse["data"])) {
        error_log("entitySuggestionPostProcess: Failed to fetch entity suggestion ID: " . $sourceEntitySuggestionID);
        $postProcessingResult['reimportStatus'] = $reimportRequested ? 'error' : 'not_attempted';
        $postProcessingResult['suggestionDeleteStatus'] = 'error';
        return $postProcessingResult;
    }

    $entitySuggestionItem = $suggestionApiResponse["data"];
    $context = (isset($entitySuggestionItem["EntitysuggestionContext"]) && is_array($entitySuggestionItem["EntitysuggestionContext"])) ? $entitySuggestionItem["EntitysuggestionContext"] : [];
    $postProcessingResult['affectedSpeeches'] = array_keys($context);

    // 2. Re-import the sessions the suggestion was found in
    if ($reimportRequested) {
        $filesToReimport = [];
        foreach ($context as $contextKey => $contextValue) {
            $itemInfos = optvParseStringId($contextKey);
            if ($itemInfos && isset($itemInfos["parliament"]) && isset($itemInfos["electoralPeriodNumber"]) && isset($itemInfos["sessionNumber"])) {
                $parliament = trim($itemInfos["parliament"]);
                $electoralPeriod = trim(substr($itemInfos["electoralPeriodNumber"], 1));
                $sessionNum = trim(substr($itemInfos["sessionNumber"], 1));
                $tmpFileName = $electoralPeriod . $sessionNum . "-session.json";
                // Keyed by file name: several speeches of one session need one copy
                $filesToReimport[$parliament][$tmpFileName] = $tmpFileName;
            }
        }
        $filesToReimport = array_map('array_values', $filesToReimport);

        if (!empty($filesToReimport)) {
            $reimportResponse = apiV1([
                "action" => "import",
                "itemType" => "reimport-sessions",
                "files" => $filesToReimport
            ], $db);

            if (($reimportResponse["meta"]["requestStatus"] ?? null) !== "success") {
                error_log("entitySuggestionPostProcess: Re-import failed for suggestion ID: " . $sourceEntitySuggestionID . " - Response: " . json_encode($reimportResponse));
                $postProcessingResult['reimportStatus'] = 'error';
                // Keep the suggestion: its context is the only record of which sessions still need the re-import
                return $postProcessingResult;
            }

            $postProcessingResult['reimportStatus'] = 'success';
            $postProcessingResult['reimportSummary'] = $reimportResponse["meta"]["summary"] ?? null;
            $postProcessingResult['affectedSessions'] = $reimportResponse["data"]["copied"] ?? null;
        } else {
            $postProcessingResult['reimportStatus'] = 'success';
            if (!empty($context)) {
                $postProcessingResult['reimportSummary'] = 'No valid session files derived from context to re-import.';
            }
        }
    }

    // 3. Delete the suggestion
    $deleteSuggestionResponse = apiV1([
        "action" => "deleteItem",
        "itemType" => "entitySuggestion",
        "EntitysuggestionID" => $sourceEntitySuggestionID
    ], $db);

    if (($deleteSuggestionResponse["meta"]["requestStatus"] ?? null) === "success") {
        $postProcessingResult['suggestionDeleteStatus'] = 'success';
    } else {
        error_log("entitySuggestionPostProcess: Failed to delete entity suggestion ID: " . $sourceEntitySuggestionID . " - Response: " . json_encode($deleteSuggestionResponse));
        $postProcessingResult['suggestionDeleteStatus'] = 'error';
    }

    return $postProcessingResult;
}
