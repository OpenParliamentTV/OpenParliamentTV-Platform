<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/functions.php");

if (!function_exists("L")) {
	require_once(__DIR__."/../../i18n.class.php");
	$i18n = new i18n(__DIR__."/../../lang/lang_{LANGUAGE}.json", __DIR__."/../../langcache/", "de");
	$i18n->init();
}

function registerUser($mail = "", $passwd = "", $passwdCheck="", $name="", $db = false) {

	global $config;


	if ($mail == "" || $passwd== "" || $name=="") {

		$return["success"] = "false";
		$return["txt"] = L::messageErrorParameterMissingDetail;
		return $return;

	} elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {

		$return["success"] = "false";
		$return["txt"] = "Mail not valid"; // TODO i18n
		return $return;

	} elseif (passwordStrength($passwd) != true) {

        $return["success"] = "false";
        $return["txt"] = L::messagePasswordTooWeak;
        return $return;

    } elseif ($passwd != $passwdCheck) {

        $return["success"] = "false";
        $return["txt"] = L::messagePasswordNotIdentical;
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
			$return["txt"] = L::messageAccountWithMailAlreadyExists;
			return $return;

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

			$registrationMailSubject = L::brand.': '.L::registerNewAccount;
			$registrationMailVerifyLink = $config['dir']['root'].'/registerConfirm?id='.$userID.'&c='.$confirmationCode;

			$message = '<html><body>';
			$message .= '<p>'.L::hello.' '.$name.',</p>';
			$message .= '<p>'.L::messageRegisterThankYou.' <b>'.$config['dir']['root'].'</b>.</p>';
			$message .= '<p>'.L::messageRegisterClickLinkToValidate.'</p>';
			$message .= '<p><a href="'.$registrationMailVerifyLink.'">'.$registrationMailVerifyLink.'</a></p>';
			$message .= '<p>'.L::messageMailGreetings.',<br>'.L::brand.'</p>';
			$message .= '</body></html>';

			$header = array(
				'MIME-Version' => '1.0',
				'Content-type' => 'text/html; charset=utf-8',
				'From' => $config["mail"]["from"],
				'X-Mailer' => 'PHP/' . phpversion()
			);


			mail($name.' <'.$mail.'>', $registrationMailSubject, $message, $header);

			$return["success"] = "true";
			$return["txt"] = L::messageRegisterSuccess;
            //$return["UserID"] = $userID; // TODO maybe delete for production server?
			return $return;

		}
	}

}

?>