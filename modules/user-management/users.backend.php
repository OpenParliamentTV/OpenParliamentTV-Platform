<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

if (!function_exists("L")) {
	require_once(__DIR__."/../../i18n.class.php");
	$i18n = new i18n(__DIR__."/../../lang/lang_{LANGUAGE}.json", __DIR__."/../../langcache/", "en");
	$i18n->init();
}

function getUsers($id = false, $db = false) {

	global $config;

	//TODO: AUTH

    if ($_SESSION["userdata"]["role"] != "admin") {

        $return["success"] = "false";
        $return["txt"] = L::messageAuthNotPermittedDetail;

        return $return;

    }

	if (!$db) {

		$opts = array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		);

		$db = new SafeMySQL($opts);

	}

	if (!$id) {

		$return["return"] = $db->getAll("SELECT UserID,UserName,UserMail,UserRole,UserRegisterDate,UserLastLogin,UserActive,UserBlocked FROM ".$config["platform"]["sql"]["tbl"]["User"]);

	} else {

		$return["return"] = $db->getRow("SELECT UserID,UserName,UserMail,UserRole,UserRegisterDate,UserLastLogin,UserActive,UserBlocked FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID=?i",$id);

	}

	if ($return["return"]) {

		$return["success"] = "true";

	} else {

		$return["success"] = "false";

	}

	return $return;


}



?>