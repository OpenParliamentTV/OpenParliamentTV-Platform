<?php
/**
 * Index Optimization Script
 * 
 * This script runs index optimization asynchronously in the background.
 * It is called by the API to perform optimization without blocking the web server.
 */

require_once(__DIR__ . "/../bootstrap-cli.php");

require_once(__DIR__ . "/../api/v1/modules/search-index.php");

function logger($type = "info", $msg) {
    file_put_contents(optvVarFilePath("log", "indexOptimizer.log"), date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

/**
 * Checks if this script is executed from CLI
 */
if (optvIsCli()) {
    // Get CLI parameters
    $input = getopt("", ["parliament:"]);
    $parliament = (!empty($input["parliament"])) ? $input["parliament"] : "DE";
    
    logger("info", "Index optimization started for parliament: {$parliament}");
    
    // Perform the optimization
    $success = performIndexOptimization($parliament);
    
    if ($success) {
        logger("info", "Index optimization completed successfully for parliament: {$parliament}");
        exit(0);
    } else {
        logger("error", "Index optimization failed for parliament: {$parliament}");
        exit(1);
    }
} else {
    logger("error", "Index optimizer script must be run from CLI");
    exit(1);
}
?>