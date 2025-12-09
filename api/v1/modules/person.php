<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");



/**
 * @param string $id PersonID (= WikidataID)
 * @return array
 */
function personGetByID($id = false, $db = false) {
    global $config;

    if (!$id) {
        return createApiErrorMissingParameter('id');
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT * FROM ?n WHERE PersonID=?s",
            $config["platform"]["sql"]["tbl"]["Person"],
            $id
        );

        if ($item) {
            $personDataObject = personGetDataObject($item, $db);
            if (!$personDataObject) {
                return createApiErrorResponse(500, 1, "messageErrorDataTransformationTitle", "messageErrorDataTransformationDetail");
            }
            return createApiSuccessResponse($personDataObject);
        } else {
            return createApiErrorNotFound('person');
        }
    } catch (exception $e) {
        return createApiErrorDatabaseConnection();
    }
}

function personGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "person";
        $return["id"] = $item["PersonID"];
        $return["attributes"]["type"] = $item["PersonType"];
        $return["attributes"]["label"] = $item["PersonLabel"];
        $return["attributes"]["labelAlternative"] = json_decode($item["PersonLabelAlternative"],true);
        $return["attributes"]["firstName"] = $item["PersonFirstName"];
        $return["attributes"]["lastName"] = $item["PersonLastName"];
        $return["attributes"]["degree"] = $item["PersonDegree"];
        $return["attributes"]["birthDate"] = $item["PersonBirthDate"];
        $return["attributes"]["gender"] = $item["PersonGender"];
        $return["attributes"]["abstract"] = $item["PersonAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["PersonThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = $item["PersonThumbnailCreator"] ? htmlentities($item["PersonThumbnailCreator"]) : null;
        $return["attributes"]["thumbnailLicense"] = $item["PersonThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["PersonEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["PersonWebsiteURI"];
        $return["attributes"]["originID"] = $item["PersonOriginID"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["PersonSocialMediaIDs"],true);
        $return["attributes"]["additionalInformation"] = json_decode($item["PersonAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["PersonLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];

        if ($item["PersonPartyOrganisationID"]) {

            $itemParty = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonPartyOrganisationID"]);

            if ($itemParty) {

                $return["relationships"]["party"]["data"]["type"] = "organisation";
                $return["relationships"]["party"]["data"]["id"] = $itemParty["OrganisationID"];
                $return["relationships"]["party"]["data"]["attributes"]["label"] = $itemParty["OrganisationLabel"];
                $return["relationships"]["party"]["data"]["attributes"]["labelAlternative"] = json_decode($itemParty["OrganisationLabelAlternative"],true);
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailURI"] = $itemParty["OrganisationThumbnailURI"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailCreator"] = $itemParty["OrganisationThumbnailCreator"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailLicense"] = $itemParty["OrganisationThumbnailLicense"];
                $return["relationships"]["party"]["data"]["attributes"]["websiteURI"] = $itemParty["OrganisationWebsiteURI"];
                $return["relationships"]["party"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["party"]["data"]["id"];

            } else {

                $return["relationships"]["party"] = array();

            }

        } else {

            $return["relationships"]["party"] = array();

        }

        if ($item["PersonFactionOrganisationID"]) {

            $itemFaction = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonFactionOrganisationID"]);

            if ($itemFaction) {

                $return["relationships"]["faction"]["data"]["type"] = "organisation";
                $return["relationships"]["faction"]["data"]["id"] = $itemFaction["OrganisationID"];
                $return["relationships"]["faction"]["data"]["attributes"]["label"] = $itemFaction["OrganisationLabel"];
                $return["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] = json_decode($itemFaction["OrganisationLabelAlternative"],true);
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailURI"] = $itemFaction["OrganisationThumbnailURI"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailCreator"] = $itemFaction["OrganisationThumbnailCreator"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailLicense"] = $itemFaction["OrganisationThumbnailLicense"];
                $return["relationships"]["faction"]["data"]["attributes"]["websiteURI"] = $itemFaction["OrganisationWebsiteURI"];
                $return["relationships"]["faction"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["faction"]["data"]["id"];

            } else {

                $return["relationships"]["faction"] = array();

            }

        } else {

            $return["relationships"]["faction"] = array();

        }

        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?personID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;


}

function personSearch($parameter, $db = false) {
    global $config;

    $outputLimit = 25;

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    $filteredParameters = filterAllowedSearchParams($parameter, 'person');

    // Validate name length
    if (array_key_exists("name", $filteredParameters)) {
        if (mb_strlen($filteredParameters["name"], "UTF-8") < 3) {
            return createApiErrorInvalidLength('name', 3);
        }
    }

    // Validate type
    if (array_key_exists("type", $filteredParameters)) {
        if (!in_array($filteredParameters["type"], $config["entityTypes"]["person"])) {
            return createApiErrorResponse(
                422, 
                2, 
                "messageErrorInvalidValueTitle", 
                "messageErrorInvalidFormatDetail", 
                ["field" => "type", "expected" => implode(" or ", $config["entityTypes"]["person"])]
            );
        }
    }

    // Validate party
    if (array_key_exists("party", $filteredParameters)) {
        if (is_array($filteredParameters["party"])) {
            foreach ($filteredParameters["party"] as $tmpParty) {
                if (mb_strlen($tmpParty, "UTF-8") < 1) {
                    return createApiErrorInvalidLength('party', 1);
                }
            }
        } else if (mb_strlen($filteredParameters["party"], "UTF-8") < 1) {
            return createApiErrorInvalidLength('party', 1);
        }
    }

    // Validate partyID
    if (array_key_exists("partyID", $filteredParameters)) {
        if (is_array($filteredParameters["partyID"])) {
            foreach ($filteredParameters["partyID"] as $tmpPartyID) {
                if (!validateWikidataID($tmpPartyID)) {
                    return createApiErrorInvalidID("Party");
                }
            }
        } else if (!validateWikidataID($filteredParameters["partyID"])) {
            return createApiErrorInvalidID("Party");
        }
    }

    // Validate faction
    if (array_key_exists("faction", $filteredParameters)) {
        if (is_array($filteredParameters["faction"])) {
            foreach ($filteredParameters["faction"] as $tmpFaction) {
                if (mb_strlen($tmpFaction, "UTF-8") < 1) {
                    return createApiErrorInvalidLength('faction', 1);
                }
            }
        } else if (mb_strlen($filteredParameters["faction"], "UTF-8") < 1) {
            return createApiErrorInvalidLength('faction', 1);
        }
    }

    // Validate factionID
    if (array_key_exists("factionID", $filteredParameters)) {
        if (is_array($filteredParameters["factionID"])) {
            foreach ($filteredParameters["factionID"] as $tmpFactionID) {
                if (!validateWikidataID($tmpFactionID)) {
                    return createApiErrorInvalidID("Faction");
                }
            }
        } else if (!validateWikidataID($filteredParameters["factionID"])) {
            return createApiErrorInvalidID("Faction");
        }
    }

    // Validate organisationID
    if (array_key_exists("organisationID", $filteredParameters)) {
        if (!validateWikidataID($filteredParameters["organisationID"])) {
            return createApiErrorInvalidID("Organisation");
        }
    }

    // Validate degree
    if (array_key_exists("degree", $filteredParameters) && mb_strlen($filteredParameters["degree"], "UTF-8") < 1) {
        return createApiErrorInvalidLength('degree', 1);
    }

    // Validate gender
    if (array_key_exists("gender", $filteredParameters)) {
        if (!in_array($filteredParameters["gender"], array("male", "female", "nonbinary"))) {
            return createApiErrorResponse(
                422, 
                3, 
                "messageErrorInvalidValueTitle", 
                "messageErrorInvalidFormatDetail", 
                ["field" => "gender", "expected" => "male, female, or nonbinary"]
            );
        }
    }

    // Validate originID
    if (array_key_exists("originID", $filteredParameters) && mb_strlen($filteredParameters["originID"], "UTF-8") < 1) {
        return createApiErrorInvalidLength('originID', 1);
    }

    // Validate abgeordnetenwatchID
    if (array_key_exists("abgeordnetenwatchID", $filteredParameters) && mb_strlen($filteredParameters["abgeordnetenwatchID"], "UTF-8") < 1) {
        return createApiErrorInvalidLength('abgeordnetenwatchID', 1);
    }

    // Validate fragDenStaatID
    if (array_key_exists("fragDenStaatID", $filteredParameters) && mb_strlen($filteredParameters["fragDenStaatID"], "UTF-8") < 1) {
        return createApiErrorInvalidLength('fragDenStaatID', 1);
    }

    try {
        $query = "SELECT            p.*,
                                op.OrganisationID as PartyID,
                                op.OrganisationLabel as PartyLabel,
                                ofr.OrganisationID as FactionID,
                                ofr.OrganisationLabel as FactionLabel
                            FROM ?n AS p
                            LEFT JOIN ?n as op 
                                ON op.OrganisationID = p.PersonPartyOrganisationID
                            LEFT JOIN ?n as ofr 
                                ON ofr.OrganisationID = p.PersonFactionOrganisationID";

        $conditions = array();

        foreach ($filteredParameters as $k=>$para) {
            if ($k == "name") {
                $conditions[] = $db->parse("MATCH(p.PersonLabel, p.PersonFirstName, p.PersonLastName) AGAINST (?s IN BOOLEAN MODE)", "*".str_replace("-", " ",$para)."*");
            }
            if ($k == "type") {
                $conditions[] = $db->parse("PersonType = ?s", $para);
            }

            if ($k == "party") {
                if (is_array($para)) {
                    $tmpStringArray = array_map(function($tmppara) use ($db) {
                        return $db->parse("(op.OrganisationLabel LIKE ?s OR op.OrganisationLabelAlternative LIKE ?s)", 
                            "%".$tmppara."%", 
                            "%".$tmppara."%"
                        );
                    }, $para);
                    $conditions[] = "(" . implode(" OR ", $tmpStringArray) . ")";
                } else {
                    $conditions[] = $db->parse("(op.OrganisationLabel LIKE ?s OR op.OrganisationLabelAlternative LIKE ?s)", 
                        "%".$para."%", 
                        "%".$para."%"
                    );
                }
            }

            if ($k == "partyID") {
                if (is_array($para)) {
                    $tmpStringArray = array_map(function($tmppara) use ($db) {
                        return $db->parse("op.OrganisationID = ?s", $tmppara);
                    }, $para);
                    $conditions[] = "(" . implode(" OR ", $tmpStringArray) . ")";
                } else {
                    $conditions[] = $db->parse("op.OrganisationID = ?s", $para);
                }
            }

            if ($k == "faction") {
                if (is_array($para)) {
                    $tmpStringArray = array_map(function($tmppara) use ($db) {
                        return $db->parse("(ofr.OrganisationLabel LIKE ?s OR ofr.OrganisationLabelAlternative LIKE ?s)", 
                            "%".$tmppara."%", 
                            "%".$tmppara."%"
                        );
                    }, $para);
                    $conditions[] = "(" . implode(" OR ", $tmpStringArray) . ")";
                } else {
                    $conditions[] = $db->parse("(ofr.OrganisationLabel LIKE ?s OR ofr.OrganisationLabelAlternative LIKE ?s)", 
                        "%".$para."%", 
                        "%".$para."%"
                    );
                }
            }

            if ($k == "factionID") {
                if (is_array($para)) {
                    $tmpStringArray = array_map(function($tmppara) use ($db) {
                        return $db->parse("p.PersonFactionOrganisationID = ?s", $tmppara);
                    }, $para);
                    $conditions[] = "(" . implode(" OR ", $tmpStringArray) . ")";
                } else {
                    $conditions[] = $db->parse("p.PersonFactionOrganisationID = ?s", $para);
                }
            }

            if ($k == "organisationID") {
                $conditions[] = $db->parse("(p.PersonFactionOrganisationID = ?s OR p.PersonPartyOrganisationID=?s)", 
                    $para, 
                    $para
                );
            }

            if ($k == "degree") {
                $conditions[] = $db->parse("PersonDegree LIKE ?s", "%".$para."%");
            }

            if ($k == "gender") {
                $conditions[] = $db->parse("PersonGender LIKE ?s", $para);
            }

            if ($k == "originID") {
                $conditions[] = $db->parse("PersonOriginID = ?s", $para);
            }

            if ($k == "abgeordnetenwatchID") {
                $conditions[] = $db->parse("JSON_EXTRACT(p.PersonAdditionalInformation, '$.abgeordnetenwatchID') = ?s", $para);
            }

            if ($k == "fragDenStaatID") {
                $conditions[] = $db->parse("JSON_EXTRACT(p.PersonAdditionalInformation, '$.fragDenStaatID') = ?s", $para);
            }
        }

        if (count($conditions) > 0) {
            $query .= " WHERE ".implode(" AND ",$conditions);

            $totalCount = $db->getAll($query, 
                $config["platform"]["sql"]["tbl"]["Person"],
                $config["platform"]["sql"]["tbl"]["Organisation"],
                $config["platform"]["sql"]["tbl"]["Organisation"]
            );

            $page = isset($parameter["page"]) ? (int)$parameter["page"] : 1;
            $query .= $db->parse(" LIMIT ?i, ?i", ($page-1)*$outputLimit, $outputLimit);

            $findings = $db->getAll($query,
                $config["platform"]["sql"]["tbl"]["Person"],
                $config["platform"]["sql"]["tbl"]["Organisation"],
                $config["platform"]["sql"]["tbl"]["Organisation"]
            );

            return createApiSuccessResponse(
                array_map(function($finding) use ($db) {
                    return personGetDataObject($finding, $db);
                }, $findings),
                [
                    "page" => $page,
                    "pageTotal" => ceil(count($totalCount)/$outputLimit)
                ],
                [
                    "self" => $config["dir"]["api"]."/search/people?".getURLParameterFromArray($filteredParameters)
                ]
            );

        } else {
            return createApiErrorResponse(
                404,
                1,
                "messageErrorParameterMissingTitle",
                "messageErrorNotEnoughSearchParametersDetail"
            );
        }

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

function personAdd($api_request, $db = false, $dbp = false) {
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
    if (!in_array($api_request["type"], $config["entityTypes"]["person"])) {
        return createApiErrorInvalidParameter("type", "messageErrorInvalidValueDetail", ["value" => $api_request["type"], "expected" => implode(" or ", $config["entityTypes"]["person"])]);
    }
    if (empty($api_request["label"])) {
        return createApiErrorMissingParameter("label");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db; // Return error from getApiDatabaseConnection
    }

    // Check for duplicates
    $existingPerson = $db->getOne("SELECT PersonID FROM ?n WHERE PersonID = ?s", $config["platform"]["sql"]["tbl"]["Person"], $api_request["id"]);
    if ($existingPerson) {
        return createApiErrorDuplicate("Person", "id");
    }
    
    $labelAlternative = !empty($api_request["labelAlternative"]) && is_array($api_request["labelAlternative"]) ? json_encode($api_request["labelAlternative"]) : json_encode([]);
    $socialMediaIDs = !empty($api_request["socialMediaIDs"]) && is_array($api_request["socialMediaIDs"]) ? json_encode($api_request["socialMediaIDs"]) : json_encode([]);
    $additionalInformation = !empty($api_request["additionalinformation"]) ? json_encode(json_decode($api_request["additionalinformation"],true)) : json_encode([]);

    $birthDateFormatted = null;
    if (!empty($api_request["birthdate"])) {
        $date = new DateTime($api_request["birthdate"]);
        $birthDateFormatted = $date->format('Y-m-d');
    }

    $sql = "INSERT INTO ?n SET PersonID=?s, PersonType=?s, PersonLabel=?s, PersonLabelAlternative=?s, PersonFirstName=?s, PersonLastName=?s, PersonDegree=?s, PersonBirthDate=?s, PersonGender=?s, PersonAbstract=?s, PersonThumbnailURI=?s, PersonThumbnailCreator=?s, PersonThumbnailLicense=?s, PersonEmbedURI=?s, PersonWebsiteURI=?s, PersonOriginID=?s, PersonPartyOrganisationID=?s, PersonFactionOrganisationID=?s, PersonSocialMediaIDs=?s, PersonAdditionalInformation=?s, PersonLastChanged=NOW()";
    
    try {
        $db->query($sql,
            $config["platform"]["sql"]["tbl"]["Person"],
            $api_request["id"],
            $api_request["type"],
            $api_request["label"],
            $labelAlternative,
            $api_request["firstName"] ?? null,
            $api_request["lastName"] ?? null,
            $api_request["degree"] ?? null,
            $birthDateFormatted,
            $api_request["gender"] ?? null,
            $api_request["abstract"] ?? null,
            $api_request["thumbnailuri"] ?? null,
            $api_request["thumbnailcreator"] ?? null,
            $api_request["thumbnaillicense"] ?? null,
            $api_request["embeduri"] ?? null,
            $api_request["websiteuri"] ?? null,
            $api_request["originid"] ?? null,
            $api_request["party"] ?? null,
            $api_request["faction"] ?? null,
            $socialMediaIDs,
            $additionalInformation
        );

        $personID = $api_request["id"];
        $personDataResponse = personGetByID($personID, $db);

        $finalMeta = [];
        $finalData = null;

        if (isset($personDataResponse["meta"]["requestStatus"]) && $personDataResponse["meta"]["requestStatus"] == "success") {
            $finalMeta['entityAddStatus'] = 'success';
            $finalData = $personDataResponse["data"];

            $actualSuggestionInternalIDToProcess = $sourceEntitySuggestionID;

            // If no internal suggestion ID was passed from the form, but reimport is flagged
            // and we have the external wikidata ID, try to find an existing suggestion.
            if (empty($actualSuggestionInternalIDToProcess) && $reimportAffectedSessions && !empty($api_request['id'])) {
                $lookupResponse = apiV1([
                    "action" => "getItemsFromDB",
                    "itemType" => "entitySuggestion",
                    "id" => $api_request['id'], // This is the external Wikidata ID of the person being added
                    "idType" => "external",
                    "limit" => 1 // We only need one if it exists
                ], $db);

                if (isset($lookupResponse["meta"]["requestStatus"]) && $lookupResponse["meta"]["requestStatus"] == "success" && !empty($lookupResponse["data"])) {
                    $foundSuggestion = $lookupResponse["data"];
                    if (isset($foundSuggestion['EntitysuggestionID'])) {
                        $actualSuggestionInternalIDToProcess = $foundSuggestion['EntitysuggestionID'];
                    }
                }
            }
            
            // Now call post-processing with the resolved internal suggestion ID (if any) and the reimport flag
            $postProcessingDetails = handleEntitySuggestionPostProcessing($reimportAffectedSessions, $actualSuggestionInternalIDToProcess, $db);
            
            $finalMeta['reimportStatus'] = $postProcessingDetails['reimportStatus'];
            $finalMeta['reimportSummary'] = $postProcessingDetails['reimportSummary'];
            $finalMeta['affectedSessions'] = $postProcessingDetails['affectedSessions'];
            $finalMeta['affectedSpeeches'] = $postProcessingDetails['affectedSpeeches'];
            $finalMeta['suggestionDeleteStatus'] = $postProcessingDetails['suggestionDeleteStatus'];

            // Determine overall request status
            if ($finalMeta['entityAddStatus'] === 'success' && 
                $postProcessingDetails['reimportStatus'] === 'success' && 
                $postProcessingDetails['suggestionDeleteStatus'] !== 'error') { // Suggestion delete error doesn't fail the whole request
                $finalMeta['requestStatus'] = 'success';
            } else {
                $finalMeta['requestStatus'] = 'error';
                 // If any critical part failed, ensure an error is logged or an error response is formed
                if ($postProcessingDetails['reimportStatus'] === 'error') {
                    // Construct a more specific error if reimport failed
                     return createApiErrorResponse(500, 1, "messageErrorItemCreationReimportFailedTitle", "messageErrorItemCreationReimportFailedDetail", ["itemType" => "Person"]);
                }
            }

        } else {
            // personGetByID failed, or returned an error structure
            $finalMeta['entityAddStatus'] = 'error';
            $finalMeta['reimportStatus'] = 'not_attempted';
            $finalMeta['suggestionDeleteStatus'] = 'not_attempted';
            $finalMeta['requestStatus'] = 'error';
            // Return the error from personGetByID or a generic one if it's not an API error structure
            if (isset($personDataResponse["errors"])) {
                return $personDataResponse; // It's already an API error response
            }
            return createApiErrorResponse(500, 1, "messageErrorItemCreationRetrievalFailedTitle", "messageErrorItemCreationRetrievalFailedDetail", ["itemType" => "Person"]);
        }
        
        // If requestStatus is error, but we have some meta, form an error response
        if ($finalMeta['requestStatus'] === 'error') {
            // You might want to pick a primary error to report. 
            // For now, let's assume if entityAddStatus is error, that's primary.
            // If reimport failed critically, we returned earlier.
            // If suggestion delete failed, it's an error in meta but not blocking overall success for entity add.
            $errorTitle = "messageErrorPartialSuccessTitle";
            $errorDetail = "messageErrorPartialSuccessDetail";
            if($finalMeta['entityAddStatus'] === 'error'){
                 $errorTitle = "messageErrorItemCreationRetrievalFailedTitle";
                 $errorDetail = "messageErrorItemCreationRetrievalFailedDetail";
            }
            // This error construction needs refinement based on which error to prioritize if multiple steps fail.
            return createApiErrorResponse(500, 1, $errorTitle, $errorDetail, ["itemType" => "Person"], null, $finalMeta);
        }

        return createApiSuccessResponse($finalData, $finalMeta);

    } catch (Exception $e) {
        error_log("Error in personAdd: " . $e->getMessage());
        // Construct meta for error response
        $errorMeta = [
            'requestStatus' => 'error',
            'entityAddStatus' => 'error',
            'reimportStatus' => 'not_attempted',
            'suggestionDeleteStatus' => 'not_attempted'
        ];
        return createApiErrorDatabaseError($e->getMessage(), $errorMeta); // Pass meta to be included in error
    }
}


function personGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false) {
    global $config;

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            return array(
                "total" => 0,
                "data" => array()
            );
        }
    }

    $queryPart = "";

    if ($id == "all") {
        $queryPart .= "1";
    } else {
        $queryPart .= $db->parse("PersonID=?s",$id);
    }


    if (!empty($search)) {
        $queryPart .= $db->parse(" AND (LOWER(PersonLabel) LIKE LOWER(?s) OR LOWER(PersonLabelAlternative) LIKE LOWER(?s) OR PersonID LIKE ?s)", "%".$search."%", "%".$search."%", "%".$search."%");
    }

    if (!empty($sort)) {

        $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);

    }


    if ($limit != 0) {

        $queryPart .= $db->parse(" LIMIT ?i, ?i",$offset,$limit);

    }

    $return["total"] = $db->getOne("SELECT COUNT(PersonID) as count FROM  ?n", $config["platform"]["sql"]["tbl"]["Person"]);
    $return["data"] = $db->getAll("SELECT
        per.PersonID,
        per.PersonType,
        per.PersonLabel,
        per.PersonLabelAlternative,
        per.PersonGender,
        per.PersonPartyOrganisationID,
        per.PersonFactionOrganisationID,
        per.PersonLastChanged,
        per.PersonThumbnailURI,
        party.OrganisationLabel as PartyLabel,
        faction.OrganisationLabel as FactionLabel
        FROM ?n AS per
        LEFT JOIN ?n as party
            ON party.OrganisationID = per.PersonPartyOrganisationID
        LEFT JOIN ?n as faction
            ON faction.OrganisationID = per.PersonFactionOrganisationID WHERE ?p", $config["platform"]["sql"]["tbl"]["Person"], $config["platform"]["sql"]["tbl"]["Organisation"], $config["platform"]["sql"]["tbl"]["Organisation"], $queryPart);
    /*
        *
        * To add annotation count uncomment. But be aware this will take some time
        *
        *
            foreach ($config["parliament"] as $parliamentShort=>$parliament) {
                $dbp[$parliamentShort] = new SafeMySQL(array(
                    'host'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["host"],
                    'user'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["user"],
                    'pass'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["passwd"],
                    'db'	=> $config["parliament"][$parliamentShort]["sql"]["db"]
                ));
            }

            foreach ($return["data"] as $k=>$person) {
                $return["data"][$k]["annotationcount"] = 0;
                foreach ($config["parliament"] as $parliamentShort=>$parliament) {
                    $tmpCnt = $dbp[$parliamentShort]->getOne("SELECT COUNT(AnnotationID) as cnt FROM `annotation` WHERE AnnotationResourceID = ?s AND AnnotationType=?s",$person["PersonID"],"person");
                    //$return["data"][$k]["annotationcount"] = $tmpCnt+$return["data"][$k]["annotationcount"];
                    $return["data"][$k]["annotationcount"] += $tmpCnt;
                }
            }*/


    return $return;

}

function personChange($parameter) {
    global $config;

    if (!$parameter["id"]) {
        return createApiErrorMissingParameter("id");
    }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    // Check if person exists
    $person = $db->getRow("SELECT * FROM ?n WHERE PersonID=?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["Person"], 
        $parameter["id"]
    );
    
    if (!$person) {
        return createApiErrorNotFound("Person");
    }

    // Define allowed parameters
    $allowedParams = array(
        "PersonType", "PersonLabel", "PersonLabelAlternative", "PersonFirstName", 
        "PersonLastName", "PersonDegree", "PersonBirthDate", "PersonGender", 
        "PersonAbstract", "PersonThumbnailURI", "PersonThumbnailCreator", 
        "PersonThumbnailLicense", "PersonEmbedURI", "PersonWebsiteURI", 
        "PersonOriginID", "PersonSocialMediaIDs", "PersonAdditionalInformation",
        "PersonPartyOrganisationID", "PersonFactionOrganisationID"
    );

    // Filter parameters
    $params = $db->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "PersonLabelAlternative" || $key === "PersonSocialMediaIDs" || $key === "PersonAdditionalInformation") {
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
            "messageErrorNoValidFieldsToUpdateDetail"
        );
    }

    // Add last changed timestamp
    $updateParams[] = "PersonLastChanged=CURRENT_TIMESTAMP()";

    try {
        // Execute update
        $db->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE PersonID=?s", 
            $config["platform"]["sql"]["tbl"]["Person"], 
            $parameter["id"]
        );

        return createApiSuccessResponse();
    } catch (exception $e) {
        return createApiErrorResponse(
            503,
            1,
            "messageErrorDatabaseGeneric",
            "messageErrorPersonUpdateFailedDetail",
            ["exception_message" => $e->getMessage()]
        );
    }
}

?>


