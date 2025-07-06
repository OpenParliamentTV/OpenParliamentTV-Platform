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
 * @return array
 * Helperfunction to setup the query for indexing and mapping a words index
 */
function getWordsIndexParameterBody() {
    $data = array();

    $data["mappings"] = array("properties" => array(
        "word" => array(
            "type" => "keyword",
            "ignore_above" => 256
        ),
        "frequency" => array(
            "type" => "long"
        ),
        "doc_count" => array(
            "type" => "long"
        ),
        "type" => array(
            "type" => "keyword"
        )
    ));

    $data["settings"] = array(
        "number_of_replicas" => 0,
        "number_of_shards" => 1,
        "analysis" => array(
            "analyzer" => array(
                "default" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase"]
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
    
    // Build words index after main index update
    try {
        $wordsResult = buildWordsIndex($parliament);
        if (isset($wordsResult['data'])) {
            $finalMessage .= " Words index: " . $wordsResult['data']['message'];
        }
    } catch (Exception $e) {
        // Log words index build failure but don't fail the main operation
        error_log("Words index build failed for parliament {$parliament}: " . $e->getMessage());
        $finalMessage .= " (Words index build failed)";
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

/**
 * Build words index from main index
 * 
 * This function extracts unique words from the main index and stores them
 * in a lightweight words index for efficient autocomplete queries.
 * 
 * @param string $parliament The parliament identifier
 * @return array API response
 */
function buildWordsIndex($parliament) {
    global $config;
    
    try {
        $ESClient = getApiOpenSearchClient();
        if (is_array($ESClient) && isset($ESClient["errors"])) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorOpenSearchTitle",
                "messageErrorOpenSearchClientInitFailed"
            );
        }

        $wordsIndexName = "openparliamenttv_words_" . $parliament;
        
        // Check if words index exists, delete if it does
        try {
            $ESClient->indices()->delete(['index' => $wordsIndexName]);
        } catch (Exception $e) {
            // Index doesn't exist, which is fine
        }
        
        // Create words index
        $indexParams = getWordsIndexParameterBody();
        $ESClient->indices()->create([
            'index' => $wordsIndexName,
            'body' => $indexParams
        ]);
        
        // Get documents and extract clean words from text content
        $query = [
            "size" => 1000, // Process in batches
            "query" => [
                "exists" => [
                    "field" => "attributes.textContents.textHTML"
                ]
            ],
            "_source" => ["attributes.textContents.textHTML"]
        ];
        
        $indexWords = [];
        $processedDocs = 0;
        $totalDocs = 0;
        
        // First, get total count
        $countQuery = [
            "size" => 0,
            "query" => [
                "exists" => [
                    "field" => "attributes.textContents.textHTML"
                ]
            ]
        ];
        
        $countResults = $ESClient->search([
            "index" => "openparliamenttv_" . $parliament,
            "body" => $countQuery
        ]);
        
        $totalDocs = $countResults["hits"]["total"]["value"] ?? 0;
        
        // Process documents in batches
        while ($processedDocs < $totalDocs) {
            $query["from"] = $processedDocs;
            $results = $ESClient->search([
                "index" => "openparliamenttv_" . $parliament,
                "body" => $query
            ]);
            
            if (!isset($results["hits"]["hits"])) {
                break;
            }
            
            foreach ($results["hits"]["hits"] as $hit) {
                if (isset($hit["_source"]["attributes"]["textContents"])) {
                    foreach ($hit["_source"]["attributes"]["textContents"] as $textContent) {
                        if (isset($textContent["textHTML"])) {
                            // Strip HTML and extract words
                            $cleanText = strip_tags($textContent["textHTML"]);
                            $words = preg_split('/\\W+/u', mb_strtolower($cleanText, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
                            
                            foreach ($words as $word) {
                                // Skip stopwords and short words
                                if (strlen($word) < 3 || in_array($word, $config["excludedStopwords"])) {
                                    continue;
                                }
                                
                                // Clean the word
                                $cleanWord = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
                                if (strlen($cleanWord) < 3) {
                                    continue;
                                }
                                
                                // Count frequency
                                if (!isset($indexWords[$cleanWord])) {
                                    $indexWords[$cleanWord] = [
                                        "word" => $cleanWord,
                                        "frequency" => 0,
                                        "doc_count" => 0,
                                        "type" => "word"
                                    ];
                                }
                                $indexWords[$cleanWord]["frequency"]++;
                            }
                        }
                    }
                }
            }
            
            $processedDocs += count($results["hits"]["hits"]);
            
            // Break if no more results
            if (count($results["hits"]["hits"]) === 0) {
                break;
            }
        }
        
        // Convert to array and sort by frequency
        $indexWords = array_values($indexWords);
        usort($indexWords, function($a, $b) {
            return $b["frequency"] - $a["frequency"];
        });
        
        // Batch index the words
        $batchSize = 1000;
        for ($i = 0; $i < count($indexWords); $i += $batchSize) {
            $batch = array_slice($indexWords, $i, $batchSize);
            $bulkData = [];
            
            foreach ($batch as $word) {
                $bulkData[] = [
                    "index" => [
                        "_index" => $wordsIndexName,
                        "_id" => $word["word"]
                    ]
                ];
                $bulkData[] = $word;
            }
            
            $ESClient->bulk(['body' => $bulkData]);
        }
        
        // Refresh the index
        $ESClient->indices()->refresh(['index' => $wordsIndexName]);
        
        return createApiSuccessResponse([
            "message" => "Words index built successfully",
            "index_name" => $wordsIndexName,
            "word_count" => count($indexWords)
        ]);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorAutocompleteIndexTitle",
            "messageErrorAutocompleteIndexBuildFailed",
            ["details" => $e->getMessage()]
        );
    }
}

?> 