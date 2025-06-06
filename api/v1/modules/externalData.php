<?php

require_once (__DIR__."/../../../config.php");
require_once(__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php"); 

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

function updateEntityFromService($type, $id, $serviceAPI, $key, $language = "de", $db = false) {

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

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"wrong or missing parameter", "field"=>"type");
        return $return;

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

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to platform database failed";
            array_push($return["errors"], $errorarray);
            return $return;

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

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"wrong or missing parameter", "field"=>"type");
        return $return;

    }

    try {
        $platformItem = $db->getRow("SELECT * FROM ?n WHERE " . $where, $table);
    } catch (Exception $e) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from DB".$e->getMessage());
        return $return;
    }



    if (empty($platformItem)) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from DB");
        return $return;

    }


    try {

        $apiItem = json_decode(file_get_contents($serviceAPI . "?key=" . $key . "&type=" . $type . "&" . $idLabelAPI . "=" . $id), true);

    } catch (Exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from AdditionalDataServiceAPI".$e->getMessage());
        return $return;

    }

    if (empty($apiItem) || $apiItem["meta"]["requestStatus"] != "success" || empty($apiItem["data"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from AdditionalDataServiceAPI");
        return $return;

    }

    if ($type == "officialDocument") {

        $updateArray = array(
            "DocumentLabel"=>$apiItem["data"]["label"],
            "DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>"",
            "DocumentAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );


    } elseif ($type == "legalDocument") {
        $updateArray = array(
            //"DocumentLabel"=>$apiItem["data"]["label"],
            //"DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>$apiItem["data"]["abstract"],
            "DocumentSourceURI"=>$apiItem["data"]["sourceURI"],
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
            "TermAbstract"=>$apiItem["data"]["abstract"],
            "TermThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "TermThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "TermThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "TermWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "TermAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "person") {
        $updateArray = array(
            "PersonLabel"=>$apiItem["data"]["label"],
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>$apiItem["data"]["firstName"],
            "PersonLastName"=>$apiItem["data"]["lastName"],
            "PersonDegree"=>$apiItem["data"]["degree"],
            "PersonBirthDate"=>date('Y-m-d', strtotime($apiItem["data"]["birthDate"])),
            "PersonGender"=>$apiItem["data"]["gender"],
            "PersonAbstract"=>$apiItem["data"]["abstract"],
            "PersonThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "PersonThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "PersonThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "PersonWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "PersonSocialMediaIDs"=>json_encode($apiItem["data"]["socialMediaIDs"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "memberOfParliament") {
        $updateArray = array(
            "PersonLabel"=>$apiItem["data"]["label"],
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>$apiItem["data"]["firstName"],
            "PersonLastName"=>$apiItem["data"]["lastName"],
            "PersonDegree"=>$apiItem["data"]["degree"],
            "PersonBirthDate"=>date('Y-m-d', strtotime($apiItem["data"]["birthDate"])),
            "PersonGender"=>$apiItem["data"]["gender"],
            "PersonAbstract"=>$apiItem["data"]["abstract"],
            "PersonThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "PersonThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "PersonThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "PersonWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "PersonSocialMediaIDs"=>json_encode($apiItem["data"]["socialMediaIDs"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonPartyOrganisationID"=>$apiItem["data"]["partyID"],
            "PersonFactionOrganisationID"=>$apiItem["data"]["factionID"]
        );

    }

    try {

        $query = $db->query("UPDATE ?n SET ?u WHERE " . $where, $table, $updateArray);

    } catch (Exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not update Item in database ".$type." ".$id.": ".$e->getMessage());
        return $return;

    }

    $return["meta"]["requestStatus"] = "success";
    $return["text"][] = array("info"=>"Item has been updated: ".$type." ".$id);
    return $return;


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
        $url = $config["ads"]["api"]["uri"]."?key=".$config["ads"]["api"]["key"]."&type=".$api_request["type"]."&wikidataID=".$api_request["wikidataID"];
        $response_json = file_get_contents($url);
        if ($response_json === false) {
            return createApiError("Failed to fetch data from external service: ".$url, "EXTERNAL_SERVICE_ERROR");
        }
        $response_array = json_decode($response_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return createApiError("Invalid JSON response from external service: ".json_last_error_msg(), "EXTERNAL_SERVICE_INVALID_JSON");
        }
        // Assuming the external service returns a structure that can be directly passed or needs specific mapping
        // The original ajaxServer.php directly returned the json_decode output.
        // We need to ensure it's compatible with createApiResponse structure or wrap it.
        // For now, let's assume the external service returns success/data or error structure
        if (isset($response_array["success"]) && $response_array["success"] == "false") {
             return createApiError($response_array["text"] ?? "Unknown error from external service", "EXTERNAL_SERVICE_API_ERROR");
        }
        // If the external service's "success" isn't "false", we assume it's a successful response.
        // The original code returned the full decoded JSON. Let's wrap it in a standard success response.
        return createApiSuccessResponse($response_array["data"] ?? $response_array, ["message" => $response_array["text"] ?? "Successfully fetched data."]);


    } catch (Exception $e) {
        return createApiError("Exception: ".$e->getMessage(), "EXTERNAL_SERVICE_EXCEPTION");
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

    $command = $config["bin"]["php"]." ".$scriptPath." --type ".$api_request["type"];
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

    if (!file_exists($progressFilePath)) {
        // Default status if progress file doesn't exist
        $defaultStatus = [
            "processName" => "additionalDataService",
            "activeType" => null,
            "status" => "idle",
            "statusDetails" => "No active or recent ADS process found.",
            "startTime" => null,
            "endTime" => null,
            "totalItems" => 0, // For the current or last activeType
            "processedItems" => 0, // For the current or last activeType
            "errors" => [],
            "lastSuccessfullyProcessedId" => null,
            "lastActivityTime" => null // It's good practice to have this, even if not explicitly in ADS progress file yet
        ];
        return createApiSuccessResponse($defaultStatus, ["message" => "ADS progress file not found, returning default idle status."]);
    }

    $progressJson = @file_get_contents($progressFilePath);
    if ($progressJson === false) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_READ_ERROR', "Could not read the ADS progress file.", "File exists at {$progressFilePath} but is unreadable.");
    }

    $progressData = json_decode($progressJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return createApiErrorResponse(500, 'PROGRESS_FILE_CORRUPT', "ADS progress file is corrupt or not valid JSON.", "JSON decode error: " . json_last_error_msg() . " in file {$progressFilePath}");
    }
    
    // Ensure all expected keys from the defaultStatus are present
    $defaultKeysTemplate = [
        "processName" => "additionalDataService", "activeType" => null, "status" => "unknown", "statusDetails" => "",
        "startTime" => null, "endTime" => null, "totalItems" => 0, "processedItems" => 0,
        "errors" => [], "lastSuccessfullyProcessedId" => null, "lastActivityTime" => date('c') // Set current time if not in file
    ];
    
    // If lastActivityTime is not in the loaded data, set it to filemtime or current time.
    if (!isset($progressData["lastActivityTime"])) {
        $progressData["lastActivityTime"] = date('c', filemtime($progressFilePath));
    }

    $progressData = array_merge($defaultKeysTemplate, $progressData);

    return createApiSuccessResponse(
        $progressData,
        [
            "message" => "ADS status retrieved successfully from progress file."
        ]
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


    $results = [];
    $errors = [];
    $successCount = 0;

    foreach ($api_request["ids"] as $k => $id) {
        // The type for each ID comes from the parallel $api_request["type"] array
        $current_type = $api_request["type"][$k]; 
        $update_result = updateEntityFromService($current_type, $id, $config["ads"]["api"]["uri"], $config["ads"]["api"]["key"], $language /*, $db - db instance could be passed or created inside updateEntityFromService */);
        
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
            return createApiErrorResponse(500, "ENTITY_UPDATE_FAILED", "All entity updates failed.", null, [], null, ["details" => $errors]);
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