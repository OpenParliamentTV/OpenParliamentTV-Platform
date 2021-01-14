<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function auth($UserID, $Action, $Entity, $dbPlatform = false) {

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}


	$user = $dbPlatform->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID=?i",$UserID);

	if ((!$user) || ($user["UserActive"] != true)) {
		return false;
	}

	if ($user["UserRole"] == "admin") {
		return true;
	}


	/*
	$dbParliament = new SafeMySQL(array(
		'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
		'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
		'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
		'db'	=> $config["parliament"][$parliament]["sql"]["db"]
	));
	*/


	switch ($Action) {
		case "VideoGetAllParameter":

			$split = explode("-",$Entity);
			$parliament = $split[0];
			$SpeechHash = $split[1];

			$dbParliament = new SafeMySQL(array(
				'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
				'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
				'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
				'db'	=> $config["parliament"][$parliament]["sql"]["db"]
			));

		break;

		default:
		return false;
	}

	return false;
}




?>