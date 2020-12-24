<?php
error_reporting(E_ALL ^ E_WARNING ^E_NOTICE);
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

if (!$db) {
	$opts = array(
		'host'	=> $config["sql"]["access"]["host"],
		'user'	=> $config["sql"]["access"]["user"],
		'pass'	=> $config["sql"]["access"]["passwd"],
		'db'	=> $config["sql"]["db"]
	);
	$db = new SafeMySQL($opts);
}

if ($_REQUEST["v"]) {



	$dbspeech = $db->getRow("SELECT s.*, ai.*, ep.*, p.*, se.*, sp.* FROM ".$config["sql"]["tbl"]["Speech"]." as s
	LEFT JOIN ".$config["sql"]["tbl"]["AgendaItem"]." AS ai
		ON ai.AgendaItemID = s.SpeechAgendaItemID
	LEFT JOIN ".$config["sql"]["tbl"]["Session"]." AS se
		ON se.SessionID = ai.AgendaItemSessionID
	LEFT JOIN ".$config["sql"]["tbl"]["ElectoralPeriod"]." AS ep
		ON ep.ElectoralPeriodID = se.SessionElectoralPeriodID
	LEFT JOIN ".$config["sql"]["tbl"]["Speaker"]." AS sp
		ON sp.SpeakerID = s.SpeechSpeakerID
	LEFT JOIN ".$config["sql"]["tbl"]["Party"]." AS p
		ON p.PartyID = sp.SpeakerPartyID
	WHERE SpeechHash = ?s LIMIT 1",$_REQUEST["v"]);

	$dbspeech["speechannotation"] = $db->getAll("SELECT * FROM ".$config["sql"]["tbl"]["SpeechAnnotation"]." WHERE SpeechAnnotationSpeechID = ?i",$dbspeech["SpeechID"]);
	$dbspeech["speechcontent"] = $db->getAll("SELECT * FROM ".$config["sql"]["tbl"]["SpeechContent"]." WHERE SpeechContentSpeechID = ?i",$dbspeech["SpeechID"]);

	echo "<pre>";
	print_r($dbspeech);
	echo "</pre>";


} else {

	echo "No hash given. Try with ?v=HASH";

	$example = $db->getOne("SELECT SpeechHash FROM ".$config["sql"]["tbl"]["Speech"]." LIMIT 1");
	if ($example) {
		echo "<br><br><br>For example: ".$_SERVER["SERVER_NAME"].$_SERVER["PHP_SELF"]."?v=".$example;
	}

}


?>