<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
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
 * TODO: IDs
 */

$config["time"]["warning"] = 30; //minutes
$config["time"]["ignore"] = 90; //minutes
$config["cronContactMail"] = ""; //minutes

// Define path for the progress file
define("CRON_ADS_PROGRESS_FILE", __DIR__ . "/progress_status/cronAdditionalDataService.json");

require_once(__DIR__ . "/../modules/utilities/functions.php");
require_once(__DIR__ . "/../modules/utilities/functions.api.php");

/**
 * @param string $type
 * @param string $msg
 *
 * Writes to log file
 */

function logger($type = "info",$msg) {
    file_put_contents(__DIR__."/cronAdditionalDataService.log",date("Y-m-d H:i:s")." - ".$type.": ".$msg."\n",FILE_APPEND );
}





/**
 * @param string $message
 *
 * Sends a message to CLI
 *
 */
function cliLog($message) {
    $message = date("Y.m.d H:i:s:u") . " - ". $message.PHP_EOL;
    print($message);
    flush();
    ob_flush();
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

                mail($config["cronContactMail"],"CronJob AdditionalDataService blocked", "CronJob AdditionalDataService was not executed. Its blocked now for over ".$config["time"]["warning"]." Minutes. Check the server and if its not running, remove the file: ".realpath(__DIR__."/cronAdditionalDataService.lock"));
                logger("warn", "CronJob was not executed and log file is there for > ".$config["time"]["warning"]." Minutes already.");

                exit;
            }

        } elseif ((time()-filemtime(__DIR__."/cronAdditionalDataService.lock")) >= ($config["time"]["ignore"]*60)) {

            logger("warn", "CronJob was blocked for > ".$config["time"]["ignore"]." Minutes now. Decided to ignore it and run it anyways.");
            unlink(realpath(__DIR__."/cronAdditionalDataService.lock"));

        } else {
            logger("warn", "Did not run the CronJob because its already running (for ".(time()-filemtime(__DIR__."/cronAdditionalDataService.lock"))." seconds now).");
            cliLog("cronAdditionalDataService still running");
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
            define("CRON_ADS_PROGRESS_FILE", __DIR__ . "/progress_status/cronAdditionalDataService.json");
        }

        $lockFile = __DIR__."/cronAdditionalDataService.lock";

        // Check if helper functions exist before calling them, as a safeguard. Now checking for Base versions.
        if (function_exists('finalizeBaseProgressFile') && function_exists('logErrorToBaseProgressFile')) {
            if (!$progressFinalized && file_exists(CRON_ADS_PROGRESS_FILE)) {
                $currentProgressJson = @file_get_contents(CRON_ADS_PROGRESS_FILE);
                if ($currentProgressJson !== false) {
                    $currentProgress = json_decode($currentProgressJson, true);
                    if (is_array($currentProgress) && isset($currentProgress["status"]) && $currentProgress["status"] === "running") {
                        $activeType = $currentProgress["activeType"] ?? 'unknown_type';
                        logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, "AdditionalDataService cron exited unexpectedly or crashed while processing: " . $activeType, $currentProgress["lastSuccessfullyProcessedId"] ?? null);
                        finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, "error_final", "Process terminated unexpectedly during active task: " . $activeType);
                        logger("error", "[ADS] Shutdown: Process was in 'running' state for type '{$activeType}'. Marked as 'error_final'.");
                    }
                } else {
                    logger("error", "[ADS] Shutdown: Could not read ADS progress file to check status.");
                }
            } else if (!$progressFinalized) { // File doesn't exist or error before init
                $details = "Process terminated unexpectedly before or during initialization, or progress file was missing.";
                // Attempt to create a final error state file if possible
                $initialErrorData = [
                    'processName' => 'additionalDataService',
                    'activeType' => 'unknown_pre_init_crash',
                    'statusDetails' => $details
                    // other relevant fields from ADS structure can be added with null/0
                ];
                if(function_exists('initBaseProgressFile')) initBaseProgressFile(CRON_ADS_PROGRESS_FILE, $initialErrorData);
                finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, "error_early_crash", $details);
                logger("error", "[ADS] Shutdown: Progress not finalized. ADS process possibly exited before full initialization or after progress file deletion. Marked as error.");
            }
        } else {
            logger("error", "[ADS] Shutdown: Base helper functions for progress reporting not available. Cannot update ADS progress file status.");
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

    // Initial ADS progress data structure
    $adsInitialProgressData = [
        "processName" => "additionalDataService",
        "activeType" => null, // Will be set when a specific type starts processing
        "statusDetails" => "Service idle, awaiting tasks.",
        "totalItems" => 0,
        "processedItems" => 0,
        "lastSuccessfullyProcessedId" => null
    ];

    if (function_exists('initBaseProgressFile')) {
        initBaseProgressFile(CRON_ADS_PROGRESS_FILE, $adsInitialProgressData);
    } else {
        logger("error", "[ADS] initBaseProgressFile function not available at start.");
    }

    //get CLI parameter to $input
    $input = getopt(null, ["type:","ids:"]);

    if (empty($input["type"])) {

        logger("error","no type has been given. Exit.");
        unlink(__DIR__."/cronAdditionalDataService.lock");
        exit;

    }

    logger("info","cronAdditionalDataService started for ".$input["type"]);


    $parliament = ((isset($input["parliament"])) ? $input["parliament"] : "DE");

    require_once(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../api/v1/modules/externalData.php");



    try {

        $db = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));

    } catch (exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Database connection error";
        $errorarray["detail"] = "Connecting to platform database failed";
        array_push($return["errors"], $errorarray);
        echo json_encode($return);
        unlink(__DIR__."/cronAdditionalDataService.lock");
        exit;

    }
    try {

        $dbp = new SafeMySQL(array(
            'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
            'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
            'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
            'db'	=> $config["parliament"][$parliament]["sql"]["db"]
        ));

    } catch (exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Database connection error";
        $errorarray["detail"] = "Connecting to parliament database failed";
        array_push($return["errors"], $errorarray);
        echo json_encode($return);
        unlink(__DIR__."/cronAdditionalDataService.lock");
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
            // For now, using person, organisation, document, term as per the new logic design
            $validADSTypes = array("person", "organisation", "document", "term"); 
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
                if (function_exists('finalizeBaseProgressFile')) finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, "idle", $noValidTypesMsg);
                $progressFinalized = true; // Mark as finalized to prevent shutdown function from overwriting
                // unlink(__DIR__."/cronAdditionalDataService.lock"); // Lock is removed by shutdown
                exit; // Exit if no valid types to process
            }
        } else {
            // Default: process all configured valid types if --type is not given
            $entityTypesToProcess = array("person", "organisation", "document", "term"); // Default set
        }

        logger("info", "[ADS] Starting processing for types: " . implode(", ", $entityTypesToProcess));
        if (function_exists('updateBaseProgressFile')) {
            updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [
                "statusDetails" => "Preparing to process types: " . implode(", ", $entityTypesToProcess),
                "status"=>"running", // Set main status to running
                "activeType" => null, // No specific type is active yet
                "totalItems" => 0,    // Overall total not applicable here, will be per type
                "processedItems" => 0
            ]);
        }

        foreach ($entityTypesToProcess as $type) {
            $processedItemsCountThisType = 0;
            $totalItemsThisType = 0;
            $typeSpecificErrors = false;
            $lastSuccessfullyProcessedIdThisType = null;

            logger("info", "[ADS] Attempting to get total count for type: {$type}");
            // Determine total items for this type for progress reporting
            try {
                // Ensure $dbp is the parliament-specific DB connection
                // Table names should come from $config for robustness
                $tableName = '';
                switch ($type) {
                    case "person":
                        $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Person"] ?? 'person';
                        $totalItemsThisType = $dbp->getOne("SELECT COUNT(*) FROM ?n", $tableName);
                        break;
                    case "organisation":
                        $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Organisation"] ?? 'organisation';
                        $totalItemsThisType = $dbp->getOne("SELECT COUNT(*) FROM ?n", $tableName);
                        break;
                    case "document": // Assuming 'document' refers to a general document table for ADS
                        $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Document"] ?? 'document';
                        // Add specific conditions if ADS processes only certain document sub-types
                        $totalItemsThisType = $dbp->getOne("SELECT COUNT(*) FROM ?n", $tableName);
                        break;
                    case "term":
                        $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Term"] ?? 'term';
                        $totalItemsThisType = $dbp->getOne("SELECT COUNT(*) FROM ?n", $tableName);
                        break;
                    default:
                        logger("warn", "[ADS] Unknown entity type '{$type}' encountered when trying to get count. Skipping count.");
                        $totalItemsThisType = 0; // Cannot determine
                }
                logger("info", "[ADS] Total items for type {$type}: {$totalItemsThisType}");

            } catch (Exception $e) {
                $countErrorMsg = "[ADS] Error getting count for type {$type}: " . $e->getMessage();
                logger("error", $countErrorMsg);
                if (function_exists('logErrorToBaseProgressFile')) logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, $countErrorMsg, "N/A - Count Query for {$type}");
                $overallErrorsEncountered = true;
                $typeSpecificErrors = true;
                if (function_exists('updateBaseProgressFile')) {
                     updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [ // Update general status, but also reflect this type is problematic
                        "statusDetails" => "Error getting count for {$type}, this type will be skipped.",
                        "activeType" => $type, // Show this type was attempted
                     ]);
                }
                continue; // Skip to next entity type if count fails
            }

            if (function_exists('updateBaseProgressFile')) {
                updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [
                    "activeType" => $type,
                    "status" => "running", // Ensure status is running for this type
                    "totalItems" => (int)$totalItemsThisType,
                    "processedItems" => 0,
                    "statusDetails" => "Starting processing for type: {$type}. Total items: {$totalItemsThisType}",
                    "errors" => [], // Clear errors from previous type for this specific activeType context in progress file
                    "lastSuccessfullyProcessedId" => null
                ]);
            }
            logger("info", "[ADS] Processing type: {$type}. Total items: {$totalItemsThisType}");

            $offset = 0;
            $limit = $config['ads']['batchSizeCli'] ?? 50; // Use a configurable batch size, default 50

            if ($totalItemsThisType == 0) {
                 logger("info", "[ADS] No items to process for type: {$type}");
                 if (function_exists('updateBaseProgressFile')) {
                    updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [
                        "statusDetails" => "No items for type {$type}. Moving to next type or finishing.",
                        "processedItems" => 0 // Explicitly set for this type
                    ]);
                 }
            } else {
                while ($offset < $totalItemsThisType) {
                    $items = array();
                    $idColumn = 'id'; // Default ID column name
                    // Determine ID column and table based on type
                    $currentBatchIds = [];
                    try {
                        $tableName = '';
                        // Assuming standard ID columns like PersonID, OrganisationID, DocumentID, TermID or a generic 'id'
                        // These should align with what externalData.php expects for entityId
                        switch ($type) {
                            case "person":
                                $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Person"] ?? 'person';
                                $idColumn = $config["parliament"][$parliament]["sql"]["idcol"]["Person"] ?? 'PersonID';
                                $items = $dbp->getAll("SELECT ?n AS id FROM ?n ORDER BY ?n LIMIT ?i, ?i", $idColumn, $tableName, $idColumn, $offset, $limit);
                                break;
                            case "organisation":
                                $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Organisation"] ?? 'organisation';
                                $idColumn = $config["parliament"][$parliament]["sql"]["idcol"]["Organisation"] ?? 'OrganisationID';
                                $items = $dbp->getAll("SELECT ?n AS id FROM ?n ORDER BY ?n LIMIT ?i, ?i", $idColumn, $tableName, $idColumn, $offset, $limit);
                                break;
                            case "document":
                                $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Document"] ?? 'document';
                                $idColumn = $config["parliament"][$parliament]["sql"]["idcol"]["Document"] ?? 'DocumentID';
                                // Potentially add WHERE clauses if only certain documents are processed by ADS
                                $items = $dbp->getAll("SELECT ?n AS id FROM ?n ORDER BY ?n LIMIT ?i, ?i", $idColumn, $tableName, $idColumn, $offset, $limit);
                                break;
                            case "term":
                                $tableName = $config["parliament"][$parliament]["sql"]["tbl"]["Term"] ?? 'term';
                                $idColumn = $config["parliament"][$parliament]["sql"]["idcol"]["Term"] ?? 'TermID';
                                $items = $dbp->getAll("SELECT ?n AS id FROM ?n ORDER BY ?n LIMIT ?i, ?i", $idColumn, $tableName, $idColumn, $offset, $limit);
                                break;
                            default:
                                logger("warn", "[ADS] Unknown entity type '{$type}' for fetching batch. Batch fetch skipped.");
                                $items = []; // Ensure items is empty
                        }
                         if (!empty($items)) {
                            $currentBatchIds = array_column($items, 'id');
                         }

                    } catch (Exception $e) {
                        $fetchErrorMsg = "[ADS] Error fetching batch for type {$type} (offset {$offset}): " . $e->getMessage();
                        logger("error", $fetchErrorMsg);
                        if (function_exists('logErrorToBaseProgressFile')) logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, $fetchErrorMsg, "N/A - Batch Fetch for type {$type}, Offset {$offset}");
                        $overallErrorsEncountered = true;
                        $typeSpecificErrors = true;
                        if (function_exists('updateBaseProgressFile')) updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, ["statusDetails" => "Error fetching items for {$type}. Skipping remaining items for this type."]);
                        break; // Break from while loop for this type if fetching fails critically
                    }

                    if (empty($items)) {
                        // This might happen if totalItemsThisType was a bit off or items were deleted during processing.
                        logger("warn", "[ADS] Fetched empty batch for type {$type} at offset {$offset}, though total was {$totalItemsThisType}. Ending processing for this type or batch was genuinely empty.");
                        break; 
                    }

    foreach ($items as $item) {
                        $itemId = $item["id"]; // id is now consistently 'id' due to "AS id" in queries
                        $apiCallParams = [
                            "action" => "externalData",   // Correct API action for ADS
                            "itemType" => "update-entities", // Correct itemType for ADS update
                            "entityType" => $type,
                            "entityId" => $itemId,
                            "parliament" => $parliament // Pass parliament context if needed by externalData.php
                        ];
                        
                        try {
                            // apiV1 is included from api.php which should be required by this script
                            // $db is platform, $dbp is parliament specific
                            $response = apiV1($apiCallParams, $db, $dbp); 
                            
                            if (!isset($response["meta"]["requestStatus"]) || $response["meta"]["requestStatus"] !== "success") {
                                $apiErrorMsg = "[ADS] API error updating {$type} ID {$itemId}: " . ($response["errors"][0]["detail"] ?? json_encode($response["errors"] ?? 'Unknown API error'));
                                logger("error", $apiErrorMsg);
                                if (function_exists('logErrorToBaseProgressFile')) logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, $apiErrorMsg, $itemId, ["type" => $type]);
                                $overallErrorsEncountered = true;
                                $typeSpecificErrors = true;
                            } else {
                                $lastSuccessfullyProcessedIdThisType = $itemId;
                                if (function_exists('updateBaseProgressFile')) updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, ["lastSuccessfullyProcessedId" => $lastSuccessfullyProcessedIdThisType]);
                            }
                        } catch (Exception $e) {
                            $exceptionMsg = "[ADS] Exception updating {$type} ID {$itemId} via apiV1(externalData): " . $e->getMessage();
                            logger("error", $exceptionMsg);
                            if (function_exists('logErrorToBaseProgressFile')) logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, $exceptionMsg, $itemId, ["type" => $type]);
                            $overallErrorsEncountered = true;
                            $typeSpecificErrors = true;
                        }
                        $processedItemsCountThisType++;
                        if (function_exists('updateBaseProgressFile')) {
                            updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [
                                "processedItems" => $processedItemsCountThisType,
                                "statusDetails" => "Processing {$type}: {$processedItemsCountThisType}/{$totalItemsThisType}, Last ID: {$itemId}"
                            ]);
                        }
                    } // End foreach $item in $items
                    $offset += count($items); 
                } // End while $offset < $totalItemsThisType
            } // End else for if ($totalItemsThisType == 0)

            $typeFinishMsg = "[ADS] Finished processing type: {$type}. Processed: {$processedItemsCountThisType}/{$totalItemsThisType}.";
            if ($typeSpecificErrors) $typeFinishMsg .= " Some errors encountered for this type.";
            logger("info", $typeFinishMsg);
            
            if (function_exists('updateBaseProgressFile')) {
                $remainingTypes = array_slice($entityTypesToProcess, array_search($type, $entityTypesToProcess) + 1);
                $nextStatusDetails = $typeFinishMsg;
                if (!empty($remainingTypes)) {
                    $nextStatusDetails .= " Preparing for next type: " . $remainingTypes[0];
            } else {
                    $nextStatusDetails .= " All specified types processed.";
                }
                updateBaseProgressFile(CRON_ADS_PROGRESS_FILE, [
                    "statusDetails" => $nextStatusDetails,
                    // "activeType" => null, // Set to null only if truly going idle or handled by finalize
                    // Keep current activeType to show what just finished, finalize will set overall.
                ]);
            }
        } // End foreach $entityTypesToProcess

        $finalOverallStatusDetails = "[ADS] Processing completed for specified types: " . implode(", ", $entityTypesToProcess) . ".";
        $finalProgressStatus = "completed_successfully";
        if ($overallErrorsEncountered) {
            $finalProgressStatus = "partially_completed_with_errors";
            $finalOverallStatusDetails .= " However, some errors were encountered during the process. Check logs and progress file errors array.";
        } else {
             $finalOverallStatusDetails .= " All tasks completed without errors.";
        }
        logger("info", $finalOverallStatusDetails);
        if (function_exists('finalizeBaseProgressFile')) {
            finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, $finalProgressStatus, $finalOverallStatusDetails);
        }
        $progressFinalized = true; // Mark as finalized

        } catch (Exception $e) {
        $criticalErrorMsg = "[ADS] CRITICAL unhandled exception in cronAdditionalDataService main processing block: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
        logger("CRITICAL", $criticalErrorMsg);
        if (function_exists('logErrorToBaseProgressFile') && function_exists('finalizeBaseProgressFile')) {
            logErrorToBaseProgressFile(CRON_ADS_PROGRESS_FILE, $criticalErrorMsg, "CRITICAL_EXCEPTION_MAIN_BLOCK");
            finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, "error_final", "Critical unhandled exception occurred: " . $e->getMessage());
        } else {
            logger("error", "[ADS] CRITICAL EXCEPTION (main block): Base helper functions for progress logging not available. " . $criticalErrorMsg);
        }
        $progressFinalized = true; // Mark as finalized even on critical error
    } finally {
        // This 'finally' is for the main processing try-catch.
        // Ensure $progressFinalized is set so shutdown function knows if normal completion/finalization occurred.
        if (!$progressFinalized && function_exists('finalizeBaseProgressFile')) {
             // If loop exited unexpectedly without setting $progressFinalized (e.g. an exit call within the loop not caught)
             logger("warn", "[ADS] Main processing block 'finally' reached without explicit finalization. Attempting to finalize as error.");
             finalizeBaseProgressFile(CRON_ADS_PROGRESS_FILE, "error_final", "Process ended unexpectedly within main block.");
        }
        $progressFinalized = true; 
        
        logger("info", "[ADS] cronAdditionalDataService main processing logic finished or caught critical error. Shutdown function will handle lock file removal.");
        // The script will now naturally proceed to the end of the is_cli() block.
        // The main lock file removal and final logging of execution time should be done by the shutdown function,
        // or if we want explicit timing before shutdown, it can be here.
        // For simplicity, relying on shutdown for lock removal.
    }
    // [END OF NEW ENTITY PROCESSING LOGIC]

} // Closing brace for if(is_cli())

?>