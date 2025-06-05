<?php

require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * Runs the cronUpdater script asynchronously
 * 
 * @param array $request The API request parameters
 * @return array Response with status information
 */
function importRunCronUpdater($request) {
    global $config;
    
    // Check if cronUpdater is already running
    $lockFile = __DIR__ . "/../../../data/cronUpdater.lock";
    if (file_exists($lockFile)) {
        $lockAge = time() - filemtime($lockFile);
        
        // If lock is older than ignore time, remove it
        if ($lockAge >= ($config["time"]["ignore"] * 60)) {
            unlink($lockFile);
        } else {
            return createApiErrorResponse(
                409, // Conflict
                "CRON_ALREADY_RUNNING",
                "messageErrorCronAlreadyRunningTitle",
                "messageErrorCronAlreadyRunningDetail",
                [],
                null,
                [
                    "running" => true,
                    "runningSince" => date("Y-m-d H:i:s", filemtime($lockFile)),
                    "runningFor" => $lockAge . " seconds"
                ]
            );
        }
    }
    
    try {
        // Execute the cronUpdater script asynchronously
        executeAsyncShellCommand($config["bin"]["php"] . " " . realpath(__DIR__ . "/../../../data/cronUpdater.php"));
        
        return createApiSuccessResponse(
            [
                "running" => true,
                "startedAt" => date("Y-m-d H:i:s"),
                "message" => "CronUpdater started successfully"
            ],
            []
        );
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            "CRON_START_FAILED",
            "messageErrorCronStartFailedTitle",
            "messageErrorCronStartFailedDetail",
            ["details" => $e->getMessage()]
        );
    }
}

/**
 * Gets the current status of the cronUpdater by reading its progress file.
 * 
 * @return array Response with status information from the cronUpdater.json file.
 */
function importGetCronUpdaterStatus() {
    global $config;
    
    $progressFilePath = __DIR__ . "/../../../data/progress_status/cronUpdater.json";

    if (!file_exists($progressFilePath)) {
        // Default status if progress file doesn't exist (e.g., never run or cleaned up)
        $defaultStatus = [
            "processName" => "cronUpdater",
            "status" => "idle",
            "statusDetails" => "No active or recent import process found.",
            "startTime" => null,
            "endTime" => null,
            "totalFiles" => 0,
            "processedFiles" => 0,
            "currentFile" => null,
            "errors" => [],
            "lastActivityTime" => null,
            // Include other fields from cronUpdater.json with default/null values
            "totalDataObjects" => 0,
            "processedDataObjects" => 0,
            "totalMediaObjects" => 0,
            "processedMediaObjects" => 0,
            "lastSuccessfullyProcessedFile" => null,
            "currentFileProgress" => [
                "totalDataObjectsInFile" => 0,
                "processedDataObjectsInFile" => 0,
                "totalMediaObjectsInFile" => 0,
                "processedMediaObjectsInFile" => 0
            ]
        ];
        return createApiSuccessResponse($defaultStatus, ["message" => "CronUpdater progress file not found, returning default idle status."]);
    }

    $progressJson = @file_get_contents($progressFilePath);
    if ($progressJson === false) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_READ_ERROR', "Could not read the CronUpdater progress file.", "File exists at {$progressFilePath} but is unreadable.");
    }

    $progressData = json_decode($progressJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_CORRUPT', "CronUpdater progress file is corrupt or not valid JSON.", "JSON decode error: " . json_last_error_msg() . " in file {$progressFilePath}");
    }

    // Ensure all expected keys from the defaultStatus are present to prevent frontend errors
    // This merges the loaded data over the defaults, so any missing keys in the file get a default value.
    $defaultKeysTemplate = [
        "processName" => "cronUpdater", "status" => "unknown", "statusDetails" => "",
        "startTime" => null, "endTime" => null, "totalFiles" => 0, "processedFiles" => 0,
        "currentFile" => null, "errors" => [], "lastActivityTime" => null,
        "totalDataObjects" => 0, "processedDataObjects" => 0, "totalMediaObjects" => 0,
        "processedMediaObjects" => 0, "lastSuccessfullyProcessedFile" => null,
        "currentFileProgress" => [
            "totalDataObjectsInFile" => 0, "processedDataObjectsInFile" => 0,
            "totalMediaObjectsInFile" => 0, "processedMediaObjectsInFile" => 0
        ]
    ];
    $progressData = array_merge($defaultKeysTemplate, $progressData);

    
    return createApiSuccessResponse(
        $progressData,
        [
            "message" => "CronUpdater status retrieved successfully from progress file."
        ]
    );
} 

/**
 * Schedules specified session files for re-import.
 *
 * @param array $request_params An array containing:
 *                              - files (array, required): An associative array where keys are parliament codes
 *                                and values are arrays of session filenames to re-import.
 *                                e.g., ["DE" => ["21001-session.json", "21002-session.json"]]
 * @param object|false $db Optional platform database connection object.
 * @return array Standard API response array.
 */
function reimportSessions($request_params, $db = false) {
    global $config; 
    
    if (empty($request_params['files']) || !is_array($request_params['files'])) {
        return createApiErrorMissingParameter('files');
    }

    $filesToReimport = $request_params['files'];
    $results = [
        'copied' => [],
        'failed' => [],
        'skipped' => []
    ];
    $projectRoot = realpath(__DIR__ . "/../../../"); // project_root

    if (!$projectRoot) {
        error_log("Critical error: Project root could not be determined in reimportSessions.");
        return createApiErrorResponse(500, 'PROJECT_ROOT_ERROR', 'messageErrorInternal', 'Could not determine project root directory.');
    }

    foreach ($filesToReimport as $parliament => $sessionFiles) {
        if (!is_array($sessionFiles)) {
            $results['failed'][] = ['parliament' => $parliament, 'file' => 'N/A', 'reason' => 'Invalid file list format for parliament.'];
            continue;
        }
        foreach ($sessionFiles as $file) {
            $sourcePath = $projectRoot . "/data/repos/" . $parliament . "/processed/" . $file;
            $destinationPath = $projectRoot . "/data/input/" . $file;

            if (!is_file($sourcePath)) {
                $results['skipped'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Source file not found or is not a file.'];
                error_log("Reimport: Source file not found or not a file: " . $sourcePath);
                continue;
            }
            if (!is_readable($sourcePath)) {
                $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Source file not readable.'];
                error_log("Reimport: Source file not readable: " . $sourcePath);
                continue;
            }

            // Ensure destination directory exists or can be created (though /data/input/ should exist)
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir)) {
                // Attempt to create if it doesn't exist, though this should ideally already be set up.
                if (!mkdir($destinationDir, 0775, true)) {
                    $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Destination directory does not exist and could not be created.'];
                    error_log("Reimport: Destination directory could not be created: " . $destinationDir);
                    continue;
                }
            }

            if (copy($sourcePath, $destinationPath)) {
                $results['copied'][] = ['parliament' => $parliament, 'file' => $file];
            } else {
                $results['failed'][] = ['parliament' => $parliament, 'file' => $file, 'reason' => 'Copy operation failed.'];
                error_log("Reimport: Failed to copy file '" . $file . "' for parliament '" . $parliament . "'.");
            }
        }
    }

    return createApiSuccessResponse(
        $results, 
        ['summary' => count($results['copied']) . ' file(s) scheduled for re-import, ' . count($results['failed']) . ' failed, ' . count($results['skipped']) . ' skipped.']);
}