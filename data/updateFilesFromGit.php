<?php

function getFilesWithDates ($dir,$pattern = "~[0-9]{5}-session\.json~") {

    if (!is_dir($dir)) {
        return array();
    }

    $files = scandir($dir);
    $return = array();

    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            $return[$file] = array("file"=>$file,"date"=>filemtime($dir."/".$file));
        }
    }

    return $return;

}

function gitSyncLog($type, $msg) {
    file_put_contents(__DIR__."/cronUpdater.log", date("Y-m-d H:i:s")." - ".$type.": [git-sync] ".$msg."\n", FILE_APPEND);
}

function runGitSyncCommand($gitBin, $command, &$exitCode = null) {
    $output = [];
    $exitCode = 0;
    exec($gitBin . ' ' . $command . ' 2>&1', $output, $exitCode);
    return trim(implode("\n", $output));
}


/**
 * @param string $parliament
 * @return bool if there are any new files in input
 *
 */

function updateFilesFromGit($parliament = "DE") {

    $return = false;
    $wasFreshClone = false;

    require_once(__DIR__ . "/../config.php");
    require_once(__DIR__ . "/../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../api/v1/utilities.php");
    require_once(__DIR__ . "/../api/v1/api.php");
    require_once(__DIR__ . "/../api/v1/modules/media.php");
    global $config;

    $gitBin = $config["bin"]["git"] ?? "git";
    $repoUrl = $config["parliament"][$parliament]["git"]["repository"] ?? null;

    if (empty($repoUrl)) {
        throw new Exception('Git repository URL is not configured for parliament ' . $parliament);
    }

    if (!is_dir(__DIR__."/repos/")) {
        if (!mkdir(__DIR__."/repos/", 0775, true) && !is_dir(__DIR__."/repos/")) {
            throw new Exception('Could not create data/repos directory');
        }
    }

    if (!is_dir(__DIR__."/repos/".$parliament)) {
        if (!mkdir(__DIR__."/repos/".$parliament, 0775, true) && !is_dir(__DIR__."/repos/".$parliament)) {
            throw new Exception('Could not create data/repos/' . $parliament . ' directory');
        }
    }

    $realpath = realpath(__DIR__."/repos/".$parliament."/");
    if ($realpath === false) {
        throw new Exception('Could not resolve path for data/repos/' . $parliament);
    }

    if (!is_dir($realpath . "/.git")) {
        gitSyncLog('info', 'Cloning ' . $repoUrl . ' into ' . $realpath);
        $cloneOutput = runGitSyncCommand(
            $gitBin,
            '-C ' . escapeshellarg($realpath) . ' clone ' . escapeshellarg($repoUrl) . ' .'
        );
        if ($cloneOutput !== '') {
            gitSyncLog('info', 'clone output: ' . $cloneOutput);
        }

        if (!is_dir($realpath . "/.git")) {
            throw new Exception(
                'Git clone failed for parliament ' . $parliament . '. ' .
                ($cloneOutput !== '' ? $cloneOutput : 'Git returned no output. Check that git is installed and data/repos is writable.')
            );
        }

        $wasFreshClone = true;
    }

    runGitSyncCommand($gitBin, 'config --global --add safe.directory ' . escapeshellarg($realpath));

    if (!is_dir($realpath . "/processed")) {
        throw new Exception('Git repository cloned but processed/ directory is missing for parliament ' . $parliament);
    }

    $checkoutOutput = runGitSyncCommand($gitBin, '-C ' . escapeshellarg($realpath) . ' checkout -f HEAD', $checkoutExit);
    if ($checkoutOutput !== '') {
        gitSyncLog('info', 'checkout output: ' . $checkoutOutput);
    }
    if ($checkoutExit !== 0) {
        throw new Exception('Git checkout failed for parliament ' . $parliament . ' (exit ' . $checkoutExit . '): ' . ($checkoutOutput !== '' ? $checkoutOutput : 'no output'));
    }

    //get all current files
    $currentFiles = getFilesWithDates($realpath."/processed/");

    //get all changes from git
    $pullOutput = runGitSyncCommand($gitBin, '-C ' . escapeshellarg($realpath) . ' pull', $pullExit);
    if ($pullOutput !== '') {
        gitSyncLog('info', 'pull output: ' . $pullOutput);
    }
    if ($pullExit !== 0) {
        throw new Exception('Git pull failed for parliament ' . $parliament . ' (exit ' . $pullExit . '): ' . ($pullOutput !== '' ? $pullOutput : 'no output'));
    }

    //get all current files again
    $newCurrentFiles = getFilesWithDates($realpath."/processed/");

    if (empty($newCurrentFiles)) {
        throw new Exception('No session files found in processed/ after git sync for parliament ' . $parliament);
    }

    if (!is_dir(__DIR__ . "/input/")) {
        if (!mkdir(__DIR__ . "/input/", 0775, true) && !is_dir(__DIR__ . "/input/")) {
            throw new Exception('Could not create data/input directory');
        }
    }

    // On first clone, import all session files. Otherwise only copy files changed by pull.
    $copiedCount = 0;
    foreach ($newCurrentFiles as $fileName=>$file) {

        if ($wasFreshClone || !array_key_exists($fileName,$currentFiles) || ($currentFiles[$fileName]["date"] != $file["date"])) {
            if (!copy($realpath."/processed/".$fileName, __DIR__."/input/".$fileName)) {
                throw new Exception('Could not copy ' . $fileName . ' to data/input/. Check directory permissions.');
            }
            $copiedCount++;
            $return = true;
        }

    }

    gitSyncLog('info', 'Copied ' . $copiedCount . ' session file(s) to data/input for parliament ' . $parliament);

    return $return;

}

?>
