<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * @param string $id String of OrganisationID (= WikidataID)
 * @return array Original format: ["meta"]["requestStatus"] and ["data"] or ["errors"]
 */
function organisationGetByID($id = false) {
    global $config;

    if (!$id || !preg_match("/(Q|P)\d+/i", $id)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingOrInvalidIDDetail",
            ["parameter" => "id"]
        );
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s",
            $config["platform"]["sql"]["tbl"]["Organisation"],
            $id
        );

        if (!$item) {
            return createApiErrorNotFound("Organisation");
        }

        $data = [
            "type" => "organisation",
            "id" => $item["OrganisationID"],
            "attributes" => [
                "type" => $item["OrganisationType"],
                "label" => $item["OrganisationLabel"],
                "labelAlternative" => json_decode($item["OrganisationLabelAlternative"], true),
                "abstract" => $item["OrganisationAbstract"],
                "thumbnailURI" => $item["OrganisationThumbnailURI"],
                "thumbnailCreator" => $item["OrganisationThumbnailCreator"],
                "thumbnailLicense" => $item["OrganisationThumbnailLicense"],
                "embedURI" => $item["OrganisationEmbedURI"],
                "websiteURI" => $item["OrganisationWebsiteURI"],
                "socialMediaIDs" => json_decode($item["OrganisationSocialMediaIDs"], true),
                "color" => $item["OrganisationColor"],
                "additionalInformation" => json_decode($item["OrganisationAdditionalInformation"], true),
                "lastChanged" => $item["OrganisationLastChanged"]
            ]
        ];

        // Add links and relationships directly to the $data array
        $data["links"] = [
            "self" => $config["dir"]["api"]."/".$data["type"]."/".$data["id"]
        ];

        $data["relationships"] = [
            "media" => [
                "links" => [
                    "self" => $config["dir"]["api"]."/"."search/media?organisationID=".$data["id"]
                ]
            ],
            "people" => [
                "links" => [
                    "self" => $config["dir"]["api"]."/"."search/people?organisationID=".$data["id"]
                ]
            ]
        ];

        return createApiSuccessResponse($data, null, null, null);

    } catch (exception $e) {
        return createApiErrorDatabaseError();
    }
}

function organisationGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "organisation";
        $return["id"] = $item["OrganisationID"];
        $return["attributes"]["type"] = $item["OrganisationType"];
        $return["attributes"]["label"] = $item["OrganisationLabel"];
        $return["attributes"]["labelAlternative"] = json_decode($item["OrganisationLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["OrganisationAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["OrganisationThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = $item["OrganisationThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["OrganisationThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["OrganisationEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["OrganisationWebsiteURI"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["OrganisationSocialMediaIDs"],true);
        $return["attributes"]["color"] = $item["OrganisationColor"];
        $return["attributes"]["additionalInformation"] = json_decode($item["OrganisationAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["OrganisationLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/"."search/media?organisationID=".$return["id"];
        $return["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/"."search/people?organisationID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;

}

/**
 * Search for organisations with various filters
 * 
 * @param array $parameter Search parameters
 * @param bool $noLimit Whether to remove result limit (max 10000)
 * @return array Original format: ["meta"]["requestStatus"], ["data"] array, and ["meta"]["page"], ["meta"]["pageTotal"]
 */
function organisationSearch($parameter, $noLimit = false) {
    global $config;

    $outputLimit = ($noLimit ? 10000 : 25);
    
    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    $filteredParameters = filterAllowedSearchParams($parameter, 'organisation');

    // Validate name parameter
    if (isset($filteredParameters["name"])) {
        if (is_array($filteredParameters["name"])) {
            foreach ($filteredParameters["name"] as $tmpName) {
                if (mb_strlen($tmpName, "UTF-8") < 3) {
                    return createApiErrorResponse(
                        400,
                        1,
                        "messageErrorSearchLabelTooShortTitle",
                        "messageErrorSearchLabelTooShortDetail",
                        ["minLength" => 3]
                    );
                }
            }
        } else if (mb_strlen($filteredParameters["name"], "UTF-8") < 3) {
            return createApiErrorResponse(
                400,
                1,
                "messageErrorSearchLabelTooShortTitle",
                "messageErrorSearchLabelTooShortDetail",
                ["minLength" => 3]
            );
        }
    }

    try {
        // Build base query
        $query = "SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"];
        $conditions = ["1"];

        // Add search conditions
        if (isset($filteredParameters["name"])) {
            if (is_array($filteredParameters["name"])) {
                $nameConditions = [];
                foreach ($filteredParameters["name"] as $name) {
                    $nameConditions[] = "LOWER(OrganisationLabel) LIKE LOWER('%" . $db->escape($name) . "%')";
                }
                $conditions[] = "(" . implode(" OR ", $nameConditions) . ")";
            } else {
                $conditions[] = "LOWER(OrganisationLabel) LIKE LOWER('%" . $db->escape($filteredParameters["name"]) . "%')";
            }
        }

        if (isset($filteredParameters["type"])) {
            if (is_array($filteredParameters["type"])) {
                $typeConditions = [];
                foreach ($filteredParameters["type"] as $type) {
                    $typeConditions[] = "OrganisationType = '" . $db->escape($type) . "'";
                }
                $conditions[] = "(" . implode(" OR ", $typeConditions) . ")";
            } else {
                $conditions[] = "OrganisationType = '" . $db->escape($filteredParameters["type"]) . "'";
            }
        }

        // Build final query
        $query = $db->parse("?p WHERE ?p", $query, implode(" AND ", $conditions));
        
        // Get total count
        $totalCount = count($db->getAll($query));
        
        // Add pagination
        $page = isset($parameter["page"]) ? (int)$parameter["page"] : 1;
        $offset = ($page - 1) * $outputLimit;
        $query .= $db->parse(" LIMIT ?i, ?i", $offset, $outputLimit);

        // Execute search
        $findings = $db->getAll($query);
        
        // Format results
        $data = array_map(function($finding) use ($db) {
            return organisationGetDataObject($finding, $db);
        }, $findings);

        $links = [
            "self" => $config["dir"]["api"]."/search/organisations?".getURLParameterFromArray($filteredParameters)
        ];

        return createApiSuccessResponse(
            $data,
            [
                "page" => $page,
                "pageTotal" => ceil($totalCount / $outputLimit)
            ],
            $links
        );

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Add a new organisation to the database
 * 
 * @param array $item Organisation data including ID, type, label, and other attributes
 * @return array Response with original format: ["meta"]["requestStatus"] and ["meta"]["itemID"] or ["errors"]
 */
function organisationAdd($item) {
    global $config;

    // Validate required fields
    if ((!$item["id"]) || (!preg_match("/(Q|P)\d+/i", $item["id"]))) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingOrInvalidIDDetail",
            ["parameter" => "id"],
            "id"
        );
    }

    if (!$item["type"]) {
        return createApiErrorMissingParameter('type', 'type');
    }

    if (!$item["label"]) {
        return createApiErrorMissingParameter('label', 'label');
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    // Check if organisation already exists
    $itemTmp = $db->getRow("SELECT OrganisationID FROM ?n WHERE OrganisationID=?s",
        $config["platform"]["sql"]["tbl"]["Organisation"],
        $item["id"]
    );

    if ($itemTmp) {
        return createApiErrorDuplicate("Organisation", "error_info");
    }

    try {
        // Process social media IDs
        $socialMedia = array();
        if ($item["socialMediaIDsLabel"]) {
            foreach ($item["socialMediaIDsLabel"] as $k => $v) {
                if ($v && $item["socialMediaIDsValue"][$k]) {
                    $socialMedia[] = array("label" => $v, "id" => $item["socialMediaIDsValue"][$k]);
                }
            }
        }

        // Process alternative labels
        $labelAlternative = array();
        if (is_array($item["labelAlternative"])) {
            foreach ($item["labelAlternative"] as $v) {
                if ($v) {
                    $labelAlternative[] = $v;
                }
            }
        }

        // Insert organisation
        $db->query("INSERT INTO ?n SET ?u",
            $config["platform"]["sql"]["tbl"]["Organisation"],
            array(
                "OrganisationID" => $item["id"],
                "OrganisationType" => $item["type"],
                "OrganisationLabel" => $item["label"],
                "OrganisationLabelAlternative" => (is_array($labelAlternative) ? 
                    json_encode($labelAlternative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 
                    "[".$item["labelAlternative"]."]"),
                "OrganisationAbstract" => $item["abstract"],
                "OrganisationThumbnailURI" => $item["thumbnailuri"],
                "OrganisationThumbnailCreator" => $item["thumbnailcreator"],
                "OrganisationThumbnailLicense" => $item["thumbnaillicense"],
                "OrganisationEmbedURI" => $item["embeduri"],
                "OrganisationWebsiteURI" => $item["websiteuri"],
                "OrganisationSocialMediaIDs" => json_encode($socialMedia),
                "OrganisationColor" => $item["color"],
                "OrganisationAdditionalInformation" => (is_array($item["additionalinformation"]) ? 
                    json_encode($item["additionalinformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 
                    $item["additionalinformation"])
            )
        );

        return createApiSuccessResponse(null, ["itemID" => $db->insertId()]);

    } catch (exception $e) {
        return createApiErrorResponse(
            422,
            2,
            "messageErrorDatabaseGeneric",
            "messageErrorOrganisationAddFailedDetail",
            ["exception_message" => $e->getMessage()],
            "error_info"
        );
    }
}

/**
 * Get an overview of organisations
 * 
 * @param string $id OrganisationID (Wikidata ID) or "all"
 * @param int $limit Limit the number of results
 * @param int $offset Offset for pagination
 * @param string $search Search term
 * @param string $sort Sort field
 * @param string $order Sort order (ASC or DESC)
 * @param string $type Filter by organisation type
 * @return array Raw data array with 'total' count and 'data' results
 */
function organisationGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $type = false) {
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
            if (!preg_match("/(Q|P)\d+/i", $id)) {
                return array(
                    "total" => 0,
                    "data" => array()
                );
            }
            $conditions[] = $db->parse("OrganisationID=?s", $id);
        }
        
        if (!empty($search)) {
            $conditions[] = $db->parse("(OrganisationLabel LIKE ?s OR OrganisationLabelAlternative LIKE ?s)", 
                "%".$search."%", 
                "%".$search."%"
            );
        }
        
        if (!empty($type)) {
            $conditions[] = $db->parse("OrganisationType=?s", $type);
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        // Get total count
        $totalCount = $db->getOne("SELECT COUNT(*) 
                                  FROM ?n 
                                  WHERE ?p",
                                  $config["platform"]["sql"]["tbl"]["Organisation"],
                                  $whereClause);
        
        // Add sorting
        if (!empty($sort)) {
            $whereClause .= $db->parse(" ORDER BY ?n ".$order, $sort);
        }
        
        // Add pagination
        if ($limit > 0) {
            $whereClause .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
        }
        
        // Get results
        $items = $db->getAll("SELECT *
                             FROM ?n 
                             WHERE ?p",
                             $config["platform"]["sql"]["tbl"]["Organisation"],
                             $whereClause);
        
        return array(
            "total" => (int)$totalCount,
            "data" => $items
        );
        
    } catch (exception $e) {
        return array(
            "total" => 0,
            "data" => array()
        );
    }
}

/**
 * Update an organisation's information
 * 
 * @param array $parameter Update parameters including organisation ID and fields to update
 * @return array API response in JSON:API format
 */
function organisationChange($parameter) {
    global $config;

    if (!isset($parameter["id"])) {
        return createApiErrorMissingParameter("id");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Check if organisation exists
    $organisation = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["Organisation"],
        $parameter["id"]
    );
    if (!$organisation) {
        return createApiErrorNotFound("Organisation");
    }

    // Define allowed parameters
    $allowedParams = array(
        "OrganisationType", "OrganisationLabel", "OrganisationLabelAlternative", 
        "OrganisationAbstract", "OrganisationThumbnailURI", "OrganisationThumbnailCreator", 
        "OrganisationThumbnailLicense", "OrganisationEmbedURI", "OrganisationWebsiteURI", 
        "OrganisationSocialMediaIDs", "OrganisationColor", "OrganisationAdditionalInformation"
    );

    // Filter parameters
    $params = $db->filterArray($parameter, $allowedParams);
    $updateParams = array();

    try {
        // Process each parameter
        foreach ($params as $key => $value) {
            if ($key === "OrganisationLabelAlternative" || 
                $key === "OrganisationSocialMediaIDs" || 
                $key === "OrganisationAdditionalInformation") {
                // Handle JSON fields
                if (is_array($value)) {
                    $updateParams[] = $db->parse("?n=?s", 
                        $key, 
                        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
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
                "messageErrorNoValidFieldsToUpdateDetail"
            );
        }

        // Add last changed timestamp
        $updateParams[] = "OrganisationLastChanged=CURRENT_TIMESTAMP()";

        // Execute update
        $result = $db->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE OrganisationID=?s", 
            $config["platform"]["sql"]["tbl"]["Organisation"], 
            $parameter["id"]
        );

        if (!$result) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorDatabaseGeneric",
                "messageErrorOrganisationUpdateFailedDetail"
            );
        }

        return createApiSuccessResponse(null, ["message" => "Organisation updated successfully"]);

    } catch (exception $e) {
        return createApiErrorDatabaseError();
    }
}
?>
