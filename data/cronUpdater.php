<?php
require_once(__DIR__ . "/../config.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

ini_set('memory_limit', '512M');
/**
 * This script expects to be run via CLI only
 *
 * it can have the following parameter
 * --parliament "DE" | default value will be "DE"
 *
 * --justUpdateSearchIndex "true" | (default not enabled) if this is set it will get all MediaItems from API or just the Items with given with following parameter (separeted IDs by comma)
 * --ids "DE-0190002013,DE-0190002014" | (default not enabled) comma separated list of MediaIDs which get updated if --justUpdateSearchIndex = true too
 * --triggerEnhancedAfterCompletion | (default not enabled) if this is set with --justUpdateSearchIndex, enhanced indexing will be triggered asynchronously after main index completion
 *
 * --ignoreGit = "true" | (default not enabled) just processes session files from $meta["inputDir"] and dont do anything with git
 *
 * this script will exit if cronUpdater.lock file is present.
 * If the lock file is older than $config["time"]["warning"] a mail will be send to $config["cronContactMail"]
 * If the lock file is older than $config["time"]["ignore"] the lock file will be removed. In this case we expect there was a crash
 *
 *
 */

$config["time"]["warning"] = 30; //minutes
$config["time"]["ignore"] = 90; //minutes
$config["cronContactMail"] = ""; //minutes

$meta["inputDir"] = __DIR__ . "/input/";
$meta["doneDir"] = __DIR__ . "/done/";
$meta["preserveFiles"] = true;

// Define path for the progress file for the DATA IMPORT part
define("CRONUPDATER_PROGRESS_FILE", __DIR__ . "/progress/cronUpdater.json");

require_once(__DIR__ . "/../modules/utilities/functions.php");

/**
 * @param string $type
 * @param string $msg
 *
 * Writes to log file
 */

function logger($type = "info",$msg) {
    file_put_contents(__DIR__."/cronUpdater.log",date("Y-m-d H:i:s")." - ".$type.": ".$msg."\n",FILE_APPEND );
}

/**
 * Checks if this script is executed from CLI
 */
if (is_cli()) {


    /**
     * Check if lock file exists and checks its age
     */
    if (file_exists(__DIR__."/cronUpdater.lock")) {

        if (((time()-filemtime(__DIR__."/cronUpdater.lock")) >= ($config["time"]["warning"]*60)) && (time()-filemtime(__DIR__."/cronUpdater.lock")) <= ($config["time"]["ignore"]*60)) {

            if (filter_var($config["cronContactMail"], FILTER_VALIDATE_EMAIL)) {
                require_once(__DIR__.'/../modules/send-mail/functions.php');
                sendSimpleMail($config["cronContactMail"], "CronJob blocked", "CronJob was not executed. Its blocked now for over ".$config["time"]["warning"]." Minutes. Check the server and if its not running, remove the file: ".realpath(__DIR__."/cronUpdater.lock"));
                logger("warn", "CronJob was not executed and log file is there for > ".$config["time"]["warning"]." Minutes already.");

                exit;
            }

        } elseif ((time()-filemtime(__DIR__."/cronUpdater.lock")) >= ($config["time"]["ignore"]*60)) {

            logger("warn", "CronJob was blocked for > ".$config["time"]["ignore"]." Minutes now. Decided to ignore it and run it anyways.");
            unlink(realpath(__DIR__."/cronUpdater.lock"));

        } else {
            logger("warn", "CronJob was blocked because its already running (for ".(time()-filemtime(__DIR__."/cronUpdater.lock"))." seconds)");
            cliLog("cronUpdater still running");
            exit;

        }

    }

    // create lock file
    touch (__DIR__."/cronUpdater.lock");

    //get CLI parameter to $input
    $input = getopt(null, ["parliament:","justUpdateSearchIndex::","ids:","ignoreGit::","triggerEnhancedAfterCompletion::"]);
    $parliament = ((!empty($input["parliament"])) ? $input["parliament"] : "DE");
    $isDataImportMode = !isset($input["justUpdateSearchIndex"]);
    $progressFinalized = false; // Flag for shutdown handler

    // --- START: Define Search Index Progress File Path (used in search index mode) ---
    // This helper function comes from the restored api/v1/modules/searchIndex.php but we need it here.
    // To avoid including the whole file, we define a compatible version.
    $searchIndexProgressFilePath = __DIR__ . "/progress/searchIndex_" . strtoupper($parliament) . ".json";
    // --- END: Define Search Index Progress File Path ---


    // Register shutdown function to ensure lock and progress are handled.
    register_shutdown_function(function() use ($isDataImportMode, &$progressFinalized, $searchIndexProgressFilePath) {
        
        // Handle DATA IMPORT progress finalization on crash
        if ($isDataImportMode && function_exists('finalizeBaseProgressFile')) {
            if (file_exists(CRONUPDATER_PROGRESS_FILE) && !$progressFinalized) {
                $currentProgressJson = @file_get_contents(CRONUPDATER_PROGRESS_FILE);
                $currentProgress = $currentProgressJson ? json_decode($currentProgressJson, true) : null;
                if (is_array($currentProgress) && $currentProgress["status"] === "running") {
                    $logMessageDetail = "CronUpdater (Data Import) exited unexpectedly while 'running'.";
                    logErrorToBaseProgressFile(CRONUPDATER_PROGRESS_FILE, $logMessageDetail, "CRASH");
                    finalizeBaseProgressFile(CRONUPDATER_PROGRESS_FILE, "error_shutdown", "Process terminated unexpectedly.");
                    logger("error", "CronUpdater (Data Import) shutdown: " . $logMessageDetail);
                }
            }
        } 
        // Handle SEARCH INDEX progress finalization on crash
        else if (!$isDataImportMode && function_exists('finalizeBaseProgressFile')) {
            if (file_exists($searchIndexProgressFilePath) && !$progressFinalized) {
                 $currentProgressJson = @file_get_contents($searchIndexProgressFilePath);
                 $currentProgress = $currentProgressJson ? json_decode($currentProgressJson, true) : null;
                 if (is_array($currentProgress) && $currentProgress["status"] === "running") {
                    $logMessageDetail = "CronUpdater (Search Index) exited unexpectedly while 'running'.";
                    logErrorToBaseProgressFile($searchIndexProgressFilePath, $logMessageDetail, "CRASH");
                    finalizeBaseProgressFile($searchIndexProgressFilePath, "error_shutdown", "Process terminated unexpectedly.");
                    logger("error", "CronUpdater (Search Index) shutdown: " . $logMessageDetail);
                }
            }
        }

        // Always try to remove the lock file
        if (file_exists(__DIR__."/cronUpdater.lock")) {
            @unlink(__DIR__."/cronUpdater.lock");
        }
    });

    logger("info","cronUpdater started in ". ($isDataImportMode ? 'Data Import' : 'Search Index Update') ." mode.");

    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../modules/utilities/functions.api.php");
    require_once(__DIR__ . "/../api/v1/api.php");
    require_once(__DIR__ . "/../api/v1/modules/searchIndex.php");


    try {
        $db = new SafeMySQL(['host' => $config["platform"]["sql"]["access"]["host"], 'user' => $config["platform"]["sql"]["access"]["user"], 'pass' => $config["platform"]["sql"]["access"]["passwd"], 'db' => $config["platform"]["sql"]["db"]]);
    } catch (exception $e) {
        logger("error", "connection to platform database failed. ".$e->getMessage());
        exit;
    }
    try {
        $dbp = new SafeMySQL(['host' => $config["parliament"][$parliament]["sql"]["access"]["host"], 'user' => $config["parliament"][$parliament]["sql"]["access"]["user"], 'pass' => $config["parliament"][$parliament]["sql"]["access"]["passwd"], 'db' => $config["parliament"][$parliament]["sql"]["db"]]);
    } catch (exception $e) {
        logger("error", "connection to parliament database failed: ".$e->getMessage());
        exit;
    }

    /**
     *
     * Just update the Search Index from API/Database
     *
     **/
    if (isset($input["justUpdateSearchIndex"])) {

        $ids = [];
        if (!empty($input["ids"])) {
            $tmpIDs = explode(",", $input["ids"]);
            foreach ($tmpIDs as $tmpID) {
                if (preg_match("/(".$parliament.")\\-\\d+/i", $tmpID)) {
                    $ids[] = trim($tmpID);
                }
            }
        } else {
            $idObjects = $dbp->getAll("SELECT MediaID FROM ?n", $config["parliament"][$parliament]["sql"]["tbl"]["Media"]);
            foreach ($idObjects as $idObject) {
                $ids[] = $idObject['MediaID'];
            }
        }
        
        $totalItems = count($ids);
        $batchSize = 10;
        $totalBatches = ceil($totalItems / $batchSize);
        logger("info", "Starting search index update for ".$totalItems." items for parliament: {$parliament}");
        
        initBaseProgressFile($searchIndexProgressFilePath, [
            'processName' => 'searchIndexFullUpdate',
            'parliament' => $parliament,
            'statusDetails' => "Starting to process {$totalItems} items in {$totalBatches} batches.",
            'totalDbMediaItems' => $totalItems,
            'processedMediaItems' => 0,
            'itemsFailed' => 0,
            'currentBatch' => 0,
            'totalBatches' => $totalBatches
        ]);

        $mediaItemsBatch = [];
        $processedItemCount = 0;
        $failedItemCount = 0;
        $currentBatchNum = 0;

        foreach ($ids as $index => $id) {
            try {
                $tmpMedia = apiV1(["action" => "getItem", "itemType" => "media", "id" => $id], $db, $dbp);
                if (isset($tmpMedia['data']['id'])) {
                    $mediaItemsBatch[] = $tmpMedia;
                } else {
                    $failedItemCount++;
                    $errorMessage = "Failed to fetch valid data (item is empty or missing an ID) for MediaID: {$id}";
                    logger("warn", $errorMessage);
                    logErrorToBaseProgressFile($searchIndexProgressFilePath, $errorMessage, $id);
                }
            } catch (Exception $e) {
                $failedItemCount++;
                $errorMessage = "Exception fetching MediaID {$id}: " . $e->getMessage();
                logger("error", $errorMessage);
                logErrorToBaseProgressFile($searchIndexProgressFilePath, $errorMessage, $id, $e->getTraceAsString());
            }

            // Process batch when it's full or it's the last item
            if (count($mediaItemsBatch) >= $batchSize || ($index + 1) === $totalItems) {
                $currentBatchNum++;
                $itemsInBatch = count($mediaItemsBatch);
                $statusDetails = "Processing batch {$currentBatchNum}/{$totalBatches} ({$itemsInBatch} items)";
                
                updateBaseProgressFile($searchIndexProgressFilePath, [
                    'currentBatch' => $currentBatchNum,
                    'statusDetails' => $statusDetails
                ]);
                
                if (!empty($mediaItemsBatch)) {
                    $updateRequest = ["parliament" => $parliament, "items" => $mediaItemsBatch, "initIndex" => ($currentBatchNum === 1), "isFullRebuild" => true];
                    $updateResult = searchIndexUpdate($updateRequest);
                    
                    $updatedInBatch = $updateResult['data']['updated'] ?? 0;
                    $failedInBatch = $updateResult['data']['failed'] ?? 0;
                    
                    $processedItemCount += $updatedInBatch;
                    $failedItemCount += $failedInBatch;

                    if ($failedInBatch > 0 && !empty($updateResult['data']['errors'])) {
                        logger("error", "Search index update batch {$currentBatchNum} failed for {$failedInBatch} items.");
                        foreach($updateResult['data']['errors'] as $error) {
                             logErrorToBaseProgressFile(
                                 $searchIndexProgressFilePath, 
                                 $error['message'] ?? 'Unknown indexing error', 
                                 $error['id'] ?? "UNKNOWN_ITEM_ID_BATCH_{$currentBatchNum}"
                            );
                        }
                    }
                }
                
                // Update progress after every batch, reflecting all successes and failures so far.
                updateBaseProgressFile($searchIndexProgressFilePath, [
                    'processedMediaItems' => $processedItemCount, // Only successfully indexed items
                    'itemsFailed' => $failedItemCount // All failures (fetch + index)
                ]);

                // Explicitly free memory
                unset($mediaItemsBatch, $updateRequest, $updateResult);
                gc_collect_cycles(); // Force garbage collection

                $mediaItemsBatch = []; // Reset batch for the next iteration
            }
        }
        
        $finalStatusDetails = "Search index update completed. Successfully indexed: {$processedItemCount}, Total failed: {$failedItemCount}.";
        $finalStatus = ($failedItemCount > 0) ? "partially_completed_with_errors" : "completed_successfully";
        if ($processedItemCount === 0 && $failedItemCount === $totalItems) {
            $finalStatus = "error_all_items_failed";
            $finalStatusDetails = "Search index update failed. All {$totalItems} items failed to process.";
        }
        
        // Enhanced indices will be handled by the search index update process
        // No need to build words index here anymore - using new enhanced indexing system
        
        finalizeBaseProgressFile($searchIndexProgressFilePath, $finalStatus, $finalStatusDetails);
        $progressFinalized = true;

        logger("info", "cronUpdater finished: Search index update complete. Total: {$totalItems}, Processed: {$processedItemCount}, Failed: {$failedItemCount}.");
        
        // Trigger enhanced indexing after successful completion if requested
        if (isset($input["triggerEnhancedAfterCompletion"]) && $finalStatus === "completed_successfully") {
            $enhancedScriptPath = realpath(__DIR__ . "/enhancedIndexer.php");
            if ($enhancedScriptPath && file_exists($enhancedScriptPath)) {
                $enhancedCommand = $config["bin"]["php"] . " " . escapeshellarg($enhancedScriptPath) . " --parliament=" . escapeshellarg($parliament) . " --batch-size=100 > /dev/null 2>&1 &";
                logger("info", "Triggering enhanced indexing after main index completion: " . $enhancedCommand);
                exec($enhancedCommand);
                logger("info", "Enhanced indexing triggered successfully.");
            } else {
                logger("warn", "Enhanced indexing script not found at: " . __DIR__ . "/enhancedIndexer.php");
            }
        }
        
        exit;

    } else {
        // NORMAL DATA IMPORT MODE
        try {
            if (!is_dir($meta["inputDir"])) { mkdir($meta["inputDir"], 0775, true); }
            if (($meta["preserveFiles"] == true) && (!is_dir($meta["doneDir"]))) { mkdir($meta["doneDir"], 0775, true); }

            // Preserve the last successfully processed file name across runs.
            $lastSuccess = null;
            if (file_exists(CRONUPDATER_PROGRESS_FILE)) {
                $previousProgressJson = @file_get_contents(CRONUPDATER_PROGRESS_FILE);
                if ($previousProgressJson) {
                    $previousProgress = json_decode($previousProgressJson, true);
                    if (isset($previousProgress['lastSuccessfullyProcessedFile'])) {
                        $lastSuccess = $previousProgress['lastSuccessfullyProcessedFile'];
                    }
                }
            }

            // Initialize progress tracking for data import
            initBaseProgressFile(CRONUPDATER_PROGRESS_FILE, [
                "processName" => "dataImport", 
                "status" => "running", 
                "statusDetails" => "Starting data import process...", 
                "totalFiles" => 0, 
                "processedFiles" => 0,
                "lastSuccessfullyProcessedFile" => $lastSuccess // Carry over the last success
            ]);
            
            // By default, sync from Git. The --ignoreGit flag prevents this.
            if (!isset($input["ignoreGit"])) {
                logger("info", "Running Git repository sync.");
                require_once(__DIR__ . "/updateFilesFromGit.php");
                updateFilesFromGit($parliament);
            } else {
                logger("info", "Skipping Git repository sync due to --ignoreGit flag.");
            }
            
            require_once(__DIR__ . "/entity-dump/function.entityDump.php");

            $inputFiles = array_filter(scandir($meta["inputDir"]), function($file) use ($meta) {
                return !is_dir($meta["inputDir"] . $file) && preg_match('/.*\.json$/DA', $file);
            });
            $totalFilesToProcess = count($inputFiles);
            updateBaseProgressFile(CRONUPDATER_PROGRESS_FILE, ["totalFiles" => $totalFilesToProcess]);

            if (empty($inputFiles)) {
                finalizeBaseProgressFile(CRONUPDATER_PROGRESS_FILE, "completed_successfully", "No new files to process.");
                $progressFinalized = true;
                logger("info", "cronUpdater finished: No input files to process.");
                exit;
            }

            $processedFileCount = 0;
            $entityDump = getEntityDump(array("type" => "all", "wiki" => true, "wikikeys" => "true"), $db);
            
            foreach ($inputFiles as $file) {
                cliLog("start processing file: " . $file);
                updateBaseProgressFile(CRONUPDATER_PROGRESS_FILE, ["currentFile" => $file, "statusDetails" => "Processing file: " . $file]);
                
                $json = json_decode(file_get_contents($meta["inputDir"] . $file), true);
                if (!$json) {
                    logger("ERROR", "Could not parse json from file: " . $file);
                    continue;
                }

                $mediaItemsForSearchIndex = [];
                foreach ($json["data"] as $media) {
                    $media["action"] = "addItem";
                    $media["itemType"] = "media";
                    $media["meta"] = $json["meta"];
                    
                    $return = mediaAdd($media, $db, $dbp, $entityDump);
                    if ($return["meta"]["requestStatus"] == "success") {
                        $tmpMedia = apiV1(["action" => "getItem", "itemType" => "media", "id" => $return["data"]["id"]], $db, $dbp);
                        if ($tmpMedia["meta"]["requestStatus"] == "success") {
                            $mediaItemsForSearchIndex[] = $tmpMedia;
                        }
                    } else {
                        logger("ERROR", "Could not add media from file " . $file . " | return: " . json_encode($return) . " | Item: " . json_encode($media));
                    }
                }
                
                cliLog("media database part for session finished. Updating " . count($mediaItemsForSearchIndex) . " Media Items at OpenSearch now.");
                if ($meta["preserveFiles"] == true) { rename($meta["inputDir"] . $file, $meta["doneDir"] . $file); } 
                else { unlink($meta["inputDir"] . $file); }
                
                if (!empty($mediaItemsForSearchIndex)) {
                    $updateRequest = ["parliament" => $parliament, "items" => $mediaItemsForSearchIndex, "initIndex" => false];
                    searchIndexUpdate($updateRequest);
                }
                
                $processedFileCount++;
                updateBaseProgressFile(CRONUPDATER_PROGRESS_FILE, [
                    "processedFiles" => $processedFileCount, 
                    "statusDetails" => "Finished file: " . $file,
                    "lastSuccessfullyProcessedFile" => $file
                ]);
            }
            
            // When finalizing, clear the currentFile since nothing is actively being processed.
            finalizeBaseProgressFile(CRONUPDATER_PROGRESS_FILE, "completed_successfully", "Data import finished. Processed {$processedFileCount}/{$totalFilesToProcess} files.");
            updateBaseProgressFile(CRONUPDATER_PROGRESS_FILE, ["currentFile" => null]);
            $progressFinalized = true;
            logger("info", "cronUpdater finished: File processing complete.");
            exit;

        } catch (Exception $e) {
            $criticalErrorMsg = "CRITICAL unhandled exception in cronUpdater data import: " . $e->getMessage();
            logger("CRITICAL", $criticalErrorMsg);
            if(function_exists('finalizeBaseProgressFile')) {
                finalizeBaseProgressFile(CRONUPDATER_PROGRESS_FILE, "error_critical", $criticalErrorMsg);
            }
            $progressFinalized = true;
            exit(1);
        }
    }
}
?>