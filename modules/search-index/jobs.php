<?php
/**
 * Orchestration around the main search index: progress files, status reads, the
 * async rebuild/optimize triggers, and the update path that composes an
 * indexer write with the statistics-index follow-up.
 *
 * Plain returns and exceptions (D2); api/v1/modules/search-index.php applies the
 * envelope. Statistics *jobs* live in modules/statistics-index/jobs.php — this
 * file only calls them.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/indexer.php");
require_once(__DIR__ . "/../statistics-index/jobs.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvVarPath()

/** Progress file the importer writes while rebuilding the index. */
function searchIndexProgressFilePath($parliamentCode)
{
    if (empty($parliamentCode)) {
        return optvVarFilePath("data/progress", "searchIndex_unknown.json");
    }
    return optvVarPath("data/progress") . "/searchIndex_" . strtoupper($parliamentCode) . ".json";
}

function searchIndexOptimizationProgressFilePath($parliament)
{
    return optvVarPath("data/progress") . "/indexOptimization_" . $parliament . ".json";
}

function searchIndexOptimizationLockFilePath($parliament)
{
    return optvVarPath("data/progress") . "/indexOptimization_" . $parliament . ".lock";
}

/**
 * Read a progress JSON file.
 *
 * @param string $path
 * @param array  $default Returned when the file does not exist.
 * @return array
 * @throws SearchIndexException when the file exists but cannot be read or parsed.
 */
function searchIndexReadProgress($path, array $default)
{
    if (!file_exists($path)) {
        return $default;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        throw new SearchIndexException("Could not read progress file.", "PROGRESS_FILE_READ_ERROR", ["path" => basename($path)]);
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !$data) {
        throw new SearchIndexException("Progress file is not valid JSON.", "PROGRESS_FILE_CORRUPT", ["path" => basename($path)]);
    }

    return $data;
}

/**
 * Index a batch of media items and keep the statistics index in step.
 *
 * @param string $parliament
 * @param array  $items   Media items in API response shape.
 * @param array  $options initIndex, isFullRebuild, skipAutoStatistics.
 * @return array ['updated','failed','errors','message'] — `message` narrates what
 *         happened to the statistics index, which the admin import UI displays.
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexUpdateItems($parliament, array $items, array $options = [])
{
    searchIndexRequireParliament($parliament);

    $initIndex = !empty($options["initIndex"]);

    if (empty($items)) {
        return ["updated" => 0, "failed" => 0, "errors" => [], "message" => "No items provided to index."];
    }

    $result = searchIndexWriteItems($parliament, $items, $initIndex);

    if ($result["updated"] === 0 && $result["failed"] > 0 && empty($result["errors"][0]["id"])) {
        // Every item lacked a data.id, so nothing reached OpenSearch.
        $result["message"] = "No valid items to index after filtering.";
        return $result;
    }

    $message = "Search index update completed. Updated: {$result['updated']}, Failed: {$result['failed']}.";

    // A full rebuild triggers statistics once at the end (the importer's
    // --triggerStatisticsAfterCompletion), and a caller batching its own trigger
    // asks to be left alone; otherwise keep the two indices in step per batch.
    $isFullRebuild = $initIndex || !empty($options["isFullRebuild"]);
    $skipAutoStatistics = !empty($options["skipAutoStatistics"]);

    if (!$isFullRebuild && !$skipAutoStatistics) {
        try {
            statsIndexTriggerForItems($parliament, $items);
            $message .= " Statistics indices update triggered.";
        } catch (Exception $e) {
            // The main index is already written; a failed statistics follow-up
            // must not undo that, so it is reported rather than thrown.
            error_log("Statistics indices update failed for parliament {$parliament}: " . $e->getMessage());
            $message .= " (Statistics indices update failed - indices may be out of sync)";
        }
    } elseif ($isFullRebuild) {
        $message .= " (Statistics indices will be updated after main index completion)";
    } elseif ($skipAutoStatistics) {
        $message .= " (Statistics indices will be updated separately)";
    }

    // The general-statistics cache is derived from the index and is now stale.
    require_once(__DIR__ . "/../../api/v1/modules/statistics.php");
    invalidateGeneralStatisticsCache($parliament);

    $result["message"] = $message;
    return $result;
}

/**
 * Re-index every media item that references one of the given documents.
 *
 * Document data inside a media index entry (relationships.documents) is read
 * from the document table at index time only, so document changes (e.g. ADS
 * enrichment) do not reach the search index until the referencing media are
 * re-indexed.
 *
 * @return array ['mediaTotal','updated','failed','errors','message']
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexUpdateForDocumentIDs(array $documentIDs, $parliament, $db, $dbp, $mediaChunkSize = 50)
{
    global $config;

    if (empty($documentIDs)) {
        return ["mediaTotal" => 0, "updated" => 0, "failed" => 0, "errors" => [], "message" => "No documents given, nothing to reindex."];
    }

    searchIndexRequireParliament($parliament);

    // media.php requires the API search-index wrapper itself, so load it lazily
    // to avoid a circular top-level require.
    require_once(__DIR__ . "/../../api/v1/modules/media.php");

    $annotationTable = $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"];

    $mediaIDs = [];
    try {
        foreach (array_chunk($documentIDs, 1000) as $idChunk) {
            $rows = $dbp->getCol("SELECT DISTINCT AnnotationMediaID FROM ?n WHERE AnnotationType = 'document' AND AnnotationResourceID IN (?a)", $annotationTable, $idChunk);
            foreach ($rows as $mediaID) {
                $mediaIDs[$mediaID] = true;
            }
        }
    } catch (Exception $e) {
        throw new SearchIndexException("Could not resolve media for documents: " . $e->getMessage(), "DATABASE_ERROR", [], $e);
    }
    $mediaIDs = array_keys($mediaIDs);

    $updated = 0;
    $failed = 0;
    $errors = [];

    foreach (array_chunk($mediaIDs, max(1, (int) $mediaChunkSize)) as $mediaChunk) {
        $items = [];
        foreach ($mediaChunk as $mediaID) {
            $mediaItem = mediaGetByID($mediaID, $db, $dbp);
            if (!empty($mediaItem) && !isset($mediaItem["errors"]) && isset($mediaItem["data"]["id"])) {
                $items[] = $mediaItem;
            } else {
                $failed++;
                $errors[] = ["type" => "media_fetch", "id" => $mediaID, "message" => "Could not build media item for reindexing."];
            }
        }
        if (empty($items)) {
            continue;
        }

        // skipAutoStatistics: statistics indices aggregate word frequencies from
        // the media text, which document enrichment does not change — no
        // statistics run is needed. (An unscoped incremental trigger would also
        // fall back to a full statistics rebuild.)
        try {
            $result = searchIndexUpdateItems($parliament, $items, ["skipAutoStatistics" => true]);
            $updated += (int) $result["updated"];
            $failed += (int) $result["failed"];
            if (!empty($result["errors"])) {
                $errors = array_merge($errors, $result["errors"]);
            }
        } catch (SearchIndexException $e) {
            // One bad chunk must not abandon the remaining documents.
            $failed += count($items);
            $errors[] = ["type" => "bulk_index", "message" => $e->getMessage()];
        }
    }

    return [
        "mediaTotal" => count($mediaIDs),
        "updated" => $updated,
        "failed" => $failed,
        "errors" => $errors,
        "message" => "Reindexed {$updated} of " . count($mediaIDs) . " media items for " . count($documentIDs) . " updated documents."
    ];
}

/**
 * Delete and rebuild the main index in the background.
 *
 * @return string A human-readable confirmation.
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexTriggerRebuild($parliament)
{
    global $config;

    $parliament = strtoupper(trim((string) $parliament));
    searchIndexRequireParliament($parliament);

    if (empty($config["bin"]["php"])) {
        throw new SearchIndexException("PHP binary path is not configured.", "CONFIG_ERROR");
    }

    // Drop the indices first; a rebuild onto a populated index would leave
    // documents behind that no longer exist in the database.
    searchIndexDeleteIndices($parliament);

    $cliScriptPath = realpath(__DIR__ . "/../../bin/import.php");
    if (!$cliScriptPath) {
        throw new SearchIndexException("Importer script not found.", "SCRIPT_NOT_FOUND");
    }

    $command = $config["bin"]["php"] . " " . escapeshellarg($cliScriptPath)
        . " --justUpdateSearchIndex --parliament=" . escapeshellarg($parliament)
        . " --triggerStatisticsAfterCompletion";

    try {
        executeAsyncShellCommand($command);
    } catch (Exception $e) {
        throw new SearchIndexException($e->getMessage(), "ASYNC_EXEC_FAIL", ["error" => $e->getMessage()], $e);
    }

    require_once(__DIR__ . "/../../api/v1/modules/statistics.php");
    invalidateGeneralStatisticsCache($parliament);

    return "Full rebuild initiated: main index followed by statistics indices for parliament: {$parliament}.";
}

/**
 * Start a background forcemerge of both indices.
 *
 * @return string A human-readable confirmation.
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function searchIndexTriggerOptimization($parliament)
{
    global $config;

    if (empty($parliament)) {
        throw new SearchIndexInvalidInputException("Missing parliament.");
    }

    $lockFile = searchIndexOptimizationLockFilePath($parliament);
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData["timestamp"] ?? 0);

        if ($lockAge < 600) { // 10 minute timeout
            throw new SearchIndexException(
                "Process started at: " . date("Y-m-d H:i:s", $lockData["timestamp"] ?? 0),
                "ALREADY_RUNNING"
            );
        }
        unlink($lockFile);
    }

    $progressFile = searchIndexOptimizationProgressFilePath($parliament);
    file_put_contents($progressFile, json_encode([
        "processName" => "indexOptimization",
        "parliament" => $parliament,
        "status" => "running",
        "statusDetails" => "Starting index optimization...",
        "startTime" => date("c"),
        "errors" => []
    ], JSON_PRETTY_PRINT));

    $scriptPath = realpath(__DIR__ . "/../../bin/optimize-index.php") ?: (__DIR__ . "/../../bin/optimize-index.php");
    $phpPath = $config["bin"]["php"] ?? PHP_BINARY;

    $command = sprintf(
        "%s %s --parliament=%s",
        escapeshellcmd($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($parliament)
    );

    try {
        executeAsyncShellCommand($command);
    } catch (Exception $e) {
        // Leaving a "running" progress file behind would block the next attempt.
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }
        throw new SearchIndexException($e->getMessage(), "ASYNC_EXEC_FAIL", ["error" => $e->getMessage()], $e);
    }

    return "Index optimization is running in the background";
}

/**
 * Run the forcemerge synchronously, maintaining the lock and progress files that
 * searchIndexTriggerOptimization() and the status endpoint read. Called by
 * bin/optimize-index.php in the spawned process.
 *
 * @return bool Whether the optimization completed.
 */
function searchIndexRunOptimization($parliament)
{
    $progressFile = searchIndexOptimizationProgressFilePath($parliament);
    $lockFile = searchIndexOptimizationLockFilePath($parliament);

    file_put_contents($lockFile, json_encode([
        "process" => "indexOptimization",
        "parliament" => $parliament,
        "timestamp" => time(),
        "pid" => getmypid()
    ]));

    try {
        $results = searchIndexOptimizeIndices($parliament, function ($statusDetails) use ($progressFile) {
            searchIndexWriteOptimizationProgress($progressFile, ["statusDetails" => $statusDetails]);
        });

        searchIndexWriteOptimizationProgress($progressFile, [
            "status" => "completed_successfully",
            "statusDetails" => "Index optimization completed successfully",
            "endTime" => date("c"),
            "results" => $results
        ]);

        return true;
    } catch (SearchIndexException $e) {
        searchIndexWriteOptimizationProgress($progressFile, [
            "status" => "error",
            "statusDetails" => $e->getErrorCode() === "OPENSEARCH_CONNECTION_ERROR"
                ? "Failed to connect to OpenSearch"
                : "Optimization failed: " . $e->getMessage(),
            "endTime" => date("c"),
            "errors" => [["message" => $e->getMessage()]]
        ]);
        return false;
    } finally {
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}

/** Merge updates into an existing optimization progress file. */
function searchIndexWriteOptimizationProgress($progressFile, array $updates)
{
    if (!file_exists($progressFile)) {
        return;
    }
    $current = json_decode(file_get_contents($progressFile), true) ?: [];
    file_put_contents($progressFile, json_encode(array_merge($current, $updates), JSON_PRETTY_PRINT));
}
