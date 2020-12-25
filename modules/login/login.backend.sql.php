<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function loginCheck($mail = "", $passwd = "") {

	global $config;


	if ($mail == "" || $passwd== "") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing";
		return $return;

	} else {

		if (!$db) {
			$opts = array(
				'host'	=> $config["sql"]["access"]["host"],
				'user'	=> $config["sql"]["access"]["user"],
				'pass'	=> $config["sql"]["access"]["passwd"],
				'db'	=> $config["sql"]["db"]
			);
			$db = new SafeMySQL($opts);
		}


		$mail = strtolower($mail);

		$userdata = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["User"]." WHERE UserMail =?s LIMIT 1", $mail);

		if ($userdata) {


			if ($userdata["UserPasswordHash"] == hash("sha512", $userdata["UserPasswordPepper"].$passwd.$config["salt"])) {


				if ($userdata["UserActive"]) {

					$_SESSION["login"] = 1;
					$_SESSION["userdata"]["mail"]	= $userdata["UserMail"];
					$_SESSION["userdata"]["name"]	= $userdata["UserName"];
					$_SESSION["userdata"]["id"]		= $userdata["UserID"];
					$_SESSION["userdata"]["role"] 	= $userdata["UserRole"];
					$return["success"] = "true";
					$return["txt"] = "Login success";

				} else {

					$return["success"] = "false";
					$return["txt"] = "Login not active";

				}


				return $return;

			} else {

				$return["success"] = "false";
				$return["txt"] = "Password not correct";
				return $return;

			}


		} else {

			$return["success"] = "false";
			$return["txt"] = "Userdata not found";
			return $return;

		}

	}


}

?>