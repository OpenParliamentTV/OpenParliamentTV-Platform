<?php
/**
 * Job orchestration for the statistics index (optv_statistics_<parliament>):
 * spawning bin/index-statistics.php and reading its progress/lock files.
 *
 * The writer itself is indexer.php in this directory. This file deliberately
 * sits here rather than in modules/search-index/, because the two indices are
 * separate: modules/search-index/ owns openparliamenttv_<parliament>, and only
 * calls in here to keep the statistics index in step after a write.
 *
 * Plain returns and exceptions (D2); the envelope is applied by
 * api/v1/modules/search-index.php, which routes both indices' admin actions.
 */

require_once(__DIR__ . "/../search-index/errors.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvVarPath()
require_once(__DIR__ . "/../search/opensearch.php");   // resolveParliamentKey()

/**
 * Progress, lock and log paths are all keyed on the CANONICAL parliament key, so
 * a caller passing "de" and one passing "DE" address the same run. The indexer
 * resolves its --parliament argument the same way.
 */
function statsIndexProgressFilePath($parliament)
{
    return optvVarPath("data/progress") . "/statisticsIndexer_" . resolveParliamentKey($parliament) . ".json";
}

function statsIndexLockFilePath($parliament)
{
    return optvVarPath("data/progress") . "/statisticsIndexer_" . resolveParliamentKey($parliament) . ".lock";
}

/**
 * Start bin/index-statistics.php in the background.
 *
 * @param string $parliament
 * @param bool   $isIncremental Update only what changed rather than rebuilding.
 * @param array  $mediaIds      Scope for an incremental run. An incremental run
 *                              with no ids falls back to --incremental, which
 *                              the indexer widens to a full pass.
 * @return array ['message','parliament','mode','logFile']
 * @throws SearchIndexException
 */
function statsIndexTriggerRun($parliament, $isIncremental = false, array $mediaIds = [])
{
    global $config;

    $phpPath = $config["bin"]["php"] ?? PHP_BINARY;
    $scriptPath = realpath(__DIR__ . "/../../bin/index-statistics.php");
    $logFile = optvVarPath("log") . "/statisticsIndexer_" . resolveParliamentKey($parliament) . ".log";

    if (!$scriptPath) {
        throw new SearchIndexException("Statistics indexer script not found", "SCRIPT_NOT_FOUND");
    }

    $command = sprintf(
        "%s %s --parliament=%s --batch-size=50",
        escapeshellcmd($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($parliament)
    );

    if ($isIncremental && !empty($mediaIds)) {
        $command .= " --media-ids=" . escapeshellarg(implode(",", $mediaIds));
    } elseif ($isIncremental) {
        $command .= " --incremental";
    }

    $command .= " > " . escapeshellarg($logFile);

    try {
        executeAsyncShellCommand($command);
    } catch (Exception $e) {
        throw new SearchIndexException("Failed to start statistics indexing", "ASYNC_EXEC_FAIL", ["error" => $e->getMessage()], $e);
    }

    return [
        "message" => "Statistics indexing started with unified settings",
        "parliament" => $parliament,
        "mode" => $isIncremental ? "incremental" : "full_rebuild",
        "logFile" => basename($logFile)
    ];
}

/**
 * Incremental statistics run scoped to the media just written to the main index.
 *
 * @param array $items Media items in API response shape.
 * @return array|null Trigger result, or null when the batch held no usable ids
 *         (nothing to do — deliberately not an error).
 * @throws SearchIndexException
 */
function statsIndexTriggerForItems($parliament, array $items)
{
    $mediaIds = [];
    foreach ($items as $item) {
        if (isset($item["data"]["id"])) {
            $mediaIds[] = $item["data"]["id"];
        }
    }

    if (empty($mediaIds)) {
        return null;
    }

    return statsIndexTriggerRun($parliament, true, $mediaIds);
}

/**
 * Start a full statistics rebuild, refusing while one is already running.
 *
 * @throws SearchIndexInvalidInputException|SearchIndexException
 */
function statsIndexTriggerRebuild($parliament)
{
    if (empty($parliament)) {
        throw new SearchIndexInvalidInputException("Missing parliament.");
    }

    // An older lock is not removed here: the indexer waits for its holder or
    // clears it once stale, judged by the holder's own timeout.
    $lockFile = statsIndexLockFilePath($parliament);
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockAge = time() - ($lockData["timestamp"] ?? 0);

        if ($lockAge < 3600) { // 1 hour timeout
            throw new SearchIndexException(
                "Process PID: " . ($lockData["pid"] ?? "unknown"),
                "ALREADY_RUNNING"
            );
        }
    }

    $result = statsIndexTriggerRun($parliament, false);

    // Statistics responses are derived from this index and are now stale.
    require_once(__DIR__ . "/../statistics/cache.php");
    statsCacheInvalidate($parliament);

    return $result;
}

/**
 * @return array Progress data, or the idle shape when no run has happened yet.
 * @throws SearchIndexException when the progress file exists but is unreadable.
 */
function statsIndexGetProgress($parliament)
{
    require_once(__DIR__ . "/../search-index/jobs.php");

    return searchIndexReadProgress(statsIndexProgressFilePath($parliament), [
        "status" => "idle",
        "statusDetails" => "Statistics indexing not running",
        "totalDbMediaItems" => 0,
        "processedMediaItems" => 0,
        "words_indexed" => 0,
        "statistics_updated" => 0,
        "performance" => ["avg_docs_per_second" => 0]
    ]);
}
