<?php

require_once (__DIR__."/../../../config.php"); 
require_once (__DIR__."/../../../modules/utilities/functions.api.php"); 
require_once (__DIR__."/../../../vendor/autoload.php"); // For Elasticsearch
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

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
                        ),
                        "autocomplete" => array(
                            "analyzer" => "autocomplete_html_analyzer",
                            "type" => "text"
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
                                ),
                                "autocomplete" => array(
                                    "analyzer" => "agenda_item_autocomplete_analyzer",
                                    "type" => "text"
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
                                ),
                                "autocomplete" => array(
                                    "analyzer" => "agenda_item_autocomplete_analyzer",
                                    "type" => "text"
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
                "autocomplete_html_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["custom_stopwords", "lowercase", "custom_synonyms"]
                ),
                "agenda_item_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),
                "agenda_item_autocomplete_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "edge_ngram",
                    "filter" => ["lowercase", "custom_synonyms"]
                )
            ),
            "tokenizer" => array(
                "edge_ngram" => array(
                    "type" => "edge_ngram",
                    "min_gram" => 2,
                    "max_gram" => 20,
                    "token_chars" => ["letter", "digit"]
                )
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

    $indexName = "openparliamenttv_" . ($config['parliament'][$parliament]['ES']['index'] ?? $parliament);
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

    $indexName = "openparliamenttv_" . ($config['parliament'][$parliament]['ES']['index'] ?? $parliament);
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

    // --- Start: Delete index before rebuilding ---
    $deleteResult = searchIndexDelete($api_request);

    // If the deletion resulted in an error, stop and return that error.
    if (isset($deleteResult['errors']) || (isset($deleteResult['meta']['requestStatus']) && $deleteResult['meta']['requestStatus'] !== 'success')) {
        return $deleteResult;
    }
    // --- End: Delete index ---

    // This script now triggers the original, reliable cronUpdater.php in search index mode.
    $cliScriptPath = realpath(__DIR__ . "/../../../data/cronUpdater.php");
    if (!$cliScriptPath) {
        return createApiErrorResponse(500, 'SCRIPT_NOT_FOUND', 'messageErrorScriptNotFound', 'messageErrorScriptNotFoundCronUpdater');
    }

    // Command to execute the background script for a full index rebuild
    $command = $config["bin"]["php"] . " " . escapeshellarg($cliScriptPath) . " --justUpdateSearchIndex --parliament=" . escapeshellarg($parliament);
    
    try {
        executeAsyncShellCommand($command);
        return createApiSuccessResponse(["message" => "Full search index update process initiated for parliament: {$parliament}."]);
    } catch (Exception $e) {
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'messageErrorAsyncExec', 'messageErrorAsyncExec', ['error' => $e->getMessage()]);
    }
}

?> 