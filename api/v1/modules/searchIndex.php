<?php

require_once (__DIR__."/../../../config.php"); 
require_once (__DIR__."/../../../modules/utilities/functions.api.php"); 
require_once (__DIR__."/../../../vendor/autoload.php"); // For Elasticsearch
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/indexing/functions.main.php");

/**
 * @return array
 * Helperfunction to setup the query for indexing and mapping an openSearch server
 */
function getSearchIndexParameterBody() {
    $data = array();

    $data["mappings"] = array("properties" => array(
        "attributes" => array("properties" => array(
            "textContents" => array("properties" => array(
                "textHTML" => array(
                    "type" => "text",
                    "analyzer" => "html_analyzer",
                    "search_analyzer" => "standard",
                    "fielddata" => true,
                    "fields" => array(
                        "keyword" => array(
                            "type" => "keyword",
                            "ignore_above" => 256
                        )
                    )
                )
            ))
        )),
        "relationships" => array("properties" => array(
            "electoralPeriod" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    )
                ))
            )),
            "session" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    )
                ))
            )),
            "agendaItem" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    ),
                    "attributes" => array("properties" => array(
                        "title" => array(
                            "type" => "text",
                            "analyzer" => "agenda_item_analyzer",
                            "search_analyzer" => "standard",
                            "fields" => array(
                                "keyword" => array(
                                    "type" => "keyword",
                                    "ignore_above" => 256
                                )
                            )
                        ),
                        "officialTitle" => array(
                            "type" => "text",
                            "analyzer" => "agenda_item_analyzer",
                            "search_analyzer" => "standard",
                            "fields" => array(
                                "keyword" => array(
                                    "type" => "keyword",
                                    "ignore_above" => 256
                                )
                            )
                        )
                    ))
                ))
            )),
            "people" => array("properties" => array(
                "data" => array(
                    "type" => "nested",
                    "properties" => array(
                        "attributes" => array("properties" => array(
                            "context" => array(
                                "type" => "keyword"
                            )
                        ))
                    ))
            )
            ),
            "organisations" => array("properties" => array(
                "data" => array(
                    "type" => "nested",
                    "properties" => array(
                        "attributes" => array("properties" => array(
                            "context" => array(
                                "type" => "keyword"
                            )
                        ))
                    ))
            )
            )),
        ),
        "annotations" => array("properties" => array(
            "data" => array(
                "type" => "nested",
                "properties" => array(
                    "attributes" => array("properties" => array(
                        "context" => array(
                            "type" => "keyword"
                        )
                    )),
                    "id" => array(
                        "type" => "keyword"
                    )
                )
            )
        ))
    ));

    $data["settings"] = array(
        "index" => array("max_ngram_diff" => 20),
        "number_of_replicas" => 0,
        "number_of_shards" => 2,
        "analysis" => array(
            "analyzer" => array(
                "default" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),
                "html_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["lowercase", "custom_synonyms"]
                ),

                "agenda_item_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),

            ),

            "char_filter" => array(
                "custom_html_strip" => array(
                    "type" => "pattern_replace",
                    "pattern" => "<\w+\s[^>]+></\w+>", 
                    "replacement" => " "
                )
            ),
            "filter" => array(
                "custom_stopwords" => array(
                    "type" => "stop",
                    "ignore_case" => true,
                    "stopwords" => "_german_"
                ),
                "custom_stemmer" => array(
                    "type" => "stemmer",
                    "name" => "light_german"
                ),
                "custom_synonyms" => array(
                    "type" => "synonym_graph",
                    "lenient" => true,
                    "synonyms_path" => "analysis/synonyms.txt" 
                )
            )
        )
    );
    return $data;
}


/**
 * Adds or updates media items in the search index.
 *
 * @param array $api_request Expected keys: "parliament", "items" (array of full media item API responses), "initIndex" (boolean, optional)
 * @return array API response
 */

function searchIndexUpdate($api_request) {
    global $config;

    $parliament = $api_request['parliament'] ?? null;
    $items = $api_request['items'] ?? []; // Array of media items to update/add
    $initIndex = $api_request['initIndex'] ?? false; // If true, tries to create index with mapping

    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    if (!isset($config['parliament'][$parliament])) {
        return createApiErrorInvalidParameter('parliament', "Invalid parliament specified: {$parliament}");
    }
     if (empty($items)) {
        return createApiSuccessResponse(['updated' => 0, 'failed' => 0, 'errors' => []], ['message' => 'No items provided to index.']);
    }

    $indexName = "openparliamenttv_" . ($config['parliament'][$parliament]['OpenSearch']['index'] ?? $parliament);
    $openSearchClient = getApiOpenSearchClient();

    if (!$openSearchClient || (is_array($openSearchClient) && isset($openSearchClient["errors"]))) {
        return createApiErrorResponse(500, 'OPENSEARCH_CONNECTION_ERROR', 'messageErrorOpenSearchConnection', 'messageErrorOpenSearchConnection', ['parliament' => $parliament]);
    }

    if ($initIndex) {
        try {
            if (!$openSearchClient->indices()->exists(['index' => $indexName])) {
                $params = [
                    'index' => $indexName,
                    'body' => getSearchIndexParameterBody() // Mapping and settings
                ];
                $openSearchClient->indices()->create($params);
            }
        } catch (Exception $e) {
            // Fail silently on index creation if it already exists, but log other errors.
            if (strpos($e->getMessage(), 'resource_already_exists_exception') === false) {
                 return createApiErrorResponse(500, 'INDEX_CREATION_FAILURE', 'messageErrorIndexCreation', 'messageErrorIndexCreation', ['indexName' => $indexName, 'error' => $e->getMessage()]);
            }
        }
    }

    $params = ['body' => []];
    $errorsEncountered = [];
    $updatedCount = 0;
    $failedCount = 0;

    for ($i = 0; $i < count($items); $i++) {
        $tmpItem = $items[$i];
        if (isset($tmpItem["data"]["id"])) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id'    => $tmpItem["data"]["id"]
                ]
            ];
            $params['body'][] = $tmpItem["data"];
        } else {
            $failedCount++;
            $errorsEncountered[] = ['type' => 'item_missing_id', 'message' => 'Item at index ' . $i . ' is missing a data.id field.'];
        }
    }

    if (empty($params['body'])) {
         return createApiSuccessResponse(['updated' => 0, 'failed' => $failedCount, 'errors' => $errorsEncountered], ['message' => 'No valid items to index after filtering.']);
    }

    try {
        $responses = $openSearchClient->bulk($params);

        if (isset($responses['errors']) && $responses['errors'] === true) {
            foreach ($responses['items'] as $idx => $responseItem) {
                if (isset($responseItem['index']['error'])) {
                    $failedId = $responseItem['index']['_id'];
                    $errorDetail = $responseItem['index']['error']['type'] . ": " . $responseItem['index']['error']['reason'];
                    $errorsEncountered[] = ['type' => 'indexing_item', 'id' => $failedId, 'message' => $errorDetail];
                    $failedCount++;
                } else {
                    $updatedCount++;
                }
            }
        } else {
            $updatedCount = count($responses['items'] ?? []);
        }

    } catch (Exception $e) {
        return createApiErrorResponse(500, 'BULK_API_EXCEPTION', 'messageErrorBulkOperation', 'messageErrorBulkOperation', ['error' => $e->getMessage()]);
    }
    
    $finalMessage = "Search index update completed. Updated: {$updatedCount}, Failed: {$failedCount}.";
    
    // Detect if this is a full rebuild by checking if initIndex was requested
    // Full rebuilds should not trigger incremental statistics index updates
    $isFullRebuild = $initIndex || (isset($api_request['isFullRebuild']) && $api_request['isFullRebuild']);
    
    if (!$isFullRebuild) {
        // Only trigger incremental statistics index updates for non-full-rebuild updates
        try {
            $statisticsUpdateResult = triggerStatisticsIndexUpdate($parliament, $items);
            if (isset($statisticsUpdateResult['meta']['requestStatus']) && $statisticsUpdateResult['meta']['requestStatus'] === 'success') {
                $finalMessage .= " Statistics indices update triggered.";
            } else {
                $finalMessage .= " (Statistics indices update failed - indices may be out of sync)";
                error_log("Statistics indices update failed after main index update: " . json_encode($statisticsUpdateResult));
            }
        } catch (Exception $e) {
            error_log("Statistics indices update failed for parliament {$parliament}: " . $e->getMessage());
            $finalMessage .= " (Statistics indices update failed - indices may be out of sync)";
        }
    } else {
        // For full rebuilds, statistics indexing will be triggered by completion hook
        $finalMessage .= " (Statistics indices will be updated after main index completion)";
    }
    
    // Invalidate general statistics cache since index has been updated
    require_once(__DIR__ . '/statistics.php');
    invalidateGeneralStatisticsCache($parliament);

    return createApiSuccessResponse(
        ['updated' => $updatedCount, 'failed' => $failedCount, 'errors' => $errorsEncountered],
        ['message' => $finalMessage]
    );
}

/**
 * Deletes items from the search index, or the entire index.
 *
 * @param array $api_request Expected keys: "parliament", "id" (optional, item ID or "*" for all)
 * @return array Status array
 */
function searchIndexDelete($api_request) {
    global $config;

    $parliament = $api_request['parliament'] ?? null;

    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    if (!isset($config['parliament'][$parliament])) {
        return createApiErrorInvalidParameter('parliament', "Invalid parliament specified: {$parliament}");
    }

    $mainIndexName = "openparliamenttv_" . ($config['parliament'][$parliament]['OpenSearch']['index'] ?? $parliament);
    $statisticsIndexName = "optv_statistics_" . strtolower($parliament);
    $openSearchClient = getApiOpenSearchClient();

    if (!$openSearchClient || (is_array($openSearchClient) && isset($openSearchClient["errors"]))) {
        return createApiErrorResponse(500, 'OPENSEARCH_CONNECTION_ERROR', 'messageErrorOpenSearchConnection', 'messageErrorOpenSearchConnection', ['parliament' => $parliament]);
    }

    $deletedIndices = [];
    $errors = [];

    try {
        // Delete main search index
        if ($openSearchClient->indices()->exists(['index' => $mainIndexName])) {
            $response = $openSearchClient->indices()->delete(['index' => $mainIndexName]);
            if (isset($response['acknowledged']) && $response['acknowledged'] === true) {
                $deletedIndices[] = $mainIndexName;
            } else {
                $errors[] = "Failed to delete main index {$mainIndexName}: not acknowledged";
            }
        } else {
            $deletedIndices[] = $mainIndexName . " (already deleted or not exists)";
        }

        // Delete statistics index
        if ($openSearchClient->indices()->exists(['index' => $statisticsIndexName])) {
            $response = $openSearchClient->indices()->delete(['index' => $statisticsIndexName]);
            if (isset($response['acknowledged']) && $response['acknowledged'] === true) {
                $deletedIndices[] = $statisticsIndexName;
            } else {
                $errors[] = "Failed to delete statistics index {$statisticsIndexName}: not acknowledged";
            }
        } else {
            $deletedIndices[] = $statisticsIndexName . " (already deleted or not exists)";
        }

        if (!empty($errors)) {
            return createApiErrorResponse(500, 'DELETE_FAILED_PARTIAL', 'messageErrorIndexDelete', 'messageErrorIndexDeletePartial', ['deletedIndices' => $deletedIndices, 'errors' => $errors]);
        }

        return createApiSuccessResponse(['deleted' => true, 'indices' => $deletedIndices], ['message' => "Indices deleted successfully: " . implode(', ', $deletedIndices)]);

    } catch (Exception $e) {
        if ($e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception || (method_exists($e, 'getCode') && $e->getCode() == 404)) {
            return createApiSuccessResponse(['deleted' => 'already_deleted_or_not_exists'], ['message' => "Indices do not exist (404)."]);
        }
        return createApiErrorResponse(500, 'DELETE_EXCEPTION', 'messageErrorIndexDelete', 'messageErrorIndexDeleteException', ['mainIndex' => $mainIndexName, 'statisticsIndex' => $statisticsIndexName, 'error' => $e->getMessage()]);
    }
}

/**
 * Generates the file path for the search index progress file.
 * This is a helper function used by other functions that need to interact with the progress file.
 * @param string $parliamentCode The parliament code (e.g., "DE").
 * @return string Full path to the progress file.
 */
function getSearchIndexProgressFilePath($parliamentCode) {
    if (empty($parliamentCode)) {
        return __DIR__ . "/../../../data/progress/searchIndex_unknown.json";
    }
    return __DIR__ . "/../../../data/progress/searchIndex_" . strtoupper($parliamentCode) . ".json";
}

/**
 * Retrieves the current status of a search index update process for a given parliament.
 * Reads the progress from the corresponding JSON file.
 *
 * @param array $api_request Expected key: "parliament"
 * @return array API response containing the progress status or an error.
 */
function searchIndexGetStatus($api_request) {
    global $config;

    if (empty($api_request["parliament"])) {
        return createApiErrorMissingParameter("parliament (for searchIndexGetStatus)");
    }
    $parliament = strtoupper(trim($api_request["parliament"]));

    if (!isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidParameter("parliament", "Invalid parliament specified for status check: {$parliament}");
    }

    $progressFilePath = getSearchIndexProgressFilePath($parliament);

    if (!file_exists($progressFilePath)) {
        // If no progress file exists, assume idle or not yet started.
        $defaultStatus = [
            "processName" => "searchIndexFullUpdate",
            "parliament" => $parliament,
            "status" => "idle",
            "statusDetails" => "No active or recent update process found for this parliament."
        ];
        return createApiSuccessResponse($defaultStatus, ["message" => "No progress file found, returning default idle status."]);
    }

    $progressJson = @file_get_contents($progressFilePath);
    if ($progressJson === false) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_READ_ERROR', 'messageErrorProgressFileRead', 'messageErrorProgressFileRead', ['parliament' => $parliament]);
    }

    $progressData = json_decode($progressJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_CORRUPT', 'messageErrorProgressFileCorrupt', 'messageErrorProgressFileCorrupt', ['parliament' => $parliament]);
    }

    return createApiSuccessResponse($progressData);
}

/**
 * Triggers a full rebuild of both main search index and statistics indices.
 * This ensures both indices stay in perfect sync.
 *
 * @param array $api_request Expected key: "parliament"
 * @return array API response indicating success or failure in triggering the process.
 */
function searchIndexTriggerFullUpdate($api_request) {
    global $config;

    if (empty($api_request["parliament"])) {
        return createApiErrorMissingParameter("parliament (for triggerFullIndexUpdate task)");
    }
    $parliament = strtoupper(trim($api_request["parliament"]));

    if (!isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidParameter("parliament", "Invalid parliament specified: {$parliament}");
    }
    if (empty($config["bin"]["php"])) {
        return createApiErrorResponse(500, 'CONFIG_ERROR', 'messageErrorConfig', 'messageErrorConfigPHPNotFound');
    }

    // --- Start: Delete main index before rebuilding ---
    $deleteResult = searchIndexDelete($api_request);

    // If the deletion resulted in an error, stop and return that error.
    if (isset($deleteResult['errors']) || (isset($deleteResult['meta']['requestStatus']) && $deleteResult['meta']['requestStatus'] !== 'success')) {
        return $deleteResult;
    }
    // --- End: Delete main index ---

    // Execute main index rebuild (without statistics trigger)
    $cliScriptPath = realpath(__DIR__ . "/../../../data/cronUpdater.php");
    
    if (!$cliScriptPath) {
        return createApiErrorResponse(500, 'SCRIPT_NOT_FOUND', 'messageErrorScriptNotFound', 'messageErrorScriptNotFoundCronUpdater');
    }

    // First: rebuild main index only
    $mainIndexCommand = $config["bin"]["php"] . " " . escapeshellarg($cliScriptPath) . " --justUpdateSearchIndex --parliament=" . escapeshellarg($parliament) . " --triggerStatisticsAfterCompletion";
    
    try {
        executeAsyncShellCommand($mainIndexCommand);
        
        // Invalidate general statistics cache since full rebuild will change all data
        require_once(__DIR__ . '/statistics.php');
        invalidateGeneralStatisticsCache($parliament);
        
        return createApiSuccessResponse(["message" => "Full rebuild initiated: main index followed by statistics indices for parliament: {$parliament}."]);
    } catch (Exception $e) {
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'messageErrorAsyncExec', 'messageErrorAsyncExec', ['error' => $e->getMessage()]);
    }
}

/**
 * Unified function to trigger statistics indexing
 * Used by both full rebuild and statistics-only operations
 */
function triggerStatisticsIndexing($parliament, $isIncremental = false, $mediaIds = []) {
    global $config;
    
    $phpPath = $config["bin"]["php"] ?? PHP_BINARY;
    $scriptPath = realpath(__DIR__ . '/../../../data/statisticsIndexer.php');
    $logFile = __DIR__ . '/../../../data/statisticsIndexer_' . $parliament . '.log';
    
    if (!$scriptPath) {
        return createApiErrorResponse(500, 'SCRIPT_NOT_FOUND', 'messageErrorScriptNotFound', 'Statistics indexer script not found');
    }
    
    // Build command with consistent optimal settings
    $command = sprintf(
        '%s %s --parliament=%s --batch-size=200',
        escapeshellcmd($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($parliament)
    );

    if ($isIncremental && !empty($mediaIds)) {
        $command .= ' --media-ids=' . escapeshellarg(implode(',', $mediaIds));
    } elseif ($isIncremental) {
        $command .= ' --incremental';
    }
    
    $command .= ' > ' . escapeshellarg($logFile);

    try {
        executeAsyncShellCommand($command);
        return createApiSuccessResponse([
            'message' => 'Statistics indexing started with unified settings',
            'parliament' => $parliament,
            'mode' => $isIncremental ? 'incremental' : 'full_rebuild',
            'logFile' => basename($logFile)
        ]);
    } catch (Exception $e) {
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'messageErrorAsyncExec', 'Failed to start statistics indexing', ['error' => $e->getMessage()]);
    }
}

/**
 * Trigger statistics index update for incremental changes
 * This function is called after main index updates to keep statistics indices in sync
 */
function triggerStatisticsIndexUpdate($parliament, $items) {
    // Extract media IDs from items
    $mediaIds = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            if (isset($item['data']['id'])) {
                $mediaIds[] = $item['data']['id'];
            }
        }
    }
    
    if (empty($mediaIds)) {
        return createApiSuccessResponse(['message' => 'No media items to process for statistics update']);
    }
    
    return triggerStatisticsIndexing($parliament, true, $mediaIds);
}

/**
 * Get statistics indexing status
 */
function searchIndexGetStatisticsStatus($api_request) {
    $parliament = $api_request['parliament'] ?? 'DE';
    $progressFile = __DIR__ . '/../../../data/progress/statisticsIndexer_' . $parliament . '.json';
    
    if (!file_exists($progressFile)) {
        return createApiSuccessResponse([
            'status' => 'idle',
            'statusDetails' => 'Statistics indexing not running',
            'totalDbMediaItems' => 0,
            'processedMediaItems' => 0,
            'words_indexed' => 0,
            'statistics_updated' => 0,
            'performance' => ['avg_docs_per_second' => 0]
        ]);
    }
    
    $progressData = json_decode(file_get_contents($progressFile), true);
    if (!$progressData) {
        return createApiErrorResponse(500, 'PROGRESS_READ_ERROR', 'Could not read progress file', 'Progress file exists but could not be parsed');
    }
    
    return createApiSuccessResponse($progressData);
}

/**
 * Statistics indexing trigger for full rebuild
 * Clean, consistent statistics indexing approach
 */
function searchIndexTriggerStatisticsUpdate($api_request) {
    $parliament = $api_request['parliament'] ?? 'DE';
    
    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    
    // Check if already running
    $lockFile = __DIR__ . '/../../../data/progress/statisticsIndexer_' . $parliament . '.lock';
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData['timestamp'] ?? 0);
        
        if ($lockAge < 3600) { // 1 hour timeout
            return createApiErrorResponse(409, 'ALREADY_RUNNING', 'Statistics indexing is already running', 'Process PID: ' . ($lockData['pid'] ?? 'unknown'));
        } else {
            // Remove stale lock
            unlink($lockFile);
        }
    }
    
    // Use unified statistics indexing with optimal settings
    $result = triggerStatisticsIndexing($parliament, false);
    
    // Invalidate general statistics cache since statistics will be rebuilt
    require_once(__DIR__ . '/statistics.php');
    invalidateGeneralStatisticsCache($parliament);
    
    return $result;
}

/**
 * Trigger asynchronous optimization of indices - removes deleted documents and consolidates segments
 */
function searchIndexOptimize($api_request) {
    global $config;
    
    $parliament = $api_request['parliament'] ?? null;
    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    
    // Check if optimization is already running
    $lockFile = __DIR__ . '/../../../data/progress/indexOptimization_' . $parliament . '.lock';
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData['timestamp'] ?? 0);
        
        if ($lockAge < 600) { // 10 minute timeout
            return createApiErrorResponse(409, 'ALREADY_RUNNING', 'Index optimization is already running', 'Process started at: ' . date('Y-m-d H:i:s', $lockData['timestamp'] ?? 0));
        } else {
            // Remove stale lock
            unlink($lockFile);
        }
    }
    
    // Create progress file for status tracking
    $progressFile = __DIR__ . '/../../../data/progress/indexOptimization_' . $parliament . '.json';
    $progressData = [
        'processName' => 'indexOptimization',
        'parliament' => $parliament,
        'status' => 'running',
        'statusDetails' => 'Starting index optimization...',
        'startTime' => date('c'),
        'errors' => []
    ];
    file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
    
    // Execute optimization script asynchronously
    $scriptPath = realpath(__DIR__ . '/../../../data/indexOptimizer.php');
    if (!$scriptPath) {
        // If script doesn't exist, we'll create it
        $scriptPath = __DIR__ . '/../../../data/indexOptimizer.php';
    }
    
    $phpPath = $config["bin"]["php"] ?? PHP_BINARY;
    $command = sprintf(
        '%s %s --parliament=%s',
        escapeshellcmd($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($parliament)
    );
    
    try {
        executeAsyncShellCommand($command);
        return createApiSuccessResponse(['message' => 'Index optimization started successfully'], ['message' => 'Index optimization is running in the background']);
    } catch (Exception $e) {
        // Clean up progress file on failure
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'Failed to start optimization process', $e->getMessage());
    }
}

/**
 * Get optimization status
 */
function searchIndexGetOptimizationStatus($api_request) {
    $parliament = $api_request['parliament'] ?? 'DE';
    $progressFile = __DIR__ . '/../../../data/progress/indexOptimization_' . $parliament . '.json';
    
    if (!file_exists($progressFile)) {
        return createApiSuccessResponse([
            'status' => 'idle',
            'statusDetails' => 'Index optimization not running',
            'processName' => 'indexOptimization',
            'parliament' => $parliament
        ]);
    }
    
    $progressData = json_decode(file_get_contents($progressFile), true);
    if (!$progressData) {
        return createApiErrorResponse(500, 'PROGRESS_READ_ERROR', 'Could not read optimization progress file', 'Progress file exists but could not be parsed');
    }
    
    return createApiSuccessResponse($progressData);
}

/**
 * Synchronous optimization function - moved for use by async script
 */
function performIndexOptimization($parliament) {
    global $config;
    
    $progressFile = __DIR__ . '/../../../data/progress/indexOptimization_' . $parliament . '.json';
    $lockFile = __DIR__ . '/../../../data/progress/indexOptimization_' . $parliament . '.lock';
    
    // Create lock file
    $lockData = [
        'process' => 'indexOptimization',
        'parliament' => $parliament,
        'timestamp' => time(),
        'pid' => getmypid()
    ];
    file_put_contents($lockFile, json_encode($lockData));
    
    function updateOptimizationProgress($progressFile, $updates) {
        if (file_exists($progressFile)) {
            $current = json_decode(file_get_contents($progressFile), true) ?: [];
            $updated = array_merge($current, $updates);
            file_put_contents($progressFile, json_encode($updated, JSON_PRETTY_PRINT));
        }
    }
    
    try {
        $mainIndexName = "openparliamenttv_" . ($config['parliament'][$parliament]['OpenSearch']['index'] ?? $parliament);
        $statisticsIndexName = "optv_statistics_" . strtolower($parliament);
        $openSearchClient = getApiOpenSearchClient();
        
        if (!$openSearchClient || (is_array($openSearchClient) && isset($openSearchClient["errors"]))) {
            updateOptimizationProgress($progressFile, [
                'status' => 'error',
                'statusDetails' => 'Failed to connect to OpenSearch',
                'endTime' => date('c')
            ]);
            return false;
        }
        
        updateOptimizationProgress($progressFile, [
            'statusDetails' => 'Connected to OpenSearch, starting optimization...'
        ]);
        
        $results = [];
        
        // Optimize main index (usually clean, but good practice)
        if ($openSearchClient->indices()->exists(['index' => $mainIndexName])) {
            updateOptimizationProgress($progressFile, [
                'statusDetails' => 'Optimizing main index...'
            ]);
            
            $startTime = microtime(true);
            $openSearchClient->indices()->forcemerge([
                'index' => $mainIndexName,
                'max_num_segments' => 1
            ]);
            $results['main_index'] = [
                'optimized' => true,
                'time_seconds' => round(microtime(true) - $startTime, 2)
            ];
        }
        
        // Optimize statistics index (this is where the real benefit is)
        if ($openSearchClient->indices()->exists(['index' => $statisticsIndexName])) {
            updateOptimizationProgress($progressFile, [
                'statusDetails' => 'Optimizing statistics index (phase 1: expunge deletes)...'
            ]);
            
            $startTime = microtime(true);
            
            // Get stats before optimization
            $statsBefore = $openSearchClient->indices()->stats(['index' => $statisticsIndexName]);
            $deletedBefore = $statsBefore['indices'][$statisticsIndexName]['total']['docs']['deleted'] ?? 0;
            $sizeBefore = $statsBefore['indices'][$statisticsIndexName]['total']['store']['size_in_bytes'] ?? 0;
            
            // First pass: Expunge deletes only
            $openSearchClient->indices()->forcemerge([
                'index' => $statisticsIndexName,
                'only_expunge_deletes' => true
            ]);
            
            sleep(3);
            
            updateOptimizationProgress($progressFile, [
                'statusDetails' => 'Optimizing statistics index (phase 2: segment consolidation)...'
            ]);
            
            // Second pass: Force merge to fewer segments
            $openSearchClient->indices()->forcemerge([
                'index' => $statisticsIndexName,
                'max_num_segments' => 1
            ]);
            
            sleep(5);
            
            // Get stats after optimization
            $statsAfter = $openSearchClient->indices()->stats(['index' => $statisticsIndexName]);
            $deletedAfter = $statsAfter['indices'][$statisticsIndexName]['total']['docs']['deleted'] ?? 0;
            $sizeAfter = $statsAfter['indices'][$statisticsIndexName]['total']['store']['size_in_bytes'] ?? 0;
            
            $results['statistics_index'] = [
                'optimized' => true,
                'time_seconds' => round(microtime(true) - $startTime, 2),
                'deleted_docs_removed' => $deletedBefore - $deletedAfter,
                'space_reclaimed_mb' => round(($sizeBefore - $sizeAfter) / 1024 / 1024, 1),
                'strategy' => 'two_pass_expunge_then_merge'
            ];
        }
        
        // Update final progress
        updateOptimizationProgress($progressFile, [
            'status' => 'completed_successfully',
            'statusDetails' => 'Index optimization completed successfully',
            'endTime' => date('c'),
            'results' => $results
        ]);
        
        return true;
        
    } catch (Exception $e) {
        updateOptimizationProgress($progressFile, [
            'status' => 'error',
            'statusDetails' => 'Optimization failed: ' . $e->getMessage(),
            'endTime' => date('c'),
            'errors' => [['message' => $e->getMessage()]]
        ]);
        return false;
    } finally {
        // Always clean up lock file
        if (isset($lockFile) && file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}



?> 