<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/entities/organisation.php");
require_once (__DIR__."/../../../modules/images/image-cache.php");

/**
 * Envelope wrapper over organisationGetNode() (modules/entities/organisation.php).
 */
function organisationGetByID($id = false) {
    try {
        $node = organisationGetNode($id);
    } catch (OptvInvalidIdException $e) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingOrInvalidIDDetail",
            ["parameter" => "id"]
        );
    } catch (OptvEntityException $e) {
        return apiErrorDatabaseConnection();
    } catch (Exception $e) {
        return apiErrorDatabaseError();
    }

    if ($node === null) {
        return apiErrorNotFound("Organisation");
    }

    return apiSuccessResponse($node, null, null, null);
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

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return apiErrorDatabaseConnection();
    }

    $filteredParameters = filterAllowedSearchParams($parameter, 'organisation');

    $outputLimit = 25;
    if ($noLimit) {
        $outputLimit = 10000;
    } else if (isset($filteredParameters["limit"]) && is_numeric($filteredParameters["limit"]) && (int)$filteredParameters["limit"] > 0) {
        $outputLimit = min(100, (int)$filteredParameters["limit"]);
    }

    // Validate page parameter. A non-positive page would become a negative
    // LIMIT offset, so it is rejected rather than passed to the database.
    $page = isset($parameter["page"]) ? (int)$parameter["page"] : 1;
    if ($page < 1) {
        return apiErrorResponse(
            422,
            4,
            "messageErrorInvalidValueTitle",
            "messageErrorPageNumberMustBePositiveDetail",
            ["field" => "page"],
            "[name='page']"
        );
    }

    // Validate name parameter
    if (isset($filteredParameters["name"])) {
        if (is_array($filteredParameters["name"])) {
            foreach ($filteredParameters["name"] as $tmpName) {
                if (mb_strlen($tmpName, "UTF-8") < 3) {
                    return apiErrorResponse(
                        400,
                        1,
                        "messageErrorSearchLabelTooShortTitle",
                        "messageErrorSearchLabelTooShortDetail",
                        ["minLength" => 3]
                    );
                }
            }
        } else if (mb_strlen($filteredParameters["name"], "UTF-8") < 3) {
            return apiErrorResponse(
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
                    $nameConditions[] = $db->parse("LOWER(OrganisationLabel) LIKE LOWER(?s)", "%" . $name . "%");
                }
                $conditions[] = "(" . implode(" OR ", $nameConditions) . ")";
            } else {
                $conditions[] = $db->parse("LOWER(OrganisationLabel) LIKE LOWER(?s)", "%" . $filteredParameters["name"] . "%");
            }
        }

        if (isset($filteredParameters["type"])) {
            if (is_array($filteredParameters["type"])) {
                $typeConditions = [];
                foreach ($filteredParameters["type"] as $type) {
                    $typeConditions[] = $db->parse("OrganisationType = ?s", $type);
                }
                $conditions[] = "(" . implode(" OR ", $typeConditions) . ")";
            } else {
                $conditions[] = $db->parse("OrganisationType = ?s", $filteredParameters["type"]);
            }
        }

        if (isset($filteredParameters["filterable"])) {
            $conditions[] = $db->parse("OrganisationFilterable = ?i", $filteredParameters["filterable"]);
        }

        // Build final query
        $query = $db->parse("?p WHERE ?p", $query, implode(" AND ", $conditions));
        
        // Get total count
        $totalCount = count($db->getAll($query));
        
        // Add pagination
        $offset = ($page - 1) * $outputLimit;
        // OrganisationOrder is not unique, so the ID tiebreak is what keeps paging stable.
        $query .= $db->parse(" ORDER BY OrganisationFilterable DESC, OrganisationOrder ASC, OrganisationID ASC LIMIT ?i, ?i", $offset, $outputLimit);

        // Execute search
        $findings = $db->getAll($query);
        
        // Format results
        $data = array_map(function($finding) use ($db) {
            return organisationBuildNode($finding, $db);
        }, $findings);

        $links = apiPaginationLinks(
            $config["dir"]["api"]."/search/organisations",
            $filteredParameters,
            $page,
            ceil($totalCount / $outputLimit)
        );

        return apiSuccessResponse(
            $data,
            [
                "page" => $page,
                "pageTotal" => ceil($totalCount / $outputLimit)
            ],
            $links
        );

    } catch (exception $e) {
        return apiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Add a new organisation to the database
 * 
 * @param array $item Organisation data including ID, type, label, and other attributes
 * @return array Response with original format: ["meta"]["requestStatus"] and ["meta"]["itemID"] or ["errors"]
 */
function organisationAdd($api_request, $db = false, $dbp = false) {
    global $config;

    // Validate required fields
    if (empty($api_request["id"])) {
        return apiErrorMissingParameter("id");
    }
    if (!optvValidateWikidataId($api_request["id"])) {
        return apiErrorInvalidId("Wikidata");
    }
    if (empty($api_request["type"])) {
        return apiErrorMissingParameter("type");
    }
    // Add more specific type validation if needed, e.g., against a list of allowed types
    if (empty($api_request["label"])) {
        return apiErrorMissingParameter("label");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Return error from getApiDatabaseConnection
    }

    // Check if organisation already exists
    $existingOrganisation = $db->getOne("SELECT OrganisationID FROM ?n WHERE OrganisationID = ?s", $config["platform"]["sql"]["tbl"]["Organisation"], $api_request["id"]);
    if ($existingOrganisation) {
        return apiErrorDuplicate("Organisation", "id");
    }

    $labelAlternative = !empty($api_request["labelAlternative"]) && is_array($api_request["labelAlternative"]) ? json_encode($api_request["labelAlternative"]) : json_encode([]);
    $socialMediaIDs = !empty($api_request["socialMediaIDs"]) && is_array($api_request["socialMediaIDs"]) ? json_encode($api_request["socialMediaIDs"]) : json_encode([]);
    $additionalInformation = !empty($api_request["additionalinformation"]) ? json_encode(json_decode($api_request["additionalinformation"],true)) : json_encode([]);

    $sql = "INSERT INTO ?n SET OrganisationID=?s, OrganisationType=?s, OrganisationLabel=?s, OrganisationLabelAlternative=?s, OrganisationAbstract=?s, OrganisationThumbnailURI=?s, OrganisationThumbnailCreator=?s, OrganisationThumbnailLicense=?s, OrganisationEmbedURI=?s, OrganisationWebsiteURI=?s, OrganisationSocialMediaIDs=?s, OrganisationColor=?s, OrganisationAdditionalInformation=?s, OrganisationLastChanged=NOW()";

    try {
        $db->query($sql,
            $config["platform"]["sql"]["tbl"]["Organisation"],
            $api_request["id"],
            $api_request["type"],
            $api_request["label"],
            $labelAlternative,
            $api_request["abstract"] ?? null,
            $api_request["thumbnailuri"] ?? null,
            $api_request["thumbnailcreator"] ?? null,
            $api_request["thumbnaillicense"] ?? null,
            $api_request["embeduri"] ?? null,
            $api_request["websiteuri"] ?? null,
            $socialMediaIDs,
            $api_request["color"] ?? null,
            $additionalInformation
        );

        $itemResponse = organisationGetByID($api_request["id"], $db);
        if (($itemResponse["meta"]["requestStatus"] ?? null) !== "success") {
            return isset($itemResponse["errors"]) ? $itemResponse : apiErrorDatabaseError();
        }

        // The entity is written: the request succeeded. What happened to its suggestion travels in meta.
        require_once(__DIR__ . "/entity-suggestion.php");
        return apiSuccessResponse(
            $itemResponse["data"],
            array_merge(["entityAddStatus" => "success"], entitySuggestionPostProcess($api_request, $api_request["id"], $db))
        );

    } catch (Exception $e) {
        error_log("Error in organisationAdd: " . $e->getMessage());
        $errorMeta = [
            'requestStatus' => 'error',
            'entityAddStatus' => 'error',
            'reimportStatus' => 'not_attempted',
            'suggestionDeleteStatus' => 'not_attempted'
        ];
        return apiErrorDatabaseError($e->getMessage(), $errorMeta);
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

        // Route thumbnails through the local image cache (admin entity table).
        if (is_array($items)) {
            foreach ($items as $k => $row) {
                if (!empty($row["OrganisationThumbnailURI"])) {
                    $items[$k]["OrganisationThumbnailURI"] = imageCacheUrl("organisation", $row["OrganisationID"], $row["OrganisationThumbnailURI"]);
                }
            }
        }

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
        return apiErrorMissingParameter("id");
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
        return apiErrorNotFound("Organisation");
    }

    // Define allowed parameters
    $allowedParams = array(
        "OrganisationType", "OrganisationLabel", "OrganisationLabelAlternative", 
        "OrganisationAbstract", "OrganisationThumbnailURI", "OrganisationThumbnailCreator", 
        "OrganisationThumbnailLicense", "OrganisationEmbedURI", "OrganisationWebsiteURI", 
        "OrganisationSocialMediaIDs", "OrganisationColor", "OrganisationFilterable", "OrganisationOrder", "OrganisationAdditionalInformation"
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
            return apiErrorResponse(
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
            return apiErrorResponse(
                500,
                1,
                "messageErrorDatabaseGeneric",
                "messageErrorOrganisationUpdateFailedDetail"
            );
        }

        return apiSuccessResponse(null, ["message" => "Organisation updated successfully"]);

    } catch (exception $e) {
        return apiErrorDatabaseError();
    }
}
?>
