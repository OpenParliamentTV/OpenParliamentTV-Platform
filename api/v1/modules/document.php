<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");




/**
 * @param string $id documentID
 * @return array
 */
function documentGetByID($id = false) {
    global $config;

    if (!$id) {
        return createApiErrorMissingParameter('id');
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i",
            $config["platform"]["sql"]["tbl"]["Document"],
            (int)$id
        );

        if ($item) {
            $documentData = documentGetDataObject($item, $db);
            if (!$documentData) {
                return createApiErrorResponse(
                    500,
                    1,
                    "messageErrorDataTransformationTitle",
                    "messageErrorDataTransformationDetail"
                );
            }
            return createApiSuccessResponse($documentData);
        } else {
            return createApiErrorNotFound('document');
        }

    } catch (exception $e) {
        return createApiErrorDatabaseConnection();
    }
}

function documentGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "document";
        $return["id"] = $item["DocumentID"];
        $return["attributes"]["type"] = $item["DocumentType"];
        $return["attributes"]["wikidataID"] = $item["DocumentWikidataID"];
        $return["attributes"]["label"] = str_replace(array("\r","\n"), " ", $item["DocumentLabel"]);
        //$return["attributes"]["labelAlternative"] = str_replace(array("\r","\n"), " ", $item["DocumentLabelAlternative"]);
        $return["attributes"]["labelAlternative"] = json_decode($item["DocumentLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["DocumentAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["DocumentThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = $item["DocumentThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["DocumentThumbnailLicense"];
        $return["attributes"]["sourceURI"] = $item["DocumentSourceURI"];
        $return["attributes"]["embedURI"] = $item["DocumentEmbedURI"];
        $return["attributes"]["additionalInformation"] = json_decode($item["DocumentAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["DocumentLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?documentID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;
}

function documentSearch($parameter, $db = false) {
    global $config;

    $outputLimit = 25;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    $filteredParameters = filterAllowedSearchParams($parameter, 'document');

    // Validate label parameter
    if (isset($filteredParameters["label"])) {
        if (is_array($filteredParameters["label"])) {
            foreach ($filteredParameters["label"] as $tmpNameID) {
                if (mb_strlen($tmpNameID, "UTF-8") < 3) {
                    return createApiErrorResponse(
                        400,
                        1,
                        "messageErrorSearchLabelTooShortTitle",
                        "messageErrorSearchLabelTooShortDetail",
                        ["minLength" => 3]
                    );
                }
            }
        } else if (mb_strlen($filteredParameters["label"], "UTF-8") < 3) {
            return createApiErrorResponse(
                400,
                1,
                "messageErrorSearchLabelTooShortTitle",
                "messageErrorSearchLabelTooShortDetail",
                ["minLength" => 3]
            );
        }
    }

    // Validate type parameter
    if (isset($filteredParameters["type"]) && mb_strlen($filteredParameters["type"], "UTF-8") < 2) {
        return createApiErrorResponse(
            400,
            2,
            "messageErrorSearchTypeTooShortTitle",
            "messageErrorSearchTypeTooShortDetail",
            ["minLength" => 2]
        );
    }

    // Validate wikidataID parameter
    if (isset($filteredParameters["wikidataID"])) {
        if (!validateWikidataID($filteredParameters["wikidataID"])) {
            return createApiErrorInvalidID("Wikidata");
        }
    }

    try {
        $query = "SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Document"];
        $conditions = [];

        foreach ($filteredParameters as $k => $para) {
            if ($k == "label") {
                if (is_array($para)) {
                    $tmpStringArray = array_map(function($tmppara) use ($db) {
                        return $db->parse("((MATCH(DocumentLabel, DocumentLabelAlternative, DocumentAbstract) AGAINST (?s IN BOOLEAN MODE)) OR (DocumentLabel LIKE ?s))", 
                            "*".$tmppara."*", 
                            "%".$tmppara."%"
                        );
                    }, $para);
                    $conditions[] = "(" . implode(" OR ", $tmpStringArray) . ")";
                } else {
                    $conditions[] = $db->parse("(MATCH(DocumentLabel, DocumentLabelAlternative, DocumentAbstract) AGAINST (?s IN BOOLEAN MODE) OR (DocumentLabel LIKE ?s))", 
                        "*".$para."*", 
                        "%".$para."%"
                    );
                }
            }

            if ($k == "type") {
                $conditions[] = $db->parse("DocumentType = ?s", $para);
            }

            if ($k == "wikidataID") {
                $conditions[] = $db->parse("DocumentWikidataID = ?s", $para);
            }
        }

        if (empty($conditions)) {
            return createApiErrorResponse(
                404,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorNotEnoughSearchParametersDetail"
            );
        }

        $query .= " WHERE " . implode(" AND ", $conditions);
        $totalCount = count($db->getAll($query));

        $page = isset($parameter["page"]) ? (int)$parameter["page"] : 1;
        $query .= $db->parse(" LIMIT ?i, ?i", ($page-1)*$outputLimit, $outputLimit);
        
        $findings = $db->getAll($query);

        $data = array_map(function($finding) use ($db) {
            $itemObj = documentGetDataObject($finding, $db);
            unset($itemObj["meta"]);
            return $itemObj;
        }, $findings);

        return createApiSuccessResponse(
            $data,
            [
                "requestStatus" => "success",
                "page" => $page,
                "pageTotal" => ceil($totalCount/$outputLimit)
            ],
            [
                "self" => $config["dir"]["api"]."/search/documents?".getURLParameterFromArray($filteredParameters)
            ]
        );

    } catch (exception $e) {
        return createApiErrorResponse(
            422,
            2,
            "messageErrorDatabaseGeneric",
            $e->getMessage()
        );
    }
}


function documentAdd($api_request, $db = false, $dbp = false /* dbp not used */) {
    global $config;

    // Parameter validation
    if (empty($api_request["id"])) {
        // DocumentID is auto-increment, but we need a wikidata ID for entity linking if provided
        // However, the main ID for documents in the DB is DocumentID (auto-increment)
        // If sourceEntitySuggestionExternalID is present, it implies a wikidata ID was the source
        // For now, we will not enforce 'id' (meaning wikidata ID) for documents as it's optional in the table
    }
    if (!empty($api_request["id"]) && !validateWikidataID($api_request["id"])) {
        return createApiErrorInvalidID("Wikidata (optional)");
    }
    if (empty($api_request["type"])) {
        return createApiErrorMissingParameter("type");
    }
    // Add specific validation for document types if necessary
    if (empty($api_request["label"])) {
        return createApiErrorMissingParameter("label");
    }
    if (empty($api_request["sourceuri"])) {
        return createApiErrorMissingParameter("sourceuri");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Return error from getApiDatabaseConnection
    }

    // Unlike person/org, DocumentID is auto-increment. WikidataID is optional.
    // We don't check for duplicates based on wikidataID for documents in the same way.
    // Duplicates might be based on label or sourceuri, but that logic is not present here yet.

    $labelAlternative = !empty($api_request["labelAlternative"]) && is_array($api_request["labelAlternative"]) ? json_encode($api_request["labelAlternative"]) : json_encode([]);
    $additionalInformation = !empty($api_request["additionalinformation"]) ? json_encode(json_decode($api_request["additionalinformation"],true)) : json_encode([]);

    $sql = "INSERT INTO ?n SET DocumentWikidataID=?s, DocumentType=?s, DocumentLabel=?s, DocumentLabelAlternative=?s, DocumentAbstract=?s, DocumentThumbnailURI=?s, DocumentThumbnailCreator=?s, DocumentThumbnailLicense=?s, DocumentSourceURI=?s, DocumentEmbedURI=?s, DocumentAdditionalInformation=?s, DocumentLastChanged=NOW()";

    try {
        $db->query($sql,
            $config["platform"]["sql"]["tbl"]["Document"],
            $api_request["id"] ?? null, // wikidataID, can be null
            $api_request["type"],
            $api_request["label"],
            $labelAlternative,
            $api_request["abstract"] ?? null,
            $api_request["thumbnailuri"] ?? null,
            $api_request["thumbnailcreator"] ?? null,
            $api_request["thumbnaillicense"] ?? null,
            $api_request["sourceuri"],
            $api_request["embeduri"] ?? null,
            $additionalInformation
        );

        $documentID = $db->insertId(); // Get the auto-incremented DocumentID
        if (!$documentID) {
            return createApiErrorDatabaseError("Failed to retrieve last insert ID for Document.");
        }

        $documentData = documentGetByID($documentID); // Fetch newly created document by its internal ID

        if (isset($documentData["meta"]["requestStatus"]) && $documentData["meta"]["requestStatus"] == "success") {
             // Augment with entity suggestion details if applicable
            // The platform DB connection is already $db from getApiDatabaseConnection
            $finalResponse = augmentResponseWithEntitySuggestionDetails($documentData, $api_request, $db);
            return $finalResponse;
        } else {
            return createApiErrorResponse(500, 1, "messageErrorItemCreationRetrievalFailedTitle", "messageErrorItemCreationRetrievalFailedDetail", ["itemType" => "Document"]);
        }

    } catch (Exception $e) {
        error_log("Error in documentAdd: " . $e->getMessage());
        return createApiErrorDatabaseError($e->getMessage());
    }
}

function documentGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false) {
    global $config;
    
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return array(
            "total" => 0,
            "data" => array()
        );
    }
    
    try {
        // Build query conditions
        $conditions = [];
        
        if ($id === "all") {
            $conditions[] = "1";
        } else {
            $conditions[] = $db->parse("DocumentID=?i", (int)$id);
        }
        
        if (!empty($search)) {
            $conditions[] = $db->parse("(DocumentLabel LIKE ?s OR DocumentLabelAlternative LIKE ?s OR DocumentID LIKE ?s)", 
                "%".$search."%", 
                "%".$search."%",
                "%".$search."%"
            );
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        // Get total count
        $totalCount = $db->getOne("SELECT COUNT(DocumentID) as count FROM ?n WHERE ?p",
            $config["platform"]["sql"]["tbl"]["Document"],
            $whereClause
        );
        
        // Add sorting
        if (!empty($sort)) {
            $whereClause .= $db->parse(" ORDER BY ?n ".$order, $sort);
        }
        
        // Add pagination
        if ($limit > 0) {
            $whereClause .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
        }
        
        // Get results
        $items = $db->getAll("SELECT
            DocumentID,
            DocumentType,
            DocumentLabel,
            DocumentLabelAlternative,
            DocumentWikidataID,
            DocumentLastChanged
            FROM ?n 
            WHERE ?p",
            $config["platform"]["sql"]["tbl"]["Document"],
            $whereClause
        );
        
        return array(
            "total" => (int)$totalCount,
            "data" => $items
        );
        
    } catch (exception $e) {
        error_log("Error in documentGetItemsFromDB: " . $e->getMessage());
        return array(
            "total" => 0,
            "data" => array()
        );
    }
}

function documentChange($parameter) {
    global $config;

    if (!$parameter["id"]) {
        return createApiErrorMissingParameter('id');
    }

    $numericDocID = (int)$parameter["id"]; // Convert to int

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    // Check if document exists
    $document = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i", 
        $config["platform"]["sql"]["tbl"]["Document"], 
        $numericDocID
    );
    
    if (!$document) {
        return createApiErrorNotFound("Document");
    }

    // Define allowed parameters
    $allowedParams = array(
        "DocumentType", "DocumentWikidataID", "DocumentLabel", "DocumentLabelAlternative",
        "DocumentAbstract", "DocumentThumbnailURI", "DocumentThumbnailCreator",
        "DocumentThumbnailLicense", "DocumentSourceURI", "DocumentEmbedURI",
        "DocumentAdditionalInformation"
    );

    // Filter parameters
    $params = $db->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "DocumentLabelAlternative" || $key === "DocumentAdditionalInformation") {
            // Handle JSON fields
            if (is_array($value)) {
                $updateParams[] = $db->parse("?n=?s", $key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } else {
            $updateParams[] = $db->parse("?n=?s", $key, $value);
        }
    }

    if (empty($updateParams)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorNoParameters"
        );
    }

    // Add last changed timestamp
    $updateParams[] = "DocumentLastChanged=CURRENT_TIMESTAMP()";

    try {
        // Execute update
        $result = $db->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE DocumentID=?i",
            $config["platform"]["sql"]["tbl"]["Document"],
            $numericDocID
        );

        if ($result) {
            return createApiSuccessResponse();
        } else {
            return createApiErrorResponse(
                503,
                1,
                "messageErrorDatabaseGeneric",
                "messageErrorUpdateNoRowsAffected"
            );
        }
    } catch (exception $e) {
        return createApiErrorResponse(
            503,
            1,
            "messageErrorDatabaseGeneric",
            "Error updating document data: " . $e->getMessage()
        );
    }
}

?>
