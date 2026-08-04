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
function apiErrorResponse($status, $code, $messageKey, $detailKey, $params = [], $domSelector = null, $additionalMeta = []) {
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    // Get message from language file or use key as fallback
    $title = defined('L::' . $messageKey) ? L($messageKey) : $messageKey;
    $detail = defined('L::' . $detailKey) ? L($detailKey) : $detailKey;

    // Replace parameters in messages
    foreach ($params as $key => $value) {
        if (!is_array($value)) {
            $title = str_replace("{".$key."}", $value, $title);
            $detail = str_replace("{".$key."}", $value, $detail);
        }
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
 * @param array $total Total number of items
 * @return array
 */
function apiSuccessResponse($data = null, $meta = [], $links = null, $relationships = null, $total = null) {
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

    if ($total !== null) {
        $return["total"] = $total;
    }

    return $return;
}

/**
 * Common error responses
 */
function apiErrorMissingParameter($field = null) {
    return apiErrorResponse(
        422,
        1,
        "messageErrorMissingParameter",
        "messageErrorMissingParameter",
        [],
        $field ? "[name='".$field."']" : null
    );
}

function apiErrorDatabaseConnection($type = 'platform') {
    $messageKey = $type === 'platform'
        ? "messageErrorDatabasePlatform"
        : "messageErrorDatabaseParliament";

    return apiErrorResponse(
        503,
        1,
        "messageErrorNoDatabaseConnectionTitle",
        $messageKey
    );
}

function apiErrorNotFound($itemType) {
    return apiErrorResponse(
        404,
        1,
        "messageErrorItemNotFound",
        "messageErrorItemNotFound",
        ["item" => ucfirst($itemType)]
    );
}

function apiErrorInvalidId($type) {
    return apiErrorResponse(
        422,
        1,
        "messageErrorInvalidID",
        "messageErrorInvalidID",
        ["type" => ucfirst($type)]
    );
}

function apiErrorInvalidFormat($field, $expectedType) {
    return apiErrorResponse(
        422,
        1,
        "messageErrorIDParseError",
        "messageErrorIDParseError",
        ["field" => $field, "expectedType" => ucfirst($expectedType)],
        $field ? "[name='".$field."']" : null
    );
}

function apiErrorInvalidLength($field, $minLength, $domSelectorField = null) {
    // If domSelectorField is not provided, use the field name itself for the selector.
    $selector = $domSelectorField ? $domSelectorField : $field;
    return apiErrorResponse(
        422, // Unprocessable Entity
        1,   // Consistent error code
        "messageErrorInvalidLengthTitle",
        "messageErrorInvalidLengthDetailMin",
        ["field" => ucfirst($field), "minLength" => $minLength], // Capitalize field for display
        $selector ? "[name=".$selector."]" : null
    );
}

function apiErrorInvalidParameter($paramName, $detailMessageKey = null, $detailParams = []) {
    return apiErrorResponse(
        422, // Unprocessable Entity or 400 Bad Request
        1,   // Consistent error code
        "messageErrorInvalidParameter",
        $detailMessageKey ?: "messageErrorInvalidParameter",
        array_merge(["param" => $paramName], $detailParams),
        "[name=".$paramName."]" // Optional: DOM selector for the field
    );
}

function apiErrorDatabaseError($detailMessage = null) {
    // Use a generic title, and the provided detail message.
    // If no detail message is provided, use a generic detail key.
    return apiErrorResponse(
        500, // Internal Server Error or 503 Service Unavailable are common for DB errors
        1,   // Consistent error code
        "messageErrorDatabaseGeneric", // Generic title
        $detailMessage ? $detailMessage : "messageErrorDatabaseRequest" // Specific detail or generic fallback
    );
}

function apiErrorDuplicate($type, $field = null) {
    return apiErrorResponse(
        409, // Conflict
        1,
        "messageErrorDuplicateNumber",
        "messageErrorDuplicateNumber",
        ["type" => ucfirst($type)],
        $field ? "[name='".$field."']" : null
    );
}

function apiError($message, $code = "GENERIC_ERROR", $status = 500) {
    return apiErrorResponse(
        $status,
        1,
        $message,
        $message,
        [],
        null,
        ["errorCode" => $code]
    );
}

/**
 * Database connection with standardized error handling.
 *
 * Envelope wrapper over optvDbConnection() (modules/database/connections.php):
 * returns a SafeMySQL on success and an error *array* on failure, which is why
 * ~113 call sites test `is_object($db)` and pass `$db` straight back out. That
 * contract is what keeps this wrapper around — extracted modules call
 * optvDbConnection() directly and catch instead.
 */
function getApiDatabaseConnection($type = 'platform', $parliament = null) {
    require_once(__DIR__ . "/../../modules/database/connections.php");

    try {
        return optvDbConnection($type, $parliament);
    } catch (OptvDbException $e) {
        switch ($e->getReason()) {
            case 'unknown-parliament':
                return apiErrorResponse(
                    422,
                    1,
                    "messageErrorInvalidParameter",
                    "messageErrorInvalidParameter",
                    ["param" => "parliament"]
                );
            case 'missing-parliament':
                return apiErrorResponse(
                    422,
                    1,
                    "messageErrorMissingParameter",
                    "messageErrorMissingParameter",
                    ["param" => "parliament"]
                );
            default:
                // Reports the scope the caller asked for, as before.
                return apiErrorDatabaseConnection($type);
        }
    }
}

/**
 * Safely sanitizes string input parameters
 * @param string $input The input to sanitize
 * @return string Sanitized input (trimmed, null-safe)
 */
function sanitizeStringInput($input) {
    if ($input === null) {
        return '';
    }
    if (!is_string($input)) {
        return (string)$input;
    }
    return trim($input);
}

/**
 * Prepares and returns the OpenSearch client.
 *
 * @return \OpenSearch\Client|array Error response array
 */
function getApiOpenSearchClient() {
    global $config;
    $clientBuilder = OpenSearch\ClientBuilder::create();

    if (!empty($config["OpenSearch"]["hosts"])) {
        $clientBuilder->setHosts($config["OpenSearch"]["hosts"]);
    }
    if (!empty($config["OpenSearch"]["BasicAuthentication"]["user"]) && isset($config["OpenSearch"]["BasicAuthentication"]["passwd"])) {
        $clientBuilder->setBasicAuthentication($config["OpenSearch"]["BasicAuthentication"]["user"], $config["OpenSearch"]["BasicAuthentication"]["passwd"]);
    }
    if (!empty($config["OpenSearch"]["SSL"]["pem"])) {
        $clientBuilder->setSSLVerification($config["OpenSearch"]["SSL"]["pem"]);
    }

    try {
        return $clientBuilder->build();
    } catch (Exception $e) {
        // Log error
        error_log("OpenSearch ClientBuilder failed: " . $e->getMessage());
        return apiErrorResponse(500, 'OPENSEARCH_CLIENT_ERROR', 'messageErrorOpenSearchClient', 'OpenSearch client initialization failed: ' . $e->getMessage());
    }
}

/**
 * Common validation functions
 */
function apiValidateRequired($value, $field) {
    if (empty($value)) {
        return apiErrorResponse(
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

function apiValidateEmail($email, $field = 'email') {
    $required = apiValidateRequired($email, $field);
    if ($required !== true) {
        return $required;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return apiErrorResponse(
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

function apiValidatePassword($password, $field = 'password') {
    $required = apiValidateRequired($password, $field);
    if ($required !== true) {
        return $required;
    }

    if (!passwordStrength($password)) {
        return apiErrorResponse(
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

function apiValidateDateRange($startDate, $endDate, $startField = 'dateStart') {
    if (!empty($startDate) && !empty($endDate)) {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start > $end) {
            return apiErrorResponse(
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

function apiValidateNumber($value, $field, $min = 1) {
    if (!is_numeric($value) || (int)$value < $min) {
        return apiErrorResponse(
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

/**
 * Merges a module response with the base API metadata
 * @param array $moduleResponse Response from a module
 * @return array Complete API response with metadata
 */
function apiResponse($moduleResponse) {
    global $config;

    $baseResponse = [
        "meta" => [
            "api" => [
                "version" => 1,
                "documentation" => $config["dir"]["root"]."/api",
                "license" => [
                    "label" => "ODC Open Database License (ODbL) v1.0",
                    "link" => "https://opendatacommons.org/licenses/odbl/1-0/"
                ]
            ]
        ]
    ];

    $result = array_replace_recursive($baseResponse, $moduleResponse);

    // Safety net: route any entity thumbnailURI (e.g. nested in search results
    // straight from the search index) through the local image cache proxy.
    require_once(__DIR__."/../../modules/images/image-cache.php");
    imageCacheApplyRecursive($result);

    return $result;
}

/**
 * Helper function to report a conflict via the internal API. Conflicts are
 * aggregated per unique issue (see api/v1/modules/conflict.php): re-reporting
 * the same issue merges the media into the existing group.
 *
 * @param array $conflict Conflict fields:
 *                        - type (string, required, one of CONFLICT_TYPES)
 *                        - parliament (string, optional)
 *                        - mediaID (string, optional; media ID or "origin:..." key)
 *                        - entityType (string, optional)
 *                        - entityLabel (string, optional)
 *                        - entityWid (string, optional)
 *                        - entityKey (string, optional; overrides the wid-or-label group key)
 *                        - data (array, optional; sample payload for the detail view)
 * @param object|false $dbPlatform Optional platform DB connection.
 * @return array The API response from apiV1 or an error structure.
 */
function reportConflict(array $conflict, $dbPlatform = false) {
    global $config; // apiV1 might use it, or config within api.php scope

    // Ensure the main API file is loaded if apiV1 is not universally available.
    require_once (__DIR__."/api.php");

    $request_params = [
        'action' => 'addItem',
        'itemType' => 'conflict',
        'ConflictType' => $conflict['type'] ?? '',
        'ConflictParliament' => $conflict['parliament'] ?? '',
        'ConflictMediaID' => $conflict['mediaID'] ?? '',
        'ConflictEntityType' => $conflict['entityType'] ?? null,
        'ConflictEntityLabel' => $conflict['entityLabel'] ?? null,
        'ConflictEntityWid' => $conflict['entityWid'] ?? null,
        'ConflictEntityKey' => $conflict['entityKey'] ?? null,
        'ConflictData' => $conflict['data'] ?? null
    ];

    try {
        $response = apiV1($request_params, $dbPlatform);
        return $response;
    } catch (Exception $e) {
        // Fallback error reporting if calling apiV1 itself throws an exception
        error_log("Exception in reportConflict (API helper) when calling apiV1: " . $e->getMessage());
        return apiErrorResponse(
            500,
            'HELPER_API_CALL_FAILED',
            'messageErrorApiCallFailedTitle',
            'messageErrorApiCallFailedDetail',
            ['details' => $e->getMessage()]
        );
    }
}

/**
 * Helper function to report an entity suggestion via the internal API.
 *
 * @param string $entitysuggestionExternalID
 * @param string $entitysuggestionType
 * @param string $entitysuggestionLabel
 * @param string $entitysuggestionContent (JSON string)
 * @param string $entitysuggestionContext (The single context string to add)
 * @param object|false $dbPlatform Optional platform DB connection.
 * @return array The API response from apiV1 or an error structure.
 */
function reportEntitySuggestion($entitysuggestionExternalID, $entitysuggestionType, $entitysuggestionLabel, $entitysuggestionContent, $entitysuggestionContext, $dbPlatform = false) {
    global $config; // apiV1 might use it, or config within api.php scope

    // just for safety:
    require_once (__DIR__."/api.php");

    $request_params = [
        'action' => 'addItem',
        'itemType' => 'entitySuggestion',
        'EntitysuggestionExternalID' => $entitysuggestionExternalID,
        'EntitysuggestionType' => $entitysuggestionType,
        'EntitysuggestionLabel' => $entitysuggestionLabel,
        'EntitysuggestionContent' => $entitysuggestionContent, // Should be a JSON string
        'EntitysuggestionContext' => $entitysuggestionContext // The single context entry
    ];

    try {
        // Pass $dbPlatform as the second argument to apiV1, which corresponds to the platform $db connection in apiV1
        $response = apiV1($request_params, $dbPlatform);
        return $response;
    } catch (Exception $e) {
        error_log("Exception in reportEntitySuggestion (API helper) when calling apiV1: " . $e->getMessage());
        return apiErrorResponse(
            500,
            'HELPER_API_CALL_FAILED',
            'messageErrorApiCallFailedTitle',
            'messageErrorApiCallFailedDetail',
            ['details' => $e->getMessage()]
        );
    }
}

/**
 * Sends a formatted message to the command line interface (CLI).
 * Prepends the message with a timestamp.
 *
 * @param string $message The message to be logged to the CLI.
 */
function cliLog($message) {
    $message = date("Y.m.d H:i:s:u") . " - ". $message.PHP_EOL;
    print($message);
    flush();
    if (ob_get_level() > 0) {
        ob_flush();
    }
}
