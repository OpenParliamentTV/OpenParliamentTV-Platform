<?php
error_reporting(E_ALL ^ E_WARNING ^E_NOTICE);
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");



$parliament = explode("-",$_REQUEST["v"]);
$parliament = $parliament[0];

if (!$_REQUEST["v"] || !array_key_exists($parliament,$config["parliament"])) {
	echo "No valid hash was given.";
	exit;
}


$dbPlatform = new SafeMySQL(array(
	'host'	=> $config["platform"]["sql"]["access"]["host"],
	'user'	=> $config["platform"]["sql"]["access"]["user"],
	'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
	'db'	=> $config["platform"]["sql"]["db"]
));

$dbParliament = new SafeMySQL(array(
	'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
	'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
	'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
	'db'	=> $config["parliament"][$parliament]["sql"]["db"]
));

$speech = $dbParliament->getRow("SELECT s.*, ai.*, se.*, ep.* FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["Speech"]." AS s
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"]." AS ai
		ON ai.AgendaItemID = s.SpeechAgendaItemID
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]["Session"]." AS se
		ON se.SessionID = ai.AgendaItemSessionID
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]." AS ep
		ON ep.ElectoralPeriodID = se.SessionElectoralPeriodID
	WHERE s.SpeechHash = ?s LIMIT 1",$_REQUEST["v"]);

$speech["speechannotation"] = $db->getAll("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["SpeechAnnotation"]." WHERE SpeechAnnotationSpeechID = ?i",$speech["SpeechID"]);
$speech["speechcontent"] = $db->getAll("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["SpeechContent"]." WHERE SpeechContentSpeechID = ?i",$speech["SpeechID"]);

$platformData = $dbPlatform->getRow("SELECT p.*, sp.* FROM ".$config["platform"]["sql"]["tbl"]["Party"]." AS sp
	LEFT JOIN ".$config["platform"]["sql"]["tbl"]["Party"]." as p
		ON p.PartyID = sp.SpeakerPartyID
	WHERE sp.SpeakerID = ?i", $speech["SpeechSpeakerID"]);


	echo "<pre>";
	print_r(array_merge($speech,$platformData));
	echo "</pre>";


/*
echo "No hash given. Try with ?v=HASH";

$example = $db->getOne("SELECT SpeechHash FROM ".$config["sql"]["tbl"]["Speech"]." LIMIT 1");
if ($example) {
	echo "<br><br><br>For example: ".$_SERVER["SERVER_NAME"].$_SERVER["PHP_SELF"]."?v=".$example;
}
*/

?>