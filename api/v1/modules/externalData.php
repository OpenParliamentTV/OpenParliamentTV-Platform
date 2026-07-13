<?php

require_once (__DIR__."/../../../config.php");
require_once(__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php"); 

function _externalData_resolve_parliament($requestedParliament = null) {
    global $config;

    if ($requestedParliament !== null && $requestedParliament !== '' && isset($config['parliament'][$requestedParliament])) {
        return $requestedParliament;
    }

    if (!empty($config['parliament']) && is_array($config['parliament'])) {
        return array_key_first($config['parliament']);
    }

    return 'DE';
}

function _externalData_build_ads_url($serviceAPI, $queryParams) {
    return $serviceAPI . '?' . http_build_query($queryParams);
}

// Helper function to safely get a string value from API data
function _optv_get_string_from_data($data_source, $key, $entity_type = 'unknown', $entity_id = 'unknown') {
    if (!isset($data_source[$key])) {
        // Log if a key is expected but missing, could be optional though
        // error_log("Data Info: Key '$key' not set for $entity_type '$entity_id'. Using empty string.");
        return ''; 
    }
    $value = $data_source[$key];
    if (is_string($value)) {
        return $value;
    }
    if (is_array($value)) {
        error_log("Data Warning: Expected string for field '$key' in $entity_type '$entity_id', but received an array. Using empty string. Array data: " . json_encode($value));
        return ''; 
    }
    if (is_null($value)) {
        return ''; 
    }
    // For other scalar types like numbers, booleans, cast to string
    return (string)$value;
}

function updateEntityFromService($type, $id, $serviceAPI, $key, $language = "de", $parliament = null, $db = false) {

    /*if (($id == "Q2415493") || ($id == "Q4316268")) {
        //TODO: Add Blacklist
        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"blacklisted");
        return $return;

    }*/

    global $config;

    $allowedTypes = array("memberOfParliament", "person", "organisation", "legalDocument", "officialDocument", "term");

    /**
     * Parameter validation
     */
    if ((empty($type) || (!in_array($type, $allowedTypes)))) {
        return createApiErrorInvalidParameter("type", "messageErrorInvalidTypeDetail", ["allowedTypes" => implode(", ", $allowedTypes)]);
    }


    if (empty($db)) {

        try {

            $db = new SafeMySQL(array(
                'host'    => $config["platform"]["sql"]["access"]["host"],
                'user'    => $config["platform"]["sql"]["access"]["user"],
                'pass'    => $config["platform"]["sql"]["access"]["passwd"],
                'db'    => $config["platform"]["sql"]["db"]
            ));

        } catch (exception $e) {

            return createApiErrorDatabaseConnection('platform');

        }


    }


    if ($type == "officialDocument") {

        $idLabelPlatform = "DocumentSourceURI";
        $idLabelAPI = "sourceURI";
        $table = $config["platform"]["sql"]["tbl"]["Document"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "legalDocument") {

        $idLabelPlatform = "DocumentWikidataID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Document"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "organisation") {

        $idLabelPlatform = "OrganisationID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Organisation"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "term") {

        $idLabelPlatform = "TermID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Term"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif (($type == "person") || ($type == "memberOfParliament")) {

        $idLabelPlatform = "PersonID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Person"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } else {

        return createApiErrorInvalidParameter("type", "messageErrorInvalidTypeDetail", ["allowedTypes" => implode(", ", $allowedTypes)]);

    }

    try {
        $platformItem = $db->getRow("SELECT * FROM ?n WHERE " . $where, $table);
    } catch (Exception $e) {
        return createApiErrorDatabaseError("Could not get Item from DB: ".$e->getMessage());
    }



    if (empty($platformItem)) {

        return createApiErrorDatabaseError("Could not get Item from DB");

    }


    try {

        $parliament = _externalData_resolve_parliament($parliament);
        $apiItem = json_decode(file_get_contents(_externalData_build_ads_url($serviceAPI, [
            'key' => $key,
            'type' => $type,
            $idLabelAPI => $id,
            'parliament' => $parliament,
        ])), true);

    } catch (Exception $e) {

        return createApiError("Could not get Item from AdditionalDataServiceAPI: ".$e->getMessage(), "EXTERNAL_API_ERROR");

    }

    if (empty($apiItem) || $apiItem["meta"]["requestStatus"] != "success" || empty($apiItem["data"])) {

        return createApiError("Could not get Item from AdditionalDataServiceAPI", "EXTERNAL_API_ERROR");

    }

    if ($type == "officialDocument") {

        $updateArray = array(
            "DocumentLabel"=>$apiItem["data"]["label"],
            "DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>"",
            "DocumentSourceURI"=>(isset($apiItem["data"]["sourceURI"]) ? $apiItem["data"]["sourceURI"] : null),
            "DocumentAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );


    } elseif ($type == "legalDocument") {
        $updateArray = array(
            //"DocumentLabel"=>$apiItem["data"]["label"],
            //"DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>$apiItem["data"]["abstract"],
            "DocumentSourceURI"=>(isset($apiItem["data"]["sourceURI"]) ? $apiItem["data"]["sourceURI"] : null),
            "DocumentAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "organisation") {

        //TODO: Color?
        $updateArray = array(

            //keep labels and labelAlternative disabled so that the regex at NEL does not break
            //"OrganisationLabel"=>_optv_get_string_from_data($apiItem["data"], "label", $type, $id),
            //"OrganisationLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "OrganisationAbstract"=>_optv_get_string_from_data($apiItem["data"], "abstract", $type, $id),
            "OrganisationThumbnailURI"=>_optv_get_string_from_data($apiItem["data"], "thumbnailURI", $type, $id),
            "OrganisationThumbnailCreator"=>_optv_get_string_from_data($apiItem["data"], "thumbnailCreator", $type, $id),
            "OrganisationThumbnailLicense"=>_optv_get_string_from_data($apiItem["data"], "thumbnailLicense", $type, $id),
            "OrganisationWebsiteURI"=>_optv_get_string_from_data($apiItem["data"], "websiteURI", $type, $id),
            "OrganisationSocialMediaIDs"=>json_encode(($apiItem["data"]["socialMediaIDs"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "OrganisationAdditionalInformation"=>json_encode(($apiItem["data"]["additionalInformation"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "term") {
        $updateArray = array(
            //"TermLabel"=>$apiItem["data"]["label"],
            //"TermLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "TermAbstract"=>(isset($apiItem["data"]["abstract"]) ? $apiItem["data"]["abstract"] : null),
            "TermThumbnailURI"=>_optv_get_string_from_data($apiItem["data"], "thumbnailURI", $type, $id),
            "TermThumbnailCreator"=>_optv_get_string_from_data($apiItem["data"], "thumbnailCreator", $type, $id),
            "TermThumbnailLicense"=>_optv_get_string_from_data($apiItem["data"], "thumbnailLicense", $type, $id),
            "TermWebsiteURI"=>_optv_get_string_from_data($apiItem["data"], "websiteURI", $type, $id),
            "TermAdditionalInformation"=>json_encode(($apiItem["data"]["additionalInformation"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "person") {
        $updateArray = array(
            "PersonLabel"=>_optv_get_string_from_data($apiItem["data"], "label", $type, $id),
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>_optv_get_string_from_data($apiItem["data"], "firstName", $type, $id),
            "PersonLastName"=>_optv_get_string_from_data($apiItem["data"], "lastName", $type, $id),
            "PersonDegree"=>_optv_get_string_from_data($apiItem["data"], "degree", $type, $id),
            "PersonBirthDate"=>isset($apiItem["data"]["birthDate"]) ? date('Y-m-d', strtotime($apiItem["data"]["birthDate"])) : '',
            "PersonGender"=>_optv_get_string_from_data($apiItem["data"], "gender", $type, $id),
            "PersonAbstract"=>_optv_get_string_from_data($apiItem["data"], "abstract", $type, $id),
            "PersonThumbnailURI"=>_optv_get_string_from_data($apiItem["data"], "thumbnailURI", $type, $id),
            "PersonThumbnailCreator"=>_optv_get_string_from_data($apiItem["data"], "thumbnailCreator", $type, $id),
            "PersonThumbnailLicense"=>_optv_get_string_from_data($apiItem["data"], "thumbnailLicense", $type, $id),
            "PersonWebsiteURI"=>_optv_get_string_from_data($apiItem["data"], "websiteURI", $type, $id),
            "PersonSocialMediaIDs"=>json_encode(($apiItem["data"]["socialMediaIDs"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode(($apiItem["data"]["additionalInformation"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "memberOfParliament") {
        $updateArray = array(
            "PersonLabel"=>_optv_get_string_from_data($apiItem["data"], "label", $type, $id),
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>_optv_get_string_from_data($apiItem["data"], "firstName", $type, $id),
            "PersonLastName"=>_optv_get_string_from_data($apiItem["data"], "lastName", $type, $id),
            "PersonDegree"=>_optv_get_string_from_data($apiItem["data"], "degree", $type, $id),
            "PersonBirthDate"=>isset($apiItem["data"]["birthDate"]) ? date('Y-m-d', strtotime($apiItem["data"]["birthDate"])) : '',
            "PersonGender"=>_optv_get_string_from_data($apiItem["data"], "gender", $type, $id),
            "PersonAbstract"=>_optv_get_string_from_data($apiItem["data"], "abstract", $type, $id),
            "PersonThumbnailURI"=>_optv_get_string_from_data($apiItem["data"], "thumbnailURI", $type, $id),
            "PersonThumbnailCreator"=>_optv_get_string_from_data($apiItem["data"], "thumbnailCreator", $type, $id),
            "PersonThumbnailLicense"=>_optv_get_string_from_data($apiItem["data"], "thumbnailLicense", $type, $id),
            "PersonWebsiteURI"=>_optv_get_string_from_data($apiItem["data"], "websiteURI", $type, $id),
            "PersonSocialMediaIDs"=>json_encode(($apiItem["data"]["socialMediaIDs"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode(($apiItem["data"]["additionalInformation"] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonPartyOrganisationID"=>($apiItem["data"]["partyID"] ?? ''),
            "PersonFactionOrganisationID"=>($apiItem["data"]["factionID"] ?? '')
        );

    }

    try {

        $query = $db->query("UPDATE ?n SET ?u WHERE " . $where, $table, $updateArray);

    } catch (Exception $e) {

        return createApiErrorDatabaseError("Could not update Item in database ".$type." ".$id.": ".$e->getMessage());

    }

    return createApiSuccessResponse(null, ["message" => "Item has been updated: ".$type." ".$id]);


}

/**
 * Bulk-enrich official documents via the ADS batch lookup (documentNumbers).
 *
 * Sends one ADS request for up to 100 documents (a single batched source
 * lookup on the ADS side) instead of one request per document. Rows are
 * matched back by the parliament-native document number derived from
 * DocumentLabel; the DB update is keyed by DocumentID so a rewritten
 * DocumentSourceURI cannot invalidate its own WHERE clause.
 *
 * @param array $documentRows [['DocumentID'=>..,'DocumentLabel'=>..,'DocumentSourceURI'=>..], ...] (max 100)
 * @return array API response; data = ['updatedIDs'=>[], 'notFoundNumbers'=>[], 'failed'=>[]]
 */
function updateOfficialDocumentsBatchFromService(array $documentRows, $serviceAPI, $key, $parliament = null, $db = false) {

    global $config;

    if (empty($documentRows)) {
        return createApiSuccessResponse(["updatedIDs" => [], "notFoundNumbers" => [], "failed" => []], ["message" => "No documents to update."]);
    }

    if (empty($db)) {

        try {

            $db = new SafeMySQL(array(
                'host'    => $config["platform"]["sql"]["access"]["host"],
                'user'    => $config["platform"]["sql"]["access"]["user"],
                'pass'    => $config["platform"]["sql"]["access"]["passwd"],
                'db'    => $config["platform"]["sql"]["db"]
            ));

        } catch (exception $e) {

            return createApiErrorDatabaseConnection('platform');

        }

    }

    $table = $config["platform"]["sql"]["tbl"]["Document"];
    $parliament = _externalData_resolve_parliament($parliament);

    $failed = [];
    $numberToRow = [];
    foreach ($documentRows as $row) {
        if (empty($row["DocumentID"]) || empty($row["DocumentLabel"]) || !preg_match('#(\d+/\d+)$#', $row["DocumentLabel"], $m)) {
            $failed[] = ["DocumentID" => $row["DocumentID"] ?? null, "error" => "Could not derive document number from label: " . ($row["DocumentLabel"] ?? "")];
            continue;
        }
        $numberToRow[$m[1]] = $row;
    }

    if (empty($numberToRow)) {
        return createApiSuccessResponse(["updatedIDs" => [], "notFoundNumbers" => [], "failed" => $failed], ["message" => "No parseable document numbers in batch."]);
    }

    try {

        $url = _externalData_build_ads_url($serviceAPI, [
            'key' => $key,
            'type' => 'officialDocument',
            'documentNumbers' => implode(",", array_keys($numberToRow)),
            'parliament' => $parliament,
        ]);
        // Batch responses can take a while when the ADS resolves truncated
        // procedure lists via per-document follow-up requests.
        $context = stream_context_create(["http" => ["timeout" => 180]]);
        $apiItem = json_decode(@file_get_contents($url, false, $context), true);

    } catch (Exception $e) {

        return createApiError("Could not get batch from AdditionalDataServiceAPI: ".$e->getMessage(), "EXTERNAL_API_ERROR");

    }

    if (empty($apiItem) || ($apiItem["meta"]["requestStatus"] ?? "") != "success" || !isset($apiItem["data"]) || !is_array($apiItem["data"])) {
        $errorDetail = !empty($apiItem["errors"]) ? json_encode($apiItem["errors"]) : "no/invalid response";
        return createApiError("Could not get batch from AdditionalDataServiceAPI: ".$errorDetail, "EXTERNAL_API_ERROR");
    }

    // Group returned items by document number: documentNumber is the exact
    // value the batch was queried by; the label suffix parse is a fallback.
    $itemsByNumber = [];
    foreach ($apiItem["data"] as $item) {
        $number = $item["documentNumber"] ?? null;
        if ($number === null && !empty($item["label"]) && preg_match('#(\d+/\d+)$#', $item["label"], $m)) {
            $number = $m[1];
        }
        if ($number === null) {
            continue;
        }
        $itemsByNumber[$number][] = $item;
    }

    $updatedIDs = [];
    $notFoundNumbers = [];

    foreach ($numberToRow as $number => $row) {

        if (empty($itemsByNumber[$number])) {
            $notFoundNumbers[] = $number;
            continue;
        }

        // Several source documents can share a number (e.g. corrected
        // reprints): prefer the item whose sourceURI matches the stored one.
        $item = $itemsByNumber[$number][0];
        if (count($itemsByNumber[$number]) > 1) {
            foreach ($itemsByNumber[$number] as $candidate) {
                if (!empty($candidate["sourceURI"]) && $candidate["sourceURI"] === $row["DocumentSourceURI"]) {
                    $item = $candidate;
                    break;
                }
            }
            error_log("updateOfficialDocumentsBatchFromService: multiple source documents for number ".$number."; using originID ".($item["additionalInformation"]["originID"] ?? "?"));
        }

        $updateArray = array(
            "DocumentLabel"=>$item["label"],
            "DocumentLabelAlternative"=>json_encode(($item["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>"",
            "DocumentSourceURI"=>(isset($item["sourceURI"]) ? $item["sourceURI"] : null),
            "DocumentAdditionalInformation"=>json_encode($item["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        try {
            $db->query("UPDATE ?n SET ?u WHERE DocumentID = ?i", $table, $updateArray, $row["DocumentID"]);
            $updatedIDs[] = (int)$row["DocumentID"];
        } catch (Exception $e) {
            $failed[] = ["DocumentID" => $row["DocumentID"], "error" => "Could not update Item in database: ".$e->getMessage()];
        }

    }

    return createApiSuccessResponse(
        ["updatedIDs" => $updatedIDs, "notFoundNumbers" => $notFoundNumbers, "failed" => $failed],
        ["message" => count($updatedIDs)." documents updated, ".count($notFoundNumbers)." not found, ".count($failed)." failed."]
    );

}

function externalDataGetInfo($api_request) {
    global $config;

    if (empty($api_request["type"])) {
        return createApiErrorMissingParameter("type");
    }
    if (empty($api_request["wikidataID"])) {
        return createApiErrorMissingParameter("wikidataID");
    }
    
    try {
        $parliament = _externalData_resolve_parliament($api_request["parliament"] ?? null);
        $url = _externalData_build_ads_url($config["ads"]["api"]["uri"], [
            'key' => $config["ads"]["api"]["key"],
            'type' => $api_request["type"],
            'wikidataID' => $api_request["wikidataID"],
            'parliament' => $parliament,
        ]);
        $response_json = file_get_contents($url);
        
        if ($response_json === false) {
            return createApiError("Failed to fetch data from external service: ".$url, "EXTERNAL_SERVICE_ERROR");
        }
        
        // Check if response is empty
        if (empty($response_json)) {
            return createApiError("Empty response from external service", "EXTERNAL_SERVICE_EMPTY_RESPONSE");
        }
        
        $response_array = json_decode($response_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return createApiError("Invalid JSON response from external service: ".json_last_error_msg(), "EXTERNAL_SERVICE_INVALID_JSON");
        }
        
        // Check if response_array is null or empty after JSON decode
        if (empty($response_array)) {
            return createApiError("No data returned from external service", "EXTERNAL_SERVICE_NO_DATA");
        }
        
        // Check for explicit error responses from the external service
        if (isset($response_array["success"]) && $response_array["success"] === false) {
            $errorMessage = $response_array["text"] ?? $response_array["message"] ?? "Unknown error from external service";
            return createApiError($errorMessage, "EXTERNAL_SERVICE_API_ERROR");
        }
        
        // Check for error status in response meta
        if (isset($response_array["meta"]["requestStatus"]) && $response_array["meta"]["requestStatus"] === "error") {
            $errorMessage = "External service returned an error";
            if (isset($response_array["errors"]) && is_array($response_array["errors"])) {
                $errorDetails = array_filter($response_array["errors"], function($error) {
                    return !empty($error);
                });
                if (!empty($errorDetails)) {
                    // Format error details properly
                    $formattedErrors = array_map(function($error) {
                        if (is_array($error)) {
                            return json_encode($error);
                        }
                        return (string)$error;
                    }, $errorDetails);
                    $errorMessage = "External service error: " . implode(", ", $formattedErrors);
                }
            }
            return createApiError($errorMessage, "EXTERNAL_SERVICE_API_ERROR");
        }
        
        // Check for error status in response
        if (isset($response_array["error"]) || (isset($response_array["status"]) && $response_array["status"] === "error")) {
            $errorMessage = $response_array["error"] ?? $response_array["message"] ?? "Error response from external service";
            return createApiError($errorMessage, "EXTERNAL_SERVICE_API_ERROR");
        }
        
        // Validate that we have actual data to return
        $data = $response_array["data"] ?? $response_array;
        if (empty($data)) {
            return createApiError("No data found in external service response", "EXTERNAL_SERVICE_NO_DATA");
        }
        
        // Return successful response with the data
        return createApiSuccessResponse($data, ["message" => $response_array["text"] ?? $response_array["message"] ?? "Successfully fetched data."]);

    } catch (Exception $e) {
        return createApiError("Exception occurred while fetching external data: ".$e->getMessage(), "EXTERNAL_SERVICE_EXCEPTION");
    }
}

function externalDataTriggerFullUpdate($api_request) {
    global $config;

    if (empty($api_request["type"])) {
        return createApiErrorMissingParameter("type");
    }

    $lockFile = __DIR__ . "/../../../data/cronAdditionalDataService.lock";
    $logFile = __DIR__ . "/../../../data/cronAdditionalDataService.log"; // For logging API-side checks

    if (file_exists($lockFile)) {
        $lockAge = time() - filemtime($lockFile);
        $ignoreTime = isset($config["time"]["ignore"]) ? ($config["time"]["ignore"] * 60) : (90 * 60); // Default 90 mins

        if ($lockAge < $ignoreTime) {
            // cronAdditionalDataService.php itself has more detailed warning/ignore logic.
            // This API check provides an immediate "already running" feedback.
            return createApiErrorResponse(
                409, // Conflict
                "FULL_UPDATE_ALREADY_RUNNING",
                "Full update process is already running.",
                "The full update process (cronAdditionalDataService.php) appears to be running. Last activity on lock file: " . date("Y-m-d H:i:s", filemtime($lockFile)) . ". Please try again later or check the status.",
                [],
                null,
                [
                    "running" => true,
                    "lockFileLastModified" => date("Y-m-d H:i:s", filemtime($lockFile)),
                    "lockFileAgeSeconds" => $lockAge
                ]
            );
        } else {
            // Lock file is stale, attempt to remove it. cronAdditionalDataService.php also has this logic.
            // Logging this attempt from the API side.
            error_log("API externalDataTriggerFullUpdate: Stale lock file found and removed: " . $lockFile . " (Age: " . $lockAge . "s)", 0, $logFile);
            unlink($lockFile);
        }
    }

    $scriptPath = realpath(__DIR__."/../../../data/cronAdditionalDataService.php");
    if ($scriptPath === false) {
        error_log("API externalDataTriggerFullUpdate: Failed to resolve realpath for cronAdditionalDataService.php", 0, $logFile);
        return createApiErrorResponse(500, "SCRIPT_PATH_ERROR", "Server configuration error: Script path not found.", "The system could not find the necessary script to run the update process.");
    }

    if (isPhpFunctionDisabled('exec')) {
        return createApiErrorResponse(500, "EXEC_DISABLED", "Could not start update process", "PHP exec() is disabled on this server. Background update jobs cannot be started from the web interface.");
    }

    $parliament = _externalData_resolve_parliament($api_request["parliament"] ?? null);
    $phpBinary = resolvePhpCliBinary($config["bin"]["php"] ?? "");

    $command = escapeshellarg($phpBinary)." ".escapeshellarg($scriptPath)
        ." --type=".escapeshellarg($api_request["type"])
        ." --parliament=".escapeshellarg($parliament);
    executeAsyncShellCommand($command);
    
    return createApiSuccessResponse(["message" => "Full update process for type '".$api_request["type"]."' initiated."]);
}

/**
 * Gets the current status of the cronAdditionalDataService by reading its progress file.
 * 
 * @param array $api_request (Not directly used by this function for now, but part of API structure)
 * @return array Response with status information from the cronAdditionalDataService.json file.
 */
function externalDataGetFullUpdateStatus($api_request = []) { // Added default for api_request
    global $config;

    $progressFilePath = __DIR__ . "/../../../data/progress/cronAdditionalDataService.json";
    
    $entityTypes = ["person", "memberOfParliament", "organisation", "legalDocument", "officialDocument", "term"];
    $defaultStatus = [
        "globalStatus" => "idle",
        "activeType" => null,
        "types" => []
    ];

    foreach ($entityTypes as $type) {
        $defaultStatus["types"][$type] = [
            "status" => "idle",
            "statusDetails" => "No active or recent process found.",
            "startTime" => null,
            "endTime" => null,
            "totalItems" => 0,
            "processedItems" => 0,
            "errors" => [],
            "lastSuccessfullyProcessedId" => null,
            "lastActivityTime" => null
        ];
    }

    if (!file_exists($progressFilePath)) {
        return createApiSuccessResponse($defaultStatus, ["message" => "ADS progress file not found, returning default idle status."]);
    }

    $progressJson = @file_get_contents($progressFilePath);
    if ($progressJson === false) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_READ_ERROR', "Could not read the ADS progress file.", "File exists at {$progressFilePath} but is unreadable.");
    }

    $progressData = json_decode($progressJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "ADS progress file is corrupt or not valid JSON. JSON decode error: " . json_last_error_msg() . " in file {$progressFilePath}";
        // If file is corrupt, we still return the default structure but with an error message
        $defaultStatus['globalStatus'] = 'error';
        foreach ($entityTypes as $type) {
            $defaultStatus['types'][$type]['status'] = 'error';
            $defaultStatus['types'][$type]['statusDetails'] = 'Progress file is corrupt.';
        }
        return createApiSuccessResponse($defaultStatus, ["message" => $error_message]);
    }
    
    // Merge loaded data with default structure to ensure all keys exist
    $response_data = $defaultStatus;
    if (isset($progressData['globalStatus'])) {
        $response_data['globalStatus'] = $progressData['globalStatus'];
    }
    if (isset($progressData['activeType'])) {
        $response_data['activeType'] = $progressData['activeType'];
    }
    if (isset($progressData['types']) && is_array($progressData['types'])) {
        foreach ($entityTypes as $type) {
            if (isset($progressData['types'][$type])) {
                $response_data['types'][$type] = array_merge($defaultStatus['types'][$type], $progressData['types'][$type]);
            }
        }
    }

    return createApiSuccessResponse(
        $response_data,
        ["message" => "ADS status retrieved successfully."]
    );
}

function externalDataUpdateEntities($api_request) {
    global $config; 

    if (empty($api_request["ids"]) || !is_array($api_request["ids"])) {
        return createApiErrorMissingParameter("ids (array)");
    }
    if (empty($api_request["type"]) || !is_array($api_request["type"])) { // Original code implies type is an array parallel to ids
        return createApiErrorMissingParameter("type (array)");
    }
    if (count($api_request["ids"]) !== count($api_request["type"])) {
        return createApiErrorResponse(400, "INVALID_PARAMETER", "ids and type arrays must have the same number of elements.");
    }
    
    $language = $api_request["language"] ?? "de";
    $parliament = _externalData_resolve_parliament($api_request["parliament"] ?? null);

    $results = [];
    $errors = [];
    $successCount = 0;

    foreach ($api_request["ids"] as $k => $id) {
        // The type for each ID comes from the parallel $api_request["type"] array
        $current_type = $api_request["type"][$k]; 
        $update_result = updateEntityFromService($current_type, $id, $config["ads"]["api"]["uri"], $config["ads"]["api"]["key"], $language, $parliament);
        
        if (isset($update_result["meta"]["requestStatus"]) && $update_result["meta"]["requestStatus"] == "success") {
            $results[] = $update_result; // Or a simplified success message
            $successCount++;
        } else {
            // Collate errors for a summary response
            $errors[] = ["id" => $id, "type" => $current_type, "error" => $update_result["errors"] ?? "Unknown error during update."];
        }
    }

    if (!empty($errors)) {
        // If there were any errors, return a mixed success/error response or just an error if all failed
        if ($successCount == 0) {
            return createApiErrorResponse(500, "ENTITY_UPDATE_FAILED", "Entity updated failed.", null, [], null, ["details" => $errors]);
        }
        // Partial success
        return createApiSuccessResponse(
            ["updated_count" => $successCount, "failed_updates" => $errors],
            ["message" => "Entities update process completed with ".$successCount." successes and ".count($errors)." failures."]
        );

    }
    
    return createApiSuccessResponse(["updated_count" => $successCount], ["message" => "All ".count($api_request["ids"])." entities updated successfully."]);
}

?> 