<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once(__DIR__."/../../../modules/i18n/language.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

function userChange($parameter) {
    global $config;

    // Validate required ID
    if (empty($parameter["id"])) {
        return createApiErrorMissingParameter("id");
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Define allowed parameters based on user role
    if ($_SESSION["userdata"]["role"] == "admin") {
        $allowedParams = ["UserName", "UserPassword", "UserActive", "UserBlocked", "UserRole"];
    } else {
        $allowedParams = ["UserName", "UserPassword"];
    }

    $params = $db->filterArray($parameter, $allowedParams);
    $updateParams = [];

    // Process UserName
    if (array_key_exists("UserName", $params)) {
        if (empty($params["UserName"])) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorFieldRequiredTitle",
                "messageErrorFieldRequiredDetail",
                ["field" => "UserName"],
                "[name='UserName']"
            );
        }
        $updateParams[] = $db->parse("UserName=?s", $params["UserName"]);
    }

    // Process Password
    if (array_key_exists("UserPassword", $params)) {
        // Only validate password if both password fields are present
        if (array_key_exists("UserPasswordConfirm", $parameter)) {
            if (empty($params["UserPassword"])) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageErrorFieldRequiredTitle",
                    "messageErrorFieldRequiredDetail",
                    ["field" => "UserPassword"],
                    "[name='UserPassword']"
                );
            }

            $passwordValidation = validateApiPassword($params["UserPassword"], "UserPassword");
            if ($passwordValidation !== true) {
                return $passwordValidation;
            }

            $userdata = $db->getRow("SELECT * FROM ?n WHERE UserID = ?i LIMIT 1", 
                $config["platform"]["sql"]["tbl"]["User"], 
                $parameter["id"]
            );
            $updateParams[] = $db->parse("UserPasswordHash=?s", 
                hash("sha512", $userdata["UserPasswordPepper"].$params["UserPassword"].$config["salt"])
            );
        } else {
            // If only UserPassword is present without UserPasswordConfirm, remove it
            unset($params["UserPassword"]);
        }
    }

    // Process boolean fields
    if (array_key_exists("UserActive", $params)) {
        $updateParams[] = $db->parse("UserActive=?i", 
            $params["UserActive"] === true || $params["UserActive"] === "true" || $params["UserActive"] === "1" ? 1 : 0
        );
    }

    if (array_key_exists("UserBlocked", $params)) {
        $updateParams[] = $db->parse("UserBlocked=?i", 
            $params["UserBlocked"] === true || $params["UserBlocked"] === "true" || $params["UserBlocked"] === "1" ? 1 : 0
        );
    }

    // Process UserRole
    if (array_key_exists("UserRole", $params)) {
        $allowedRoles = ["user", "admin"];
        if (!in_array($params["UserRole"], $allowedRoles)) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorInvalidRoleTitle",
                "messageErrorInvalidRoleDetail",
                [],
                "[name='UserRole']"
            );
        }
        $updateParams[] = $db->parse("UserRole=?s", $params["UserRole"]);
    }

    // Validate that we have something to update
    if (empty($updateParams)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorNoValidFieldsToUpdateDetail"
        );
    }

    // Update user
    $userUpdateQuery = "UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE UserID = ?i";
    $db->query($userUpdateQuery, $config["platform"]["sql"]["tbl"]["User"], $parameter["id"]);

    return createApiSuccessResponse();
}

function userLogin($parameter) {
    global $config;

    if (!$config["allow"]["login"]) {
        return createApiErrorResponse(
            403,
            1,
            "messageErrorLoginNotAllowedTitle",
            "messageErrorLoginNotAllowedDetail"
        );
    }

    // Validate required fields
    $requiredFields = ["UserMail", "UserPassword"];
    foreach ($requiredFields as $field) {
        if (empty($parameter[$field])) {
            return createApiErrorMissingParameter($field);
        }
    }

    // Validate email format
    $emailValidation = validateApiEmail($parameter["UserMail"], "UserMail");
    if ($emailValidation !== true) {
        return $emailValidation;
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if user exists
    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ?n WHERE UserMail = ?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"], 
        $mail
    );

    if (!$userdata) {
        return createApiErrorResponse(
            401,
            1,
            "messageAuthAccountNotFoundTitle",
            "messageAuthAccountNotFoundDetail",
            [],
            "[name='UserMail']"
        );
    }

    // Validate password
    if ($userdata["UserPasswordHash"] != hash("sha512", $userdata["UserPasswordPepper"].$parameter["UserPassword"].$config["salt"])) {
        return createApiErrorResponse(
            401,
            1,
            "messageAuthPasswordIncorrectTitle",
            "messageLoginErrorPasswordNotCorrect",
            [],
            "[name='UserPassword']"
        );
    }

    // Check account status
    if (!$userdata["UserActive"]) {
        return createApiErrorResponse(
            403,
            1,
            "messageAuthAccountNotActiveTitle",
            "messageAuthAccountNotActiveDetail"
        );
    }

    if ($userdata["UserBlocked"] == 1) {
        return createApiErrorResponse(
            403,
            1,
            "messageAuthAccountBlockedTitle",
            "messageAuthAccountBlockedDetail"
        );
    }

    // Update last login timestamp
    $db->query("UPDATE ?n SET UserLastLogin=current_timestamp() WHERE UserID=?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"],
        $userdata["UserID"]
    );

    // Set session data
    $_SESSION["login"] = 1;
    $_SESSION["userdata"]["mail"] = $userdata["UserMail"];
    $_SESSION["userdata"]["name"] = $userdata["UserName"];
    $_SESSION["userdata"]["id"] = $userdata["UserID"];
    $_SESSION["userdata"]["role"] = $userdata["UserRole"];

    return createApiSuccessResponse([
        "message" => L::messageLoginSuccessGeneric(),
        "user" => [
            "id" => $userdata["UserID"],
            "name" => $userdata["UserName"],
            "mail" => $userdata["UserMail"],
            "role" => $userdata["UserRole"]
        ]
    ]);
}

function userRegister($parameter) {
    global $config;

    if (!$config["allow"]["register"]) {
        return createApiErrorResponse(
            403,
            1,
            "messageErrorRegistrationNotAllowedTitle",
            "messageErrorRegistrationNotAllowedDetail"
        );
    }

    // Validate required fields
    $requiredFields = ["UserMail", "UserPassword", "UserName"];
    foreach ($requiredFields as $field) {
        if (empty($parameter[$field])) {
            return createApiErrorMissingParameter($field);
        }
    }

    // Validate email format
    $emailValidation = validateApiEmail($parameter["UserMail"], "UserMail");
    if ($emailValidation !== true) {
        return $emailValidation;
    }

    // Validate password strength
    $passwordValidation = validateApiPassword($parameter["UserPassword"], "UserPassword");
    if ($passwordValidation !== true) {
        return $passwordValidation;
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if email already exists
    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ?n WHERE UserMail = ?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"], 
        $mail
    );

    if ($userdata) {
        return createApiErrorResponse(
            409,
            1,
            "messageErrorAccountExistsTitle",
            "messageAccountWithMailAlreadyExists"
        );
    }

    // Generate security tokens
    $pepper = bin2hex(random_bytes(9));
    $confirmationCode = bin2hex(random_bytes(10));

    // Insert new user
    $db->query("INSERT INTO ?n SET
        UserName=?s,
        UserMail=?s,
        UserPasswordHash=?s,
        UserPasswordPepper=?s,
        UserRole=?s,
        UserActive=?i,
        UserRegisterConfirmation=?s",
        $config["platform"]["sql"]["tbl"]["User"],
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
    $registrationMailSubject = L::brand().': '.L::registerNewAccount();
    $registrationMailVerifyLink = $config['dir']['root'].'/registerConfirm?id='.$userID.'&c='.$confirmationCode;

    require_once(__DIR__.'/../../../modules/send-mail/functions.php');
    require_once(__DIR__.'/../../../modules/utilities/security.php');
    
    $message = '<html><body>';
    $message .= '<p>'.L::hello().' '.h($parameter["UserName"]).',</p>';
    $message .= '<p>'.L::messageRegisterThankYou().' <b>'.h($config['dir']['root']).'</b>.</p>';
    $message .= '<p>'.L::messageRegisterClickLinkToValidate().'</p>';
    $message .= '<p><a href="'.hAttr($registrationMailVerifyLink).'">'.h($registrationMailVerifyLink).'</a></p>';
    $message .= '<p>'.L::messageMailGreetings().',<br>'.L::brand().'</p>';
    $message .= '</body></html>';

    sendHtmlMail($mail, $registrationMailSubject, $message, $parameter["UserName"]);

    return createApiSuccessResponse([
        "message" => L::messageRegisterSuccess(),
        "user" => [
            "id" => $userID,
            "name" => $parameter["UserName"],
            "mail" => $mail
        ]
    ]);
}

function userLogout() {
    // Clear all session data
    session_unset();
    session_destroy();
    
    return createApiSuccessResponse([
        "message" => "Successfully logged out"
    ]);
}

function userPasswordResetRequest($parameter) {
    global $config;

    // Validate required fields
    if (empty($parameter["UserMail"])) {
        return createApiErrorMissingParameter("UserMail");
    }

    // Validate email format
    $emailValidation = validateApiEmail($parameter["UserMail"], "UserMail");
    if ($emailValidation !== true) {
        return $emailValidation;
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if user exists
    $mail = strtolower($parameter["UserMail"]);
    $userdata = $db->getRow("SELECT * FROM ?n WHERE UserMail = ?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"], 
        $mail
    );

    if (!$userdata) {
        return createApiErrorResponse(
            404,
            1,
            "messageAuthAccountNotFoundTitle",
            "messageAuthAccountNotFoundDetail",
            [],
            "[name='UserMail']"
        );
    }

    // Generate and save reset code
    $confirmationCode = bin2hex(random_bytes(10));
    $db->query("UPDATE ?n SET UserPasswordReset=?s WHERE UserID=?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"],
        $confirmationCode, 
        $userdata["UserID"]
    );

    // Send reset email
    $passwordresetMailSubject = L::brand().': '.L::resetPassword();
    $resetLink = $config["dir"]["root"] . "/password-reset?id=" . $userdata['UserID'] . "&code=" . $confirmationCode;

    require_once(__DIR__.'/../../../modules/send-mail/functions.php');
    require_once(__DIR__.'/../../../modules/utilities/security.php');
    
    $message = '<html><body>';
    $message .= '<p>'.L::hello().' '.h($userdata["UserName"]).',</p>';
    $message .= '<p>'.L::messagePasswordResetMailStart().'</p>';
    $message .= '<p><a href="'.hAttr($resetLink).'">'.h($resetLink).'</a></p>';
    $message .= '<p>'.L::messagePasswordResetMailEnd().'</p>';
    $message .= '<p>'.L::messageMailGreetings().',<br>'.L::brand().'</p>';
    $message .= '</body></html>';

    sendHtmlMail($mail, $passwordresetMailSubject, $message, $userdata["UserName"]);

    return createApiSuccessResponse([
        "message" => L::messagePasswordResetMailSent()
    ]);
}

function userPasswordReset($parameter) {
    global $config;

    // Validate required fields
    $requiredFields = ["UserID", "ResetCode", "NewPassword"];
    foreach ($requiredFields as $field) {
        if (empty($parameter[$field])) {
            return createApiErrorMissingParameter($field);
        }
    }

    // Validate password strength
    $passwordValidation = validateApiPassword($parameter["NewPassword"], "NewPassword");
    if ($passwordValidation !== true) {
        return $passwordValidation;
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if user exists
    $userdata = $db->getRow("SELECT * FROM ?n WHERE UserID = ?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"],
        $parameter["UserID"]
    );

    if (!$userdata) {
        return createApiErrorNotFound("User");
    }

    // Validate reset code
    if ($userdata["UserPasswordReset"] != $parameter["ResetCode"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorInvalidResetCodeTitle",
            "messagePasswordResetCodeIncorrect"
        );
    }

    // Generate new password hash and clear reset code
    $pepper = bin2hex(random_bytes(9));
    $db->query("UPDATE ?n SET 
        UserPasswordHash=?s, 
        UserPasswordReset=?i, 
        UserPasswordPepper=?s 
        WHERE UserID=?i", 
        $config["platform"]["sql"]["tbl"]["User"],
        hash("sha512", $pepper.$parameter["NewPassword"].$config["salt"]),
        0,
        $pepper,
        $parameter["UserID"]
    );

    return createApiSuccessResponse([
        "message" => L::messagePasswordResetSuccess()
    ]);
}

function userConfirmRegistration($parameter) {
    global $config;

    // Validate required fields
    $requiredFields = ["UserID", "ConfirmationCode"];
    foreach ($requiredFields as $field) {
        if (empty($parameter[$field])) {
            return createApiErrorMissingParameter($field);
        }
    }

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if user exists
    $userdata = $db->getRow("SELECT * FROM ?n WHERE UserID = ?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"],
        $parameter["UserID"]
    );

    if (!$userdata) {
        return createApiErrorNotFound("User");
    }

    // Check if account is blocked
    if ($userdata["UserBlocked"] == 1) {
        return createApiErrorResponse(
            403,
            1,
            "messageAuthAccountBlockedTitle",
            "messageAuthAccountBlockedDetail"
        );
    }

    // Validate confirmation code
    if ($userdata["UserRegisterConfirmation"] != $parameter["ConfirmationCode"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorInvalidConfirmationCodeTitle",
            "messageRegisterWrongConfirmationCode"
        );
    }

    // Activate account
    $db->query("UPDATE ?n SET UserActive=1, UserRegisterConfirmation=1 WHERE UserID=?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["User"],
        $userdata["UserID"]
    );

    return createApiSuccessResponse([
        "message" => L::messageAccountActivationSuccess()
    ]);
}

function userGetItemsFromDB($id = "all", $limit = 10, $offset = 0, $search = false, $sort = false, $order = false) {
    global $config;

    // Get database connection
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Build query conditions
    $queryPart = "";

    if ($id == "all") {
        $queryPart .= "1";
    } else {
        $queryPart .= $db->parse("UserID=?i", $id);
    }

    // Add search condition if provided
    if (!empty($search)) {
        $queryPart .= $db->parse(" AND (LOWER(UserName) LIKE LOWER(?s) OR LOWER(UserMail) LIKE LOWER(?s))", 
            "%".$search."%", 
            "%".$search."%"
        );
    }

    // Add sorting if provided
    if (!empty($sort)) {
        $allowedSortFields = ["UserName", "UserMail", "UserRole", "UserActive", "UserBlocked", "UserLastLogin", "UserRegisterDate"];
        if (in_array($sort, $allowedSortFields)) {
            $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);
        }
    }

    // Add pagination
    if ($limit != 0) {
        $queryPart .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
    }

    // Get total count
    $total = $db->getOne("SELECT COUNT(UserID) as count FROM ?n", 
        $config["platform"]["sql"]["tbl"]["User"]
    );

    // Get user data
    $rows = $db->getAll("SELECT 
        UserID, UserName, UserMail, UserRole, UserActive, 
        UserBlocked, UserLastLogin, UserRegisterDate 
        FROM ?n WHERE ?p", 
        $config["platform"]["sql"]["tbl"]["User"], 
        $queryPart
    );

    // Convert integer values to booleans
    foreach ($rows as &$row) {
        $row["UserActive"] = (bool)$row["UserActive"];
        $row["UserBlocked"] = (bool)$row["UserBlocked"];
    }

    // Return in old format with total at root level
    return [
        "meta" => [
            "requestStatus" => "success"
        ],
        "total" => $total,
        "data" => $rows
    ];
}

?>
