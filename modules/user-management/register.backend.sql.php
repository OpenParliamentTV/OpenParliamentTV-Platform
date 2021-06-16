<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/functions.php");



function registerUser($mail = "", $passwd = "", $name="", $db = false) {

	global $config;


	if ($mail == "" || $passwd== "" || $name=="") {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing"; // TODO i18n
		return $return;

	} elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

		$return["success"] = "false";
		$return["txt"] = "Mail not valid"; // TODO i18n
		return $return;

	} elseif (passwordStrength($passwd) != true) {

		$return["success"] = "false";
		$return["txt"] = "Password doesn't comply with the requirements"; // TODO i18n
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
			$return["txt"] = "Mail already registered"; // TODO i18n
			return $return;

			//TODO: Send Mail with forgotten-link?

		} else {

			$pepper = bin2hex(random_bytes(9));
			$confirmationCode = bin2hex(random_bytes(10));


			$db->query("INSERT INTO ".$config["platform"]["sql"]["tbl"]["User"]." SET
				UserName=?s,
				UserMail=?s,
				UserPasswordHash=?s,
				UserPasswordPepper=?s,
				UserRole=?s,
				UserRegisterDate=?s,
				UserActive=?i,
				UserRegisterConfirmation=?s",
				$name, $mail, hash("sha512", $pepper.$passwd.$config["salt"]),  $pepper, "user", time(), 0, $confirmationCode);
			$userID = $db->insertId();

			$registrationMailSubject = "Your registration"; // TODO i18n
			$registrationMailMessagePart1 = "You registered to Openparliament-TV. Please confirm your Mail-Address by visit the following website:\r\n"; // TODO i18n
			$registrationMailMessageLink = $_SERVER['HTTP_HOST']."/index.php?a=registerConfirm&id=".$userID."&c=".$confirmationCode; // TODO i18n
			$registrationMailMessagePart2 = "\r\n\r\nThank you.\r\n"; // TODO i18n
			$registrationMailMessage = $registrationMailMessagePart1.$registrationMailMessageLink.$registrationMailMessagePart2;  // TODO i18n

			$header = array(
				'From' => $config["mail"]["from"],
				'Reply-To' => $config["mail"]["replyto"],
				'X-Mailer' => 'PHP/' . phpversion()
			);


			mail($mail, $registrationMailSubject, $registrationMailMessage, $header);

			$return["success"] = "true";
			$return["txt"] = "User has been registered"; // TODO i18n
			$return["UserID"] = $userID; // TODO maybe delete for production server?
			return $return;

		}
	}

}

?>