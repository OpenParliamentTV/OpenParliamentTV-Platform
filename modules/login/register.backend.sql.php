<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function registerUser($mail = "", $passwd = "", $name="") {

	global $config;


	if ($mail == "" || $passwd== "" || $name=="") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing";
		return $return;

	} elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

		$return["success"] = "false";
		$return["txt"] = "Mail not valid";
		return $return;

	} else {

		if (!$db) {
			$opts = array(
				'host'	=> $config["platform"]["sql"]["access"]["host"],
				'user'	=> $config["platform"]["sql"]["access"]["user"],
				'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
				'db'	=> $config["platform"]["sql"]["db"]
			);
			$db = new SafeMySQL($opts);
		}


		$mail = strtolower($mail);

		$userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1",$mail);

		if ($userdata) {

			$return["success"] = "false";
			$return["txt"] = "Mail already registered";
			return $return;

			//TODO: Send Mail with forgotten-link?

		} else {

			$pepper = bin2hex(random_bytes(9));

			//TODO: User ConfirmationLink

			$db->query("INSERT INTO ".$config["platform"]["sql"]["tbl"]["User"]." SET
				UserName=?s,
				UserMail=?s,
				UserPasswordHash=?s,
				UserPasswordPepper=?s,
				UserRole=?s,
				UserRegisterDate=?s,
				UserActive=?i",
				$name, $mail, hash("sha512", $pepper.$passwd.$config["salt"]),  $pepper, "user", time(), 0);

			$return["success"] = "true";
			$return["txt"] = "User has been registered";
			return $return;

		}
	}

}

?>