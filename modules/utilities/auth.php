<?php

require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../i18n/language.php");

// Language is automatically initialized by language.php if needed

function auth($userID, $action, $entity, $db = false) {

	global $config;

    if  (($config["allow"]["publicAccess"] != true) && (!$userID)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "401"; //TODO CODE
        $errorarray["code"] = "1";
        $errorarray["title"] = L::messageAuthLoginRequiredTitle();
        $errorarray["detail"] = L::messageAuthLoginRequiredDetail();
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ((!$action) || (!$entity)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503"; //TODO CODE
        $errorarray["code"] = "1";
        $errorarray["title"] = L::messageErrorParameterMissingTitle();
        $errorarray["detail"] = L::messageErrorParameterMissingDetail();
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
            $errorarray["title"] = L::messageErrorNoDatabaseConnectionTitle();
            $errorarray["detail"] = L::messageErrorNoDatabaseConnectionDetail();
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    if (($config["allow"]["publicAccess"] != true) || ($userID)) {

        $user = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID=?i",$userID);

        if (!$user) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "403"; //TODO CODE
            $errorarray["code"] = "1";
            $errorarray["title"] = L::messageAuthAccountNotFoundTitle();
            $errorarray["detail"] = L::messageAuthAccountNotFoundDetail();
            array_push($return["errors"], $errorarray);
            return $return;
        }

        if ($user["UserActive"] != true) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "403"; //TODO CODE
            $errorarray["code"] = "1";
            $errorarray["title"] = L::messageAuthAccountNotActiveTitle();
            $errorarray["detail"] = L::messageAuthAccountNotActiveDetail();
            array_push($return["errors"], $errorarray);
            return $return;
        }

        if ($user["UserBlocked"] != false) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "403"; //TODO CODE
            $errorarray["code"] = "1";
            $errorarray["title"] = L::messageAuthAccountBlockedTitle();
            $errorarray["detail"] = L::messageAuthAccountBlockedDetail();
            array_push($return["errors"], $errorarray);
            return $return;
        }

        if ($user["UserRole"] == "admin") {
            $return["meta"]["requestStatus"] = "success";
            $return["meta"]["detail"] = "User is admin - action was permitted";
            $return["meta"]["code"] = "200";
            return $return;
        }

        if ($user["UserRole"] == "manager" && $action == "requestPage" && $entity == "hidden") {
            $return["meta"]["requestStatus"] = "success";
            $return["meta"]["detail"] = "User is manager - action was permitted";
            $return["meta"]["code"] = "200";
            return $return;
        }

    }

	switch ($action) {

		case "requestPage":

		    $whitelist = array(
		        "default",
		        "results",
                "entity"
            );


            if (in_array($entity, $whitelist)) {

                $return["meta"]["requestStatus"] = "success";
                $return["meta"]["detail"] = "Action permitted";
                $return["meta"]["code"] = "200";
                return $return;

            } else {

                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "403"; //TODO CODE
                $errorarray["code"] = "1";
                $errorarray["title"] = L::messageAuthNotPermittedTitle();
                $errorarray["detail"] = L::messageAuthNotPermittedDetail();
                array_push($return["errors"], $errorarray);
                return $return;

            }

		break;

        case "apiV1":
            $whitelist = array(
                "getItem",
                "search",
                "autocomplete",
                "statistics",
                "user",
                "status",
                "lang"
            );

            if (in_array($entity, $whitelist)) {

                $return["meta"]["requestStatus"] = "success";
                $return["meta"]["detail"] = "Action permitted";
                $return["meta"]["code"] = "200";
                return $return;

            } else {

                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "403"; //TODO CODE
                $errorarray["code"] = "1";
                $errorarray["title"] = L::messageAuthNotPermittedTitle();
                $errorarray["detail"] = L::messageAuthNotPermittedDetail();
                array_push($return["errors"], $errorarray);
                return $return;

            }

        break;

		default:
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "403";
            $errorarray["code"] = "1";
            $errorarray["title"] = L::messageAuthNotPermittedTitle();
            $errorarray["detail"] = L::messageAuthNotPermittedDetail();
            array_push($return["errors"], $errorarray);
            return $return;
	}

	return false;
}




?>