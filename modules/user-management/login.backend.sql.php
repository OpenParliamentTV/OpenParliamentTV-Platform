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
				'host'	=> $config["platform"]["sql"]["access"]["host"],
				'user'	=> $config["platform"]["sql"]["access"]["user"],
				'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
				'db'	=> $config["platform"]["sql"]["db"]
			);
			$db = new SafeMySQL($opts);
		}


		$mail = strtolower($mail);

		$userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail =?s LIMIT 1", $mail);

		if ($userdata) {


			if ($userdata["UserPasswordHash"] == hash("sha512", $userdata["UserPasswordPepper"].$passwd.$config["salt"])) {


				if ($userdata["UserActive"] && ($userdata["UserBlocked"] != 1)) {

					$_SESSION["login"] = 1;
					$_SESSION["userdata"]["mail"]	= $userdata["UserMail"];
					$_SESSION["userdata"]["name"]	= $userdata["UserName"];
					$_SESSION["userdata"]["id"]		= $userdata["UserID"];
					$_SESSION["userdata"]["role"] 	= $userdata["UserRole"];
					$return["success"] = "true";
					$return["txt"] = "Login success"; // TODO i18n

					$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserLastLogin=current_timestamp() WHERE UserID=?i LIMIT 1", $userdata["UserID"]);

				} elseif ($userdata["UserBlocked"] == 1) {

					$return["success"] = "false";
					$return["txt"] = "Account has been blocked. Please get in touch"; // TODO i18n

				} else {

					$return["success"] = "false";
					$return["txt"] = "Login not active"; // TODO i18n

				}


				return $return;

			} else {

				$return["success"] = "false";
				$return["txt"] = "Password not correct"; // TODO i18n
				return $return;

			}


		} else {

			$return["success"] = "false";
			$return["txt"] = "Userdata not found"; // TODO i18n
			return $return;

		}

	}


}

?>