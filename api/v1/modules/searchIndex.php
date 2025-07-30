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
    // Full rebuilds should not trigger incremental enhanced index updates
    $isFullRebuild = $initIndex || (isset($api_request['isFullRebuild']) && $api_request['isFullRebuild']);
    
    if (!$isFullRebuild) {
        // Only trigger incremental enhanced index updates for non-full-rebuild updates
        try {
            $enhancedUpdateResult = triggerEnhancedIndexUpdate($parliament, $items);
            if ($enhancedUpdateResult['success']) {
                $finalMessage .= " Enhanced indices update triggered.";
            } else {
                $finalMessage .= " (Enhanced indices update failed - indices may be out of sync)";
                error_log("Enhanced indices update failed after main index update: " . json_encode($enhancedUpdateResult));
            }
        } catch (Exception $e) {
            error_log("Enhanced indices update failed for parliament {$parliament}: " . $e->getMessage());
            $finalMessage .= " (Enhanced indices update failed - indices may be out of sync)";
        }
    } else {
        // For full rebuilds, enhanced indexing will be triggered by completion hook
        $finalMessage .= " (Enhanced indices will be updated after main index completion)";
    }
    
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

    $indexName = "openparliamenttv_" . ($config['parliament'][$parliament]['OpenSearch']['index'] ?? $parliament);
    $openSearchClient = getApiOpenSearchClient();

    if (!$openSearchClient || (is_array($openSearchClient) && isset($openSearchClient["errors"]))) {
        return createApiErrorResponse(500, 'OPENSEARCH_CONNECTION_ERROR', 'messageErrorOpenSearchConnection', 'messageErrorOpenSearchConnection', ['parliament' => $parliament]);
    }

    try {
        if ($openSearchClient->indices()->exists(['index' => $indexName])) {
            $response = $openSearchClient->indices()->delete(['index' => $indexName]);
            if (isset($response['acknowledged']) && $response['acknowledged'] === true) {
                return createApiSuccessResponse(['deleted' => true], ['message' => "Search index {$indexName} deleted successfully."]);
            } else {
                 return createApiErrorResponse(500, 'DELETE_FAILED_NOT_ACKNOWLEDGED', 'messageErrorIndexDelete', 'messageErrorIndexDeleteNotAcknowledged', ['indexName' => $indexName]);
            }
        } else {
            return createApiSuccessResponse(['deleted' => 'already_deleted_or_not_exists'], ['message' => "Search index {$indexName} does not exist. Nothing to delete."]);
        }
    } catch (Exception $e) {
        if ($e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception || (method_exists($e, 'getCode') && $e->getCode() == 404)) {
            return createApiSuccessResponse(['deleted' => 'already_deleted_or_not_exists'], ['message' => "Search index {$indexName} does not exist (404)."]);
        }
        return createApiErrorResponse(500, 'DELETE_EXCEPTION', 'messageErrorIndexDelete', 'messageErrorIndexDeleteException', ['indexName' => $indexName, 'error' => $e->getMessage()]);
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
 * Triggers an asynchronous background process to perform a full search index update.
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

    // Execute main index rebuild asynchronously
    $cliScriptPath = realpath(__DIR__ . "/../../../data/cronUpdater.php");
    
    if (!$cliScriptPath) {
        return createApiErrorResponse(500, 'SCRIPT_NOT_FOUND', 'messageErrorScriptNotFound', 'messageErrorScriptNotFoundCronUpdater');
    }

    // Command to execute: rebuild main index with enhanced index trigger flag
    $mainIndexCommand = $config["bin"]["php"] . " " . escapeshellarg($cliScriptPath) . " --justUpdateSearchIndex --parliament=" . escapeshellarg($parliament) . " --triggerEnhancedAfterCompletion";
    
    try {
        executeAsyncShellCommand($mainIndexCommand);
        return createApiSuccessResponse(["message" => "Full search index update process initiated for parliament: {$parliament}. Enhanced indices will be triggered automatically after main index completion."]);
    } catch (Exception $e) {
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'messageErrorAsyncExec', 'messageErrorAsyncExec', ['error' => $e->getMessage()]);
    }
}

/**
 * Build enhanced indices after main index update
 * 
 * This function creates enhanced indices for improved autocomplete and statistics
 * using the new enhanced indexing system.
 * 
 * @param string $parliament The parliament identifier
 * @return array API response
 */
function buildEnhancedIndices($parliament) {
    // This function is now deprecated as enhanced indices are handled
    // by the chained command approach in searchIndexTriggerFullUpdate
    return createApiSuccessResponse([
        "message" => "Enhanced indices are now automatically triggered by full index rebuild"
    ]);
}

/**
 * Trigger enhanced index update for incremental changes
 * This function is called after main index updates to keep enhanced indices in sync
 */
function triggerEnhancedIndexUpdate($parliament, $items) {
    try {
        // Use incremental update for better performance
        $result = processEnhancedIndexIncremental($parliament, $items);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => "Enhanced indices updated incrementally ({$result['processed']} speeches processed)",
                'type' => 'incremental'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update enhanced indices incrementally',
                'errors' => $result['errors'] ?? []
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exception triggering enhanced indices update: ' . $e->getMessage(),
            'errors' => [$e->getMessage()]
        ];
    }
}

/**
 * Process enhanced indices for specific items incrementally
 * This is much faster than full rebuilds for regular updates
 */
function processEnhancedIndexIncremental($parliament, $items) {
    try {
        require_once(__DIR__ . '/../../../modules/indexing/functions.main.php');
        
        $parliamentCode = strtolower($parliament);
        $processed = 0;
        $errors = [];
        
        // Ensure enhanced indices exist
        require_once(__DIR__ . '/../../../modules/indexing/functions.enhanced.php');
        require_once(__DIR__ . '/../../../modules/indexing/functions.statistics.php');
        $createResult = createStatisticsIndexing($parliamentCode, false);
        if (!$createResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to ensure enhanced indices exist',
                'errors' => [$createResult['error']]
            ];
        }
        
        // Process each item incrementally
        foreach ($items as $item) {
            if (!isset($item['data']['id'])) {
                continue;
            }
            
            try {
                // Process this specific speech for enhanced indexing
                $speechId = $item['data']['id'];
                $speechData = $item['data'];
                
                // Update statistics index only (word events eliminated)
                $statisticsResult = processStatisticsForSpeechOptimized($speechData, $parliamentCode);
                if (!$statisticsResult['success']) {
                    $errors[] = "Statistics update failed for {$speechId}: " . $statisticsResult['error'];
                }
                
                if ($statisticsResult['success']) {
                    $processed++;
                }
                
            } catch (Exception $e) {
                $errors[] = "Processing failed for {$speechId}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => count($errors) === 0,
            'processed' => $processed,
            'errors' => $errors,
            'message' => "Processed {$processed} speeches for enhanced indices"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exception in incremental processing: ' . $e->getMessage(),
            'errors' => [$e->getMessage()]
        ];
    }
}

/**
 * Enhanced indexing trigger for full rebuild (includes auto-setup)
 */
function searchIndexTriggerEnhancedUpdate($api_request) {
    $parliament = $api_request['parliament'] ?? 'DE';
    $batchSize = $api_request['batchSize'] ?? 100;
    $startFrom = $api_request['startFrom'] ?? 0;
    
    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    
    // Check if already running
    $lockFile = __DIR__ . '/../../../data/progress/enhancedIndexer_' . $parliament . '.lock';
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData['timestamp'] ?? 0);
        
        if ($lockAge < 3600) { // 1 hour timeout
            return createApiErrorResponse(409, 'ALREADY_RUNNING', 'Enhanced indexing is already running', 'Process PID: ' . ($lockData['pid'] ?? 'unknown'));
        } else {
            // Remove stale lock
            unlink($lockFile);
        }
    }
    
    // Start the batch processor in the background (it will handle clean rebuild automatically)
    global $config;
    $phpPath = $config["bin"]["php"] ?? PHP_BINARY;
    $scriptPath = __DIR__ . '/../../../data/enhancedIndexer.php';
    $logFile = __DIR__ . '/../../../data/enhancedIndexer_' . $parliament . '.log';
    
    $command = sprintf(
        '%s %s --parliament=%s --batch-size=%d --start-from=%d > %s 2>&1 &',
        escapeshellcmd($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($parliament),
        (int)$batchSize,
        (int)$startFrom,
        escapeshellarg($logFile)
    );
    
    exec($command, $output, $returnCode);
    
    return createApiSuccessResponse([
        'message' => 'Enhanced indexing rebuild started (clean rebuild with auto-setup)',
        'parliament' => $parliament,
        'batchSize' => $batchSize,
        'logFile' => basename($logFile)
    ]);
}


/**
 * Get enhanced indexing progress file path
 */
function getEnhancedIndexProgressFilePath($parliamentCode) {
    $progressDir = __DIR__ . "/../../../data/progress/";
    $progressFileName = "enhancedIndexer_" . $parliamentCode . ".json";
    return $progressDir . $progressFileName;
}

/**
 * Enhanced indexing status
 */
function searchIndexGetEnhancedStatus($api_request) {
    $parliament = $api_request['parliament'] ?? 'DE';
    
    if (empty($parliament)) {
        return createApiErrorMissingParameter('parliament');
    }
    
    $progressFile = getEnhancedIndexProgressFilePath($parliament);
    $lockFile = __DIR__ . '/../../../data/progress/enhancedIndexer_' . $parliament . '.lock';
    
    // Check if process is running
    $isRunning = false;
    $pid = null;
    
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData['timestamp'] ?? 0);
        $pid = $lockData['pid'] ?? null;
        
        if ($lockAge < 3600 && $pid) {
            // Check if process is actually running (simplified check)
            $isRunning = file_exists("/proc/$pid") || (PHP_OS_FAMILY === 'Windows' && $pid);
        } else {
            // Remove stale lock
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }
    
    // Default progress data
    $progressData = [
        'status' => $isRunning ? 'running' : 'idle',
        'statusDetails' => $isRunning ? 'Enhanced indexing in progress' : 'Enhanced indexing idle',
        'parliament' => $parliament,
        'totalDbMediaItems' => 0,
        'processedMediaItems' => 0,
        'successful_documents' => 0,
        'failed_documents' => 0,
        'current_batch' => 0,
        'total_batches' => 0,
        'words_indexed' => 0,
        'statistics_updated' => 0,
        'is_running' => $isRunning,
        'pid' => $pid,
        'performance' => [
            'avg_docs_per_second' => 0,
            'avg_words_per_doc' => 0
        ]
    ];
    
    // Read actual progress data if available
    if (file_exists($progressFile)) {
        $fileData = json_decode(file_get_contents($progressFile), true);
        if ($fileData) {
            $progressData = array_merge($progressData, $fileData);
            
            // Map enhanced indexing fields to standard fields for compatibility
            $progressData['totalDbMediaItems'] = $fileData['total_documents'] ?? 0;
            $progressData['processedMediaItems'] = $fileData['processed_documents'] ?? 0;
            
            // Set status details based on enhanced indexing status
            switch ($fileData['status'] ?? 'idle') {
                case 'starting':
                    $progressData['statusDetails'] = 'Starting enhanced indexing...';
                    break;
                case 'initializing':
                case 'setting_up_indices':
                    $progressData['statusDetails'] = 'Setting up enhanced indices (auto-setup)...';
                    break;
                case 'counting_documents':
                    $progressData['statusDetails'] = 'Counting documents...';
                    break;
                case 'processing':
                case 'processing_batch':
                    $batch = $fileData['current_batch'] ?? 0;
                    $totalBatches = $fileData['total_batches'] ?? 0;
                    $currentDoc = $fileData['current_document_id'] ?? '';
                    $progressData['statusDetails'] = "Processing batch $batch/$totalBatches";
                    if ($currentDoc && $currentDoc !== "batch_$batch") {
                        $progressData['statusDetails'] .= " (Document: $currentDoc)";
                    }
                    break;
                case 'completed':
                    $progressData['statusDetails'] = 'Enhanced indexing completed';
                    $progressData['status'] = 'completed';
                    break;
                case 'error':
                    $progressData['statusDetails'] = 'Enhanced indexing failed';
                    $progressData['status'] = 'error';
                    break;
                default:
                    $progressData['statusDetails'] = 'Enhanced indexing idle';
            }
        }
    }
    
    return createApiSuccessResponse($progressData);
}

?> 