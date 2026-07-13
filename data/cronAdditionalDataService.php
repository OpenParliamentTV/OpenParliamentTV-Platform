<?php

require_once(__DIR__ . "/../config.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * This script expects to be run via CLI only
 *
 * it can have the following parameter
 * --type (required) "memberOfParliament", "person", "organisation", "term", "legalDocument" or "officialDocument"
 *
 * this script will exit if cronAdditionalDataService.lock file is present.
 * If the lock file is older than $config["time"]["warning"] a mail will be send to $config["cronContactMail"]
 * If the lock file is older than $config["time"]["ignore"] the lock file will be removed. In this case we expect there was a crash
 *
 * --ids (optional, officialDocument only): comma-separated DocumentIDs to
 * (re-)enrich instead of the default missing-info selection. Batch requests
 * always fetch fresh from the source, so this doubles as a manual refresh.
 */

$config["time"]["warning"] = 30; //minutes
$config["time"]["ignore"] = 90; //minutes
$config["cronContactMail"] = ""; //minutes

// Define path for the progress file
define("CRON_ADS_PROGRESS_FILE", __DIR__ . "/progress/cronAdditionalDataService.json");

require_once(__DIR__ . "/../modules/utilities/functions.php");
require_once(__DIR__ . "/../api/v1/utilities.php");

/**
 * @param string $type
 * @param string $msg
 *
 * Writes to log file
 */

function logger($type = "info",$msg) {
    file_put_contents(__DIR__."/cronAdditionalDataService.log",date("Y-m-d H:i:s")." - ".$type.": ".$msg."\n",FILE_APPEND );
}

function _ads_get_progress_data() {
    $entityTypes = ["person", "memberOfParliament", "organisation", "legalDocument", "officialDocument", "term"];
    $defaultStatus = [
        "globalStatus" => "idle",
        "activeType" => null,
        "types" => []
    ];

    $defaultTypeStatus = [
        "status" => "idle",
        "statusDetails" => "Awaiting process.",
        "startTime" => null,
        "endTime" => null,
        "totalItems" => 0,
        "processedItems" => 0,
        "errors" => [],
        "lastSuccessfullyProcessedId" => null,
        "lastActivityTime" => null
    ];

    foreach ($entityTypes as $type) {
        $defaultStatus["types"][$type] = $defaultTypeStatus;
    }

    if (!file_exists(CRON_ADS_PROGRESS_FILE)) {
        return $defaultStatus;
    }

    $progressJson = @file_get_contents(CRON_ADS_PROGRESS_FILE);
    if ($progressJson === false) {
        logger('error', 'Could not read progress file ' . CRON_ADS_PROGRESS_FILE);
        return $defaultStatus; // Return default if unreadable
    }

    $savedProgress = json_decode($progressJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logger('error', 'Could not decode progress file JSON: ' . json_last_error_msg());
        return $defaultStatus; // Return default if corrupt
    }

    // Build a new, clean progress structure
    $newProgress = [
        "globalStatus" => $savedProgress['globalStatus'] ?? 'idle',
        "activeType" => $savedProgress['activeType'] ?? null,
        "types" => []
    ];

    // Deep merge types, ensuring no old top-level keys are carried over
    foreach ($entityTypes as $type) {
        $newProgress["types"][$type] = array_merge($defaultTypeStatus, $savedProgress['types'][$type] ?? []);
    }

    return $newProgress;
}

function _ads_save_progress_data($data) {
    if (!is_dir(dirname(CRON_ADS_PROGRESS_FILE))) {
        mkdir(dirname(CRON_ADS_PROGRESS_FILE), 0775, true);
    }
    @file_put_contents(CRON_ADS_PROGRESS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function _ads_resolve_parliament($requestedParliament = null) {
    global $config;

    if ($requestedParliament !== null && $requestedParliament !== '' && isset($config['parliament'][$requestedParliament])) {
        return $requestedParliament;
    }

    if (!empty($config['parliament']) && is_array($config['parliament'])) {
        return array_key_first($config['parliament']);
    }

    return 'DE';
}

/**
 * Bulk enrichment path for officialDocument.
 *
 * Selects only documents that are missing enrichment info (no originID) or
 * whose stored procedure list is shorter than the known count, fetches them
 * from the ADS in batches via the documentNumbers lookup (one HTTP request per
 * 50 documents instead of one per document), and finally re-indexes the media
 * items referencing the updated documents. Progress reporting uses the same
 * fields as the generic per-item path, written once per batch.
 */
function _ads_process_official_documents($db, $dbp, $parliament, $input, &$overallErrorsEncountered) {
    global $config;

    $type = "officialDocument";
    $batchSize = 50;
    $table = $config["platform"]["sql"]["tbl"]["Document"];

    $typeSpecificErrors = false;
    $processedItemsCount = 0;
    $lastSuccessfullyProcessedId = null;
    $updatedDocumentIDs = [];

    logger("info", "[ADS] Selecting officialDocuments for bulk enrichment" . (!empty($input["ids"]) ? " (explicit ids)" : " (missing enrichment info)"));

    try {
        if (!empty($input["ids"])) {
            // Explicit (re-)enrichment of specific documents; the batch lookup
            // always fetches fresh from the source, so this is the refresh path.
            $requestedIds = array_filter(array_map('trim', explode(",", $input["ids"])), 'strlen');
            $rows = $db->getAll(
                "SELECT DocumentID, DocumentLabel, DocumentSourceURI FROM ?n WHERE DocumentType = 'officialDocument' AND DocumentID IN (?a) ORDER BY DocumentID",
                $table, $requestedIds
            );
        } else {
            // Missing enrichment info: never enriched (NULL/''/'null'/invalid
            // JSON/no originID) or an incomplete procedure list from a failed
            // completeness fetch (procedureIDsCount > stored list length). The
            // JSON_TYPE guard neutralizes JSON_LENGTH('null') = 1.
            $rows = $db->getAll(
                "SELECT DocumentID, DocumentLabel, DocumentSourceURI FROM ?n
                 WHERE DocumentType = 'officialDocument'
                   AND (
                        DocumentAdditionalInformation IS NULL
                     OR DocumentAdditionalInformation IN ('', 'null')
                     OR NOT JSON_VALID(DocumentAdditionalInformation)
                     OR JSON_VALUE(DocumentAdditionalInformation, '$.originID') IS NULL
                     OR CAST(JSON_VALUE(DocumentAdditionalInformation, '$.procedureIDsCount') AS UNSIGNED)
                        > COALESCE(IF(JSON_TYPE(JSON_QUERY(DocumentAdditionalInformation, '$.procedureIDs')) = 'ARRAY',
                                      JSON_LENGTH(DocumentAdditionalInformation, '$.procedureIDs'), 0), 0)
                   )
                 ORDER BY DocumentID",
                $table
            );
        }
    } catch (Exception $e) {
        $selectErrorMsg = "[ADS] Error selecting documents for type {$type}: " . $e->getMessage();
        logger("error", $selectErrorMsg);

        $progress = _ads_get_progress_data();
        $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $selectErrorMsg];
        $progress['types'][$type]['statusDetails'] = "Error selecting documents. " . $e->getMessage();
        $progress['types'][$type]['status'] = 'error';
        _ads_save_progress_data($progress);

        $overallErrorsEncountered = true;
        return;
    }

    $totalItems = count($rows);
    logger("info", "[ADS] Total items for type {$type}: {$totalItems}");

    $progress = _ads_get_progress_data();
    $progress['types'][$type]['status'] = 'running';
    $progress['types'][$type]['totalItems'] = (int)$totalItems;
    $progress['types'][$type]['processedItems'] = 0;
    $progress['types'][$type]['startTime'] = date('c');
    $progress['types'][$type]['endTime'] = null;
    $progress['types'][$type]['statusDetails'] = "Starting processing. Total items: {$totalItems}";
    $progress['types'][$type]['errors'] = [];
    $progress['types'][$type]['lastSuccessfullyProcessedId'] = null;
    _ads_save_progress_data($progress);

    foreach (array_chunk($rows, $batchSize) as $chunk) {

        $response = null;
        try {
            $response = updateOfficialDocumentsBatchFromService($chunk, $config["ads"]["api"]["uri"], $config["ads"]["api"]["key"], $parliament, $db);
        } catch (Exception $e) {
            $response = ["errors" => [["info" => $e->getMessage()]]];
        }

        if (!isset($response["meta"]["requestStatus"]) || $response["meta"]["requestStatus"] !== "success") {
            $errorDetail = !empty($response["errors"]) ? json_encode($response["errors"]) : "Unknown API error";
            $chunkErrorMsg = "[ADS] Batch error updating {$type} (documents " . $chunk[0]["DocumentID"] . "-" . $chunk[count($chunk)-1]["DocumentID"] . "): " . $errorDetail;
            logger("error", $chunkErrorMsg);
            $typeSpecificErrors = true;
            $overallErrorsEncountered = true;

            $progress = _ads_get_progress_data();
            $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => "Batch failed for " . count($chunk) . " documents: " . mb_substr($errorDetail, 0, 300)];
            _ads_save_progress_data($progress);
        } else {
            $result = $response["data"];

            foreach (($result["updatedIDs"] ?? []) as $updatedID) {
                $updatedDocumentIDs[] = $updatedID;
                $lastSuccessfullyProcessedId = $updatedID;
            }

            foreach (($result["notFoundNumbers"] ?? []) as $notFoundNumber) {
                logger("warn", "[ADS] Document number {$notFoundNumber} not found in source (type {$type}).");
                $typeSpecificErrors = true;
                $overallErrorsEncountered = true;
                $progress = _ads_get_progress_data();
                $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => "Document number {$notFoundNumber} not found in source."];
                _ads_save_progress_data($progress);
            }

            foreach (($result["failed"] ?? []) as $failedItem) {
                logger("error", "[ADS] Failed document update (type {$type}, ID " . ($failedItem["DocumentID"] ?? "?") . "): " . ($failedItem["error"] ?? "unknown"));
                $typeSpecificErrors = true;
                $overallErrorsEncountered = true;
                $progress = _ads_get_progress_data();
                $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => "Error on ID " . ($failedItem["DocumentID"] ?? "?") . ": " . ($failedItem["error"] ?? "unknown"), "itemId" => $failedItem["DocumentID"] ?? null];
                _ads_save_progress_data($progress);
            }
        }

        $processedItemsCount += count($chunk);

        $progress = _ads_get_progress_data();
        $progress['types'][$type]['processedItems'] = $processedItemsCount;
        $progress['types'][$type]['lastSuccessfullyProcessedId'] = $lastSuccessfullyProcessedId;
        $progress['types'][$type]['statusDetails'] = "Processing: {$processedItemsCount}/{$totalItems}";
        $progress['types'][$type]['lastActivityTime'] = date('c');
        _ads_save_progress_data($progress);

    }

    // Targeted reindex: document data inside media index entries is read from
    // the document table only at index time, so the media referencing the
    // updated documents must be re-indexed for the changes to reach search.
    $reindexSummary = "";
    if (!empty($updatedDocumentIDs)) {
        logger("info", "[ADS] Reindexing media for " . count($updatedDocumentIDs) . " updated documents.");
        try {
            require_once(__DIR__ . "/../api/v1/modules/searchIndex.php");
            $reindexResult = searchIndexUpdateForDocuments($updatedDocumentIDs, $parliament, $db, $dbp);
            if (($reindexResult["meta"]["requestStatus"] ?? "") === "success") {
                $reindexData = $reindexResult["data"];
                $reindexSummary = " Reindexed " . ($reindexData["updated"] ?? 0) . " of " . ($reindexData["mediaTotal"] ?? 0) . " media items.";
                if (!empty($reindexData["failed"])) {
                    $reindexSummary .= " (" . $reindexData["failed"] . " media failed.)";
                    $typeSpecificErrors = true;
                    $overallErrorsEncountered = true;
                    $progress = _ads_get_progress_data();
                    $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => "Reindex: " . $reindexData["failed"] . " media items failed."];
                    _ads_save_progress_data($progress);
                }
            } else {
                $reindexErrorMsg = "[ADS] Media reindex failed: " . json_encode($reindexResult["errors"] ?? "unknown error");
                logger("error", $reindexErrorMsg);
                $reindexSummary = " Media reindex failed.";
                $typeSpecificErrors = true;
                $overallErrorsEncountered = true;
                $progress = _ads_get_progress_data();
                $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $reindexErrorMsg];
                _ads_save_progress_data($progress);
            }
        } catch (Exception $e) {
            $reindexErrorMsg = "[ADS] Exception during media reindex: " . $e->getMessage();
            logger("error", $reindexErrorMsg);
            $reindexSummary = " Media reindex failed.";
            $typeSpecificErrors = true;
            $overallErrorsEncountered = true;
            $progress = _ads_get_progress_data();
            $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $reindexErrorMsg];
            _ads_save_progress_data($progress);
        }
        logger("info", "[ADS]" . $reindexSummary);
    }

    $typeFinishMsg = "Finished processing. Processed: {$processedItemsCount}/{$totalItems}. Updated: " . count($updatedDocumentIDs) . "." . $reindexSummary;
    $finalTypeStatus = 'completed_successfully';

    if ($typeSpecificErrors) {
        $finalTypeStatus = 'partially_completed_with_errors';
        $progress = _ads_get_progress_data();
        $errorCount = isset($progress['types'][$type]['errors']) ? count($progress['types'][$type]['errors']) : 0;
        $typeFinishMsg .= " | Errors: {$errorCount}";
    }
    logger("info", "[ADS] {$typeFinishMsg}");

    $progress = _ads_get_progress_data();
    $progress['types'][$type]['status'] = $finalTypeStatus;
    $progress['types'][$type]['statusDetails'] = $typeFinishMsg;
    $progress['types'][$type]['processedItems'] = $processedItemsCount;
    $progress['types'][$type]['totalItems'] = max((int)($progress['types'][$type]['totalItems'] ?? 0), $processedItemsCount);
    $progress['types'][$type]['endTime'] = date('c');
    $progress['types'][$type]['lastActivityTime'] = date('c');
    $progress['types'][$type]['lastSuccessfullyProcessedId'] = $lastSuccessfullyProcessedId;
    _ads_save_progress_data($progress);
}

function _ads_record_startup_error($errorMessage, $entityType = null) {
    $progress = _ads_get_progress_data();
    $progress['globalStatus'] = 'error';
    $progress['activeType'] = null;
    $progress['statusDetails'] = $errorMessage;

    $typesToUpdate = [];
    if ($entityType) {
        $typesToUpdate = array_map('trim', explode(',', $entityType));
    }

    foreach ($typesToUpdate as $type) {
        if (!isset($progress['types'][$type])) {
            continue;
        }

        $progress['types'][$type]['status'] = 'error_critical';
        $progress['types'][$type]['statusDetails'] = $errorMessage;
        $progress['types'][$type]['endTime'] = date('c');
        $progress['types'][$type]['lastActivityTime'] = date('c');
        $progress['types'][$type]['errors'][] = [
            'timestamp' => date('c'),
            'message' => $errorMessage
        ];
    }

    _ads_save_progress_data($progress);
}


/**
 * Checks if this script is executed from CLI
 */
if (is_cli()) {

    $startTime = microtime(true); // Record start time

    /**
     * Check if lock file exists and checks its age
     */
    if (file_exists(__DIR__."/cronAdditionalDataService.lock")) {

        if (((time()-filemtime(__DIR__."/cronAdditionalDataService.lock")) >= ($config["time"]["warning"]*60)) && (time()-filemtime(__DIR__."/cronAdditionalDataService.lock")) <= ($config["time"]["ignore"]*60)) {

            if (filter_var($config["cronContactMail"], FILTER_VALIDATE_EMAIL)) {
                require_once(__DIR__.'/../modules/send-mail/functions.php');
                sendSimpleMail($config["cronContactMail"], "CronJob AdditionalDataService blocked", "CronJob AdditionalDataService was not executed. Its blocked now for over ".$config["time"]["warning"]." Minutes. Check the server and if its not running, remove the file: ".realpath(__DIR__."/cronAdditionalDataService.lock"));
                logger("warn", "CronJob was not executed and log file is there for > ".$config["time"]["warning"]." Minutes already.");

                exit;
            }

        } elseif ((time()-filemtime(__DIR__."/cronAdditionalDataService.lock")) >= ($config["time"]["ignore"]*60)) {

            logger("warn", "CronJob was blocked for > ".$config["time"]["ignore"]." Minutes now. Decided to ignore it and run it anyways.");
            unlink(realpath(__DIR__."/cronAdditionalDataService.lock"));

        } else {
            logger("warn", "Did not run the CronJob because its already running (for ".(time()-filemtime(__DIR__."/cronAdditionalDataService.lock"))." seconds now).");
            exit;

        }

    }

    // create lock file
    touch (__DIR__."/cronAdditionalDataService.lock");

    // Flag to ensure progress finalization happens once
    $progressFinalized = false;

    // Register shutdown function to ensure lock and progress are handled.
    register_shutdown_function(function() use (&$progressFinalized) {
        // Define CRON_ADS_PROGRESS_FILE inside shutdown if not already defined globally.
        if (!defined('CRON_ADS_PROGRESS_FILE')) {
            define("CRON_ADS_PROGRESS_FILE", __DIR__ . "/progress/cronAdditionalDataService.json");
        }

        $lockFile = __DIR__."/cronAdditionalDataService.lock";
        
        if (!$progressFinalized) {
            $progress = _ads_get_progress_data();
            if ($progress['globalStatus'] === 'running') {
                $activeType = $progress['activeType'] ?? 'unknown_type';
                
                $progress['globalStatus'] = 'error';
                $progress['activeType'] = null;
                
                if (isset($progress['types'][$activeType])) {
                    $progress['types'][$activeType]['status'] = 'error_final';
                    $progress['types'][$activeType]['statusDetails'] = 'Process terminated unexpectedly.';
                    $progress['types'][$activeType]['endTime'] = date('c');
                    $progress['types'][$activeType]['lastActivityTime'] = date('c');
                    $error_log_entry = [
                        "timestamp" => date('c'),
                        "message" => "Process terminated unexpectedly during active task: " . $activeType,
                        "itemId" => $progress['types'][$activeType]['lastSuccessfullyProcessedId'] ?? null
                    ];
                    $progress['types'][$activeType]['errors'][] = $error_log_entry;
                }
                
                _ads_save_progress_data($progress);
                logger("error", "[ADS] Shutdown: Process was in 'running' state for type '{$activeType}'. Marked as 'error_final'.");
            }
        }

        // Always try to remove the lock file
        if (file_exists($lockFile)) {
            if (@unlink($lockFile)) {
                logger("info", "[ADS] Shutdown: Lock file removed.");
            } else {
                logger("error", "[ADS] Shutdown: Failed to remove lock file: " . $lockFile);
            }
        }
    });

    logger("info","[ADS] cronAdditionalDataService started");
    
    // Initialize progress file
    $progress = _ads_get_progress_data();
    _ads_save_progress_data($progress);

    //get CLI parameter to $input
    $input = getopt("", ["type:", "ids:", "parliament:"]);

    if (empty($input["type"])) {

        logger("error","no type has been given. Exit.");
        $progress['globalStatus'] = 'idle';
        _ads_save_progress_data($progress);
        $progressFinalized = true;
        unlink(__DIR__."/cronAdditionalDataService.lock");
        exit;

    }

    logger("info","cronAdditionalDataService started for ".$input["type"]);

    $parliament = _ads_resolve_parliament($input["parliament"] ?? null);
    logger("info", "[ADS] Using parliament database: {$parliament}");

    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../api/v1/modules/externalData.php");
    require_once(__DIR__ . "/../api/v1/api.php");

    // Use helper function to get database connections
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        // The helper returns an error array on failure
        $errorMessage = $db['errors'][0]['detail'] ?? 'Platform database connection failed.';
        logger("error", "[ADS] CRITICAL: " . $errorMessage);
        _ads_record_startup_error($errorMessage, $input["type"]);
        $progressFinalized = true;
        exit;
    }

    $dbp = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($dbp)) {
        // The helper returns an error array on failure
        $errorMessage = $dbp['errors'][0]['detail'] ?? "Parliament database connection failed for {$parliament}.";
        logger("error", "[ADS] CRITICAL: " . $errorMessage);
        _ads_record_startup_error($errorMessage, $input["type"]);
        $progressFinalized = true;
        exit;
    }

    // [START OF NEW ENTITY PROCESSING LOGIC - MOVED HERE]
    // This entire block was previously outside the if(is_cli())
    // It replaces the old logic that was here.
    try {
        $entityTypesToProcess = array();
        $runAll = true;
        $overallErrorsEncountered = false; // For tracking if any entity type processing had issues

        // Determine which entity types to process based on $input["type"]
        // The $input variable is from getopt earlier in the is_cli() block.
        if (isset($input["type"])) {
            $runAll = false;
            // Valid types for ADS, ensure these match what externalData.php supports
            $validADSTypes = ["person", "memberOfParliament", "organisation", "term", "legalDocument", "officialDocument"];
            $typesRequested = explode(",", $input["type"]);
            
            foreach ($typesRequested as $typeReq) {
                $trimmedType = trim($typeReq);
                if (in_array($trimmedType, $validADSTypes)) {
                    if (!in_array($trimmedType, $entityTypesToProcess)) { // Avoid duplicates
                        $entityTypesToProcess[] = $trimmedType;
                    }
                } else {
                    logger("warn", "[ADS] Requested type '{$trimmedType}' is not a valid or supported type for ADS processing. Skipping.");
                }
            }

            if (empty($entityTypesToProcess)) {
                $noValidTypesMsg = "[ADS] No valid and supported entity types specified via --type parameter or all requested types were invalid. Valid types: " . implode(", ", $validADSTypes) . ". Exiting.";
                logger("warn", $noValidTypesMsg);
                $progress = _ads_get_progress_data();
                $progress['globalStatus'] = 'idle';
                $progress['statusDetails'] = $noValidTypesMsg; // A general status detail
                _ads_save_progress_data($progress);
                $progressFinalized = true;
                exit; // Exit if no valid types to process
            }
        } else {
            // Default: process all configured valid types if --type is not given
            $entityTypesToProcess = ["person", "memberOfParliament", "organisation", "term", "legalDocument", "officialDocument"]; // Default set
        }

        logger("info", "[ADS] Starting processing for types: " . implode(", ", $entityTypesToProcess));
        
        $progress = _ads_get_progress_data();
        $progress['globalStatus'] = 'running';
        $progress['statusDetails'] = "Preparing to process types: " . implode(", ", $entityTypesToProcess);
        _ads_save_progress_data($progress);


        foreach ($entityTypesToProcess as $type) {
            $processedItemsCountThisType = 0;
            $totalItemsThisType = 0;
            $typeSpecificErrors = false;
            $lastSuccessfullyProcessedIdThisType = null;
            
            $progress = _ads_get_progress_data();
            $progress['activeType'] = $type;
            _ads_save_progress_data($progress);

            if ($type === "officialDocument") {
                // Bulk path: batched ADS lookups + targeted media reindex.
                _ads_process_official_documents($db, $dbp, $parliament, $input, $overallErrorsEncountered);
                continue;
            }

            logger("info", "[ADS] Attempting to get total count for type: {$type}");
            // Determine total items for this type for progress reporting
            try {
                // Table names for entities should come from the PLATFORM config
                $tableName = '';
                $idColumn = '';
                $whereClause = '';
                switch ($type) {
                    case "person":
                        $tableName = $config["platform"]["sql"]["tbl"]["Person"];
                        $idColumn = 'PersonID';
                        $whereClause = $db->parse("WHERE PersonType = 'person' OR PersonType IS NULL");
                        break;
                    case "memberOfParliament":
                        $tableName = $config["platform"]["sql"]["tbl"]["Person"];
                        $idColumn = 'PersonID';
                        $whereClause = $db->parse("WHERE PersonType = 'memberOfParliament'");
                        break;
                    case "organisation":
                        $tableName = $config["platform"]["sql"]["tbl"]["Organisation"];
                        $idColumn = 'OrganisationID';
                        $whereClause = "";
                        break;
                    case "legalDocument":
                        $tableName = $config["platform"]["sql"]["tbl"]["Document"];
                        $idColumn = 'DocumentWikidataID';
                        $whereClause = $db->parse("WHERE DocumentType = 'legalDocument'");
                        break;
                    // officialDocument is handled by the bulk path above
                    case "term":
                        $tableName = $config["platform"]["sql"]["tbl"]["Term"];
                        $idColumn = 'TermID';
                        $whereClause = "";
                        break;
                    default:
                         $tableName = null;
                }
                // Fetch the full list of IDs once, up front. The per-item enrichment below
                // writes back to these same rows (it can rewrite PersonID/PersonType), so
                // paginating the live table with LIMIT/OFFSET would let a re-keyed row shift
                // into a later page and be processed twice -> the deterministic "N+1 / N"
                // off-by-one. Iterating a fixed snapshot makes processedItems == totalItems.
                $allIdsThisType = [];
                if ($tableName) {
                    $allIdsThisType = $db->getAll("SELECT ?n AS id FROM ?n ?p ORDER BY ?n", $idColumn, $tableName, $whereClause, $idColumn);
                    $totalItemsThisType = count($allIdsThisType);
                } else {
                    logger("warn", "[ADS] Unknown entity type '{$type}' encountered when trying to get count. Skipping count.");
                    $totalItemsThisType = 0;
                }
                logger("info", "[ADS] Total items for type {$type}: {$totalItemsThisType}");

            } catch (Exception $e) {
                $countErrorMsg = "[ADS] Error getting count for type {$type}: " . $e->getMessage();
                logger("error", $countErrorMsg);
                
                $progress = _ads_get_progress_data();
                $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $countErrorMsg];
                $progress['types'][$type]['statusDetails'] = "Error getting count. " . $e->getMessage();
                $progress['types'][$type]['status'] = 'error';
                _ads_save_progress_data($progress);
                
                $overallErrorsEncountered = true;
                $typeSpecificErrors = true;
                continue; // Skip to next entity type if count fails
            }

            $progress = _ads_get_progress_data();
            $progress['types'][$type]['status'] = 'running';
            $progress['types'][$type]['totalItems'] = (int)$totalItemsThisType;
            $progress['types'][$type]['processedItems'] = 0;
            $progress['types'][$type]['startTime'] = date('c');
            $progress['types'][$type]['endTime'] = null;
            $progress['types'][$type]['statusDetails'] = "Starting processing. Total items: {$totalItemsThisType}";
            $progress['types'][$type]['errors'] = [];
            $progress['types'][$type]['lastSuccessfullyProcessedId'] = null;
            _ads_save_progress_data($progress);
            
            logger("info", "[ADS] Processing type: {$type}. Total items: {$totalItemsThisType}");

            $offset = 0;
            // Number of pre-fetched IDs to process per chunk (controls progress-write cadence).
            $limit = 5;

            if ($totalItemsThisType > 0) {
                while ($offset < $totalItemsThisType) {
                    $items = [];
                    try {
                        $typeForAPI = $type;
                        // Slice the pre-fetched ID snapshot instead of re-querying the live
                        // table. The enrichment below mutates these same rows, so a fresh
                        // LIMIT/OFFSET query would shift under us and double-count a boundary
                        // row (the deterministic "N+1 / N" off-by-one). The snapshot and the
                        // total were captured together, above.
                        $items = array_slice($allIdsThisType, $offset, $limit);

                    } catch (Exception $e) {
                        $fetchErrorMsg = "[ADS] Error fetching batch for type {$type} (offset {$offset}): " . $e->getMessage();
                        logger("error", $fetchErrorMsg);
                        $overallErrorsEncountered = true;
                        $typeSpecificErrors = true;
                        
                        $progress = _ads_get_progress_data();
                        $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $fetchErrorMsg];
                        $progress['types'][$type]['statusDetails'] = "Error fetching items. Skipping remaining items for this type.";
                        $progress['types'][$type]['status'] = 'error';
                        _ads_save_progress_data($progress);
                        break; // Break from while loop for this type
                    }

                    if (empty($items)) {
                        logger("warn", "[ADS] Fetched empty batch for type {$type} at offset {$offset}. Ending processing for this type.");
                        break; 
                    }

                    foreach ($items as $item) {
                        $itemId = $item["id"];
                        $apiCallParams = [
                            "action" => "externalData",
                            "itemType" => "update-entities",
                            "ids" => [$itemId],
                            "type" => [$typeForAPI], 
                            "parliament" => $parliament
                        ];
                        
                        try {
                            $response = apiV1($apiCallParams, $db, $dbp); 
                            
                            if (!isset($response["meta"]["requestStatus"]) || $response["meta"]["requestStatus"] !== "success") {
                                $errorDetail = "Unknown API error";
                                if (!empty($response["errors"])) {
                                    $errorDetail = json_encode($response["errors"]);
                                }
                                $fullApiErrorMsg = "[ADS] API error updating {$type} ID {$itemId}: " . $errorDetail;
                                logger("error", $fullApiErrorMsg);
                                $typeSpecificErrors = true;
                                $overallErrorsEncountered = true;
                                
                                // Create a simpler error for the UI
                                $simpleError = "API error: " . ($response["errors"][0]["title"] ?? "Unknown failure.");
                                $info = $response["errors"][0]["meta"]["details"][0]["error"][0]["info"] ?? null;
                                if ($info) {
                                    // Extract the core message, e.g., "Could not update Item in database"
                                    $simpleError = explode(':', $info, 2)[0];
                                }
                                $uiMessage = "Error on ID {$itemId}: {$simpleError}";

                                $progress = _ads_get_progress_data();
                                $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $uiMessage, "itemId" => $itemId];
                                _ads_save_progress_data($progress);

                            } else {
                                $lastSuccessfullyProcessedIdThisType = $itemId;
                            }
                        } catch (Exception $e) {
                            $exceptionMsg = "[ADS] Exception updating {$type} ID {$itemId}: " . $e->getMessage();
                            logger("error", $exceptionMsg);
                            $typeSpecificErrors = true;
                            $overallErrorsEncountered = true;
                            
                            $progress = _ads_get_progress_data();
                            $progress['types'][$type]['errors'][] = ["timestamp" => date('c'), "message" => $exceptionMsg, "itemId" => $itemId];
                            _ads_save_progress_data($progress);
                        }
                        $processedItemsCountThisType++;

                        // Update progress file every item
                        $progress = _ads_get_progress_data();
                        $progress['types'][$type]['processedItems'] = $processedItemsCountThisType;
                        $progress['types'][$type]['lastSuccessfullyProcessedId'] = $lastSuccessfullyProcessedIdThisType;
                        $progress['types'][$type]['statusDetails'] = "Processing: {$processedItemsCountThisType}/{$totalItemsThisType}";
                        $progress['types'][$type]['lastActivityTime'] = date('c');
                        _ads_save_progress_data($progress);
                    }
                    $offset += count($items); 
                }
            }

            $typeFinishMsg = "Finished processing. Processed: {$processedItemsCountThisType}/{$totalItemsThisType}.";
            $finalTypeStatus = 'completed_successfully';

            if ($typeSpecificErrors) {
                $finalTypeStatus = 'partially_completed_with_errors';
                $progress = _ads_get_progress_data();
                $errorCount = isset($progress['types'][$type]['errors']) ? count($progress['types'][$type]['errors']) : 0;
                $typeFinishMsg = "Finished processing. Processed: {$processedItemsCountThisType}/{$totalItemsThisType} | Errors: {$errorCount}";
            }
            logger("info", "[ADS] {$typeFinishMsg}");
            
            $progress = _ads_get_progress_data();
            $progress['types'][$type]['status'] = $finalTypeStatus;
            $progress['types'][$type]['statusDetails'] = $typeFinishMsg;
            $progress['types'][$type]['processedItems'] = $processedItemsCountThisType;
            // Safety net: max can never read below the processed count, so the UI never
            // shows "N+1 / N" even if the working set changed in some future code path.
            $progress['types'][$type]['totalItems'] = max((int)($progress['types'][$type]['totalItems'] ?? 0), $processedItemsCountThisType);
            $progress['types'][$type]['endTime'] = date('c');
            $progress['types'][$type]['lastActivityTime'] = date('c');
            $progress['types'][$type]['lastSuccessfullyProcessedId'] = $lastSuccessfullyProcessedIdThisType;
            _ads_save_progress_data($progress);
            
        } // End foreach $entityTypesToProcess

        // Finalize the global status
        $progress = _ads_get_progress_data();
        $progress['globalStatus'] = 'idle';
        $progress['activeType'] = null;
        if ($overallErrorsEncountered) {
            logger("info", "[ADS] Processing completed for specified types: " . implode(", ", $entityTypesToProcess) . ". Errors were encountered.");
        } else {
            logger("info", "[ADS] Processing completed for specified types: " . implode(", ", $entityTypesToProcess) . ". All tasks completed without errors.");
        }
        _ads_save_progress_data($progress);
        
        $progressFinalized = true;

    } catch (Exception $e) {
        $criticalErrorMsg = "[ADS] CRITICAL UNHANDLED EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
        logger("CRITICAL", $criticalErrorMsg);
        
        $progress = _ads_get_progress_data();
        $activeType = $progress['activeType'] ?? 'unknown';
        $progress['globalStatus'] = 'error_critical';
        $progress['activeType'] = null;
        if(isset($progress['types'][$activeType])) {
            $progress['types'][$activeType]['status'] = 'error_critical';
            $progress['types'][$activeType]['errors'][] = ['timestamp' => date('c'), 'message' => $criticalErrorMsg];
        }
        _ads_save_progress_data($progress);
        
        $progressFinalized = true;
    } finally {
        if (!$progressFinalized) {
             logger("warn", "[ADS] Reached 'finally' without explicit finalization. Attempting to finalize as error.");
             $progress = _ads_get_progress_data();
             $progress['globalStatus'] = 'error';
             $progress['activeType'] = null;
             _ads_save_progress_data($progress);
        }
        $progressFinalized = true; 
        
        logger("info", "[ADS] cronAdditionalDataService main processing logic finished.");
    }

} // Closing brace for if(is_cli())

?>