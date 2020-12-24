<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");


/**
 * @param string $tbl - Table where we are looking for a unique string
 * @param string $column - the affected column
 * @param int $length - how long the unique string shall be
 * @param SafeMySQLObject $db
 * @return string of characters unique for this column at this table
 */
function getUniqueFreeString($tbl = false, $column = false, $length = 7, $db = false) {
	global $config;

	if (!$tbl || !$column) {
		return false;
	}

	if (!$db) {
		$opts = array(
				'host'	=> $config["sql"]["access"]["host"],
				'user'	=> $config["sql"]["access"]["user"],
				'pass'	=> $config["sql"]["access"]["passwd"],
				'db'	=> $config["sql"]["db"]
		);
		$db = new SafeMySQL($opts);
	}

	$randString = bin2hex(random_bytes($length));

	$count = $db->getOne("SELECT COUNT(*) as cnt FROM ".$tbl." WHERE ".$column." = ?s",$randString);
	if ($count > 0) {
		$randString = getUniqueFreeString($tbl, $column, $length, $db);
	}
	return $randString;

}



?>