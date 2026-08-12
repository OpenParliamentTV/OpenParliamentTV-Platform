<?php

require_once(__DIR__ . "/../../modules/utilities/paths.php");

function importGitSyncSessionFiles ($dir,$pattern = "~[0-9]{5}-session\.json~") {

    if (!is_dir($dir)) {
        return array();
    }

    // The caller scans the same paths before and after the pull, so drop PHP's stat cache or filemtime() reports pre-pull values.
    clearstatcache();

    $files = scandir($dir);
    $return = array();

    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            $return[$file] = array("file"=>$file,"date"=>filemtime($dir."/".$file));
        }
    }

    return $return;

}

function importGitSyncLog($type, $msg) {
    file_put_contents(optvVarFilePath("log", "cronUpdater.log"), date("Y-m-d H:i:s")." - ".$type.": [git-sync] ".$msg."\n", FILE_APPEND);
}

function importGitSyncCommand($gitBin, $command, &$exitCode = null) {
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

function importGitSync($parliament = "DE") {

    $return = false;
    $wasFreshClone = false;

    require_once(__DIR__ . "/../../config.php");
    require_once(__DIR__ . "/../../modules/utilities/safemysql.class.php");
    require_once(__DIR__ . "/../../api/v1/utilities.php");
    require_once(__DIR__ . "/../../api/v1/api.php");
    require_once(__DIR__ . "/../../api/v1/modules/media.php");
    global $config;

    $gitBin = $config["bin"]["git"] ?? "git";
    $repoUrl = $config["parliament"][$parliament]["git"]["repository"] ?? null;

    if (empty($repoUrl)) {
        throw new Exception('Git repository URL is not configured for parliament ' . $parliament);
    }

    // optvVarPath() creates var/data/repos on first use.
    $reposDir = optvVarPath("data/repos");
    if (!is_dir($reposDir)) {
        throw new Exception('Could not create var/data/repos directory');
    }

    if (!is_dir($reposDir."/".$parliament)) {
        if (!mkdir($reposDir."/".$parliament, 0775, true) && !is_dir($reposDir."/".$parliament)) {
            throw new Exception('Could not create var/data/repos/' . $parliament . ' directory');
        }
    }

    $realpath = realpath($reposDir."/".$parliament."/");
    if ($realpath === false) {
        throw new Exception('Could not resolve path for var/data/repos/' . $parliament);
    }

    if (!is_dir($realpath . "/.git")) {
        importGitSyncLog('info', 'Cloning ' . $repoUrl . ' into ' . $realpath);
        // Only the working tree at HEAD is ever read, so clone shallow. Later pulls fast-forward fine on a shallow clone.
        $cloneOutput = importGitSyncCommand(
            $gitBin,
            '-C ' . escapeshellarg($realpath) . ' clone --depth 1 ' . escapeshellarg($repoUrl) . ' .'
        );
        if ($cloneOutput !== '') {
            importGitSyncLog('info', 'clone output: ' . $cloneOutput);
        }

        if (!is_dir($realpath . "/.git")) {
            throw new Exception(
                'Git clone failed for parliament ' . $parliament . '. ' .
                ($cloneOutput !== '' ? $cloneOutput : 'Git returned no output. Check that git is installed and var/data/repos is writable.')
            );
        }

        $wasFreshClone = true;
    }

    // --add appends unconditionally, so only register the path when it is not already listed.
    $safeDirectories = explode("\n", importGitSyncCommand($gitBin, 'config --global --get-all safe.directory'));
    if (!in_array($realpath, $safeDirectories, true)) {
        importGitSyncCommand($gitBin, 'config --global --add safe.directory ' . escapeshellarg($realpath));
    }

    if (!is_dir($realpath . "/processed")) {
        throw new Exception('Git repository cloned but processed/ directory is missing for parliament ' . $parliament);
    }

    $checkoutOutput = importGitSyncCommand($gitBin, '-C ' . escapeshellarg($realpath) . ' checkout -f HEAD', $checkoutExit);
    if ($checkoutOutput !== '') {
        importGitSyncLog('info', 'checkout output: ' . $checkoutOutput);
    }
    if ($checkoutExit !== 0) {
        throw new Exception('Git checkout failed for parliament ' . $parliament . ' (exit ' . $checkoutExit . '): ' . ($checkoutOutput !== '' ? $checkoutOutput : 'no output'));
    }

    //get all current files
    $currentFiles = importGitSyncSessionFiles($realpath."/processed/");

    //get all changes from git
    $pullOutput = importGitSyncCommand($gitBin, '-C ' . escapeshellarg($realpath) . ' pull', $pullExit);
    if ($pullOutput !== '') {
        importGitSyncLog('info', 'pull output: ' . $pullOutput);
    }
    if ($pullExit !== 0) {
        throw new Exception('Git pull failed for parliament ' . $parliament . ' (exit ' . $pullExit . '): ' . ($pullOutput !== '' ? $pullOutput : 'no output'));
    }

    //get all current files again
    $newCurrentFiles = importGitSyncSessionFiles($realpath."/processed/");

    if (empty($newCurrentFiles)) {
        throw new Exception('No session files found in processed/ after git sync for parliament ' . $parliament);
    }

    // optvVarPath() creates var/data/input on first use.
    $inputDir = optvVarPath("data/input");
    if (!is_dir($inputDir)) {
        throw new Exception('Could not create var/data/input directory');
    }

    // On first clone, import all session files. Otherwise only copy files changed by pull.
    $copiedCount = 0;
    foreach ($newCurrentFiles as $fileName=>$file) {

        if ($wasFreshClone || !array_key_exists($fileName,$currentFiles) || ($currentFiles[$fileName]["date"] != $file["date"])) {
            if (!copy($realpath."/processed/".$fileName, $inputDir."/".$fileName)) {
                throw new Exception('Could not copy ' . $fileName . ' to var/data/input/. Check directory permissions.');
            }
            $copiedCount++;
            $return = true;
        }

    }

    importGitSyncLog('info', 'Copied ' . $copiedCount . ' session file(s) to var/data/input for parliament ' . $parliament);

    return $return;

}

?>
