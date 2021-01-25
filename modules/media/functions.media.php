<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function getMedia($identifier, $parliament = false, $getConflicts = false, $dbPlatform = false, $dbParliament = false) {

	global $config;

	if (preg_match("~^[0-9]+$~",$identifier)) {
		$field = "MediaID";
		$fieldType = "?i";

		//If we just get an internal ID we need to know the parliament.
		if (!$parliament) {
			return false;
		}

	} else {
		$field = "MediaHash";
		$fieldType = "?s";
		if (!$parliament) {
			$parliament = explode("-",$identifier);
			$parliament = $parliament[0];
		}
	}

	if (!array_key_exists($parliament,$config["parliament"])) {
		return false;
	}


	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}

	if (!$dbParliament) {
		$dbParliament = new SafeMySQL(array(
			'host' => $config["parliament"][$parliament]["sql"]["access"]["host"],
			'user' => $config["parliament"][$parliament]["sql"]["access"]["user"],
			'pass' => $config["parliament"][$parliament]["sql"]["access"]["passwd"],
			'db' => $config["parliament"][$parliament]["sql"]["db"]
		));
	}

	$return = $dbParliament->getRow("SELECT m.*, ai.*, se.*, ep.*  FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["Media"]." as m
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"]." AS ai
		ON ai.AgendaItemID = m.MediaAgendaItemID
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]["Session"]." AS se
		ON se.SessionID = ai.AgendaItemSessionID
	LEFT JOIN ".$config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"]." AS ep
		ON ep.ElectoralPeriodID = se.SessionElectoralPeriodID
		WHERE m.?n = ".$fieldType,$field,$identifier);


	$return["mediaannotation"] = $dbParliament->getAll("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["MediaAnnotation"]." WHERE MediaAnnotationMediaID = ?i",$return["MediaID"]);
	$return["mediacontent"] = $dbParliament->getAll("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["MediaContent"]." WHERE MediaContentMediaID = ?i",$return["MediaID"]);
	$return["mediaperson"] = $dbParliament->getAll("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["MediaPerson"]." WHERE MediaPersonMediaID = ?i",$return["MediaID"]);

	foreach ($return["mediaperson"] as $k=>$mp) {
		$tmpMediaPersonPlatformData = $dbPlatform->getRow("SELECT pe.*, pa.* FROM ".$config["platform"]["sql"]["tbl"]["Person"]." AS pe
			LEFT JOIN ".$config["platform"]["sql"]["tbl"]["Party"]." as pa
				ON pa.PartyID = pe.PersonPartyID
			WHERE pe.PersonID = ?i", $mp["MediaPersonPersonID"]);

		$return["mediaperson"][$k] = array_merge($return["mediaperson"][$k], $tmpMediaPersonPlatformData);

	}

	if ($getConflicts) {

		$return["conflicts"] = $dbPlatform->getAll("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Conflict"]." WHERE ConflictIdentifier = ?i OR ConflictRival = ?i",$return["MediaID"],$return["MediaID"]);

	}



	return $return;




}


?>