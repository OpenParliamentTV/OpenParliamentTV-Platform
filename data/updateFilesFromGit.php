<?php

function getFilesWithDates ($dir,$pattern = "~[0-9]{5}-session\.json~") {

    $files = scandir($dir);
    $return = array();

    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            $return[$file] = array("file"=>$file,"date"=>filemtime($dir."/".$file));
        }
    }

    return $return;

}


/**
 * @param string $parliament
 * @return bool if there are any new files in input
 *
 * Will not do anything at the first pull/clone
 *
 */

function updateFilesFromGit($parliament = "DE") {

    $return = false;

    require_once(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../modules/utilities/functions.conflicts.php");
    require_once(__DIR__ . "/../api/v1/api.php");
    require_once(__DIR__ . "/../api/v1/modules/media.php");
    global $config;


    if (!is_dir(__DIR__."/repos/")) {
        mkdir(__DIR__."/repos/");
    }



    if (!is_dir(__DIR__."/repos/".$parliament)) {
        mkdir(__DIR__."/repos/".$parliament);
    }

    $realpath = realpath(__DIR__."/repos/".$parliament."/");

    if (!is_dir(__DIR__."/repos/".$parliament."/.git")) {

        chdir($realpath);
        shell_exec($config["parliament"][$parliament]["git"]["bin"].' -C "'.$realpath.'" clone '.$config["parliament"][$parliament]["git"]["repository"].' .');

    }

    shell_exec($config["parliament"][$parliament]["git"]["bin"]." config --global --add safe.directory ".$realpath);


    chdir($realpath);

    //If files were deleted locally restore them first

    shell_exec($config["parliament"][$parliament]["git"]["bin"].' -C "'.$realpath.'" checkout -f HEAD');

    //get all current files
    $currentFiles = getFilesWithDates($realpath."/processed/");


    //get all changes from git
    shell_exec($config["parliament"][$parliament]["git"]["bin"].' -C "'.$realpath.'" pull');

    //get all current files again
    $newCurrentFiles = getFilesWithDates($realpath."/processed/");
    //print_r($currentFiles);
    //print_r($newCurrentFiles);

    //compare the new fileslist with the old fileslist
    foreach ($newCurrentFiles as $fileName=>$file) {

        if (!array_key_exists($fileName,$currentFiles) || ($currentFiles[$fileName]["date"] != $file["date"])) {
            copy($realpath."/processed/".$fileName, __DIR__."/input/".$fileName);
            $return = true;
        }

    }

    return $return;

}

?>
