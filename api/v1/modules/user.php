<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once(__DIR__."/../../../modules/utilities/language.php");

function userChange($parameter) {

    global $config;

    if (!$parameter["id"]) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter (id) is missing";
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

    $params = $db->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Only validate and update fields that are present in the request
    if (array_key_exists("UserName", $params)) {
        if (empty($params["UserName"])) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "422";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Missing required field";
            $errorarray["detail"] = L::messageErrorFieldRequired;
            $errorarray["meta"]["domSelector"] = "[name='UserName']";
            array_push($return["errors"], $errorarray);
            return $return;
        }
        $updateParams[] = $db->parse("UserName=?s", $params["UserName"]);
    }

    if (array_key_exists("UserPassword", $params)) {
        // Only validate password if both password fields are present (indicating a password change)
        if (array_key_exists("UserPasswordConfirm", $parameter)) {
            if (empty($params["UserPassword"])) {
                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Missing required field";
                $errorarray["detail"] = L::messageErrorFieldRequired;
                $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
                array_push($return["errors"], $errorarray);
                return $return;
            }

            if (passwordStrength($params["UserPassword"]) != true) {
                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Password too weak";
                $errorarray["detail"] = L::messagePasswordTooWeak;
                $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
                array_push($return["errors"], $errorarray);
                return $return;
            }

            $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID = ?i LIMIT 1", $parameter["id"]);
            $updateParams[] = $db->parse("UserPasswordHash=?s", hash("sha512", $userdata["UserPasswordPepper"].$params["UserPassword"].$config["salt"]));
        } else {
            // If only UserPassword is present without UserPasswordConfirm, remove it from params
            unset($params["UserPassword"]);
        }
    }

    if (array_key_exists("UserActive", $params)) {
        $updateParams[] = $db->parse("UserActive=?i", $params["UserActive"] === true || $params["UserActive"] === "true" || $params["UserActive"] === "1" ? 1 : 0);
    }

    if (array_key_exists("UserBlocked", $params)) {
        $updateParams[] = $db->parse("UserBlocked=?i", $params["UserBlocked"] === true || $params["UserBlocked"] === "true" || $params["UserBlocked"] === "1" ? 1 : 0);
    }

    if (array_key_exists("UserRole", $params)) {
        // Validate role
        $allowedRoles = array("user", "admin");
        if (!in_array($params["UserRole"], $allowedRoles)) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "422";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Invalid role";
            $errorarray["detail"] = "Role must be either 'user' or 'admin'";
            $errorarray["meta"]["domSelector"] = "[name='UserRole']";
            array_push($return["errors"], $errorarray);
            return $return;
        }
        $updateParams[] = $db->parse("UserRole=?s", $params["UserRole"]);
    }

    if (empty($updateParams)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "No parameter";
        $errorarray["detail"] = "No parameter for changing userdata has been provided";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    $userUpdateQuery = "UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE UserID = ?i";
    $db->query($userUpdateQuery, $config["platform"]["sql"]["tbl"]["User"], $parameter["id"]);

    $return["meta"]["requestStatus"] = "success";
    return $return;
}

function userLogin($parameter) {
    global $config;

    if (!$config["allow"]["login"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Login not allowed";
        $errorarray["detail"] = "Login functionality is currently disabled";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!$parameter["UserMail"] || !$parameter["UserPassword"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameters";
        $errorarray["detail"] = L::messageErrorFieldRequired;
        if (!$parameter["UserMail"]) {
            $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        } else if (!$parameter["UserPassword"]) {
            $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
        }
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!filter_var($parameter["UserMail"], FILTER_VALIDATE_EMAIL)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid email";
        $errorarray["detail"] = "Mail not valid"; // TODO i18n
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    try {
        $db = new SafeMySQL(array(
            'host'  => $config["platform"]["sql"]["access"]["host"],
            'user'  => $config["platform"]["sql"]["access"]["user"],
            'pass'  => $config["platform"]["sql"]["access"]["passwd"],
            'db'    => $config["platform"]["sql"]["db"]
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

    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1", $mail);

    if (!$userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "401";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Authentication failed";
        $errorarray["detail"] = L::messageAuthAccountNotFoundDetail;
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ($userdata["UserPasswordHash"] != hash("sha512", $userdata["UserPasswordPepper"].$parameter["UserPassword"].$config["salt"])) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "401";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Authentication failed";
        $errorarray["detail"] = L::messageLoginErrorPasswordNotCorrect;
        $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!$userdata["UserActive"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account inactive";
        $errorarray["detail"] = L::messageAuthAccountNotActiveDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ($userdata["UserBlocked"] == 1) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account blocked";
        $errorarray["detail"] = L::messageAuthAccountBlockedDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Update last login timestamp
    $db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserLastLogin=current_timestamp() WHERE UserID=?i LIMIT 1", $userdata["UserID"]);

    // Set session data
    $_SESSION["login"] = 1;
    $_SESSION["userdata"]["mail"] = $userdata["UserMail"];
    $_SESSION["userdata"]["name"] = $userdata["UserName"];
    $_SESSION["userdata"]["id"] = $userdata["UserID"];
    $_SESSION["userdata"]["role"] = $userdata["UserRole"];

    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => L::messageLoginSuccessGeneric,
        "user" => array(
            "id" => $userdata["UserID"],
            "name" => $userdata["UserName"],
            "mail" => $userdata["UserMail"],
            "role" => $userdata["UserRole"]
        )
    );

    return $return;
}

function userRegister($parameter) {
    global $config;

    if (!$config["allow"]["register"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Registration not allowed";
        $errorarray["detail"] = "Registration functionality is currently disabled";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!$parameter["UserMail"] || !$parameter["UserPassword"] || !$parameter["UserName"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameters";
        $errorarray["detail"] = L::messageErrorFieldRequired;
        if (!$parameter["UserName"]) {
            $errorarray["meta"]["domSelector"] = "[name='UserName']";
        } else if (!$parameter["UserMail"]) {
            $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        } else if (!$parameter["UserPassword"]) {
            $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
        }
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!filter_var($parameter["UserMail"], FILTER_VALIDATE_EMAIL)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid email";
        $errorarray["detail"] = "Mail not valid"; // TODO i18n
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (passwordStrength($parameter["UserPassword"]) != true) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Password too weak";
        $errorarray["detail"] = L::messagePasswordTooWeak;
        $errorarray["meta"]["domSelector"] = "[name='UserPassword']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    try {
        $db = new SafeMySQL(array(
            'host'  => $config["platform"]["sql"]["access"]["host"],
            'user'  => $config["platform"]["sql"]["access"]["user"],
            'pass'  => $config["platform"]["sql"]["access"]["passwd"],
            'db'    => $config["platform"]["sql"]["db"]
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

    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1", $mail);

    if ($userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "409";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account exists";
        $errorarray["detail"] = L::messageAccountWithMailAlreadyExists;
        array_push($return["errors"], $errorarray);
        return $return;
    }

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
        $parameter["UserName"],
        $mail,
        hash("sha512", $pepper.$parameter["UserPassword"].$config["salt"]),
        $pepper,
        "user",
        0,
        $confirmationCode
    );

    $userID = $db->insertId();

    // Send confirmation email
    $registrationMailSubject = L::brand.': '.L::registerNewAccount;
    $registrationMailVerifyLink = $config['dir']['root'].'/registerConfirm?id='.$userID.'&c='.$confirmationCode;

    $message = '<html><body>';
    $message .= '<p>'.L::hello.' '.$parameter["UserName"].',</p>';
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

    mail($parameter["UserName"].' <'.$mail.'>', $registrationMailSubject, $message, $header);

    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => L::messageRegisterSuccess,
        "user" => array(
            "id" => $userID,
            "name" => $parameter["UserName"],
            "mail" => $mail
        )
    );

    return $return;
}

function userLogout() {
    // Clear all session data
    session_unset();
    session_destroy();
    
    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => "Successfully logged out"
    );
    
    return $return;
}

function userPasswordResetRequest($parameter) {
    global $config;

    if (!$parameter["UserMail"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = L::messageErrorFieldRequired;
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!filter_var($parameter["UserMail"], FILTER_VALIDATE_EMAIL)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid email";
        $errorarray["detail"] = "Mail not valid"; // TODO i18n
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    try {
        $db = new SafeMySQL(array(
            'host'  => $config["platform"]["sql"]["access"]["host"],
            'user'  => $config["platform"]["sql"]["access"]["user"],
            'pass'  => $config["platform"]["sql"]["access"]["passwd"],
            'db'    => $config["platform"]["sql"]["db"]
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

    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1", $mail);

    if (!$userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account not found";
        $errorarray["detail"] = L::messageAuthAccountNotFoundDetail;
        $errorarray["meta"]["domSelector"] = "[name='UserMail']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    $confirmationCode = bin2hex(random_bytes(10));
    $db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserPasswordReset=?s WHERE UserID=?i LIMIT 1", $confirmationCode, $userdata["UserID"]);

    $passwordresetMailSubject = L::brand.': '.L::resetPassword;
    $resetLink = $config["dir"]["root"] . "/password-reset?id=" . $userdata['UserID'] . "&code=" . $confirmationCode;

    $message = '<html><body>';
    $message .= '<p>'.L::hello.' '.$userdata["UserName"].',</p>';
    $message .= '<p>'.L::messagePasswordResetMailStart.'</p>';
    $message .= '<p><a href="'.$resetLink.'">'.$resetLink.'</a></p>';
    $message .= '<p>'.L::messagePasswordResetMailEnd.'</p>';
    $message .= '<p>'.L::messageMailGreetings.',<br>'.L::brand.'</p>';
    $message .= '</body></html>';

    $header = array(
        'MIME-Version' => '1.0',
        'Content-type' => 'text/html; charset=utf-8',
        'From' => $config["mail"]["from"],
        'X-Mailer' => 'PHP/' . phpversion()
    );

    mail($userdata["UserName"].' <'.$mail.'>', $passwordresetMailSubject, $message, $header);

    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => L::messagePasswordResetMailSent
    );

    return $return;
}

function userPasswordReset($parameter) {
    global $config;

    if (!$parameter["UserID"] || !$parameter["ResetCode"] || !$parameter["NewPassword"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameters";
        $errorarray["detail"] = L::messageErrorFieldRequired;
        if (!$parameter["NewPassword"]) {
            $errorarray["meta"]["domSelector"] = "[name='NewPassword']";
        }
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (passwordStrength($parameter["NewPassword"]) != true) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Password too weak";
        $errorarray["detail"] = L::messagePasswordTooWeak;
        $errorarray["meta"]["domSelector"] = "[name='NewPassword']";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    try {
        $db = new SafeMySQL(array(
            'host'  => $config["platform"]["sql"]["access"]["host"],
            'user'  => $config["platform"]["sql"]["access"]["user"],
            'pass'  => $config["platform"]["sql"]["access"]["passwd"],
            'db'    => $config["platform"]["sql"]["db"]
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

    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID = ?i LIMIT 1", $parameter["UserID"]);

    if (!$userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account not found";
        $errorarray["detail"] = L::messageAuthAccountNotFoundDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ($userdata["UserPasswordReset"] != $parameter["ResetCode"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid reset code";
        $errorarray["detail"] = L::messagePasswordResetCodeIncorrect;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    $pepper = bin2hex(random_bytes(9));
    $db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET 
        UserPasswordHash=?s, 
        UserPasswordReset=?i, 
        UserPasswordPepper=?s 
        WHERE UserID=?i", 
        hash("sha512", $pepper.$parameter["NewPassword"].$config["salt"]), 
        0, 
        $pepper, 
        $parameter["UserID"]
    );

    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => L::messagePasswordResetSuccess
    );

    return $return;
}

function userConfirmRegistration($parameter) {
    global $config;

    if (!$parameter["UserID"] || !$parameter["ConfirmationCode"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameters";
        $errorarray["detail"] = L::messageErrorParameterMissingDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    try {
        $db = new SafeMySQL(array(
            'host'  => $config["platform"]["sql"]["access"]["host"],
            'user'  => $config["platform"]["sql"]["access"]["user"],
            'pass'  => $config["platform"]["sql"]["access"]["passwd"],
            'db'    => $config["platform"]["sql"]["db"]
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

    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID = ?i LIMIT 1", $parameter["UserID"]);

    if (!$userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account not found";
        $errorarray["detail"] = L::messageErrorGeneric;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ($userdata["UserBlocked"] == 1) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "403";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Account blocked";
        $errorarray["detail"] = L::messageAuthAccountBlockedDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if ($userdata["UserRegisterConfirmation"] != $parameter["ConfirmationCode"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid confirmation code";
        $errorarray["detail"] = L::messageRegisterWrongConfirmationCode;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    $db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserActive=1, UserRegisterConfirmation=1 WHERE UserID=?i LIMIT 1", $userdata["UserID"]);

    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => L::messageAccountActivationSuccess
    );

    return $return;
}

function userGetItemsFromDB($id = "all", $limit = 10, $offset = 0, $search = false, $sort = false, $order = false) {
    global $config;

    $db = new SafeMySQL(array(
        'host'  => $config["platform"]["sql"]["access"]["host"],
        'user'  => $config["platform"]["sql"]["access"]["user"],
        'pass'  => $config["platform"]["sql"]["access"]["passwd"],
        'db'    => $config["platform"]["sql"]["db"]
    ));

    $queryPart = "";

    if ($id == "all") {
        $queryPart .= "1";
    } else {
        $queryPart .= $db->parse("UserID=?i", $id);
    }

    if (!empty($search)) {
        $queryPart .= $db->parse(" AND (LOWER(UserName) LIKE LOWER(?s) OR LOWER(UserMail) LIKE LOWER(?s))", "%".$search."%", "%".$search."%");
    }

    if (!empty($sort)) {
        $allowedSortFields = array("UserName", "UserMail", "UserRole", "UserActive", "UserBlocked", "UserLastLogin", "UserRegisterDate");
        if (in_array($sort, $allowedSortFields)) {
            $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);
        }
    }

    if ($limit != 0) {
        $queryPart .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
    }

    $return = array();
    $return["meta"]["requestStatus"] = "success";

    $return["total"] = $db->getOne("SELECT COUNT(UserID) as count FROM ?n", $config["platform"]["sql"]["tbl"]["User"]);
    $rows = $db->getAll("SELECT UserID, UserName, UserMail, UserRole, UserActive, UserBlocked, UserLastLogin, UserRegisterDate FROM ?n WHERE ?p", 
        $config["platform"]["sql"]["tbl"]["User"], 
        $queryPart
    );
    // Convert integer values to booleans
    foreach ($rows as &$row) {
        $row["UserActive"] = (bool)$row["UserActive"];
        $row["UserBlocked"] = (bool)$row["UserBlocked"];
    }
    $return["data"] = $rows;

    return $return;
}

?>
