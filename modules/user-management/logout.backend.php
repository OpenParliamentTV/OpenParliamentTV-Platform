<?php

function logout() {

	$_SESSION = array();


	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params["path"],
			$params["domain"], $params["secure"], $params["httponly"]
		);
	}


	session_destroy();

	$ret["success"] = "true";
	return $ret;


}

?>