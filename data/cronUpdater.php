<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
/**
 * This script expects to be run via CLI only
 *
 * it can have the following parameter
 * --parliament "DE" | default value will be "DE"
 *
 * --justUpdateSearchIndex "true" | (default not enabled) if this is set it will get all MediaItems from API or just the Items with given with following parameter (separeted IDs by comma)
 * --ids "DE-0190002013,DE-0190002014" | (default not enabled) comma separated list of MediaIDs which get updated if --justUpdateSearchIndex = true too
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


    /**
     * Check if lock file exists and checks its age
     */
    if (file_exists(__DIR__."/cronUpdater.lock")) {

        if ((time()-filemtime(__DIR__."/cronUpdater.lock")) >= ($config["time"]["warning"]*60)) {

            if (filter_var($config["cronContactMail"], FILTER_VALIDATE_EMAIL)) {

                mail($config["cronContactMail"],"CronJob blocked", "CronJob was not executed. Its blocked now for over ".$config["time"]["warning"]." Minutes. Check the server and if its not running, remove the file: ".realpath(__DIR__."/cronUpdater.lock"));
                logger("warn", "CronJob was not executed and log file is there for > ".$config["time"]["warning"]." Minutes already.");

                exit;
            }

        } elseif ((time()-filemtime(__DIR__."/cronUpdater.lock")) >= ($config["time"]["ignore"]*60)) {

            logger("warn", "CronJob was blocked for > ".$config["time"]["ignore"]." Minutes now. Decided to ignore it and run it anyways.");
            unlink(realpath(__DIR__."/cronUpdater.lock"));

        } else {

            cliLog("cronUpdater still running");
            exit;

        }

    }

    // create lock file
    touch (__DIR__."/cronUpdater.lock");

    logger("info","cronUpdater started");

    //get CLI parameter to $input
    $input = getopt(null, ["parliament:","justUpdateSearchIndex:","ids:","ignoreGit:"]);

    //cliLog(json_encode($input));
    //cliLog($input["parliament"]);

    $parliament = ((isset($input["parliament"])) ? $input["parliament"] : "DE");

    require_once(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../modules/utilities/functions.conflicts.php");
    require_once(__DIR__ . "/../api/v1/api.php");
    require_once(__DIR__ . "/../api/v1/modules/media.php");


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
        unlink(__DIR__."/cronUpdater.lock");
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
        unlink(__DIR__."/cronUpdater.lock");
        exit;

    }




    /**
     *
     * Just update the Search Index from API/Database
     *
     **/

    if ($input["justUpdateSearchIndex"]) {



        if ($input["ids"]) {

            //If ids are given in a comma separated list,
            $tmpIDs = explode(",", $input["ids"]);
            $ids = array();
            foreach ($tmpIDs as $tmpID) {
                if (preg_match("/(".$parliament.")\-\d+/i", $tmpID)) {
                    $ids[] = trim($tmpID);
                }
            }

        } else {

            $ids = $dbp->getAll("SELECT MediaID FROM ?n",$config["parliament"][$parliament]["sql"]["tbl"]["Media"]);

        }

        //cliLog(json_encode($ids));

        require_once (__DIR__."/../data/updateSearchIndex.php");

        $mediaItems = array();


        foreach ($ids as $id) {
            $requestID = ((is_array($id)) ? $id["MediaID"] : $id);

            try {
                $tmpMedia = apiV1([
                    "action" => "getItem",
                    "itemType" => "media",
                    "id" => $requestID
                ], $db, $dbp);

                array_push($mediaItems, $tmpMedia);

                if (count($mediaItems) == 30) {
                    updateSearchIndex($parliament, $mediaItems, true);

                    $mediaItems = array();
                }

            } catch (Exception $e) {

                cliLog(json_encode($e->getMessage()));

            }
        }

        if (!empty($mediaItems)) {

            updateSearchIndex($parliament, $mediaItems, true);

        }

        unlink(__DIR__."/cronUpdater.lock");

        exit;


    } else {

        if (!is_dir($meta["inputDir"])) {

            mkdir($meta["inputDir"]);

        }

        if (($meta["preserveFiles"] == true) && (!is_dir($meta["doneDir"]))) {

            mkdir($meta["doneDir"]);

        }

        require_once(__DIR__ . "/updateSearchIndex.php");
        require_once(__DIR__ . "/updateFilesFromGit.php");
        require_once(__DIR__ . "/entity-dump/function.entityDump.php");


        if (!$input["ignoreGit"]) {

            try {

                cliLog("start updateFilesFromGit");
                updateFilesFromGit($meta["parliament"]);
                cliLog("end updateFilesFromGit");

            } catch (Exception $e) {

                logger("", $e->getMessage());

            }

        }

        $inputFiles = scandir($meta["inputDir"]);

        if (count(array_diff($inputFiles, array('..', '.'))) < 1) {

            // No files to import
            unlink(__DIR__."/cronUpdater.lock");
            exit;
        }



        foreach ($inputFiles as $file) {

            //just handle files that match .json
            if ((is_dir($meta["inputDir"] . $file)) || (!is_file($meta["inputDir"] . $file)) || (!preg_match('/.*\.json$/DA', $file))) {
                continue;
            }

            cliLog("start processing file: " . $file);

            try {

                $json = json_decode(file_get_contents($meta["inputDir"] . $file), true);

            } catch (exception $e) {

                reportConflict("Media", "mediaAdd cronUpdater - File Parse Error", "", "", "Could not parse json from file: " . $file . " ||| Error:" . $e->getMessage(), $db);
                echo "Could parse file " . $file . " | " . $e->getMessage();
                logger("ERROR", "Could parse file " . $file . " | " . $e->getMessage());
                cliLog("ERROR: Could parse file " . $file . " | " . $e->getMessage());

                continue;

            }


            $mediaItems = array();

            $entityDump = getEntityDump(array("type" => "all", "wiki" => true, "wikikeys" => "true"), $db);

            foreach ($json["data"] as $spKey => $media) {

                $media["action"] = "addMedia";
                $media["itemType"] = "addMedia";
                $media["meta"] = $json["meta"];

                try {

                    $return = mediaAdd($media, $db, $dbp, $entityDump);

                } catch (exception $e) {

                    echo "Could add media " . $file . " | " . $e->getMessage();
                    logger("ERROR", "Could add media " . $file . " | " . $e->getMessage());
                    continue;

                }

                if ($return["meta"]["requestStatus"] != "success") {

                    echo "Could not add media " . $file . " | " . $e->getMessage();
                    logger("ERROR", "Could not add media " . $file . " | return: " . $return . " | Item: " . json_encode($media));

                } else {

                    cliLog("media " . $return["data"]["id"] . " processed to database. Getting content.");

                    //$tmpMedia = mediaGetByID($return["data"]["id"],$db,$dbp);
                    $tmpMedia = apiV1([
                        "action" => "getItem",
                        "itemType" => "media",
                        "id" => $return["data"]["id"]
                    ], $db, $dbp);

                    cliLog("media " . $return["data"]["id"] . " got its content");

                    if (($tmpMedia["meta"]["requestStatus"] == "success") && (count($return["data"]) > 0)) {

                        array_push($mediaItems, $tmpMedia);

                    }

                }

            }

            cliLog("media database part for session finished. Updating " . count($mediaItems) . " Media Items at OpenSearch now.");

            if ($meta["preserveFiles"] == true) {

                rename($meta["inputDir"] . $file, $meta["doneDir"] . $file);

            } else {

                unlink($meta["inputDir"] . $file);

            }

            // Update all added media items to OpenSearch/ElasticSearch
            $updatedItems = updateSearchIndex($meta["parliament"], $mediaItems);

            cliLog("OpenSearch for file " . $file . " updated " . $updatedItems . " media items.");

        }

        cliLog("Update complete.");

        unlink(__DIR__."/cronUpdater.lock");
        exit;

    }

    unlink(__DIR__."/cronUpdater.lock");
}


?>