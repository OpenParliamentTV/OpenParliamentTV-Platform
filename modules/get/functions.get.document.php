<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/../import/functions.conflicts.php");


function getDocument($type = false, $label = false, $wikidataID = false, $dbPlatform = false) {

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}

	if (!$dbPlatform) {
		$return["success"] = "false";
		$return["text"] = "Not connectable to Platform Database";
		return $return;
	}

	if (!$type && !$label && !$wikidataID) {
		$return["success"] = "false";
		$return["text"] = "No parameter given";
		return $return;
	}


	if ($type == "ALL") {
		$sqlpart = "1";
	} else {

		if ($type == "NULL") {
			$sqlpart = "OrganisationType IS NULL";
		} elseif ($type) {
			$sqlpart = $dbPlatform->parse("OrganisationType = ?s", $type);
		} else {
			$sqlpart = "1";
		}

		if ($label == "NULL") {
			$sqlpart .= " AND OrganisationLabel IS NULL";
		} elseif ($label) {
			$sqlpart .= $dbPlatform->parse(" AND OrganisationLabel = ?s",$label);
		}

		if ($wikidataID) {
			$sqlpart .= $dbPlatform->parse(" AND OrganisationWikidataID = ?s",$wikidataID);
		}
	}

	$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE ?p",$sqlpart);


	$return["success"] = "true";
	$return["text"] = "";
	$return["data"] = $data;
	return $return;

}



?>