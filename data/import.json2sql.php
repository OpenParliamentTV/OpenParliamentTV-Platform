<?php
require_once(__DIR__."/../config.php");
require_once(__DIR__."/../modules/utilities/safemysql.class.php");
require_once(__DIR__."/../modules/utilities/functions.conflicts.php");
require_once(__DIR__."/../api/v1/api.php");

error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);


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
                echo "<pre>";
                echo "###########\n";
                echo $file."\n";
                echo "\n-----------\n";
                print_r($return);
                echo "<textarea>";
                print_r($media);
                echo "</textarea>";
                echo "\n###########\n\n\n\n";
                echo "</pre>";
            }

        }

        if ($meta["preserveFiles"] == true) {

            rename($meta["inputDir"] . $file, $meta["doneDir"] . $file);

        } else {

            unlink($meta["inputDir"] . $file);

        }

        //ToDo: Remove temporary limit to 5 files
        $fileCnt++;
        if ($fileCnt >= 5) {
            echo 'EXITING ...';
            exit();
        }

    }
}
importJson2sql();
?>
