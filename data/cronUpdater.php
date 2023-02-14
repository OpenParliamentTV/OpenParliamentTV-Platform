<?php

/**
 * This script expects to be run via CLI only
 *
 * it can have the parameter --parliament=DE | default value will be "DE"
 *
 */

require_once(__DIR__ . "/../modules/utilities/functions.php");


if (is_cli()) {

    function cliLog($message)
    {
        $message = date("Y.m.d H:i:s:u") . " - ". $message.PHP_EOL;
        print($message);
        flush();
        ob_flush();
    }

    function logger($type = "info",$msg) {
        file_put_contents(__DIR__."/cronUpdater.log",date("Y-m-d H:i:s")." - ".$type.": ".$msg."\n",FILE_APPEND );
    }

    require_once(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../modules/utilities/functions.conflicts.php");
    require_once(__DIR__ . "/../api/v1/api.php");
    require_once(__DIR__ . "/../api/v1/modules/media.php");

    $val = getopt(null, ["parliament:"]);

    $meta["inputDir"] = __DIR__ . "/input/";
    $meta["doneDir"] = __DIR__ . "/done/";
    $meta["preserveFiles"] = true;
    $meta["parliament"] = ($val["parliament"] ? $val["parliament"] : "DE");

    if (!is_dir($meta["inputDir"])) {

        mkdir($meta["inputDir"]);

    }

    if (($meta["preserveFiles"] == true) && (!is_dir($meta["doneDir"]))) {

        mkdir($meta["doneDir"]);

    }

    require_once (__DIR__."/updateSearchIndex.php");
    require_once (__DIR__."/updateFilesFromGit.php");

    try {
        //TODO - Just on return true go ahead
        cliLog("start updateFilesFromGit");
        updateFilesFromGit($meta["parliament"]);
        cliLog("end updateFilesFromGit");

    } catch (Exception $e) {

        logger("", $e->getMessage());

    }

    $inputFiles = scandir($meta["inputDir"]);

    if (count(array_diff($inputFiles, array('..', '.'))) < 1) {
        //no files in input, exit script
        exit;
    }

    try {

        $dbp = new SafeMySQL(array(
            'host' => $config["parliament"][$meta["parliament"]]["sql"]["access"]["host"],
            'user' => $config["parliament"][$meta["parliament"]]["sql"]["access"]["user"],
            'pass' => $config["parliament"][$meta["parliament"]]["sql"]["access"]["passwd"],
            'db' => $config["parliament"][$meta["parliament"]]["sql"]["db"]
        ));

    } catch (exception $e) {

        echo "Could not connect to Parliament Database.";
        logger("", "Could not connect to Parliament Database: ".$e->getMessage());
        exit;

    }

    try {

        $db = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));

    } catch (exception $e) {

        echo "Could not connect to Platform Database.";
        logger("", "Could not connect to Database Database: ".$e->getMessage());
        exit;

    }

    foreach ($inputFiles as $file) {

        //just handle files that match .json
        if ((is_dir($meta["inputDir"] . $file)) || (!is_file($meta["inputDir"] . $file)) || (!preg_match('/.*\.json$/DA', $file))) {
            continue;
        }
        cliLog("start processing file: ".$file);

        try {

            $json = json_decode(file_get_contents($meta["inputDir"] . $file), true);

        } catch (exception $e) {

            reportConflict("Media", "mediaAdd cronUpdater - File Parse Error", "", "", "Could not parse json from file: " . $file . " ||| Error:" . $e->getMessage(), $db);
            echo "Could parse file ".$file." | ".$e->getMessage();
            logger("ERROR", "Could parse file ".$file." | ".$e->getMessage());
            exit;
        }


        $mediaItems = array();

        foreach ($json["data"] as $spKey => $media) {

            $media["action"] = "addMedia";
            $media["itemType"] = "addMedia";
            $media["meta"] = $json["meta"];

            try {

                $return = mediaAdd($media,$db,$dbp);

            } catch (exception $e) {

                echo "Could add media ".$file." | ".$e->getMessage();
                logger("ERROR", "Could add media ".$file." | ".$e->getMessage());

            }

            if ($return["meta"]["requestStatus"] != "success") {

                echo "Could not add media ".$file." | ".$e->getMessage();
                logger("ERROR", "Could not add media ".$file." | return: ".$return." | Item: ".json_encode($media));

            } else {

                cliLog("media ".$return["data"]["id"]." processed to database. Getting content.");

                //$tmpMedia = mediaGetByID($return["data"]["id"],$db,$dbp);
                $tmpMedia = apiV1([
                    "action" => "getItem",
                    "itemType" => "media",
                    "id" => $return["data"]["id"]
                ], $db, $dbp);

                cliLog("media ".$return["data"]["id"]." got its content");

                if (($tmpMedia["meta"]["requestStatus"] == "success") && (count($return["data"]) > 0)) {

                    array_push($mediaItems, $tmpMedia);

                }

            }

        }
        cliLog("media database part finished. Updating ".count($mediaItems)." Media Items at OpenSearch now.");

        if ($meta["preserveFiles"] == true) {

            rename($meta["inputDir"] . $file, $meta["doneDir"] . $file);

        } else {

            unlink($meta["inputDir"] . $file);

        }

        // Update all added media items to OpenSearch/ElasticSearch

        $updatedItems = updateSearchIndex($meta["parliament"],$mediaItems);

        cliLog("OpenSearch for file ".$file." updated ".$updatedItems." media items.");

    }
    cliLog("Update complete.");

    exit;

}


?>