<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/../import/functions.conflicts.php");


function getOrganisation($type = false, $label = false, $wikidataID = false, $dbPlatform = false) {

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

	if (($type === NULL) && (!$label) && (!$wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType IS NULL");
	} elseif (($type == NULL) && ($label) && (!$wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType IS NULL AND OrganisationLabel=?s", $label);
	} elseif (($type == NULL) && (!$label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType IS NULL AND OrganisationWikidataID=?s", $wikidataID);
	} elseif (($type == NULL) && ($label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType IS NULL AND OrganisationWikidataID=?s AND OrganisationLabel=?s", $wikidataID, $label);
	} elseif (($type) && (!$label) && (!$wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType=?s", $type);
	} elseif (($type) && ($label) && (!$wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType=?s AND OrganisationLabel=?s", $type, $label);
	} elseif (($type) && (!$label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType=?s AND OrganisationWikidataID=?s", $type, $wikidataID);
	} elseif (($type) && ($label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationType=?s AND OrganisationWikidataID=?s AND OrganisationLabel=?s", $type, $wikidataID, $label);
	} elseif ((!$type) && ($label) && (!$wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationLabel=?s", $label);
	} elseif ((!$type) && (!$label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationWikidataID=?s", $wikidataID);
	} elseif ((!$type) && ($label) && ($wikidataID)) {
		$data = $dbPlatform->getAll("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationWikidataID=?s AND OrganisationLabel=?s", $wikidataID, $label);
	}
	$return["success"] = "true";
	$return["text"] = "";
	$return["data"] = $data;
	return $return;

}



?>