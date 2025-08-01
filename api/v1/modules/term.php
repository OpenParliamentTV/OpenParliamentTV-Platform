<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");




/**
 * @param string $id TermID
 * @return array
 */
function termGetByID($id = false) {
    global $config;

    if (!$id) {
        return createApiErrorMissingParameter('id');
    }

    // Validate ID format (Q or P followed by numbers)
    if (!preg_match("/(Q|P)\d+/i", $id)) {
        return createApiErrorInvalidID('term');
    }

    $db = getApiDatabaseConnection();
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT * FROM ?n WHERE TermID=?s",
            $config["platform"]["sql"]["tbl"]["Term"],
            $id
        );

        if (!$item) {
            return createApiErrorNotFound('term');
        }

        // termGetDataObject already returns the correct structure for the 'data' field
        $termDataObject = termGetDataObject($item, $db);
        if (!$termDataObject) { // Add a check in case termGetDataObject fails
            return createApiErrorResponse(500, 1, "messageErrorDataTransformationTitle", "messageErrorDataTransformationDetail");
        }
        return createApiSuccessResponse($termDataObject);

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}


function termGetDataObject($item = false, $db = false) {
    global $config;

    if (!is_array($item) || !$db || !is_object($db)) {
        return false;
    }

    try {
        // Safely decode JSON fields with fallback to empty arrays
        $labelAlternative = json_decode($item["TermLabelAlternative"], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $labelAlternative = [];
        }

        $additionalInfo = json_decode($item["TermAdditionalInformation"], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $additionalInfo = [];
        }

        return [
            "type" => "term",
            "id" => $item["TermID"],
            "attributes" => [
                "type" => $item["TermType"],
                "label" => $item["TermLabel"],
                "labelAlternative" => $labelAlternative,
                "abstract" => $item["TermAbstract"],
                "thumbnailURI" => $item["TermThumbnailURI"],
                "thumbnailCreator" => $item["TermThumbnailCreator"],
                "thumbnailLicense" => $item["TermThumbnailLicense"],
                "websiteURI" => $item["TermWebsiteURI"],
                "embedURI" => $item["TermEmbedURI"],
                "additionalInformation" => $additionalInfo,
                "lastChanged" => $item["TermLastChanged"]
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/term/".$item["TermID"]
            ],
            "relationships" => [
                "media" => [
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/media?termID=".$item["TermID"]
                    ]
                ]
            ]
        ];

    } catch (Exception $e) {
        error_log("Error in termGetDataObject: " . $e->getMessage());
        return false;
    }
}


function termSearch($parameter, $db = false) {
    global $config;
    $outputLimit = 25;

    if (!$db) {
        $db = getApiDatabaseConnection();
        if (!is_object($db)) {
            return createApiErrorDatabaseConnection();
        }
    }

    // Validate and sanitize parameters
    $filteredParameters = filterAllowedSearchParams($parameter, 'term');
    
    // Validate label parameter
    if (isset($filteredParameters["label"])) {
        if (is_array($filteredParameters["label"])) {
            foreach ($filteredParameters["label"] as $label) {
                if (mb_strlen($label, "UTF-8") < 3) {
                    return createApiErrorResponse(
                        422,
                        1,
                        "messageErrorInvalidLengthTitle",
                        "messageErrorInvalidLengthDetailMin",
                        ["field" => "label", "minLength" => 3],
                        "[name='label']"
                    );
                }
            }
        } else if (mb_strlen($filteredParameters["label"], "UTF-8") < 3) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorInvalidLengthTitle",
                "messageErrorInvalidLengthDetailMin",
                ["field" => "label", "minLength" => 3],
                "[name='label']"
            );
        }
    }

    // Validate type parameter
    if (isset($filteredParameters["type"]) && mb_strlen($filteredParameters["type"], "UTF-8") < 2) {
        return createApiErrorResponse(
            422,
            2,
            "messageErrorInvalidLengthTitle",
            "messageErrorInvalidLengthDetailMin",
            ["field" => "type", "minLength" => 2],
            "[name='type']"
        );
    }

    // Validate wikidataID parameter
    if (isset($filteredParameters["wikidataID"]) && !preg_match("/(Q|P)\d+/i", $filteredParameters["wikidataID"])) {
        return createApiErrorInvalidID('term');
    }

    // Validate page parameter
    $page = isset($parameter["page"]) ? (int)$parameter["page"] : 1;
    if ($page < 1) {
        return createApiErrorResponse(
            422,
            4,
            "messageErrorInvalidValueTitle",
            "messageErrorPageNumberMustBePositiveDetail",
            ["field" => "page"],
            "[name='page']"
        );
    }

    try {
        $query = "SELECT * FROM ?n";
        $conditions = [];
        $params = [$config["platform"]["sql"]["tbl"]["Term"]];

        // Build search conditions
        if (isset($filteredParameters["label"])) {
            if (is_array($filteredParameters["label"])) {
                $labelConditions = [];
                foreach ($filteredParameters["label"] as $label) {
                    $labelConditions[] = "(MATCH(TermLabel, TermLabelAlternative, TermAbstract) AGAINST (?s IN BOOLEAN MODE) OR TermLabel LIKE ?s)";
                    $params[] = "*" . $label . "*";
                    $params[] = "%" . $label . "%";
                }
                $conditions[] = "(" . implode(" OR ", $labelConditions) . ")";
            } else {
                $conditions[] = "(MATCH(TermLabel, TermLabelAlternative, TermAbstract) AGAINST (?s IN BOOLEAN MODE) OR TermLabel LIKE ?s)";
                $params[] = "*" . $filteredParameters["label"] . "*";
                $params[] = "%" . $filteredParameters["label"] . "%";
            }
        }

        if (isset($filteredParameters["type"])) {
            $conditions[] = "TermType = ?s";
            $params[] = $filteredParameters["type"];
        }

        if (isset($filteredParameters["wikidataID"])) {
            $conditions[] = "TermID = ?s";
            $params[] = $filteredParameters["wikidataID"];
        }

        // Add WHERE clause if conditions exist
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        } else {
            return createApiErrorMissingParameter();
        }

        // Get total count for pagination
        $totalCount = count($db->getAll($query, ...$params));
        $totalPages = ceil($totalCount / $outputLimit);

        // Add pagination
        $query .= " LIMIT ?i, ?i";
        $params[] = ($page - 1) * $outputLimit;
        $params[] = $outputLimit;

        // Execute final query
        $findings = $db->getAll($query, ...$params);

        // Build response with exact same format as before
        return createApiSuccessResponse(
            array_map(function($item) use ($db) {
                return termGetDataObject($item, $db);
            }, $findings),
            [
                "page" => $page,
                "pageTotal" => ceil($totalCount / $outputLimit)
            ],
            [
                "self" => $config["dir"]["api"]."/search/terms?".getURLParameterFromArray($filteredParameters)
            ]
        );

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}


function termAdd($api_request, $db = false, $dbp = false) {
    global $config;

    // Extract reimportAffectedSessions and sourceEntitySuggestionID from $api_request
    $reimportAffectedSessions = isset($api_request['reimportAffectedSessions']) ? (bool)$api_request['reimportAffectedSessions'] : false;
    $sourceEntitySuggestionID = $api_request['sourceEntitySuggestionID'] ?? null;

    // Parameter validation
    if (empty($api_request["id"])) {
        return createApiErrorMissingParameter("id");
    }
    if (!validateWikidataID($api_request["id"])) {
        return createApiErrorInvalidID("Wikidata");
    }
    if (empty($api_request["type"])) {
        return createApiErrorMissingParameter("type");
    }
    // Add specific validation for term types if necessary
    if (empty($api_request["label"])) {
        return createApiErrorMissingParameter("label");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Return error from getApiDatabaseConnection
    }

    // Check if term already exists
    $existingTerm = $db->getOne("SELECT TermID FROM ?n WHERE TermID = ?s", $config["platform"]["sql"]["tbl"]["Term"], $api_request["id"]);
    if ($existingTerm) {
        return createApiErrorDuplicate("Term", "id");
    }

    $labelAlternative = !empty($api_request["labelAlternative"]) && is_array($api_request["labelAlternative"]) ? json_encode($api_request["labelAlternative"]) : json_encode([]);
    $additionalInformation = !empty($api_request["additionalinformation"]) ? json_encode(json_decode($api_request["additionalinformation"],true)) : json_encode([]);

    $sql = "INSERT INTO ?n SET TermID=?s, TermType=?s, TermLabel=?s, TermLabelAlternative=?s, TermAbstract=?s, TermThumbnailURI=?s, TermThumbnailCreator=?s, TermThumbnailLicense=?s, TermWebsiteURI=?s, TermEmbedURI=?s, TermAdditionalInformation=?s, TermLastChanged=NOW()";

    try {
        $db->query($sql,
            $config["platform"]["sql"]["tbl"]["Term"],
            $api_request["id"],
            $api_request["type"],
            $api_request["label"],
            $labelAlternative,
            $api_request["abstract"] ?? null,
            $api_request["thumbnailuri"] ?? null,
            $api_request["thumbnailcreator"] ?? null,
            $api_request["thumbnaillicense"] ?? null,
            $api_request["websiteuri"] ?? null,
            $api_request["embeduri"] ?? null,
            $additionalInformation
        );

        $termID = $api_request["id"]; // TermID is the wikidata ID provided
        $termDataResponse = termGetByID($termID, $db);

        $finalMeta = [];
        $finalData = null;

        if (isset($termDataResponse["meta"]["requestStatus"]) && $termDataResponse["meta"]["requestStatus"] == "success") {
            $finalMeta['entityAddStatus'] = 'success';
            $finalData = $termDataResponse["data"];

            $actualSuggestionInternalIDToProcess = $sourceEntitySuggestionID;

            if (empty($actualSuggestionInternalIDToProcess) && $reimportAffectedSessions && !empty($api_request['id'])) {
                $lookupResponse = apiV1([
                    "action" => "getItemsFromDB",
                    "itemType" => "entitySuggestion",
                    "id" => $api_request['id'], 
                    "idType" => "external",
                    "limit" => 1 
                ], $db);

                if (isset($lookupResponse["meta"]["requestStatus"]) && $lookupResponse["meta"]["requestStatus"] == "success" && !empty($lookupResponse["data"])) {
                    $foundSuggestion = $lookupResponse["data"];
                    if (isset($foundSuggestion['EntitysuggestionID'])) {
                        $actualSuggestionInternalIDToProcess = $foundSuggestion['EntitysuggestionID'];
                    }
                }
            }
            
            $postProcessingDetails = handleEntitySuggestionPostProcessing($reimportAffectedSessions, $actualSuggestionInternalIDToProcess, $db);
            
            $finalMeta['reimportStatus'] = $postProcessingDetails['reimportStatus'];
            $finalMeta['reimportSummary'] = $postProcessingDetails['reimportSummary'];
            $finalMeta['affectedSessions'] = $postProcessingDetails['affectedSessions'];
            $finalMeta['affectedSpeeches'] = $postProcessingDetails['affectedSpeeches'];
            $finalMeta['suggestionDeleteStatus'] = $postProcessingDetails['suggestionDeleteStatus'];

            if ($finalMeta['entityAddStatus'] === 'success' && 
                $postProcessingDetails['reimportStatus'] === 'success' && 
                $postProcessingDetails['suggestionDeleteStatus'] !== 'error') {
                $finalMeta['requestStatus'] = 'success';
            } else {
                $finalMeta['requestStatus'] = 'error';
                if ($postProcessingDetails['reimportStatus'] === 'error') {
                    return createApiErrorResponse(500, 1, "messageErrorItemCreationReimportFailedTitle", "messageErrorItemCreationReimportFailedDetail", ["itemType" => "Term"]);
                }
            }

        } else {
            $finalMeta['entityAddStatus'] = 'error';
            $finalMeta['reimportStatus'] = 'not_attempted';
            $finalMeta['suggestionDeleteStatus'] = 'not_attempted';
            $finalMeta['requestStatus'] = 'error';
            if (isset($termDataResponse["errors"])) {
                return $termDataResponse;
            }
            return createApiErrorResponse(500, 1, "messageErrorItemCreationRetrievalFailedTitle", "messageErrorItemCreationRetrievalFailedDetail", ["itemType" => "Term"]);
        }
        
        if ($finalMeta['requestStatus'] === 'error') {
            $errorTitle = "messageErrorPartialSuccessTitle";
            $errorDetail = "messageErrorPartialSuccessDetail";
            if($finalMeta['entityAddStatus'] === 'error'){
                 $errorTitle = "messageErrorItemCreationRetrievalFailedTitle";
                 $errorDetail = "messageErrorItemCreationRetrievalFailedDetail";
            }
            return createApiErrorResponse(500, 1, $errorTitle, $errorDetail, ["itemType" => "Term"], null, $finalMeta);
        }

        return createApiSuccessResponse($finalData, $finalMeta);

    } catch (Exception $e) {
        error_log("Error in termAdd: " . $e->getMessage());
        $errorMeta = [
            'requestStatus' => 'error',
            'entityAddStatus' => 'error',
            'reimportStatus' => 'not_attempted',
            'suggestionDeleteStatus' => 'not_attempted'
        ];
        return createApiErrorDatabaseError($e->getMessage(), $errorMeta);
    }
}



/**
 * Get an overview of terms from the database
 * 
 * @param string $id TermID or "all"
 * @param int $limit Maximum number of results to return
 * @param int $offset Offset for pagination
 * @param string|array $search Search term(s)
 * @param string $sort Sort field
 * @param string $order Sort order (ASC or DESC)
 * @param object $db Database connection
 * @return array Raw data array with 'total' count and 'data' results
 */
function termGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false) {
    global $config;

    // Validate database connection
    if (!$db) {
        $db = getApiDatabaseConnection();
        if (!is_object($db)) {
            return [
                "total" => 0,
                "data" => []
            ];
        }
    }

    try {
        // Base query for counting
        $baseQuery = "SELECT * FROM ?n";
        $params = [$config["platform"]["sql"]["tbl"]["Term"]];
        $conditions = [];

        // Handle ID filtering
        if ($id !== "all") {
            if (!preg_match("/(Q|P)\d+/i", $id)) {
                return createApiErrorInvalidID('term');
            }
            $conditions[] = "TermID = ?s";
            $params[] = $id;
        }

        // Handle search
        if ($search) {
            if (is_array($search)) {
                $searchConditions = [];
                foreach ($search as $term) {
                    if (mb_strlen($term, "UTF-8") >= 3) {
                        $searchConditions[] = "(MATCH(TermLabel, TermLabelAlternative, TermAbstract) AGAINST (?s IN BOOLEAN MODE) OR TermLabel LIKE ?s)";
                        $params[] = "*" . $term . "*";
                        $params[] = "%" . $term . "%";
                    }
                }
                if (!empty($searchConditions)) {
                    $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
                }
            } else if (mb_strlen($search, "UTF-8") >= 3) {
                $conditions[] = "(MATCH(TermLabel, TermLabelAlternative, TermAbstract) AGAINST (?s IN BOOLEAN MODE) OR TermLabel LIKE ?s)";
                $params[] = "*" . $search . "*";
                $params[] = "%" . $search . "%";
            }
        }

        // Add WHERE clause if conditions exist
        $whereClause = "";
        if (!empty($conditions)) {
            $whereClause = " WHERE " . implode(" AND ", $conditions);
        }

        // Get total count
        $countQuery = $baseQuery . $whereClause;
        $totalCount = count($db->getAll($countQuery, ...$params));

        // Construct the main query with selected fields
        $mainQuery = "SELECT 
            TermID,
            TermType,
            TermLabel,
            TermLabelAlternative,
            TermAbstract,
            TermThumbnailURI,
            TermThumbnailCreator,
            TermThumbnailLicense,
            TermWebsiteURI,
            TermEmbedURI,
            TermAdditionalInformation,
            TermLastChanged
            FROM ?n" . $whereClause;

        // Add sorting
        if ($sort) {
            $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
            $mainQuery .= " ORDER BY ?n " . $order;
            $params[] = $sort;
        }

        // Add pagination if limit is set
        if ($limit > 0) {
            $mainQuery .= " LIMIT ?i, ?i";
            $params[] = $offset;
            $params[] = $limit;
        }

        // Execute main query
        $items = $db->getAll($mainQuery, ...$params);

        // Process items to ensure proper JSON encoding of certain fields
        foreach ($items as &$item) {
            // Ensure TermLabelAlternative is properly JSON encoded
            if (isset($item['TermLabelAlternative'])) {
                $labelAlt = json_decode($item['TermLabelAlternative'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['TermLabelAlternative'] = json_encode($labelAlt);
                }
            }
            
            // Ensure TermAdditionalInformation is properly JSON encoded
            if (isset($item['TermAdditionalInformation'])) {
                $addInfo = json_decode($item['TermAdditionalInformation'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $item['TermAdditionalInformation'] = json_encode($addInfo);
                }
            }
        }

        return [
            "total" => $totalCount,
            "data" => $items
        ];

    } catch (Exception $e) {
        error_log("Error in termGetItemsFromDB: " . $e->getMessage());
        return [
            "total" => 0,
            "data" => []
        ];
    }
}

function termChange($parameter) {
    global $config;

    if (!isset($parameter["id"])) {
        return createApiErrorMissingParameter("id");
    }

    // Validate ID format
    if (!preg_match("/(Q|P)\d+/i", $parameter["id"])) {
        return createApiErrorInvalidID("term");
    }

    $db = getApiDatabaseConnection();
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        // Check if term exists
        $existingTerm = $db->getRow("SELECT * FROM ?n WHERE TermID = ?s",
            $config["platform"]["sql"]["tbl"]["Term"],
            $parameter["id"]
        );

        if (!$existingTerm) {
            return createApiErrorNotFound("term");
        }

        // Define allowed fields and their validation rules
        $allowedFields = [
            "type" => [
                "min" => 2,
                "max" => 50,
                "required" => true
            ],
            "label" => [
                "min" => 2,
                "max" => 255,
                "required" => true
            ],
            "abstract" => [
                "min" => 5,
                "max" => 2000,
                "required" => true
            ],
            "labelAlternative" => [
                "type" => "array",
                "required" => false
            ],
            "thumbnailURI" => [
                "type" => "url",
                "required" => false
            ],
            "thumbnailCreator" => [
                "max" => 255,
                "required" => false
            ],
            "thumbnailLicense" => [
                "max" => 255,
                "required" => false
            ],
            "websiteURI" => [
                "type" => "url",
                "required" => false
            ],
            "embedURI" => [
                "type" => "url",
                "required" => false
            ],
            "additionalInformation" => [
                "type" => "array",
                "required" => false
            ]
        ];

        // Filter and validate parameters
        $updateData = [];
        foreach ($allowedFields as $field => $rules) {
            $dbField = "Term" . ucfirst($field);
            
            if (isset($parameter[$field])) {
                // Validate required fields
                if ($rules["required"] && (trim($parameter[$field]) === "")) {
                    return createApiErrorResponse(
                        422,
                        1,
                        "messageErrorFieldRequiredTitle",
                        "messageErrorFieldRequiredDetail",
                        ["field" => $field],
                        "[name='$field']"
                    );
                }

                // Validate string lengths
                if (isset($rules["min"]) && mb_strlen($parameter[$field], "UTF-8") < $rules["min"]) {
                    return createApiErrorResponse(
                        422,
                        1,
                        "messageErrorInvalidLengthTitle",
                        "messageErrorInvalidLengthDetailMin",
                        ["field" => $field, "minLength" => $rules["min"]],
                        "[name='$field']"
                    );
                }

                if (isset($rules["max"]) && mb_strlen($parameter[$field], "UTF-8") > $rules["max"]) {
                    return createApiErrorResponse(
                        422,
                        1,
                        "messageErrorInvalidLengthTitle",
                        "messageErrorInvalidLengthDetailMax",
                        ["field" => $field, "maxLength" => $rules["max"]],
                        "[name='$field']"
                    );
                }

                // Validate arrays
                if (isset($rules["type"]) && $rules["type"] === "array") {
                    if (!is_array($parameter[$field])) {
                        return createApiErrorResponse(
                            422,
                            1,
                            "messageErrorInvalidFormatTitle",
                            "messageErrorInvalidFormatDetail",
                            ["field" => $field, "expected" => "array"],
                            "[name='$field']"
                        );
                    }
                    $updateData[$dbField] = json_encode($parameter[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                // Validate URLs
                else if (isset($rules["type"]) && $rules["type"] === "url") {
                    if ($parameter[$field] !== "" && !filter_var($parameter[$field], FILTER_VALIDATE_URL)) {
                        return createApiErrorResponse(
                            422,
                            1,
                            "messageErrorInvalidURLTitle",
                            "messageErrorInvalidURLDetail",
                            ["field" => $field],
                            "[name='$field']"
                        );
                    }
                    $updateData[$dbField] = $parameter[$field] ?: null;
                }
                // Regular fields
                else {
                    $updateData[$dbField] = $parameter[$field];
                }
            }
        }

        // If no valid fields to update
        if (empty($updateData)) {
            return createApiErrorResponse(
                422,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorNoValidFieldsToUpdateDetail"
            );
        }

        // Add last changed timestamp
        $updateData["TermLastChanged"] = date("Y-m-d H:i:s");

        // Update term
        $result = $db->query("UPDATE ?n SET ?u WHERE TermID = ?s",
            $config["platform"]["sql"]["tbl"]["Term"],
            $updateData,
            $parameter["id"]
        );

        if (!$result) {
            return createApiErrorResponse(500, 1, "messageErrorDatabaseGeneric", "messageErrorTermUpdateFailedDetail");
        }

        // Return success response with id
        return createApiSuccessResponse(null, [
            "id" => $parameter["id"]
        ]);

    } catch (Exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

?>
