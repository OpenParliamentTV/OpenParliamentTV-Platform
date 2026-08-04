<?php
/**
 * Write path for the main search index (openparliamenttv_<parliament>).
 *
 * Primitives only: each function talks to OpenSearch, returns plain data and
 * throws on failure (D2). Orchestration — statistics triggers, cache
 * invalidation, async job spawning — lives in jobs.php, and the JSON:API
 * envelope only in api/v1/modules/search-index.php.
 *
 * Not to be confused with modules/statistics-index/, which writes the separate
 * word-frequency index (optv_statistics_<parliament>).
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/index-config.php");
require_once(__DIR__ . "/../../api/v1/utilities.php"); // getApiOpenSearchClient()

/**
 * @throws SearchIndexInvalidInputException when the parliament is unknown.
 */
function searchIndexRequireParliament($parliament)
{
    global $config;

    if (empty($parliament)) {
        throw new SearchIndexInvalidInputException("Missing parliament.");
    }
    if (!isset($config["parliament"][$parliament])) {
        throw new SearchIndexInvalidInputException("Invalid parliament specified: {$parliament}");
    }
    return $parliament;
}

/** Main search index name for a parliament. */
function searchIndexName($parliament)
{
    global $config;
    return "openparliamenttv_" . ($config["parliament"][$parliament]["OpenSearch"]["index"] ?? $parliament);
}

/** Statistics index name for a parliament (owned by modules/statistics-index). */
function searchIndexStatisticsName($parliament)
{
    return "optv_statistics_" . strtolower($parliament);
}

/**
 * @return object A usable OpenSearch client.
 * @throws SearchIndexException when the client cannot be built.
 */
function searchIndexClient($parliament = null)
{
    $client = getApiOpenSearchClient();

    if (!$client || (is_array($client) && isset($client["errors"]))) {
        throw new SearchIndexException(
            "Could not connect to OpenSearch.",
            "OPENSEARCH_CONNECTION_ERROR",
            $parliament === null ? [] : ["parliament" => $parliament]
        );
    }

    return $client;
}

/**
 * Create the index with its mapping if it does not exist yet.
 *
 * @throws SearchIndexException when creation fails for any reason other than
 *         the index already existing (a concurrent create is not an error).
 */
function searchIndexEnsureIndex($client, $indexName)
{
    try {
        if (!$client->indices()->exists(["index" => $indexName])) {
            $client->indices()->create([
                "index" => $indexName,
                "body" => searchIndexMappingBody()
            ]);
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "resource_already_exists_exception") === false) {
            throw new SearchIndexException(
                $e->getMessage(),
                "INDEX_CREATION_FAILURE",
                ["indexName" => $indexName, "error" => $e->getMessage()],
                $e
            );
        }
    }
}

/**
 * Bulk-write media items into the main index.
 *
 * @param string $parliament Parliament code.
 * @param array  $items      Media items in API response shape; each item's
 *                           `data` is the indexed document and `data.id` the _id.
 * @param bool   $initIndex  Create the index (with mapping) first if missing.
 * @return array ['updated' => int, 'failed' => int, 'errors' => array]
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexWriteItems($parliament, array $items, $initIndex = false)
{
    searchIndexRequireParliament($parliament);

    if (empty($items)) {
        return ["updated" => 0, "failed" => 0, "errors" => []];
    }

    $indexName = searchIndexName($parliament);
    $client = searchIndexClient($parliament);

    if ($initIndex) {
        searchIndexEnsureIndex($client, $indexName);
    }

    $params = ["body" => []];
    $errorsEncountered = [];
    $updatedCount = 0;
    $failedCount = 0;

    for ($i = 0; $i < count($items); $i++) {
        $tmpItem = $items[$i];
        if (isset($tmpItem["data"]["id"])) {
            $params["body"][] = [
                "index" => [
                    "_index" => $indexName,
                    "_id"    => $tmpItem["data"]["id"]
                ]
            ];
            $params["body"][] = $tmpItem["data"];
        } else {
            $failedCount++;
            $errorsEncountered[] = ["type" => "item_missing_id", "message" => "Item at index " . $i . " is missing a data.id field."];
        }
    }

    if (empty($params["body"])) {
        return ["updated" => 0, "failed" => $failedCount, "errors" => $errorsEncountered];
    }

    try {
        $responses = $client->bulk($params);
    } catch (Exception $e) {
        throw new SearchIndexException($e->getMessage(), "BULK_API_EXCEPTION", ["error" => $e->getMessage()], $e);
    }

    // A bulk call succeeds as a whole while individual documents fail, so the
    // per-item errors are counted rather than thrown.
    if (isset($responses["errors"]) && $responses["errors"] === true) {
        foreach ($responses["items"] as $idx => $responseItem) {
            if (isset($responseItem["index"]["error"])) {
                $failedId = $responseItem["index"]["_id"];
                $errorDetail = $responseItem["index"]["error"]["type"] . ": " . $responseItem["index"]["error"]["reason"];
                $errorsEncountered[] = ["type" => "indexing_item", "id" => $failedId, "message" => $errorDetail];
                $failedCount++;
            } else {
                $updatedCount++;
            }
        }
    } else {
        $updatedCount = count($responses["items"] ?? []);
    }

    return ["updated" => $updatedCount, "failed" => $failedCount, "errors" => $errorsEncountered];
}

/**
 * Drop both the main and the statistics index for a parliament.
 *
 * @return array ['deleted' => bool|string, 'indices' => string[]] — `indices`
 *         lists what was removed, annotating names that were already absent.
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexDeleteIndices($parliament)
{
    searchIndexRequireParliament($parliament);

    $mainIndexName = searchIndexName($parliament);
    $statisticsIndexName = searchIndexStatisticsName($parliament);
    $client = searchIndexClient($parliament);

    $deletedIndices = [];
    $errors = [];

    try {
        foreach ([$mainIndexName, $statisticsIndexName] as $indexName) {
            if ($client->indices()->exists(["index" => $indexName])) {
                $response = $client->indices()->delete(["index" => $indexName]);
                if (isset($response["acknowledged"]) && $response["acknowledged"] === true) {
                    $deletedIndices[] = $indexName;
                } else {
                    $errors[] = "Failed to delete " . ($indexName === $mainIndexName ? "main" : "statistics") . " index {$indexName}: not acknowledged";
                }
            } else {
                $deletedIndices[] = $indexName . " (already deleted or not exists)";
            }
        }
    } catch (Exception $e) {
        // A 404 means the work is already done, not a failure.
        if ($e instanceof \Elasticsearch\Common\Exceptions\Missing404Exception || (method_exists($e, "getCode") && $e->getCode() == 404)) {
            return ["deleted" => "already_deleted_or_not_exists", "indices" => []];
        }
        throw new SearchIndexException(
            $e->getMessage(),
            "DELETE_EXCEPTION",
            ["mainIndex" => $mainIndexName, "statisticsIndex" => $statisticsIndexName, "error" => $e->getMessage()],
            $e
        );
    }

    if (!empty($errors)) {
        throw new SearchIndexException(
            "Index deletion partially failed.",
            "DELETE_FAILED_PARTIAL",
            ["deletedIndices" => $deletedIndices, "errors" => $errors]
        );
    }

    return ["deleted" => true, "indices" => $deletedIndices];
}

/**
 * Forcemerge both indices: reclaims the space held by deleted documents.
 *
 * Synchronous and slow (~1 minute) — bin/optimize-index.php runs it in the
 * background. The statistics index gets two passes because it is written with
 * update-with-upsert, which leaves large numbers of deleted docs behind:
 * expunge deletes first, then consolidate to a single segment.
 *
 * @param string        $parliament
 * @param callable|null $onProgress Receives a status string as each phase starts.
 * @return array Per-index timing and reclaimed-space results.
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexOptimizeIndices($parliament, callable $onProgress = null)
{
    searchIndexRequireParliament($parliament);

    $report = function ($message) use ($onProgress) {
        if ($onProgress !== null) {
            $onProgress($message);
        }
    };

    $mainIndexName = searchIndexName($parliament);
    $statisticsIndexName = searchIndexStatisticsName($parliament);
    $client = searchIndexClient($parliament);

    $report("Connected to OpenSearch, starting optimization...");

    $results = [];

    try {
        if ($client->indices()->exists(["index" => $mainIndexName])) {
            $report("Optimizing main index...");

            $startTime = microtime(true);
            $client->indices()->forcemerge([
                "index" => $mainIndexName,
                "max_num_segments" => 1
            ]);
            $results["main_index"] = [
                "optimized" => true,
                "time_seconds" => round(microtime(true) - $startTime, 2)
            ];
        }

        if ($client->indices()->exists(["index" => $statisticsIndexName])) {
            $report("Optimizing statistics index (phase 1: expunge deletes)...");

            $startTime = microtime(true);

            $statsBefore = $client->indices()->stats(["index" => $statisticsIndexName]);
            $deletedBefore = $statsBefore["indices"][$statisticsIndexName]["total"]["docs"]["deleted"] ?? 0;
            $sizeBefore = $statsBefore["indices"][$statisticsIndexName]["total"]["store"]["size_in_bytes"] ?? 0;

            $client->indices()->forcemerge([
                "index" => $statisticsIndexName,
                "only_expunge_deletes" => true
            ]);

            sleep(3);

            $report("Optimizing statistics index (phase 2: segment consolidation)...");

            $client->indices()->forcemerge([
                "index" => $statisticsIndexName,
                "max_num_segments" => 1
            ]);

            sleep(5);

            $statsAfter = $client->indices()->stats(["index" => $statisticsIndexName]);
            $deletedAfter = $statsAfter["indices"][$statisticsIndexName]["total"]["docs"]["deleted"] ?? 0;
            $sizeAfter = $statsAfter["indices"][$statisticsIndexName]["total"]["store"]["size_in_bytes"] ?? 0;

            $results["statistics_index"] = [
                "optimized" => true,
                "time_seconds" => round(microtime(true) - $startTime, 2),
                "deleted_docs_removed" => $deletedBefore - $deletedAfter,
                "space_reclaimed_mb" => round(($sizeBefore - $sizeAfter) / 1024 / 1024, 1),
                "strategy" => "two_pass_expunge_then_merge"
            ];
        }
    } catch (Exception $e) {
        throw new SearchIndexException($e->getMessage(), "OPTIMIZATION_FAILURE", ["error" => $e->getMessage()], $e);
    }

    return $results;
}
