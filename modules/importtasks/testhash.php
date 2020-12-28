<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
$time_start = microtime(true);

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/uniqueFreeString.php");


if (!$db) {
	$opts = array(
		'host'	=> $config["sql"]["access"]["host"],
		'user'	=> $config["sql"]["access"]["user"],
		'pass'	=> $config["sql"]["access"]["passwd"],
		'db'	=> $config["sql"]["db"]
	);
	$db = new SafeMySQL($opts);
}

$speeches = $db->getAll("SELECT * FROM ".$config["sql"]["tbl"]["Speech"]);

$output = "";

$tmpCnt[0] = 0;


function checkHash($hash, $string, $loop=0) {

	$return["success"] = true;
	$return["loop"] = $loop;

	if (hash("crc32b", $string) != $hash) {
		$return = checkHash($hash, $string.$string, $loop+1);
	}
	return $return;

}


foreach ($speeches as $speech) {

	$checkHash = checkHash($speech["SpeechHash"], $speech["SpeechOriginalMediaURL"]);

	if ($checkHash["loop"] > 0) {
		$output .= json_encode($checkHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
		$output .= json_encode($speech, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
		if (!array_key_exists($checkHash["loop"], $tmpCnt)) {
			$tmpCnt[$checkHash["loop"]] = 0;
		}
		$tmpCnt[$checkHash["loop"]]++;
		$output .= "\n#################################################################################\n\n\n";
	} else {
		$tmpCnt[0]++;
	}

}
//print_r($tmpCnt);
$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;
echo "<pre>";
echo count($speeches)." speech hashes had been analyzed in ".number_format((float) $execution_time, 10)." seconds.\n";
echo "#################################----SUMMARY----#################################\n";
foreach ($tmpCnt as $k=>$v) {

	echo "#\t".$v." speech".(($v>1)?"es":"")." needed ".$k." additional attempt".(($k>1)?"s":"")." to get an unique hash.\t".((strlen($k.$v)<4)?"\t":"")."#\n";

}
echo "#################################################################################\n\n\n";
echo "#################################################################################\n";
echo "#################################################################################\n";
echo "#################################----DETAILS----#################################\n";
echo $output;
echo "</pre>";
?>