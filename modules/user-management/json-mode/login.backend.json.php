<?php

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__."/functions.json.php");

function loginCheck($mail = "", $passwd = "") {

	global $config;



	if (!file_exists(__DIR__."/userdata.json")) {

		$tmp = array();
		file_put_contents(__DIR__."/userdata.json", json_encode($tmp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

	}



	if ($mail == "" || $passwd== "") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing";
		return $return;

	} else {

		$mail = strtolower($mail);

		$userdata = getUserdata($mail);

		if ($userdata["success"] == "true") {


			if ($userdata["user"]["passwd"] == hash("sha512", $userdata["user"]["pepper"].$passwd.$config["salt"])) {

				$_SESSION["login"] = 1;
				$_SESSION["mail"] = $userdata["user"]["mail"];
				$_SESSION["name"] = $userdata["user"]["name"];
				$_SESSION["index"] = $userdata["index"];
				$_SESSION["level"] = $userdata["user"]["level"];

				$return["success"] = "true";
				$return["txt"] = "Login success";
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