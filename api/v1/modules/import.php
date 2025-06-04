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
                "startedAt" => date("Y-m-d H:i:s")
            ],
            [
                "message" => "CronUpdater started successfully"
            ]
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
 * Gets the current status of the cronUpdater
 * 
 * @return array Response with status information
 */
function importGetCronUpdaterStatus() {
    global $config;
    
    $lockFile = __DIR__ . "/../../../data/cronUpdater.lock";
    $logFile = __DIR__ . "/../../../data/cronUpdater.log";
    
    $status = [
        "running" => false,
        "lastRun" => null,
        "lastError" => null
    ];
    
    // Check if currently running
    if (file_exists($lockFile)) {
        $status["running"] = true;
        $status["runningSince"] = date("Y-m-d H:i:s", filemtime($lockFile));
        $status["runningFor"] = time() - filemtime($lockFile) . " seconds";
    }
    
    // Get last run info from log file
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        
        // Find last start and end entries
        $lastStart = null;
        $lastEnd = null;
        $lastError = null;
        
        foreach (array_reverse($logLines) as $line) {
            if (empty($line)) continue; // Skip empty lines

            if (strpos($line, "started") !== false && !$lastStart) {
                $lastStart = $line;
            }
            if (strpos($line, "finished") !== false && !$lastEnd) {
                $lastEnd = $line;
            }
            if (strpos($line, "ERROR") !== false && !$lastError) {
                $lastError = $line;
            }
            if ($lastStart && $lastEnd && $lastError) break;
        }
        
        if ($lastStart) {
            $status["lastRun"] = $lastEnd ? $lastEnd : $lastStart;
        }
        if ($lastError) {
            $status["lastError"] = $lastError;
        }
    }
    
    return createApiSuccessResponse(
        $status,
        [
            "message" => "CronUpdater status retrieved successfully"
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