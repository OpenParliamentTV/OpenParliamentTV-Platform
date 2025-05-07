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

        $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserID = ?i LIMIT 1",$parameter["id"]);
        $updateParams[] = $db->parse("UserPasswordHash=?s", hash("sha512", $userdata["UserPasswordPepper"].$params["UserPassword"].$config["salt"]));
    }

    if (array_key_exists("UserActive", $params)) {
        $updateParams[] = $db->parse("UserActive=?i", $params["UserActive"] === true || $params["UserActive"] === "true" || $params["UserActive"] === "1" ? 1 : 0);
    }

    if (array_key_exists("UserBlocked", $params)) {
        $updateParams[] = $db->parse("UserBlocked=?i", $params["UserBlocked"] === true || $params["UserBlocked"] === "true" || $params["UserBlocked"] === "1" ? 1 : 0);
    }

    if ($params["UserRole"]) {
        // Validate role
        $allowedRoles = array("user", "admin");
        if (!in_array($params["UserRole"], $allowedRoles)) {
            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "422";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Invalid role";
            $errorarray["detail"] = "Role must be either 'user' or 'admin'";
            array_push($return["errors"], $errorarray);
            return $return;
        }
        $updateParams[] = $db->parse("UserRole=?s", $params["UserRole"]);
    }

    if ($params) {

        $userUpdateQuery = "UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE UserID = ?i";
        $db->query($userUpdateQuery, $config["platform"]["sql"]["tbl"]["User"], $parameter["id"]);

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

    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1", $mail);

    if (!$userdata) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "401";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Authentication failed";
        $errorarray["detail"] = L::messageAuthAccountNotFoundDetail;
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
        $errorarray["detail"] = L::messageErrorParameterMissingDetail;
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
        $errorarray["detail"] = L::messageErrorParameterMissingDetail;
        array_push($return["errors"], $errorarray);
        return $return;
    }

    if (!filter_var($parameter["UserMail"], FILTER_VALIDATE_EMAIL)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid email";
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

    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["User"]." WHERE UserMail = ?s LIMIT 1", $mail);

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

    $confirmationCode = bin2hex(random_bytes(10));
    $db->query("UPDATE ".$config["platform"]["sql"]["tbl"]["User"]." SET UserPasswordReset=?s WHERE UserID=?i LIMIT 1", $confirmationCode, $userdata["UserID"]);

    $passwordresetMailSubject = L::brand.': '.L::resetPassword;
    $passwordresetMailVerifyLink = $config['dir']['root'].'/passwordReset?id='.$userdata['UserID'].'&c='.$confirmationCode;

    $message = '<html><body>';
    $message .= '<p>'.L::hello.' '.$userdata["UserName"].',</p>';
    $message .= '<p>'.L::messagePasswordResetMailStart.'</p>';
    $message .= '<p><a href="'.$passwordresetMailVerifyLink.'">'.$passwordresetMailVerifyLink.'</a></p>';
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
        $errorarray["detail"] = L::messageErrorParameterMissingDetail;
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

function userGetOverview($id = "all", $limit = 10, $offset = 0, $search = false, $sort = false, $order = false, $getCount = true) {
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

    if ($getCount == true) {
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
        $return["rows"] = $rows;
    } else {
        $rows = $db->getAll("SELECT UserID, UserName, UserMail, UserRole, UserActive, UserBlocked, UserLastLogin, UserRegisterDate FROM ?n WHERE ?p", 
            $config["platform"]["sql"]["tbl"]["User"], 
            $queryPart
        );
        // Convert integer values to booleans
        foreach ($rows as &$row) {
            $row["UserActive"] = (bool)$row["UserActive"];
            $row["UserBlocked"] = (bool)$row["UserBlocked"];
        }
        $return = $rows;
    }

    return $return;
}

?>
