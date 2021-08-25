<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

if (!function_exists("L")) {
	require_once(__DIR__."/../../i18n.class.php");
	$i18n = new i18n(__DIR__."/../../lang/lang_{LANGUAGE}.json", __DIR__."/../../langcache/", "de");
	$i18n->init();
}

function loginCheck($mail = "", $passwd = "") {

	global $config;


	if ($mail == "" || $passwd== "") {

		$return["success"] = "false";
		$return["txt"] = L::messageErrorParameterMissingDetail;
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
					$return["txt"] = L::messageLoginSuccessGeneric;

					$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserLastLogin=current_timestamp() WHERE UserID=?i LIMIT 1", $userdata["UserID"]);

				} elseif ($userdata["UserBlocked"] == 1) {

					$return["success"] = "false";
					$return["txt"] = L::messageAuthAccountBlockedDetail;

				} else {

					$return["success"] = "false";
					$return["txt"] = L::messageAuthAccountNotActiveDetail;

				}


				return $return;

			} else {

				$return["success"] = "false";
				$return["txt"] = L::messageLoginErrorPasswordNotCorrect;
				return $return;

			}


		} else {

			$return["success"] = "false";
			$return["txt"] = L::messageAuthAccountNotFoundDetail;
			return $return;

		}

	}


}

?>