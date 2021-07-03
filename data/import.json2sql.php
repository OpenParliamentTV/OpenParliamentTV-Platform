<?php
require_once(__DIR__."/../config.php");
require_once(__DIR__."/../modules/utilities/safemysql.class.php");
require_once(__DIR__."/../modules/utilities/functions.conflicts.php");
require_once(__DIR__."/../api/v1/api.php");

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

ignore_user_abort(true);
set_time_limit(0);


$meta["inputDir"] = __DIR__."/input/";
$meta["doneDir"] = __DIR__."/done/";
$meta["preserveFiles"] = true;

function importJson2sql() {

    global $config;
    global $meta;


    if (!is_dir($meta["inputDir"])) {

        $return["success"] = "false";
        $return["txt"] = "Missing parameter";
        return $return;

    }

    if (($meta["preserveFiles"] == true) && (!is_dir($meta["doneDir"]))) {
        $return["success"] = "false";
        $return["txt"] = "Preserve Directory does not exist.";
        return $return;
    }

    $inputFiles = scandir($meta["inputDir"]);

    if (count(array_diff($inputFiles, array('..', '.'))) < 1) {
        $return["success"] = "false";
        $return["txt"] = "No Inputfiles";
        return $return;
    }

    //ToDo: Remove temporary limit to 5 files
    $fileCnt = 0;
    $mCnt = 0;

    foreach ($inputFiles as $file) {

        if ((is_dir($meta["inputDir"] . $file)) || (!is_file($meta["inputDir"] . $file)) || (!preg_match('/.*\.json$/DA', $file))) {
            continue;
        }


        $json = json_decode(file_get_contents($meta["inputDir"] . $file), true);

        foreach ($json as $spKey => $media) {
            $media["action"] = "addMedia";
            $media["itemType"] = "addMedia";

            $return = apiV1($media);
            if ($return["meta"]["requestStatus"] != "success") {
                
                $logMessage = "###########\n";
                $logMessage .= $file."\n";
                $logMessage .= "\n-----------\n";
                $logMessage .= json_encode($return);
                $logMessage .= "\nMEDIA ITEM:\n\n";
                $logMessage .= json_encode($media);
                $logMessage .= "\n###########\n\n\n\n";

                file_put_contents("import-error-log.txt", $logMessage, FILE_APPEND);

            } else {
                $mCnt++;
                if ($mCnt>=20) {
                    header("Refresh:0");
                }
            }

        }

        if ($meta["preserveFiles"] == true) {

            rename($meta["inputDir"] . $file, $meta["doneDir"] . $file);

        } else {

            unlink($meta["inputDir"] . $file);

        }

        //ToDo: Remove temporary limit to 5 files
        /*
        $fileCnt++;
        if ($fileCnt >= 5) {
            echo 'EXITING ...';
            exit();
        }
        */

    }
}
importJson2sql();
?>
