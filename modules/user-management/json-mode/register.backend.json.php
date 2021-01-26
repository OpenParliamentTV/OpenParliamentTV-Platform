<?php

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__."/functions.json.php");

function registerUser($mail = "", $passwd = "", $name="") {

	global $config;


	if (!file_exists(__DIR__."/userdata.json")) {

		$tmp = array();
		file_put_contents(__DIR__."/userdata.json", json_encode($tmp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

	}



	if ($mail == "" || $passwd== "" || $name=="") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing";
		return $return;

	} else {

		$mail = strtolower($mail);

		$userdata = getUserdata($mail);

		if ($userdata) {

			$return["success"] = "false";
			$return["txt"] = "Mail already registered";
			return $return;

			//TODO: Send Mail with forgotten-link

		} else {

			$userdata["name"] = $name;
			$userdata["mail"] = $mail;
			$userdata["pepper"] = bin2hex(random_bytes(9));
			$userdata["level"] = "user";
			$userdata["passwd"] = hash("sha512", $userdata["pepper"].$passwd.$config["salt"]);

			$file = new sharedFile(__DIR__."/userdata.json");
			$json = $file->read();
			$res = json_decode($json,true);
			$res[] = $userdata;
			$file->writeClose(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

			$return["success"] = "true";
			$return["txt"] = "User has been registered";
			return $return;

		}
	}

}

?>