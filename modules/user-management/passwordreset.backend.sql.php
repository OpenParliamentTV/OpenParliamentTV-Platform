<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/functions.php");

if (!function_exists("L")) {
	require_once(__DIR__."/../../i18n.class.php");
	$i18n = new i18n(__DIR__."/../../lang/lang_{LANGUAGE}.json", __DIR__."/../../langcache/", "de");
	$i18n->init();
}


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
		$return["txt"] = L::messageErrorParameterMissingDetail;
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

			$passwordresetMailSubject = L::brand.': '.L::resetPassword;
			$passwordresetMailVerifyLink = $config['dir']['root'].'/passwordReset?id='.$userdata['UserID'].'&c='.$confirmationCode;

			$message = '<html><body>';
			$message .= '<p>'.L::messagePasswordResetMailStart.'</p>';
			$message .= '<p><a href="'.$passwordresetMailVerifyLink.'">'.$passwordresetMailVerifyLink.'</a></p>';
			$message .= '<p>'.L::messagePasswordResetMailEnd.'</p>';
			$message .= '</body></html>';

			$header = array(
				'From' => $config["mail"]["from"],
				'Reply-To' => $config["mail"]["replyto"],
				'X-Mailer' => 'PHP/' . phpversion()
			);


			mail($mail, $passwordresetMailSubject, $message, $header);

			$return["success"] = "true";
			$return["txt"] = L::messagePasswordResetMailSent;

		} else {

			$return["success"] = "false";
			$return["txt"] = L::messageAuthAccountNotFoundDetail;

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
		$return["txt"] = L::messageErrorParameterMissingDetail;
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
			$return["txt"] = L::messagePasswordResetCodeIncorrect;

			return $return;

		} else {

			$return["success"] = "true";
			$return["txt"] = "Password reset allowed"; // TODO i18n
			$return["UserPasswordReset"] = $userdata["UserPasswordReset"];
			$return["UserID"] = $userdata["UserID"];

			return $return;

		}

	}

}


function passwordResetChangePassword($id = false, $code = false, $password = false, $passwordCheck = false, $db = false) {

	global $config;

	if ((!$id) || (!$code)) {

		$return["success"] = "false";
		$return["txt"] = L::messageErrorParameterMissingDetail;
		return $return;

	}

	if (!passwordResetChangePassword($password)) {

		$return["success"] = "false";
		$return["txt"] = L::messagePasswordTooWeak;

		return $return;

	}

	if ($password != $passwordCheck) {

		$return["success"] = "false";
		$return["txt"] = L::messagePasswordNotIdentical;
		return $return;

	}

	$checkCode = passwordResetCheckCode($id, $code);
	if ($checkCode["success"] != "true") {

		$return["success"] = "false";
		$return["txt"] = L::messagePasswordResetCodeIncorrect;
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
	$return["txt"] = L::messagePasswordResetSuccess;
	return $return;






}

?>