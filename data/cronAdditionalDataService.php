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


require_once(__DIR__ . "/../modules/utilities/functions.php");

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
    require_once(__DIR__ . "/../modules/utilities/functions.conflicts.php");
    require_once(__DIR__ . "/updateEntityFromService.php");



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



    if ($input["type"] == "memberOfParliament") {

        $items = $db->getAll("SELECT PersonID AS id FROM ?n WHERE PersonType = 'memberOfParliament'",$config["platform"]["sql"]["tbl"]["Person"]);

    } elseif ($input["type"] == "person") {

        $items = $db->getAll("SELECT PersonID AS id FROM ?n",$config["platform"]["sql"]["tbl"]["Person"]);


    } elseif ($input["type"] == "organisation") {


        $items = $db->getAll("SELECT OrganisationID AS id FROM ?n",$config["platform"]["sql"]["tbl"]["Organisation"]);


    } elseif ($input["type"] == "term") {


        $items = $db->getAll("SELECT TermWikidataID AS id FROM ?n WHERE TermWikidataID IS NOT NULL",$config["platform"]["sql"]["tbl"]["Term"]);


    } elseif ($input["type"] == "legalDocument") {


        $items = $db->getAll("SELECT DocumentWikidataID AS id FROM ?n WHERE DocumentType = 'legalDocument' AND DocumentWikidataID IS NOT NULL",$config["platform"]["sql"]["tbl"]["Document"]);


    } elseif ($input["type"] == "officialDocument") {


        $items = $db->getAll("SELECT DocumentSourceURI as id FROM ?n WHERE DocumentType = 'officialDocument'",$config["platform"]["sql"]["tbl"]["Document"]);


    }


    $itemsUpdated = 0;

    foreach ($items as $item) {

        try {

            $tmp = updateEntityFromService($input["type"], $item["id"], $config["ads"]["api"]["uri"], $config["ads"]["api"]["key"], "de", $db);

            if ($tmp["meta"]["requestStatus"] != "success") {

                logger("error","Error while updating ".$input["type"]." ".$item["id"].": ".json_encode($tmp["meta"]).": ".json_encode($tmp["errors"]));

            } else {
                $itemsUpdated++;
            }

        } catch (Exception $e) {

            logger("error","Error while updating ".$input["type"]." ".$item["id"].": ".$e->getMessage());

        }
    }

    logger("info",$itemsUpdated." items of type ".$input["type"]." has been updated");
    unlink(__DIR__."/cronAdditionalDataService.lock");
    exit;


}


?>