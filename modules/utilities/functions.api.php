<?php

/**
 * API Helper Functions
 * Standardized functions for API error handling, validation, and database connections
 */

/**
 * Creates a standardized error response
 * @param string $status HTTP status code
 * @param string $code Internal error code
 * @param string $messageKey Language constant key for the title
 * @param string $detailKey Language constant key for the detail
 * @param array $params Optional parameters for message interpolation
 * @param string|null $domSelector Optional DOM selector for frontend
 * @param array $additionalMeta Optional additional metadata
 * @return array
 */
function createApiErrorResponse($status, $code, $messageKey, $detailKey, $params = [], $domSelector = null, $additionalMeta = []) {
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();
    
    // Get message from language file or use key as fallback
    $title = defined('L::' . $messageKey) ? L($messageKey) : $messageKey;
    $detail = defined('L::' . $detailKey) ? L($detailKey) : $detailKey;
    
    // Replace parameters in messages
    foreach ($params as $key => $value) {
        $title = str_replace("{".$key."}", $value, $title);
        $detail = str_replace("{".$key."}", $value, $detail);
    }
    
    $errorarray = [
        "status" => $status,
        "code" => $code,
        "title" => $title,
        "detail" => $detail
    ];
    
    if ($domSelector) {
        $errorarray["meta"]["domSelector"] = $domSelector;
    }
    
    if (!empty($additionalMeta)) {
        $errorarray["meta"] = array_merge(
            $errorarray["meta"] ?? [],
            $additionalMeta
        );
    }
    
    array_push($return["errors"], $errorarray);
    return $return;
}

/**
 * Creates a standardized success response
 * @param mixed $data Response data
 * @param array $meta Additional metadata
 * @param array $links Links to be included at root level
 * @param array $relationships Relationships to be included at root level
 * @return array
 */
function createApiSuccessResponse($data = null, $meta = [], $links = null, $relationships = null) {
    // Ensure $meta is an array before merging
    if (!is_array($meta)) {
        $meta = [];
    }

    $return = [
        "meta" => array_merge(
            ["requestStatus" => "success"],
            $meta
        )
    ];
    
    if ($data !== null) {
        $return["data"] = $data;
    }

    // Ensure $links is an array if it's not null, otherwise keep it null
    if ($links !== null && !is_array($links)) {
        $links = []; // Or handle as an error, but empty array is safer for now
    }
    if ($links !== null) {
        $return["links"] = $links;
    }

    // Ensure $relationships is an array if it's not null, otherwise keep it null
    if ($relationships !== null && !is_array($relationships)) {
        $relationships = []; // Or handle as an error, but empty array is safer for now
    }
    if ($relationships !== null) {
        $return["relationships"] = $relationships;
    }
    
    return $return;
}

/**
 * Common error responses
 */
function createApiErrorMissingParameter($field = null) {
    return createApiErrorResponse(
        422,
        1,
        "messageErrorMissingParameter",
        "messageErrorMissingParameter",
        [],
        $field ? "[name='".$field."']" : null
    );
}

function createApiErrorDatabaseConnection($type = 'platform') {
    $messageKey = $type === 'platform' 
        ? "messageErrorDatabasePlatform" 
        : "messageErrorDatabaseParliament";
    
    return createApiErrorResponse(
        503,
        1,
        "messageErrorNoDatabaseConnectionTitle",
        $messageKey
    );
}

function createApiErrorNotFound($itemType) {
    return createApiErrorResponse(
        404,
        1,
        "messageErrorItemNotFound",
        "messageErrorItemNotFound",
        ["item" => ucfirst($itemType)]
    );
}

function createApiErrorInvalidID($type) {
    return createApiErrorResponse(
        422,
        1,
        "messageErrorInvalidID",
        "messageErrorInvalidID",
        ["type" => ucfirst($type)]
    );
}

function createApiErrorInvalidFormat($field, $expectedType) {
    return createApiErrorResponse(
        422, 
        1,   
        "messageErrorIDParseError",
        "messageErrorIDParseError",
        ["field" => $field, "expectedType" => ucfirst($expectedType)], 
        $field ? "[name='".$field."']" : null 
    );
}

function createApiErrorInvalidLength($field, $minLength, $domSelectorField = null) {
    // If domSelectorField is not provided, use the field name itself for the selector.
    $selector = $domSelectorField ? $domSelectorField : $field;
    return createApiErrorResponse(
        422, // Unprocessable Entity
        1,   // Consistent error code
        "messageErrorInvalidLengthTitle",
        "messageErrorInvalidLengthDetailMin",
        ["field" => ucfirst($field), "minLength" => $minLength], // Capitalize field for display
        $selector ? "[name='".$selector."']" : null
    );
}

function createApiErrorDatabaseError($detailMessage = null) {
    // Use a generic title, and the provided detail message.
    // If no detail message is provided, use a generic detail key.
    return createApiErrorResponse(
        500, // Internal Server Error or 503 Service Unavailable are common for DB errors
        1,   // Consistent error code
        "messageErrorDatabaseGeneric", // Generic title
        $detailMessage ? $detailMessage : "messageErrorDatabaseRequest" // Specific detail or generic fallback
    );
}

/**
 * Database connection with standardized error handling
 */
function getApiDatabaseConnection($type = 'platform', $parliament = null) {
    global $config;
    
    try {
        if ($type === 'platform') {
            return new SafeMySQL([
                'host' => $config["platform"]["sql"]["access"]["host"],
                'user' => $config["platform"]["sql"]["access"]["user"],
                'pass' => $config["platform"]["sql"]["access"]["passwd"],
                'db' => $config["platform"]["sql"]["db"]
            ]);
        } else if ($parliament) {
            if (!isset($config["parliament"][$parliament])) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageErrorInvalidParameter",
                    "messageErrorInvalidParameter",
                    ["param" => "parliament"]
                );
            }
            
            return new SafeMySQL([
                'host' => $config["parliament"][$parliament]["sql"]["access"]["host"],
                'user' => $config["parliament"][$parliament]["sql"]["access"]["user"],
                'pass' => $config["parliament"][$parliament]["sql"]["access"]["passwd"],
                'db' => $config["parliament"][$parliament]["sql"]["db"]
            ]);
        }
    } catch (exception $e) {
        return createApiErrorDatabaseConnection($type);
    }
    
    return createApiErrorResponse(
        422,
        1,
        "messageErrorMissingParameter",
        "messageErrorMissingParameter",
        ["param" => "parliament"]
    );
}

/**
 * Common validation functions
 */
function validateApiRequired($value, $field) {
    if (empty($value)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorFieldRequired",
            "messageErrorFieldRequired",
            [],
            "[name='".$field."']"
        );
    }
    return true;
}

function validateApiEmail($email, $field = 'email') {
    $required = validateApiRequired($email, $field);
    if ($required !== true) {
        return $required;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorInvalidParameter",
            "messageErrorInvalidParameter",
            ["param" => "email"],
            "[name='".$field."']"
        );
    }
    
    return true;
}

function validateApiPassword($password, $field = 'password') {
    $required = validateApiRequired($password, $field);
    if ($required !== true) {
        return $required;
    }
    
    if (!passwordStrength($password)) {
        return createApiErrorResponse(
            422,
            1,
            "messagePasswordTooWeak",
            "messagePasswordTooWeak",
            [],
            "[name='".$field."']"
        );
    }
    
    return true;
}

function validateApiDateRange($startDate, $endDate, $startField = 'dateStart') {
    if (!empty($startDate) && !empty($endDate)) {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start > $end) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorInvalidDateRange",
                "messageErrorInvalidDateRange",
                [],
                "[name='".$startField."']"
            );
        }
    }
    return true;
}

function validateApiNumber($value, $field, $min = 1) {
    if (!is_numeric($value) || (int)$value < $min) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorInvalidNumber",
            "messageErrorInvalidNumber",
            [],
            "[name='".$field."']"
        );
    }
    return true;
} 