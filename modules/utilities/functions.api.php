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
function createApiSuccessResponse($data = null, $meta = [], $links = null, $relationships = null, $total = null) {
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
        $selector ? "[name=".$selector."]" : null
    );
}

function createApiErrorInvalidParameter($paramName, $detailMessageKey = null, $detailParams = []) {
    return createApiErrorResponse(
        422, // Unprocessable Entity or 400 Bad Request
        1,   // Consistent error code
        "messageErrorInvalidParameter", 
        $detailMessageKey ?: "messageErrorInvalidParameter", 
        array_merge(["param" => $paramName], $detailParams), 
        "[name=".$paramName."]" // Optional: DOM selector for the field
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

function createApiErrorDuplicate($type, $field = null) {
    return createApiErrorResponse(
        409, // Conflict
        1,
        "messageErrorDuplicateNumber",
        "messageErrorDuplicateNumber",
        ["type" => ucfirst($type)],
        $field ? "[name='".$field."']" : null
    );
}

function createApiError($message, $code = "GENERIC_ERROR", $status = 500) {
    return createApiErrorResponse(
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
        return createApiErrorResponse(500, 'OPENSEARCH_CLIENT_ERROR', 'messageErrorOpenSearchClient', 'OpenSearch client initialization failed: ' . $e->getMessage());
    }
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

/**
 * Merges a module response with the base API metadata
 * @param array $moduleResponse Response from a module
 * @return array Complete API response with metadata
 */
function createApiResponse($moduleResponse) {
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
    return $result;
}

/**
 * Helper function to report a conflict via the internal API.
 *
 * @param string $entity
 * @param string $subject
 * @param string $identifier
 * @param string $rival
 * @param string $description
 * @param object|false $dbPlatform 
 * @return array The API response from apiV1 or an error structure.
 */
function reportConflict($entity, $subject, $identifier = "", $rival = "", $description = "", $dbPlatform = false /* Kept for signature compatibility */) {
    global $config; // apiV1 might use it, or config within api.php scope

    // Ensure the main API file is loaded if apiV1 is not universally available.
    // This might already be handled by the calling script's autoloader or includes.
    // If apiV1 is in the global scope already, this require might not be strictly necessary
    // but it's safer to ensure it's available.
    require_once (__DIR__."/../../api/v1/api.php"); 

    $request_params = [
        'action' => 'addItem',
        'itemType' => 'conflict',
        'ConflictEntity' => $entity,
        'ConflictSubject' => $subject,
        'ConflictIdentifier' => $identifier,
        'ConflictRival' => $rival,
        'ConflictDescription' => $description
    ];

    try {
        $response = apiV1($request_params, $dbPlatform);
        return $response;
    } catch (Exception $e) {
        // Fallback error reporting if calling apiV1 itself throws an exception
        error_log("Exception in reportConflict (API helper) when calling apiV1: " . $e->getMessage());
        return createApiErrorResponse(
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
    require_once (__DIR__."/../../api/v1/api.php"); 

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
        return createApiErrorResponse(
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

/**
 * Handles post-processing for entity suggestions after an entity has been added.
 * This includes re-importing affected sessions and deleting the original suggestion.
 *
 * @param bool $reimportAffectedSessionsFlag Indicates if re-import should be attempted.
 * @param string|int|null $sourceEntitySuggestionID The internal ID of the entity suggestion.
 * @param object $db The platform database connection.
 * @return array An array containing post-processing status and data:
 *               [
 *                 'reimportStatus' => string ("success", "error", "not_attempted", "no_sessions_to_reimport"),
 *                 'reimportSummary' => string|null,
 *                 'affectedSessions' => array|null (list of successfully re-imported session files),
 *                 'affectedSpeeches' => array|null (list of speech IDs from EntitysuggestionContext),
 *                 'suggestionDeleteStatus' => string ("success", "error", "not_attempted")
 *               ]
 */
function handleEntitySuggestionPostProcessing($reimportAffectedSessionsFlag, $sourceEntitySuggestionID, $db) {
    global $config; // Required for apiV1 and getInfosFromStringID

    // Ensure the main API file is loaded for apiV1 calls
    // and functions.php for getInfosFromStringID
    require_once (__DIR__."/../../api/v1/api.php"); 

    $postProcessingResult = [
        'reimportStatus' => 'not_attempted',
        'reimportSummary' => null,
        'affectedSessions' => null,
        'affectedSpeeches' => null,
        'suggestionDeleteStatus' => 'not_attempted'
    ];

    if (!$reimportAffectedSessionsFlag || empty($sourceEntitySuggestionID)) {
        // If no re-import is flagged or no suggestion ID, reimportStatus remains 'not_attempted'
        // and suggestionDeleteStatus also remains 'not_attempted'
        return $postProcessingResult;
    }

    // 1. Fetch Entity Suggestion Details
    $suggestionApiResponse = apiV1([
        "action" => "getItemsFromDB",
        "itemType" => "entitySuggestion",
        "id" => $sourceEntitySuggestionID,
        "idType" => "internal"
    ], $db);

    if (!isset($suggestionApiResponse["meta"]["requestStatus"]) || $suggestionApiResponse["meta"]["requestStatus"] !== "success" || empty($suggestionApiResponse["data"])) {
        error_log("handleEntitySuggestionPostProcessing: Failed to fetch entity suggestion ID: " . $sourceEntitySuggestionID);
        $postProcessingResult['reimportStatus'] = 'error'; // Cannot proceed with re-import
        $postProcessingResult['suggestionDeleteStatus'] = 'error'; // Cannot proceed with delete if suggestion not fetched
        return $postProcessingResult;
    }
    
    $entitySuggestionItem = is_array($suggestionApiResponse["data"]) && isset($suggestionApiResponse["data"][0]) ? $suggestionApiResponse["data"][0] : $suggestionApiResponse["data"];

    if (isset($entitySuggestionItem["EntitysuggestionContext"]) && is_array($entitySuggestionItem["EntitysuggestionContext"])) {
        $postProcessingResult['affectedSpeeches'] = array_keys($entitySuggestionItem["EntitysuggestionContext"]);
    } else {
        // No context found, so no sessions to reimport for this suggestion.
        $postProcessingResult['reimportStatus'] = 'success'; // No sessions to reimport is a success state for reimport
        $postProcessingResult['affectedSpeeches'] = []; // Ensure it's an empty array
        // Proceed to attempt deletion as the suggestion might still exist and needs cleanup.
    }

    // 2. Prepare filesToReimport array
    $filesToReimport = [];
    if (!empty($postProcessingResult['affectedSpeeches'])) {
        foreach ($entitySuggestionItem["EntitysuggestionContext"] as $contextKey => $contextValue) {
            $itemInfos = getInfosFromStringID($contextKey);
            if ($itemInfos && isset($itemInfos["parliament"]) && isset($itemInfos["electoralPeriodNumber"]) && isset($itemInfos["sessionNumber"])) {
                $parliament = trim($itemInfos["parliament"]);
                $electoralPeriod = trim(substr($itemInfos["electoralPeriodNumber"], 1));
                $sessionNum = trim(substr($itemInfos["sessionNumber"], 1));
                $tmpFileName = $electoralPeriod . $sessionNum . "-session.json";
                if (!isset($filesToReimport[$parliament])) {
                    $filesToReimport[$parliament] = [];
                }
                $filesToReimport[$parliament][] = $tmpFileName;
            }
        }
    }

    // 3. Trigger Re-import
    if (!empty($filesToReimport)) {
        $reimportResponse = apiV1([
            "action" => "import",
            "itemType" => "reimport-sessions",
            "files" => $filesToReimport
        ], $db);

        if (isset($reimportResponse["meta"]["requestStatus"]) && $reimportResponse["meta"]["requestStatus"] === "success") {
            $postProcessingResult['reimportStatus'] = 'success';
            if (isset($reimportResponse["meta"]["summary"])) {
                $postProcessingResult['reimportSummary'] = $reimportResponse["meta"]["summary"];
            }
            if (isset($reimportResponse["data"]["copied"])) {
                $postProcessingResult['affectedSessions'] = $reimportResponse["data"]["copied"];
            }
        } else {
            error_log("handleEntitySuggestionPostProcessing: Re-import failed for suggestion ID: " . $sourceEntitySuggestionID . " - Response: " . json_encode($reimportResponse));
            $postProcessingResult['reimportStatus'] = 'error';
            // Do not attempt to delete the suggestion if re-import failed, to allow for manual retry/investigation
            return $postProcessingResult; // Exit early
        }
    } else if (empty($postProcessingResult['affectedSpeeches'])) {
        // This means EntitysuggestionContext was empty or null, so reimport is successful as there was nothing to do.
        $postProcessingResult['reimportStatus'] = 'success';
    } else {
        // Context was present but resulted in no valid files (e.g., getInfosFromStringID failed for all)
        // This is also considered a success for re-import as there were no valid sessions to process.
        $postProcessingResult['reimportStatus'] = 'success'; 
        $postProcessingResult['reimportSummary'] = 'No valid session files derived from context to re-import.';
    }

    // 4. (Attempt to) Delete Entity Suggestion if re-import was successful
    if ($postProcessingResult['reimportStatus'] === 'success') {
        $deleteSuggestionResponse = apiV1([
            "action" => "deleteItem", // This action needs to be added to api.php
            "itemType" => "entitySuggestion",
            "EntitysuggestionID" => $sourceEntitySuggestionID
        ], $db);

        if (isset($deleteSuggestionResponse["meta"]["requestStatus"]) && $deleteSuggestionResponse["meta"]["requestStatus"] === "success") {
            $postProcessingResult['suggestionDeleteStatus'] = 'success';
        } else {
            error_log("handleEntitySuggestionPostProcessing: Failed to delete entity suggestion ID: " . $sourceEntitySuggestionID . " - Response: " . json_encode($deleteSuggestionResponse));
            $postProcessingResult['suggestionDeleteStatus'] = 'error';
            // Note: The overall process might still be considered a success for the user if entity add + reimport worked,
            // but an error for suggestion deletion should be logged for admin attention.
        }
    } else {
        // If re-import was not successful, don't attempt to delete the suggestion.
        $postProcessingResult['suggestionDeleteStatus'] = 'not_attempted';
    }

    return $postProcessingResult;
}

?> 