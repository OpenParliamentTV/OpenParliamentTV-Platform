<?php
/**
 * API boundary for both indices: translates modules/search-index/ and
 * modules/statistics-index/ into JSON:API envelopes. Routed from api/v1/api.php
 * as action "index" (itemTypes update | full-update | delete | status |
 * statistics-update | statistics-status | optimize | optimization-status).
 *
 * The logic lives in the modules; these wrappers only map plain returns to
 * apiSuccessResponse() and the two module exception types to error responses:
 * SearchIndexInvalidInputException → 422, SearchIndexException → 500 (or the
 * status its error code implies, e.g. ALREADY_RUNNING → 409).
 */

require_once(__DIR__ . "/../../../config.php");
require_once(__DIR__ . "/../../../api/v1/utilities.php");
require_once(__DIR__ . "/../../../modules/search-index/jobs.php");
require_once(__DIR__ . "/../../../modules/statistics-index/jobs.php");
require_once(__DIR__ . "/../../../modules/statistics-index/indexer.php");

/** Error codes whose HTTP status is not the generic 500. */
const SEARCH_INDEX_ERROR_STATUS = [
    "ALREADY_RUNNING" => 409,
];

/**
 * Map a module exception onto the standard error envelope. Keeps the machine
 * readable code and the context fields the admin UI reads.
 */
function searchIndexErrorResponse(Exception $e)
{
    if ($e instanceof SearchIndexInvalidInputException) {
        return apiErrorInvalidParameter("parliament", $e->getMessage());
    }

    if ($e instanceof SearchIndexException) {
        $code = $e->getErrorCode();
        return apiErrorResponse(
            SEARCH_INDEX_ERROR_STATUS[$code] ?? 500,
            $code,
            "messageErrorSearchIndex",
            $e->getMessage(),
            [],
            null,
            $e->getContext()
        );
    }

    return apiErrorResponse(500, "SEARCH_INDEX_FAILURE", "messageErrorSearchIndex", $e->getMessage());
}

/**
 * Index a batch of media items.
 *
 * @param array $api_request parliament, items, and the optional flags
 *              initIndex | isFullRebuild | skipAutoStatistics.
 */
function searchIndexUpdate($api_request)
{
    if (empty($api_request["parliament"])) {
        return apiErrorMissingParameter("parliament");
    }

    try {
        $result = searchIndexUpdateItems(
            $api_request["parliament"],
            $api_request["items"] ?? [],
            [
                "initIndex" => $api_request["initIndex"] ?? false,
                "isFullRebuild" => $api_request["isFullRebuild"] ?? false,
                "skipAutoStatistics" => $api_request["skipAutoStatistics"] ?? false,
            ]
        );
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    $message = $result["message"];
    unset($result["message"]);

    return apiSuccessResponse($result, ["message" => $message]);
}

/**
 * Re-index every media item referencing the given documents. Called directly by
 * bin/additional-data.php after ADS enrichment changed document data.
 */
function searchIndexUpdateForDocuments(array $documentIDs, $parliament, $db, $dbp, $mediaChunkSize = 50)
{
    try {
        $result = searchIndexUpdateForDocumentIDs($documentIDs, $parliament, $db, $dbp, $mediaChunkSize);
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    $message = $result["message"];
    unset($result["message"]);

    return apiSuccessResponse($result, ["message" => $message]);
}

/** Drop the main and statistics indices for a parliament. */
function searchIndexDelete($api_request)
{
    if (empty($api_request["parliament"])) {
        return apiErrorMissingParameter("parliament");
    }

    try {
        $result = searchIndexDeleteIndices($api_request["parliament"]);
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    if ($result["deleted"] === "already_deleted_or_not_exists") {
        return apiSuccessResponse(["deleted" => $result["deleted"]], ["message" => "Indices do not exist (404)."]);
    }

    return apiSuccessResponse($result, ["message" => "Indices deleted successfully: " . implode(", ", $result["indices"])]);
}

/** Progress of a running or finished index rebuild. */
function searchIndexGetStatus($api_request)
{
    global $config;

    if (empty($api_request["parliament"])) {
        return apiErrorMissingParameter("parliament (for searchIndexGetStatus)");
    }
    $parliament = strtoupper(trim($api_request["parliament"]));

    if (!isset($config["parliament"][$parliament])) {
        return apiErrorInvalidParameter("parliament", "Invalid parliament specified for status check: {$parliament}");
    }

    $progressFilePath = searchIndexProgressFilePath($parliament);
    $hadProgressFile = file_exists($progressFilePath);

    try {
        $progressData = searchIndexReadProgress($progressFilePath, [
            "processName" => "searchIndexFullUpdate",
            "parliament" => $parliament,
            "status" => "idle",
            "statusDetails" => "No active or recent update process found for this parliament."
        ]);
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    if (!$hadProgressFile) {
        return apiSuccessResponse($progressData, ["message" => "No progress file found, returning default idle status."]);
    }

    return apiSuccessResponse($progressData);
}

/** Delete and rebuild the main index in the background. */
function searchIndexTriggerFullUpdate($api_request)
{
    if (empty($api_request["parliament"])) {
        return apiErrorMissingParameter("parliament (for triggerFullIndexUpdate task)");
    }

    try {
        $message = searchIndexTriggerRebuild($api_request["parliament"]);
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    return apiSuccessResponse(["message" => $message]);
}

/** Progress of a running or finished statistics index run. */
function searchIndexGetStatisticsStatus($api_request)
{
    try {
        return apiSuccessResponse(statsIndexGetProgress($api_request["parliament"] ?? "DE"));
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }
}

/** Start a full statistics index rebuild. */
function searchIndexTriggerStatisticsUpdate($api_request)
{
    $parliament = $api_request["parliament"] ?? "DE";

    if (empty($parliament)) {
        return apiErrorMissingParameter("parliament");
    }

    try {
        return apiSuccessResponse(statsIndexTriggerRebuild($parliament));
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }
}

/** Start a background forcemerge of both indices. */
function searchIndexOptimize($api_request)
{
    if (empty($api_request["parliament"])) {
        return apiErrorMissingParameter("parliament");
    }

    try {
        $message = searchIndexTriggerOptimization($api_request["parliament"]);
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }

    return apiSuccessResponse(["message" => "Index optimization started successfully"], ["message" => $message]);
}

/** Progress of a running or finished optimization. */
function searchIndexGetOptimizationStatus($api_request)
{
    $parliament = $api_request["parliament"] ?? "DE";

    try {
        return apiSuccessResponse(searchIndexReadProgress(
            searchIndexOptimizationProgressFilePath($parliament),
            [
                "status" => "idle",
                "statusDetails" => "Index optimization not running",
                "processName" => "indexOptimization",
                "parliament" => $parliament
            ]
        ));
    } catch (Exception $e) {
        return searchIndexErrorResponse($e);
    }
}
