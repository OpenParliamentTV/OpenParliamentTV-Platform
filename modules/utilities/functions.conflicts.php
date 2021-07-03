<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/safemysql.class.php");
require_once(__DIR__."/uniqueFreeString.php");

/**
 * @param string $entity
 * What kind of element is it
 *
 * @param string $identifier [optional]
 * How to identify the item - e.g. its ID
 *
 * @param string $rival [optional]
 * With which other Item does the conflict exist
 *
 * @param string $subject
 * Short Subject about the conflict like the taken action
 *
 * @param string $description [optional]
 * Long description like a log or errormessage
 *
 * @param bool $dbPlatform [optional]
 * The SafeMySQL Object for the platform database
 *
 * @return int
 * The ID of the added conflict
 *
 */
function reportConflict($entity, $subject, $identifier="", $rival="", $description="", $dbPlatform = false) {

	//TODO Auth

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}

	$dbPlatform->query("INSERT INTO " . $config["platform"]["sql"]["tbl"]["Conflict"] . " SET ConflictEntity = ?s, ConflictIdentifier=?s, ConflictRival=?s, ConflictSubject=?s, ConflictDescription=?s, ConflictDate=?s, ConflictTimestamp=?i", $entity, $identifier, $rival, $subject, $description, date("Ymd H:i:s"), time());
	return $dbPlatform->insertId();

}

function getConflicts($id = "all", $includeResolved = false, $dbPlatform = false) {

	//TODO Auth

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}

	$queryPart = "";

	if ($id == "all") {
		$queryPart .= "1";
	} else {
		$queryPart .= $dbPlatform->parse("ConflictID=?i",$id);
	}


	if ($includeResolved === true) {
		$queryPart .= " AND 1";
	} else {
		$queryPart .= " AND ConflictResolved=0";
	}


	return $dbPlatform->getAll("SELECT * FROM  " . $config["platform"]["sql"]["tbl"]["Conflict"]." WHERE ?p", $queryPart);

}


?>