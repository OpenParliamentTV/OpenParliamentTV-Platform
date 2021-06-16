<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/functions.php");


/**
 * @param string $mail
 * @param object $db
 * @return mixed
 *
 * generates a password reset code and sends mail to user
 *
 */

function passwordResetMail($mail = "", $db = false) {

	global $config;

	if (($mail == "") || (!filter_var($mail, FILTER_VALIDATE_EMAIL))) {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing"; // TODO i18n
		return $return;

	} else {

		if (!$db) {
			$opts = array(
				'host' => $config["platform"]["sql"]["access"]["host"],
				'user' => $config["platform"]["sql"]["access"]["user"],
				'pass' => $config["platform"]["sql"]["access"]["passwd"],
				'db' => $config["platform"]["sql"]["db"]
			);
			$db = new SafeMySQL($opts);
		}

		$mail = strtolower($mail);

		$userdata = $db->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["User"] . " WHERE UserMail =?s LIMIT 1", $mail);

		if ($userdata) {

			$confirmationCode = bin2hex(random_bytes(10));

			$db->query("UPDATE " . $config["platform"]["sql"]["tbl"]["User"] . " SET UserPasswordReset=?s WHERE UserID=?i LIMIT 1", $confirmationCode, $userdata["UserID"]);

			$passwordresetMailSubject = "Reset your password";  // TODO i18n
			$passwordresetMailMessagePart1 = "You requested to reset your password. Please visit the following website:\r\n";  // TODO i18n
			$passwordresetMailMessageLink = $_SERVER['HTTP_HOST'] . "/index.php?a=passwordReset&id=" . $userdata["UserID"] . "&c=" . $confirmationCode;  // TODO i18n
			$passwordresetMailMessagePart2 = "\r\n\r\nIn case your didn't request to reset the password, you can just ignore this mail.\r\n";  // TODO i18n
			$passwordresetMailMessage = $passwordresetMailMessagePart1 . $passwordresetMailMessageLink . $passwordresetMailMessagePart2;  // TODO i18n

			$header = array(
				'From' => $config["mail"]["from"],
				'Reply-To' => $config["mail"]["replyto"],
				'X-Mailer' => 'PHP/' . phpversion()
			);


			mail($mail, $passwordresetMailSubject, $passwordresetMailMessage, $header);

			$return["success"] = "true";
			$return["txt"] = "Link to reset the password has been sent."; // TODO i18n

		} else {

			$return["success"] = "false";
			$return["txt"] = "User not found"; // TODO i18n

		}
	}
	return $return;

}


/**
 * @param int $id
 * @param string $code
 * @return mixed
 * Checks if password reset code matches with given user id
 */
function passwordResetCheckCode($id = false, $code = false) {


	global $config;

	if ((!$id) || (strlen($code) <= 10)) {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing"; // TODO i18n
		return $return;

	} else {

		$opts = array(
			'host' => $config["platform"]["sql"]["access"]["host"],
			'user' => $config["platform"]["sql"]["access"]["user"],
			'pass' => $config["platform"]["sql"]["access"]["passwd"],
			'db' => $config["platform"]["sql"]["db"]
		);

		$db = new SafeMySQL($opts);

		$userdata = $db->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["User"] . " WHERE UserID=?i LIMIT 1", $id);

		if ($userdata["UserPasswordReset"] != $code) {

			$return["success"] = "false";
			$return["txt"] = "Code incorrect for given user."; // TODO i18n

			return $return;

		} else {

			$return["success"] = "true";
			$return["txt"] = "Password reset allowed"; // TODO i18n
			$return["UserPasswordReset"] = $userdata["UserPasswordReset"]; // TODO i18n
			$return["UserID"] = $userdata["UserID"]; // TODO i18n

			return $return;

		}

	}

}


function passwordResetChangePassword($id = false, $code = false, $password = false, $passwordCheck = false, $db = false) {

	global $config;

	if ((!$id) || (!$code)) {

		$return["success"] = "false";
		$return["txt"] = "Parameter missing"; // TODO i18n
		return $return;

	}

	if (!passwordResetChangePassword($password)) {

		$return["success"] = "false";
		$return["txt"] = "Password too weak"; // TODO i18n
		return $return;

	}

	if ($password != $passwordCheck) {

		$return["success"] = "false";
		$return["txt"] = "Passwords not the same"; // TODO i18n
		return $return;

	}

	$checkCode = passwordResetCheckCode($id, $code);
	if ($checkCode["success"] != "true") {

		$return["success"] = "false";
		$return["txt"] = "wrong password resetcode"; // TODO i18n
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

	$pepper = bin2hex(random_bytes(9));

	$db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserPasswordHash=?s, UserPasswordReset=?i, UserPasswordPepper=?s WHERE UserID=?i", hash("sha512", $pepper.$password.$config["salt"]), 0,  $pepper, $id);
	$return["success"] = "true";
	$return["txt"] = "User has been registered"; // TODO i18n
	return $return;






}

?>