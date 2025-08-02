<?php
/**
 * Index Optimization Script
 * 
 * This script runs index optimization asynchronously in the background.
 * It is called by the API to perform optimization without blocking the web server.
 */

require_once(__DIR__ . "/../config.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__ . "/../modules/utilities/functions.php");
require_once(__DIR__ . "/../api/v1/modules/searchIndex.php");

function logger($type = "info", $msg) {
    file_put_contents(__DIR__ . "/indexOptimizer.log", date("Y-m-d H:i:s") . " - " . $type . ": " . $msg . "\n", FILE_APPEND);
}

/**
 * Checks if this script is executed from CLI
 */
if (is_cli()) {
    // Get CLI parameters
    $input = getopt(null, ["parliament:"]);
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