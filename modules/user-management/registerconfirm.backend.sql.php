<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function registerConfirm($id = "", $registerConfirmation = "", $db = false) {

	global $config;


	if ($id == "" || $registerConfirmation== "") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing"; // TODO i18n
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


		$userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID =?i LIMIT 1", $id);

		if ($userdata) {


			if ($userdata["UserBlocked"] == 1) {

				$return["success"] = "false";
				$return["txt"] = "Account has been blocked. Please get in touch"; // TODO i18n

				return $return;

			}


			if ($userdata["UserRegisterConfirmation"] == $registerConfirmation) {


				$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserActive=1, UserRegisterConfirmation=1 WHERE UserID=?i LIMIT 1", $userdata["UserID"]);

				$return["success"] = "true";
				$return["txt"] = "Account has been activated"; // TODO i18n

				return $return;

			} else {

				$return["success"] = "false";
				$return["txt"] = "wrong confirmation code"; // TODO i18n

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