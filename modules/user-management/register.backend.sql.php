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
				UserActive=?i,
				UserRegisterConfirmation=?s",
				$name, $mail, hash("sha512", $pepper.$passwd.$config["salt"]),  $pepper, "user", 0, $confirmationCode);
			$userID = $db->insertId();

			$registrationMailSubject = "Open Parliament TV: Registrierung"; // TODO i18n
			$registrationMailVerifyLink = $config['dir']['root'].'/registerConfirm?id='.$userID.'&c='.$confirmationCode;

			$message = '<html><body>';
			$message .= '<p>Vielen Dank für deine Registrierung auf <b>de.openparliament.tv</b>.</p>'; // TODO i18n
			$message .= '<p>Bitte bestätige deine E-Mail-Adresse indem du folgenden Link anklickst:</p>\r\n'; // TODO i18n
			$message .= '<a href="'.$registrationMailVerifyLink.'"></a><br>\r\n';
			$message .= '</body></html>';

			$header = array(
				'MIME-Version' => '1.0',
				'Content-type' => 'text/html; charset=iso-8859-1',
				'From' => $config["mail"]["from"],
				'Reply-To' => $config["mail"]["replyto"],
				'X-Mailer' => 'PHP/' . phpversion()
			);


			mail($mail, $registrationMailSubject, $message, $header);

			$return["success"] = "true";
			$return["txt"] = "User has been registered"; // TODO i18n
			$return["UserID"] = $userID; // TODO maybe delete for production server?
			return $return;

		}
	}

}

?>