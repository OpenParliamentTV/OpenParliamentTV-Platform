<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/entities.php");
require_once (__DIR__."/../../../modules/utilities/text-array-converters.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/images/image-cache.php");

/**
 * Helper function to process document annotations
 * 
 * @param array $annotation The annotation data
 * @param SafeMySQL $db Platform database connection
 * @param array $config Global config
 * @return array|false Returns processed annotation data or false if invalid
 */
function processDocumentAnnotation($annotation, $db, $config) {
    $ditem = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["Document"], 
        (int)$annotation["AnnotationResourceID"]
    );
    
    if (!$ditem) {
        return false;
    }

    $tmpAnnotationItem = [
        "type" => "document",
        "id" => $annotation["AnnotationResourceID"],
        "attributes" => [
            "context" => $annotation["AnnotationContext"],
            "type" => $ditem["DocumentType"],
            "label" => str_replace(["\r","\n"], " ", $ditem["DocumentLabel"]),
            "labelAlternative" => json_decode($ditem["DocumentLabelAlternative"]),
            "thumbnailURI" => imageCacheUrl("document", $annotation["AnnotationResourceID"], $ditem["DocumentThumbnailURI"]),
            "thumbnailCreator" => $ditem["DocumentThumbnailCreator"],
            "thumbnailLicense" => $ditem["DocumentThumbnailLicense"],
            "additionalInformation" => json_decode($ditem["DocumentAdditionalInformation"], true) ?: [],
            "sourceURI" => $ditem["DocumentSourceURI"],
            "embedURI" => $ditem["DocumentEmbedURI"]
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/document/".$annotation["AnnotationResourceID"]
        ]
    ];

    return $tmpAnnotationItem;
}

/**
 * Helper function to process organisation annotations
 * 
 * @param array $annotation The annotation data
 * @param SafeMySQL $db Platform database connection
 * @param array $config Global config
 * @return array|false Returns processed annotation data or false if invalid
 */
function processOrganisationAnnotation($annotation, $db, $config) {
    $ditem = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["Organisation"], 
        $annotation["AnnotationResourceID"]
    );
    
    if (!$ditem) {
        return false;
    }

    return [
        "type" => "organisation",
        "id" => $annotation["AnnotationResourceID"],
        "attributes" => [
            "context" => $annotation["AnnotationContext"],
            "type" => $ditem["OrganisationType"],
            "label" => $ditem["OrganisationLabel"],
            "labelAlternative" => json_decode($ditem["OrganisationLabelAlternative"]),
            "thumbnailURI" => imageCacheUrl("organisation", $annotation["AnnotationResourceID"], $ditem["OrganisationThumbnailURI"]),
            "thumbnailCreator" => $ditem["OrganisationThumbnailCreator"],
            "thumbnailLicense" => $ditem["OrganisationThumbnailLicense"],
            "additionalInformation" => json_decode($ditem["OrganisationAdditionalInformation"] ?? "", true) ?: [],
            "color" => $ditem["OrganisationColor"]
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/organisation/".$annotation["AnnotationResourceID"]
        ]
    ];
}

/**
 * Helper function to process term annotations
 * 
 * @param array $annotation The annotation data
 * @param SafeMySQL $db Platform database connection
 * @param array $config Global config
 * @return array|false Returns processed annotation data or false if invalid
 */
function processTermAnnotation($annotation, $db, $config) {
    $ditem = $db->getRow("SELECT * FROM ?n WHERE TermID=?s LIMIT 1", 
        $config["platform"]["sql"]["tbl"]["Term"], 
        $annotation["AnnotationResourceID"]
    );
    
    if (!$ditem) {
        return false;
    }

    return [
        "type" => "term",
        "id" => $annotation["AnnotationResourceID"],
        "attributes" => [
            "context" => $annotation["AnnotationContext"],
            "type" => $ditem["TermType"],
            "label" => $ditem["TermLabel"],
            "labelAlternative" => json_decode($ditem["TermLabelAlternative"]),
            "websiteURI" => $ditem["TermWebsiteURI"],
            "thumbnailURI" => imageCacheUrl("term", $annotation["AnnotationResourceID"], $ditem["TermThumbnailURI"]),
            "thumbnailCreator" => $ditem["TermThumbnailCreator"],
            "thumbnailLicense" => $ditem["TermThumbnailLicense"],
            "additionalInformation" => json_decode($ditem["TermAdditionalInformation"], true) ?: []
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/term/".$annotation["AnnotationResourceID"]
        ]
    ];
}

/**
 * Helper function to process person annotations
 * 
 * @param array $annotation The annotation data
 * @param SafeMySQL $db Platform database connection
 * @param array $config Global config
 * @return array|false Returns processed annotation data or false if invalid
 */
function processPersonAnnotation($annotation, $db, $config) {
    $pitem = $db->getRow("SELECT
            p.*,
            op.OrganisationID,
            op.OrganisationLabel,
            op.OrganisationID as PartyID,
            op.OrganisationLabel as PartyLabel,
            op.OrganisationLabelAlternative as PartyLabelAlternative,
            ofr.OrganisationID as FactionID,
            ofr.OrganisationLabel as FactionLabel,
            ofr.OrganisationLabelAlternative as FactionLabelAlternative
        FROM ?n AS p
        LEFT JOIN ?n as op 
            ON op.OrganisationID = p.PersonPartyOrganisationID
        LEFT JOIN ?n as ofr 
            ON ofr.OrganisationID = p.PersonFactionOrganisationID
        WHERE PersonID=?s LIMIT 1",
        $config["platform"]["sql"]["tbl"]["Person"],
        $config["platform"]["sql"]["tbl"]["Organisation"],
        $config["platform"]["sql"]["tbl"]["Organisation"], 
        $annotation["AnnotationResourceID"]
    );
    
    if (!$pitem) {
        return false;
    }

    return [
        "type" => "person",
        "id" => $pitem["PersonID"],
        "attributes" => [
            "type" => $pitem["PersonType"],
            "label" => $pitem["PersonLabel"],
            "labelAlternative" => json_decode($pitem["PersonLabelAlternative"]),
            "degree" => $pitem["PersonDegree"],
            "thumbnailURI" => imageCacheUrl("person", $pitem["PersonID"], $pitem["PersonThumbnailURI"]),
            "thumbnailCreator" => $pitem["PersonThumbnailCreator"],
            "thumbnailLicense" => $pitem["PersonThumbnailLicense"],
            "additionalInformation" => json_decode($pitem["PersonAdditionalInformation"], true) ?: [],
            "party" => [
                "id" => $pitem["PartyID"],
                "label" => $pitem["PartyLabel"],
                "labelAlternative" => json_decode($pitem["PartyLabelAlternative"])
            ],
            "faction" => [
                "id" => $pitem["FactionID"],
                "label" => $pitem["FactionLabel"],
                "labelAlternative" => json_decode($pitem["FactionLabelAlternative"])
            ]
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/person/".$pitem["PersonID"]
        ]
    ];
}

/**
 * Helper function to get additional information from annotation item
 * 
 * @param array $annotationItem The processed annotation item
 * @param string $type The type of annotation (document, organisation, term, person)
 * @param array $pitem Optional person data for person annotations
 * @return array Additional information for the annotation
 */
function getAnnotationAdditionalInfo($annotationItem, $type, $pitem = null) {
    $additionalInfo = [];
    
    switch ($type) {
        case 'document':
            $additionalInfo["originID"] = !empty($annotationItem["attributes"]["additionalInformation"]["originID"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["originID"] 
                : [];
            $additionalInfo["procedureIDs"] = !empty($annotationItem["attributes"]["additionalInformation"]["procedureIDs"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["procedureIDs"] 
                : [];
            break;
            
        case 'organisation':
        case 'term':
            $additionalInfo["fragDenStaatID"] = !empty($annotationItem["attributes"]["additionalInformation"]["fragDenStaatID"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["fragDenStaatID"] 
                : "";
            $additionalInfo["abgeordnetenwatchID"] = !empty($annotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"] 
                : "";
            break;
            
        case 'person':
            $additionalInfo["fragDenStaatID"] = !empty($annotationItem["attributes"]["additionalInformation"]["fragDenStaatID"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["fragDenStaatID"] 
                : "";
            $additionalInfo["abgeordnetenwatchID"] = !empty($annotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) 
                ? $annotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"] 
                : "";
            $additionalInfo["originID"] = !empty($pitem["PersonOriginID"]) 
                ? $pitem["PersonOriginID"] 
                : "";
            break;
    }
    
    return $additionalInfo;
}

/**
 * Get media item by ID
 * 
 * @param string $id MediaID
 * @param object|false $db Optional platform database connection
 * @param object|false $dbp Optional parliament database connection
 * @return array Response in JSON:API format
 */
function mediaGetByID($id = false, $db = false, $dbp = false) {
    global $config;

    if (!$id) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorMissingParameter",
            "messageErrorMissingParameter",
            ["parameter" => "id"]
        );
    }

    // Parse and validate ID
    $IDInfos = optvParseStringId($id);
    if (!$IDInfos) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorIDParseError",
            "messageErrorIDParseError",
            ["type" => "Media"]
        );
    }

    $parliament = $IDInfos["parliament"];
    if (!array_key_exists($parliament, $config["parliament"])) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorInvalidID",
            "messageErrorInvalidID",
            ["type" => "Media"]
        );
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    // Connect to platform database if not provided
    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            return $db; // Error response from getApiDatabaseConnection
        }
    }

    // Connect to parliament database if not provided
    if (!$dbp) {
        $dbp = getApiDatabaseConnection('parliament', $parliament);
        if (!is_object($dbp)) {
            return $dbp; // Error response from getApiDatabaseConnection
        }
    }

    // Get media item with all related data
    $item = $dbp->getRow("
        SELECT
            m.*,
            ai.*,
            sess.*,
            ep.*
        FROM ?n AS m
        LEFT JOIN ?n AS ai
            ON m.MediaAgendaItemID=ai.AgendaItemID
        LEFT JOIN ?n AS sess
            ON ai.AgendaItemSessionID=sess.SessionID
        LEFT JOIN ?n AS ep
            ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
        WHERE m.MediaID=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"],
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
        $id
    );

    if (!$item) {
        return apiErrorNotFound("Media");
    }

    // Check media access permissions
    $userRole = null;
    if (isset($_SESSION) && isset($_SESSION["userdata"]) && isset($_SESSION["userdata"]["role"])) {
        $userRole = $_SESSION["userdata"]["role"];
    }
    
    if (($item["MediaPublic"] == 0) && ($userRole != "admin") && (!optvIsCli())) {
        return apiErrorResponse(
            511,
            1,
            "messageAuthNotPermittedTitle",
            "messageErrorItemNotPublic"
        );
    }

    // Build response data
    $data = [
        "type" => "media",
        "id" => $item["MediaID"],
        "attributes" => [
            "originID" => $item["MediaOriginID"],
            "originMediaID" => $item["MediaOriginMediaID"],
            "creator" => $item["MediaCreator"],
            "license" => $item["MediaLicense"],
            "parliament" => $parliament,
            "parliamentLabel" => $parliamentLabel,
            "order" => (int)$item["MediaOrder"],
            "public" => (($item["MediaPublic"] === "1") ? true : false),
            "aligned" => (($item["MediaAligned"] === "1") ? true : false),
            "dateStartTimestamp" => strtotime($item["MediaDateStart"]),
            "dateStart" => $item["MediaDateStart"],
            "dateEnd" => $item["MediaDateEnd"],
            "duration" => (float)$item["MediaDuration"],
            "videoFileURI" => $item["MediaVideoFileURI"],
            "audioFileURI" => $item["MediaAudioFileURI"],
            "sourcePage" => $item["MediaSourcePage"],
            "thumbnailURI" => $item["MediaThumbnailURI"],
            "thumbnailCreator" => $item["MediaThumbnailCreator"],
            "thumbnailLicense" => $item["MediaThumbnailLicense"],
            "additionalInformation" => json_decode($item["MediaAdditionalInformation"], true),
            "lastChanged" => $item["MediaLastChanged"],
            "lastChangedTimestamp" => strtotime($item["MediaLastChanged"]),
            "textContents" => []
        ]
    ];

    // Move links and relationships into the data object
    $data["links"] = [
        "self" => $config["dir"]["api"]."/media/".$item["MediaID"]
    ];

    $data["relationships"] = [
        "electoralPeriod" => [
            "data" => [
                "type" => "electoralPeriod",
                "id" => $item["ElectoralPeriodID"],
                "attributes" => [
                    "number" => (int)$item["ElectoralPeriodNumber"],
                    "dateStart" => $item["ElectoralPeriodDateStart"],
                    "dateEnd" => $item["ElectoralPeriodDateEnd"]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"]
                ]
            ]
        ],
        "session" => [
            "data" => [
                "type" => "session",
                "id" => $item["SessionID"],
                "attributes" => [
                    "number" => (int)$item["SessionNumber"]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/session/".$item["SessionID"]
                ]
            ]
        ],
        "agendaItem" => [
            "data" => [
                "type" => "agendaItem",
                "id" => $item["AgendaItemID"],
                "attributes" => [
                    "officialTitle" => $item["AgendaItemOfficialTitle"],
                    "title" => $item["AgendaItemTitle"]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/agendaItem/".$parliament."-".$item["AgendaItemID"]
                ]
            ]
        ],
        "people" => ["data" => []],
        "organisations" => ["data" => []],
        "documents" => ["data" => []],
        "terms" => ["data" => []]
    ];

    // Get text contents
    $itemTexts = $dbp->getAll("SELECT * FROM ?n WHERE TextMediaID=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Text"], 
        $id
    );

    foreach ($itemTexts as $itemText) {
        $textBody = json_decode($itemText["TextBody"], true);
        
        // Build HTML text
        $textHTML = '';
        foreach ($textBody as $paragraph) {
            $textHTML .= $paragraph["text"];
        }


        
        $data["attributes"]["textContents"][] = [
            "id" => $itemText["TextID"],
            "type" => $itemText["TextType"],
            "textBody" => $textBody,
            "textHTML" => $textHTML,
            "sourceURI" => $itemText["TextSourceURI"],
            "creator" => $itemText["TextCreator"],
            "license" => $itemText["TextLicense"],
            "language" => $itemText["TextLanguage"],
            "originTextID" => $itemText["TextOriginTextID"],
            "lastChanged" => $itemText["TextLastChanged"]
        ];
    }

    $data["attributes"]["textContentsCount"] = count($data["attributes"]["textContents"]);

    // Get annotations
    $annotations = $dbp->getAll("SELECT * FROM ?n WHERE AnnotationMediaID=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
        $item["MediaID"]
    );
    $annotations = annotationRawSortByMainSpeaker($annotations);

    $tmpResources = [];
    foreach ($annotations as $annotation) {
        $annotationAttributes = [
            "timeStart" => $annotation["AnnotationTimeStart"],
            "timeEnd" => $annotation["AnnotationTimeEnd"],
            "context" => $annotation["AnnotationContext"],
            "additionalInformation" => json_decode($annotation["AnnotationAdditionalInformation"], true)
        ];

        $tmpAnnotationItem = [];
        
        switch ($annotation["AnnotationType"]) {
            case "document":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processDocumentAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["documents"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "organisation":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processOrganisationAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["organisations"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "term":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processTermAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["terms"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "person":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processPersonAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["people"]["data"], $tmpAnnotationItem);
                    }
                }
                break;
        }

        $data["annotations"]["data"][] = [
            "type" => $annotation["AnnotationType"],
            "id" => $annotation["AnnotationResourceID"],
            "attributes" => $annotationAttributes
        ];
    }

    // Add relationship links (these are for the collections, should remain in data.relationships.documents.links etc)
    $data["relationships"]["documents"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=document";
    $data["relationships"]["organisations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=organisation";
    $data["relationships"]["terms"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=term";
    $data["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=person";

    return apiSuccessResponse($data, null, null, null);
}

/**
 * Import one speech from a session file.
 *
 * Envelope wrapper over importMediaWrite() (modules/import/media-writer.php),
 * which holds the write path. Behaviour of `addItem&itemType=media` is
 * unchanged: same success payload, same error envelopes — including the ones
 * getApiDatabaseConnection() produces, which is why the connections are opened
 * here rather than in the module.
 *
 * bin/import.php calls importMediaWrite() directly instead of going through here.
 *
 * @param array $item The speech, in session-file shape.
 * @param SafeMySQL|false $db  Platform connection; opened here when not given.
 * @param SafeMySQL|false $dbp Parliament connection; opened here when not given.
 * @param array|false $entityDump Pre-built entity dump, to avoid rebuilding it per speech.
 * @return array JSON:API response.
 */
function mediaAdd($item = false, $db = false, $dbp = false, $entityDump = false) {
    require_once(__DIR__ . "/../../../modules/import/media-writer.php");

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            return $db; // Error response from getApiDatabaseConnection
        }
    }

    if (!$dbp) {
        $dbp = getApiDatabaseConnection('parliament', is_array($item) ? ($item["parliament"] ?? null) : null);
        if (!is_object($dbp)) {
            return $dbp; // Error response from getApiDatabaseConnection
        }
    }

    try {
        return apiSuccessResponse(["id" => importMediaWrite($item, $db, $dbp, $entityDump)]);
    } catch (ImportValidationException $e) {
        return importValidationEnvelope($e);
    } catch (Exception $e) {
        return apiErrorResponse(
            503,
            2,
            "messageErrorDatabaseGeneric",
            "messageErrorMediaAddFailed",
            ["details" => $e->getMessage()]
        );
    }
}
function mediaChange($parameter) {
    global $config;

    if (!$parameter["id"]) {
        return apiErrorMissingParameter("id");
    }

    // Parse parliament from ID
    $IDInfos = optvParseStringId($parameter["id"]);
    if (!is_array($IDInfos) || !array_key_exists($IDInfos["parliament"], $config["parliament"])) {
        return apiErrorInvalidId("Media");
    }

    $parliament = $IDInfos["parliament"];

    // Connect to parliament database
    $dbp = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($dbp)) {
        return $dbp; // Error response from getApiDatabaseConnection
    }

    // Check if media exists
    $media = $dbp->getRow("SELECT * FROM ?n WHERE MediaID=?s LIMIT 1", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
        $parameter["id"]
    );
    
    if (!$media) {
        return apiErrorNotFound("Media");
    }

    // Define allowed parameters
    $allowedParams = array(
        "MediaOriginID", "MediaOriginMediaID", "MediaCreator", "MediaLicense",
        "MediaOrder", "MediaPublic", "MediaAligned", "MediaDateStart", "MediaDateEnd",
        "MediaDuration", "MediaVideoFileURI", "MediaAudioFileURI", "MediaSourcePage",
        "MediaThumbnailURI", "MediaThumbnailCreator", "MediaThumbnailLicense",
        "MediaAdditionalInformation"
    );

    // Filter parameters
    $params = $dbp->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Debug logging (remove when stable)
    // error_log("MediaChange Debug - Received parameters: " . json_encode($parameter));
    // error_log("MediaChange Debug - Filtered parameters: " . json_encode($params));

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "MediaAdditionalInformation") {
            // Handle JSON fields
            if (is_array($value)) {
                $updateParams[] = $dbp->parse("?n=?s", $key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } else if ($key === "MediaPublic" || $key === "MediaAligned") {
            // Convert boolean values to integers
            $updateParams[] = $dbp->parse("?n=?i", $key, ($value === true || $value === "true" || $value === "1") ? 1 : 0);
        } else if ($key === "MediaOrder") {
            // Convert to integer
            $updateParams[] = $dbp->parse("?n=?i", $key, (int)$value);
        } else if ($key === "MediaDuration") {
            // Convert to float
            $updateParams[] = $dbp->parse("?n=?f", $key, (float)$value);
        } else {
            $updateParams[] = $dbp->parse("?n=?s", $key, $value);
        }
    }

    if (empty($updateParams)) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorNoParameters"
        );
    }

    // Add last changed timestamp
    $updateParams[] = "MediaLastChanged=CURRENT_TIMESTAMP()";

    // Debug logging (remove when stable)
    // $updateQuery = "UPDATE " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] . " SET " . implode(", ", $updateParams) . " WHERE MediaID='" . $parameter["id"] . "'";
    // error_log("MediaChange Debug - Update query: " . $updateQuery);
    // error_log("MediaChange Debug - Update params: " . json_encode($updateParams));

    // Execute update
    $result = $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE MediaID=?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
        $parameter["id"]
    );

    // error_log("MediaChange Debug - Query executed, affected rows: " . $dbp->affectedRows());

    // Update OpenSearch index with the changed media item
    if ($dbp->affectedRows() > 0) {
        try {
            $db = getApiDatabaseConnection('platform');
            if (!is_object($db)) {
                throw new RuntimeException("Platform database unavailable.");
            }

            $updatedMediaData = mediaGetByID($parameter["id"], $db, $dbp);

            if ($updatedMediaData && !isset($updatedMediaData["errors"])) {
                require_once(__DIR__ . "/../../../modules/search-index/jobs.php");
                searchIndexUpdateItems($parliament, [$updatedMediaData]);
            } else {
                error_log("MediaChange: Could not retrieve updated media data for OpenSearch indexing");
            }
        } catch (Exception $e) {
            // The row is already updated; a failed reindex leaves the index
            // stale but must not turn the change itself into an error.
            error_log("MediaChange: OpenSearch update exception: " . $e->getMessage());
        }
    }

    return apiSuccessResponse();
}

/**
 * Search for media items
 * 
 * @param array $parameter Search parameters
 * @param object|false $db Optional platform database connection
 * @param object|false $dbp Optional parliament database connection
 * @return array Response in JSON:API format
 */
function mediaSearch($parameter, $db = false, $dbp = false) {
    global $config;

    require_once (__DIR__."/../../../modules/utilities/functions.php");
    require_once (__DIR__."/../../../modules/search/opensearch.php");

    $filteredParameters = filterAllowedSearchParams($parameter, 'media');

    $getAllResults = isset($filteredParameters["getAllResults"]) ? boolval($filteredParameters["getAllResults"]) : false;

    try {
        $search = searchSpeeches($filteredParameters, $getAllResults);

        // Search service (OpenSearch) unavailable — surface as an error so the
        // UI can show a "temporarily unavailable" notice instead of an empty
        // "no speeches found" result. Absent flag = available.
        if (isset($search["serviceAvailable"]) && $search["serviceAvailable"] === false) {
            return apiErrorResponse(
                503,
                3,
                "messageErrorSearchGenericTitle",
                "messageServiceSearchUnavailable"
            );
        }

        if (!isset($search["hits"]["hits"])) {
            return apiErrorResponse(
                503,
                3,
                "messageErrorSearchGenericTitle",
                "messageErrorSearchRequestDetail",
                ["details" => json_encode($search)]
            );
        }

        // Handle fields=id for returning only IDs
        if (isset($filteredParameters['fields']) && $filteredParameters['fields'] === 'id') {
            $idList = [];
            if (is_array($search["hits"]["hits"])) {
                foreach ($search["hits"]["hits"] as $hit) {
                    if (isset($hit["_source"]["id"])) {
                        $idList[] = ["type" => "media", "id" => $hit["_source"]["id"]];
                    }
                }
            }
            // Prepare meta for ID list response
            $metaForIds = [
                "requestStatus" => "success",
                "results" => [
                    "count" => count($idList),
                    "total" => $search["hits"]["total"]["value"] ?? 0,
                ]
            ];
            // Prepare links for ID list response
            $linksForIds = [
                "self" => $config["dir"]["api"]."/search/media?".getURLParameterFromArray($filteredParameters)
            ];
            return apiSuccessResponse(
                $idList,
                $metaForIds,
                $linksForIds
            );
        }

        // This is the path for full search results
        $return = [
            "data" => [],
            "meta" => [
                "requestStatus" => "success",
                "attributes" => [
                    "speechFirstDateStr" => $search["aggregations"]["dateFirst"]["value_as_string"] ?? null,
                    "speechFirstDateTimestamp" => $search["aggregations"]["dateFirst"]["value"] ?? null,
                    "speechLastDateStr" => $search["aggregations"]["dateLast"]["value_as_string"] ?? null,
                    "speechLastDateTimestamp" => $search["aggregations"]["dateLast"]["value"] ?? null,
                    "resultsPerFaction" => [], // Placeholder
                    "days" => []             // Placeholder
                ],
                "results" => [
                    "count" => ((gettype($search["hits"]["hits"]) == "array") || (gettype($search["hits"]["hits"]) == "object")) ? count($search["hits"]["hits"]) : 0,
                    "total" => $search["hits"]["total"]["value"],
                    "totalHits" => $search["hits"]["totalHits"] ?? 0
                ]
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/search/media?".getURLParameterFromArray($filteredParameters)
            ]
        ];

        // Process search hits into $return["data"]
        foreach ($search["hits"]["hits"] as $hit) {
            $resultData = $hit["_source"];
            $resultData["_score"] = $hit["_score"] ?? 0;
            $resultData["_highlight"] = $hit["highlight"] ?? [];
            $resultData["_finds"] = $hit["finds"] ?? [];
            $resultData["highlight_count"] = $hit["highlight_count"] ?? 0;

            $return["data"][] = $resultData;
        }

        // Process aggregations into $return["meta"]["attributes"]
        if (isset($search["aggregations"]["types_count"]["factions"]["terms"]["buckets"])) {
            foreach ($search["aggregations"]["types_count"]["factions"]["terms"]["buckets"] as $buckets) {
                $return["meta"]["attributes"]["resultsPerFaction"][$buckets["key"]] = $buckets["doc_count"];
            }
        }

        if (isset($search["aggregations"]["datesCount"]["buckets"])) {
            foreach($search["aggregations"]["datesCount"]["buckets"] as $day) {
                $return["meta"]["attributes"]["days"][$day["key_as_string"]] = $day;
            }
        }
        
        // Add electoral periods data for timeline visualization
        require_once(__DIR__ . "/electoral-period.php");
        
        $electoralPeriodsData = electoralPeriodGetItemsFromDB("all");
        $electoralPeriods = [];
        
        // Convert to JSON:API format consistent with electoralPeriodSearch output
        if (isset($electoralPeriodsData["data"]) && is_array($electoralPeriodsData["data"])) {
            foreach ($electoralPeriodsData["data"] as $ep) {
                $electoralPeriods[] = [
                    "type" => "electoralPeriod",
                    "id" => $ep["ElectoralPeriodID"],
                    "attributes" => [
                        "number" => (int)$ep["ElectoralPeriodNumber"],
                        "dateStart" => $ep["ElectoralPeriodDateStart"],
                        "dateEnd" => $ep["ElectoralPeriodDateEnd"],
                        "parliament" => $ep["Parliament"],
                        "parliamentLabel" => $ep["ParliamentLabel"]
                    ]
                ];
            }
        }
        
        $return["meta"]["attributes"]["electoralPeriods"] = $electoralPeriods;
        
        // Now $return["meta"] and $return["links"] are fully populated
        return apiSuccessResponse(
            $return["data"],
            $return["meta"],
            $return["links"]
        );

    } catch (Exception $e) {
        return apiErrorResponse(
            503,
            3,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => json_encode($search)]
        );
    }
}
