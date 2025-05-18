<?php

require_once (__DIR__."/../../../config.php"); 
require_once (__DIR__."/../../../modules/utilities/functions.api.php"); 
require_once (__DIR__."/../../../vendor/autoload.php"); // For Elasticsearch
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

/**
 * Prepares and returns the Elasticsearch client.
 *
 * @return \Elasticsearch\Client|array Error response array
 */
function getESClient() {
    global $config;
    $ESClientBuilder = Elasticsearch\ClientBuilder::create();

    if (!empty($config["ES"]["hosts"])) {
        $ESClientBuilder->setHosts($config["ES"]["hosts"]);
    }
    if (!empty($config["ES"]["BasicAuthentication"]["user"]) && isset($config["ES"]["BasicAuthentication"]["passwd"])) {
        $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"], $config["ES"]["BasicAuthentication"]["passwd"]);
    }
    if (!empty($config["ES"]["SSL"]["pem"])) {
        $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
    }
    
    try {
        return $ESClientBuilder->build();
    } catch (Exception $e) {
        // Log error
        error_log("Elasticsearch ClientBuilder failed: " . $e->getMessage());
        return createApiErrorResponse(500, 'ES_CLIENT_ERROR', 'messageErrorESClient', 'Elasticsearch client initialization failed: ' . $e->getMessage());
    }
}

/**
 * @return array
 * Helperfunction to setup the query for indexing and mapping an openSearch server
 */
function getSearchIndexParameterBody() {
    // This function is taken directly from data/updateSearchIndex.php
    // Ensure any necessary $config variables used here are available globally or passed.
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
                    )
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
 * @param array $api_request Expected keys: "parliament", "items" (array of media items), "initIndex" (boolean, optional)
 *                             Alternatively, "mediaIDs" (comma-separated string or array)
 * @return array API response
 */
function searchIndexUpdate($api_request) {
    global $config;
    // api.php is needed for apiV1 call; media.php is then included by api.php if itemType=media
    require_once (__DIR__."/../api.php");      

    set_time_limit(0);
    ini_set('memory_limit', '500M'); 
    date_default_timezone_set('CET'); 

    if (empty($api_request["parliament"])) {
        return createApiErrorMissingParameter("parliament");
    }
    $parliament = $api_request["parliament"];
    if (!isset($config["parliament"][$parliament]["ES"]["index"])) {
         return createApiErrorInvalidParameter("parliament", "Specified parliament has no ES index configured.");
    }
    if (!isset($config["parliament"][$parliament]["sql"])){
        return createApiErrorInvalidParameter("parliament", "Specified parliament has no SQL configuration.");
    }

    $items = [];
    $db = null;
    $dbp = null;

    if (isset($api_request["items"]) && is_array($api_request["items"])) {
        $items = $api_request["items"];
    } elseif (!empty($api_request["mediaIDs"])) {
        if (!isset($config["parliament"][$parliament]["sql"])){
            return createApiErrorInvalidParameter("parliament", "Specified parliament has no SQL configuration for fetching by mediaID.");
        }
        
        // Get platform DB
        $db = getApiDatabaseConnection('platform');
        if (!($db instanceof SafeMySQL)) {
            // $db is an error array, return it directly
            return $db; 
        }

        // Get parliament DB
        $dbp = getApiDatabaseConnection('parliament', $parliament);
        if (!($dbp instanceof SafeMySQL)) {
            // $dbp is an error array, return it directly
            return $dbp;
        }
        
        $mediaIdList = [];
        $rawMediaIDs = is_array($api_request["mediaIDs"]) ? $api_request["mediaIDs"] : explode(",", $api_request["mediaIDs"]);
        foreach ($rawMediaIDs as $tmpID) {
            if (preg_match("/(".$parliament.")\-\d+/i", trim($tmpID))) {
                $mediaIdList[] = trim($tmpID);
            }
        }

        if (empty($mediaIdList)) {
            return createApiSuccessResponse(["updated" => 0, "message" => "No valid media IDs provided to update."]);
        }

        foreach ($mediaIdList as $mediaID) {
            $mediaData = apiV1(["action" => "getItem", "itemType" => "media", "id" => $mediaID], $db, $dbp);
            if (isset($mediaData["data"])) {
                $items[] = $mediaData; 
            } else {
                error_log("Failed to fetch media item $mediaID for indexing: " . json_encode($mediaData));
            }
        }
    } else {
        return createApiErrorMissingParameter("items or mediaIDs");
    }

    if (empty($items)) {
        return createApiSuccessResponse(["updated" => 0, "message" => "No items to update after processing inputs."]);
    }

    $ESClient = getESClient();
    if (!($ESClient instanceof \Elasticsearch\Client)) {
        // It's an error array returned by getESClient due to a build failure
        return $ESClient;
    }

    $initIndex = !empty($api_request["initIndex"]);

    if ($initIndex) {
        $indexName = "openparliamenttv_" . strtolower($config["parliament"][$parliament]["ES"]["index"]);
        $indexParams = [
            "index" => $indexName,
            "body" => getSearchIndexParameterBody()
        ];
        try {
            if (!$ESClient->indices()->exists(['index' => $indexName])) {
                $ESClient->indices()->create($indexParams);
            }
        } catch (Exception $e) {
            error_log("ES Index Creation Error for $indexName: " . $e->getMessage());
        }
    }

    $updatedCount = 0;
    $errors = [];
    
    foreach ($items as $item) {
        if (!isset($item["data"]["id"]) || !isset($item["data"])) {
            $errors[] = ["message" => "Item missing data.id or data.", "item_snippet" => substr(json_encode($item), 0, 100)];
            continue;
        }
        
        $docParams = [
            "index" => "openparliamenttv_" . strtolower($config["parliament"][$parliament]["ES"]["index"]),
            "id" => $item["data"]["id"],
            "body" => $item["data"]
        ];

        try {
            $ESClient->index($docParams);
            $updatedCount++;
        } catch (Exception $e) {
            error_log("ES Indexing Error for item ID ".$item["data"]["id"].": " . $e->getMessage());
            $errors[] = ["id" => $item["data"]["id"], "error" => $e->getMessage()];
        }
    }
    
    if (!empty($errors)) {
        return createApiErrorResponse(500, 'ES_INDEXING_PARTIAL_FAIL', 'Some items failed to index', 'Partial failure during indexing.', [], null, ['updated' => $updatedCount, 'failures' => count($errors), 'failure_details' => $errors]);
    }

    return createApiSuccessResponse(["updated" => $updatedCount, "message" => "Search index updated successfully."]);
}

/**
 * Triggers an asynchronous full update of the search index for a given parliament by running cronUpdater.php.
 *
 * @param array $api_request Expected keys: "parliament"
 * @return array API response
 */
function searchIndexTriggerFullUpdate($api_request) {
    global $config;
    // functions.php (for executeAsyncShellCommand) should be available globally if included by api.php or cronUpdater.php
    // However, to be safe, ensure it's loaded if this module might be called in a context where it isn't.
    // For now, we rely on api.php having included ../../modules/utilities/functions.php

    if (empty($api_request["parliament"])) {
        return createApiErrorMissingParameter("parliament (for triggerFullIndexUpdate task)");
    }
    $parliament = $api_request["parliament"];

    if (!isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidParameter("parliament", "Invalid parliament specified for full index update.");
    }
    if (empty($config["bin"]["php"])) {
        return createApiErrorResponse(500, 'CONFIG_ERROR', 'PHP binary path not configured.', 'PHP binary path (config[bin][php]) is not set.');
    }

    $cronUpdaterPath = realpath(__DIR__ . "/../../../data/cronUpdater.php");
    if (!$cronUpdaterPath) {
        return createApiErrorResponse(500, 'FILE_NOT_FOUND', 'cronUpdater.php not found.', 'The cronUpdater.php script could not be located.');
    }

    $command = $config["bin"]["php"] . " " . $cronUpdaterPath . " --parliament " . escapeshellarg($parliament) . " --justUpdateSearchIndex true";
    
    try {
        executeAsyncShellCommand($command); // Assumes executeAsyncShellCommand is globally available
        return createApiSuccessResponse(["message" => "Full search index update process initiated for parliament: $parliament."]);
    } catch (Exception $e) {
        error_log("Failed to execute async shell command for cronUpdater from searchIndexTriggerFullUpdate: " . $e->getMessage());
        return createApiErrorResponse(500, 'ASYNC_EXEC_FAIL', 'Failed to start update process', $e->getMessage());
    }
}

/**
 * Deletes the search index of a parliament and optionally recreates the mapping.
 *
 * @param array $api_request Expected keys: "parliament", "init" (boolean, optional, default false)
 * @return array API response
 */
function searchIndexDelete($api_request) {
    global $config;

    set_time_limit(0);
    ini_set('memory_limit', '500M');
    date_default_timezone_set('CET');

    if (empty($api_request["parliament"])) {
        return createApiErrorMissingParameter("parliament");
    }
    $parliament = $api_request["parliament"];
     if (!isset($config["parliament"][$parliament]["ES"]["index"])) {
         return createApiErrorInvalidParameter("parliament", "Specified parliament has no ES index configured.");
    }

    $ESClient = getESClient();
    if (!($ESClient instanceof \Elasticsearch\Client)) {
        // It's an error array returned by getESClient due to a build failure
        return $ESClient;
    }

    $indexName = "openparliamenttv_" . strtolower($config["parliament"][$parliament]["ES"]["index"]);

    try {
        if ($ESClient->indices()->exists(['index' => $indexName])) {
            $ESClient->indices()->delete(["index" => $indexName]);
        } else {
            // If index doesn't exist, it's a success in terms of the desired state (it's gone).
            return createApiSuccessResponse(["message" => "Index $indexName does not exist. No action taken."]);
        }
    } catch (Exception $e) {
        error_log("ES Index Deletion Error for $indexName: " . $e->getMessage());
        return createApiErrorResponse(500, 'ES_INDEX_DELETE_ERROR', 'ES Index Deletion Error', $e->getMessage());
    }

    $init = !empty($api_request["init"]); 

    if ($init) {
        $indexParams = [
            "index" => $indexName,
            "body" => getSearchIndexParameterBody()
        ];
        try {
            $ESClient->indices()->create($indexParams);
        } catch (Exception $e) {
            error_log("ES Index Re-Creation Error for $indexName: " . $e->getMessage());
            return createApiErrorResponse(500, 'ES_INDEX_RECREATE_ERROR', 'ES Index Re-Creation Error after delete', $e->getMessage());
        }
    }

    return createApiSuccessResponse(["message" => "Search index $indexName deleted" . ($init ? " and re-initialized." : ".")]);
}

?> 