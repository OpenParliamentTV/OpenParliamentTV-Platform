<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");

function auth($userID, $action, $entity, $db = false) {

	global $config;

    if  (!$userID) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "401"; //TODO CODE
        $errorarray["code"] = "1";
        $errorarray["title"] = "Userdata missing";
        $errorarray["detail"] = "No Userdata has been provided."; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ((!$action) || (!$entity)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503"; //TODO CODE
        $errorarray["code"] = "1";
        $errorarray["title"] = "Parameter missing";
        $errorarray["detail"] = "Auth required parameter are missing"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!$db) {

        try {

            $db = new SafeMySQL(array(
                'host'	=> $config["platform"]["sql"]["access"]["host"],
                'user'	=> $config["platform"]["sql"]["access"]["user"],
                'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                'db'	=> $config["platform"]["sql"]["db"]
            ));

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503"; //TODO CODE
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }


	$user = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID=?i",$userID);

	if ((!$user) || ($user["UserActive"] != true) || ($user["UserBlocked"] != false)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403"; //TODO CODE
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account not active";
        $errorarray["detail"] = "This account has not been activated, is blocked or was not found"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;
	}

	if ($user["UserRole"] == "admin") {
        $return["meta"]["requestStatus"] = "success";
        $return["errors"] = array();
        $errorarray["status"] = "200";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Allowed";
        $errorarray["detail"] = "User is admin - action was permitted"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;
	}

	switch ($action) {

		case "requestPage":

            $return["meta"]["requestStatus"] = "success";
            $return["errors"] = array();
            $errorarray["status"] = "200";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Allowed";
            $errorarray["detail"] = "Action permitted"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

		break;

		default:
            $return["meta"]["requestStatus"] = "success";
            $return["errors"] = array();
            $errorarray["status"] = "403";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Allowed";
            $errorarray["detail"] = "User is admin - action was permitted"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;
	}

	return false;
}




?>