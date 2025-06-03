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