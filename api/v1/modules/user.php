<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");

if (!function_exists("L")) {
    require_once(__DIR__."/../../../i18n.class.php");
    $i18n = new i18n(__DIR__."/../../../lang/lang_{LANGUAGE}.json", __DIR__."/../../langcache/", "de");
    $i18n->init();
}

function userChange($parameter) {

    global $config;

    if (!$parameter["UserID"]) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter (UserID) is missing";
        array_push($return["errors"], $errorarray);

        return $return;

    }

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
        $errorarray["status"] = "503";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Database connection error";
        $errorarray["detail"] = "Connecting to platform database failed";
        array_push($return["errors"], $errorarray);
        return $return;

    }


    if ($_SESSION["userdata"]["role"] == "admin") {

        $allowedParams = array("UserName", "UserPassword", "UserActive", "UserBlocked", "UserRole");

    } else {

        $allowedParams = array("UserName", "UserPassword");

    }


    $params = $db->filterArray($parameter,$allowedParams);

    $updateParams = array();

    if ($params["UserName"]) {
        $updateParams[] = $db->parse("UserName=?s", $params["UserName"]);
    }

    if ($params["UserPassword"]) {

        if (passwordStrength($params["UserPassword"]) != true) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "422";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Password too weak";
            $errorarray["detail"] = L::messagePasswordTooWeak;
            array_push($return["errors"], $errorarray);
            return $return;
        }

        $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID = ?i LIMIT 1",$parameter["UserID"]);
        $updateParams[] = $db->parse("UserPasswordHash=?s", hash("sha512", $userdata["UserPasswordPepper"].$params["UserPassword"].$config["salt"]));
    }

    if (array_key_exists("UserActive", $params)) {
        $updateParams[] = $db->parse("UserActive=?i", $params["UserActive"]);
    }

    if (array_key_exists("UserBlocked", $params)) {
        $updateParams[] = $db->parse("UserBlocked=?i", $params["UserBlocked"]);
    }

    if ($params["UserRole"]) {
        $updateParams[] = $db->parse("UserRole=?s", $params["UserRole"]);
    }

    if ($params) {

        $userUpdateQuery = "UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE UserID = ?i";
        $db->query($userUpdateQuery, $config["platform"]["sql"]["tbl"]["User"], $parameter["UserID"]);

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "No parameter";
        $errorarray["detail"] = "No parameter for changing userdata has been provided";
        array_push($return["errors"], $errorarray);

        return $return;

    }

    $return["meta"]["requestStatus"] = "success";

    return $return;

}

?>
