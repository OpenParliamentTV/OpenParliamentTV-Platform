<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.conflicts.php");
require_once (__DIR__."/../../../modules/utilities/functions.entities.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

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
            "thumbnailURI" => $ditem["DocumentThumbnailURI"],
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
            "thumbnailURI" => $ditem["OrganisationThumbnailURI"],
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
            "thumbnailURI" => $ditem["TermThumbnailURI"],
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
            "thumbnailURI" => $pitem["PersonThumbnailURI"],
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
        return createApiErrorResponse(
            422,
            1,
            "messageErrorMissingParameter",
            "messageErrorMissingParameter",
            ["parameter" => "id"]
        );
    }

    // Parse and validate ID
    $IDInfos = getInfosFromStringID($id);
    if (!$IDInfos) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorIDParseError",
            "messageErrorIDParseError",
            ["type" => "Media"]
        );
    }

    $parliament = $IDInfos["parliament"];
    if (!array_key_exists($parliament, $config["parliament"])) {
        return createApiErrorResponse(
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
        return createApiErrorNotFound("Media");
    }

    // Check media access permissions
    if (($item["MediaPublic"] == 0) && ($_SESSION["userdata"]["role"] != "admin") && (!is_cli())) {
        return createApiErrorResponse(
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

    $links = [
        "self" => $config["dir"]["api"]."/media/".$item["MediaID"]
    ];

    $relationships = [
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
                        array_push($relationships["documents"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "organisation":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processOrganisationAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($relationships["organisations"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "term":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processTermAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($relationships["terms"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "person":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = processPersonAnnotation($annotation, $db, $config);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($relationships["people"]["data"], $tmpAnnotationItem);
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

    // Add relationship links
    $relationships["documents"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=document";
    $relationships["organisations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=organisation";
    $relationships["terms"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=term";
    $relationships["people"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$data["id"]."&type=person";

    return createApiSuccessResponse($data, null, $links, $relationships);
}

/**
 * Process annotations for a media item
 * 
 * @param array $annotations Array of annotations
 * @param string $mediaID Media ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $db Platform database connection
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return void
 */
function processAnnotations($annotations, $mediaID, $parliament, $db, $dbp, $config) {
    if (!is_array($annotations)) {
        return;
    }

    foreach ($annotations as $annotation) {
        try {
            // Check for existing annotation
            $tmpAnnotation = $dbp->getRow(
                "SELECT * FROM ?n 
                WHERE 
                    AnnotationMediaID = ?s 
                    AND AnnotationType = ?s 
                    AND AnnotationResourceID = ?s 
                    AND AnnotationTimeStart = ?s 
                    AND AnnotationTimeEnd = ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
                $mediaID,
                $annotation["type"],
                $annotation["resourceID"],
                $annotation["timeStart"],
                $annotation["timeEnd"]
            );

            if (!$tmpAnnotation) {
                $dbp->query(
                    "INSERT INTO ?n SET
                        AnnotationMediaID = ?s,
                        AnnotationType = ?s,
                        AnnotationResourceID = ?s,
                        AnnotationTimeStart = ?s,
                        AnnotationTimeEnd = ?s,
                        AnnotationContext = ?s,
                        AnnotationAdditionalInformation = ?s",
                    $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
                    $mediaID,
                    $annotation["type"],
                    $annotation["resourceID"],
                    $annotation["timeStart"],
                    $annotation["timeEnd"],
                    $annotation["context"],
                    json_encode($annotation["additionalInformation"])
                );
            }
        } catch (Exception $e) {
            reportConflict(
                "Media",
                "mediaAdd Annotation Add failed",
                $mediaID,
                "",
                "Could not add Annotation to Media. Annotation: " . json_encode($annotation) . " ||| Error:" . $e->getMessage(),
                null
            );
            throw $e;
        }
    }
}

/**
 * Add Media
 */

/**
 * Validates parameters for media addition
 * 
 * @param array $item Media item parameters to validate
 * @param array $config Global config
 * @return array|true Returns true if valid, or error response array if invalid
 */
function validateMediaAddParameters($item, $config) {
    // Validate parliament
    if (!array_key_exists($item["parliament"], $config["parliament"])) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "parliament"]
        );
    }

    // Validate electoral period number
    if (!is_numeric($item["electoralPeriod"]["number"])) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "electoralPeriod[number]"]
        );
    }

    // Validate session number
    if (!is_numeric($item["session"]["number"])) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "session[number]"]
        );
    }

    // Handle title fallbacks
    $item["agendaItem"]["officialTitle"] = ($item["agendaItem"]["officialTitle"] ? $item["agendaItem"]["officialTitle"] : $item["agendaItem"]["title"]);
    $item["agendaItem"]["title"] = ($item["agendaItem"]["title"] ? $item["agendaItem"]["title"] : $item["agendaItem"]["officialTitle"]);

    // Validate agenda item titles
    if (!$item["agendaItem"]["officialTitle"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "agendaItem[officialTitle]"]
        );
    }

    if (!$item["agendaItem"]["title"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "agendaItem[title]"]
        );
    }

    // Validate media parameters
    if (!$item["media"]["videoFileURI"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "media[videoFileURI]"]
        );
    }

    if (!$item["media"]["sourcePage"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "media[sourcePage]"]
        );
    }

    // Validate date
    if (!$item["dateStart"]) {
        return createApiErrorResponse(
            400,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorMissingParameter",
            ["parameter" => "dateStart"]
        );
    }

    // Validate date range if both dates are provided
    if ($item["dateStart"] && $item["dateEnd"]) {
        $dateRangeValidation = validateApiDateRange($item["dateStart"], $item["dateEnd"]);
        if ($dateRangeValidation !== true) {
            return $dateRangeValidation;
        }
    }

    return true;
}

/**
 * Process session data - creates or updates session record
 * 
 * @param array $sessionData Session data
 * @param string $electoralPeriodID Electoral period ID
 * @param array $meta Meta data for fallback dates
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return string Returns the session ID
 */
function processSession($sessionData, $electoralPeriodID, $meta, $parliament, $dbp, $config) {
    $sessionID = $electoralPeriodID . sprintf("%04d", $sessionData["number"]);
    
    // Handle date fallbacks for sessions without proceedings
    $sessionData["dateStart"] = ($sessionData["dateStart"] ? $sessionData["dateStart"] : $meta["dateStart"]);
    $sessionData["dateEnd"] = ($sessionData["dateEnd"] ? $sessionData["dateEnd"] : $meta["dateEnd"]);
    
    $tmpSession = $dbp->getRow(
        "SELECT * FROM ?n WHERE SessionID = ?s LIMIT 1", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Session"], 
        $sessionID
    );

    if (!$tmpSession) {
        // Create new session
        $dbp->query(
            "INSERT INTO ?n SET
                SessionID = ?s,
                SessionNumber = ?s,
                SessionElectoralPeriodID = ?s,
                SessionDateStart = ?s,
                SessionDateEnd = ?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
            $sessionID,
            $sessionData["number"],
            $electoralPeriodID,
            $sessionData["dateStart"],
            $sessionData["dateEnd"]
        );
    } else {
        // Update existing session if needed
        $updates = [];
        
        if (!$tmpSession["SessionDateStart"] && $sessionData["dateStart"]) {
            $updates[] = $dbp->parse("SessionDateStart=?s", $sessionData["dateStart"]);
        }

        if (!$tmpSession["SessionDateEnd"] && $sessionData["dateEnd"]) {
            $updates[] = $dbp->parse("SessionDateEnd=?s", $sessionData["dateEnd"]);
        }

        if ($updates) {
            $updateQuery = "UPDATE " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] 
                        . " SET " . implode(", ", $updates) 
                        . " WHERE SessionID = ?s";
            $dbp->query($updateQuery, $sessionID);
        }
    }

    return $sessionID;
}

/**
 * Process agenda item data - creates or updates agenda item record
 * 
 * @param array $agendaItemData Agenda item data
 * @param string $sessionID Session ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return int Returns the agenda item ID
 */
function processAgendaItem($agendaItemData, $sessionID, $parliament, $dbp, $config) {
    $tmpAgendaItem = $dbp->getRow(
        "SELECT * FROM ?n
        WHERE
            AgendaItemSessionID = ?s
        AND
            AgendaItemOfficialTitle LIKE ?s
        AND
            AgendaItemTitle LIKE ?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
        $sessionID,
        $agendaItemData["officialTitle"],
        $agendaItemData["title"]
    );

    if (!$tmpAgendaItem) {
        // Create new agenda item
        $dbp->query(
            "INSERT INTO ?n SET
                AgendaItemSessionID = ?s,
                AgendaItemOfficialTitle = ?s,
                AgendaItemTitle = ?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
            $sessionID,
            $agendaItemData["officialTitle"],
            $agendaItemData["title"]
        );

        $agendaItemID = $dbp->insertId();

        // Set order if provided
        if (!empty($agendaItemData["order"])) {
            $dbp->query(
                "UPDATE ?n SET
                    AgendaItemOrder = ?i
                WHERE
                    AgendaItemID = ?i",
                $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                $agendaItemData["order"],
                $agendaItemID
            );
        }
    } else {
        $agendaItemID = $tmpAgendaItem["AgendaItemID"];

        // Update order if not set and provided
        if (!$tmpAgendaItem["AgendaItemOrder"] && !empty($agendaItemData["order"])) {
            $dbp->query(
                "UPDATE ?n SET
                    AgendaItemOrder = ?i
                WHERE
                    AgendaItemID = ?i",
                $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                $agendaItemData["order"],
                $agendaItemID
            );
        }
    }

    return $agendaItemID;
}

/**
 * Process media item data - creates or updates media record
 * 
 * @param array $mediaData Media item data
 * @param int $agendaItemID Agenda item ID
 * @param string $sessionID Session ID
 * @param string $dateStart Start date
 * @param string $dateEnd End date
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return string Returns the media ID
 */
function processMediaItem($mediaData, $agendaItemID, $sessionID, $dateStart, $dateEnd, $parliament, $dbp, $config) {
    $tmpMediaItem = $dbp->getRow(
        "SELECT * FROM ?n WHERE MediaSourcePage = ?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
        $mediaData["sourcePage"]
    );

    if (!$tmpMediaItem) {
        // Generate new media ID
        $nextID = $dbp->getOne(
            "SELECT MediaID FROM ?n WHERE MediaID LIKE ?s ORDER BY MediaID DESC LIMIT 1",
            $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
            $sessionID . "%"
        );

        if (!$nextID) {
            $nextID = $sessionID . sprintf("%03d", 1);
        } else {
            $nextID = ((int)substr($nextID, -3)) + 1;
            $nextID = $sessionID . sprintf("%03d", $nextID);
        }

        try {
            $dbp->query(
                "INSERT INTO ?n SET
                    MediaID = ?s,
                    MediaOriginID = ?s,
                    MediaOriginMediaID = ?s,
                    MediaAgendaItemID = ?s,
                    MediaCreator = ?s,
                    MediaLicense = ?s,
                    MediaOrder = 0,
                    MediaAligned = ?i,
                    MediaPublic = 1,
                    MediaDateStart = ?s,
                    MediaDateEnd = ?s,
                    MediaDuration = ?i,
                    MediaVideoFileURI = ?s,
                    MediaAudioFileURI = ?s,
                    MediaSourcePage = ?s,
                    MediaThumbnailURI = ?s,
                    MediaThumbnailCreator = ?s,
                    MediaThumbnailLicense = ?s,
                    MediaAdditionalInformation = ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["Media"],
                $nextID,
                $mediaData["originID"],
                $mediaData["originMediaID"],
                $agendaItemID,
                $mediaData["creator"],
                $mediaData["license"],
                ($mediaData["aligned"] ? 1 : 0),
                $dateStart,
                $dateEnd,
                ($mediaData["duration"] ? (float)$mediaData["duration"] : 0),
                $mediaData["videoFileURI"],
                $mediaData["audioFileURI"],
                $mediaData["sourcePage"],
                $mediaData["thumbnailURI"],
                $mediaData["thumbnailCreator"],
                $mediaData["thumbnailLicense"],
                json_encode($mediaData["additionalInformation"])
            );
        } catch (Exception $e) {
            reportConflict(
                "Media",
                "mediaAdd failed",
                "",
                "",
                "Could not add Media with ID: originID: " . $mediaData["originID"] . 
                ", originMediaID: " . $mediaData["originMediaID"] . 
                " (new id:" . $nextID . ") Error:" . $e->getMessage(),
                null
            );
            throw $e;
        }
    } else {
        $nextID = $tmpMediaItem["MediaID"];
        $updates = [];

        // Build update array for non-empty new values
        $updateFields = [
            ["MediaOriginID", $mediaData["originID"]],
            ["MediaOriginMediaID", $mediaData["originMediaID"]],
            ["MediaOrder", $mediaData["order"]],
            ["MediaAligned", $mediaData["aligned"]],
            ["MediaDateStart", $dateStart],
            ["MediaDateEnd", $dateEnd],
            ["MediaDuration", $mediaData["duration"]],
            ["MediaVideoFileURI", $mediaData["videoFileURI"]],
            ["MediaAudioFileURI", $mediaData["audioFileURI"]],
            ["MediaThumbnailURI", $mediaData["thumbnailURI"]],
            ["MediaThumbnailCreator", $mediaData["thumbnailCreator"]],
            ["MediaThumbnailLicense", $mediaData["thumbnailLicense"]],
            ["MediaAdditionalInformation", json_encode($mediaData["additionalInformation"])]
        ];

        foreach ($updateFields as [$field, $value]) {
            if (!$tmpMediaItem[$field] && $value) {
                if (in_array($field, ["MediaOrder", "MediaDuration"])) {
                    $updates[] = $dbp->parse("?n=?i", $field, $value);
                } elseif ($field === "MediaAligned") {
                    $updates[] = $dbp->parse("?n=?i", $field, $value ? 1 : 0);
                } else {
                    $updates[] = $dbp->parse("?n=?s", $field, $value);
                }
            }
        }

        if ($updates) {
            $updateQuery = "UPDATE " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] 
                        . " SET " . implode(", ", $updates) 
                        . " WHERE MediaID = ?s";
            $dbp->query($updateQuery, $nextID);
        }
    }

    return $nextID;
}

/**
 * Process text content for a media item
 * 
 * @param array $textContent Text content data
 * @param string $mediaID Media ID
 * @param array $sentence Sentence data
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return string Returns the processed HTML output
 */
function processTextContentSentence($sentence, $entities = []) {
    $timeAttributes = '';
    if (isset($sentence['timeStart']) && isset($sentence['timeEnd'])) {
        $timeAttributes = ' class="timebased" data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';
    }
    
    // Keep exact same entity attribute handling
    $tempEntityAttribute = "";  // Maintain commented functionality
    // If entities should be at the html als data-attribute - uncomment this.
    // $tempEntityAttribute = ((count($entities) > 0) ? ' data-entities="'.json_encode($entities).'"' : "");
    
    $sentenceText = (is_array($sentence)) ? $sentence['text'] : $sentence;
    return '<span'.$timeAttributes.$tempEntityAttribute.'>'.$sentenceText.' </span>';
}

/**
 * Process text content for a media item
 * 
 * @param array $textContent Text content data
 * @param string $mediaID Media ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return void
 */
function processTextContent($textContent, $mediaID, $parliament, $dbp, $config) {
    if (gettype($textContent["textBody"]) == "string") {
        $textContent["textBody"] = json_decode($textContent["textBody"], true);
    }

    foreach ($textContent["textBody"] as $textBodyIndex => $textBodyItem) {
        $outputHTML = '<p data-type="'.$textBodyItem['type'].'">';
        
        foreach ($textBodyItem['sentences'] as $sentenceKey => $sentence) {
            $entities = [];
            
            // Process entities if they exist
            if (is_array($sentence["entities"]) && count($sentence["entities"]) > 0) {
                foreach ($sentence["entities"] as $entity) {
                    if (!$entity["wid"]) {
                        continue;
                    }
                    $entities[] = $entity["wid"];
                }
            }
            
            // Delete entities from text (maintaining exact behavior)
            unset($textContent["textBody"][$textBodyIndex]["sentences"][$sentenceKey]["entities"]);
            
            $outputHTML .= processTextContentSentence($sentence, $entities);
        }
        
        $outputHTML .= '</p>';
        $textContent["textBody"][$textBodyIndex]["text"] = $outputHTML;
    }

    $textHash = hash("sha256", json_encode($textContent["textBody"]));

    // Check for existing text content
    $tmpTextItem = $dbp->getRow(
        "SELECT * FROM ?n WHERE TextMediaID = ?s AND TextType=?s AND TextLanguage=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Text"],
        $mediaID,
        $textContent["type"],
        $textContent["language"]
    );

    if (!$tmpTextItem) {
        try {
            $dbp->query(
                "INSERT INTO ?n SET
                    TextOriginTextID = ?s,
                    TextMediaID = ?s,
                    TextType = ?s,
                    TextBody = ?s,
                    TextSourceURI = ?s,
                    TextCreator = ?s,
                    TextLicense = ?s,
                    TextHash = ?s,
                    TextLanguage = ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["Text"],
                $textContent["originTextID"],
                $mediaID,
                $textContent["type"],
                json_encode($textContent["textBody"]),
                $textContent["sourceURI"],
                $textContent["creator"],
                $textContent["license"],
                $textHash,
                $textContent["language"]
            );
        } catch (Exception $e) {
            reportConflict(
                "Media",
                "mediaAdd TextContent Add failed",
                $mediaID,
                "",
                "Could not add Text to Media. TextItem: " . json_encode($textContent) . " ||| Error:" . $e->getMessage(),
                null
            );
            throw $e;
        }
    } else {
        try {
            $dbp->query(
                "UPDATE ?n SET
                    TextOriginTextID = ?s,
                    TextBody = ?s,
                    TextSourceURI = ?s,
                    TextCreator = ?s,
                    TextLicense = ?s,
                    TextHash = ?s
                WHERE TextID = ?i",
                $config["parliament"][$parliament]["sql"]["tbl"]["Text"],
                $textContent["originTextID"],
                json_encode($textContent["textBody"]),
                $textContent["sourceURI"],
                $textContent["creator"],
                $textContent["license"],
                $textHash,
                $tmpTextItem["TextID"]
            );
        } catch (Exception $e) {
            reportConflict(
                "Media",
                "mediaAdd TextContent Update failed",
                $mediaID,
                "",
                "Could not update Text of Media. TextItem: " . json_encode($textContent) . " ||| Error:" . $e->getMessage(),
                null
            );
            throw $e;
        }
    }
}

/**
 * Process text contents for a media item
 * 
 * @param array $textContents Array of text contents
 * @param string $mediaID Media ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return void
 */
function processTextContents($textContents, $mediaID, $parliament, $dbp, $config) {
    if (!is_array($textContents)) {
        return;
    }

    foreach ($textContents as $textContent) {
        processTextContent($textContent, $mediaID, $parliament, $dbp, $config);
    }
}

function mediaAdd($item = false, $db = false, $dbp = false, $entityDump = false) {
    global $config;

    // Validate input parameters
    $validationResult = validateMediaAddParameters($item, $config);
    if ($validationResult !== true) {
        return $validationResult;
    }

    // Connect to platform database if not provided
    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            return $db; // Error response from getApiDatabaseConnection
        }
    }

    // Connect to parliament database if not provided
    if (!$dbp) {
        $dbp = getApiDatabaseConnection('parliament', $item["parliament"]);
        if (!is_object($dbp)) {
            return $dbp; // Error response from getApiDatabaseConnection
        }
    }

    include_once(__DIR__."/person.php");
    include_once(__DIR__."/organisation.php");
    include_once(__DIR__."/term.php");

    try {
        // Process electoral period
        $item["electoralPeriod"] = processElectoralPeriod($item["electoralPeriod"], $item["parliament"], $dbp, $config);

        // Process session
        $item["session"]["SessionID"] = processSession($item["session"], $item["electoralPeriod"], $item["meta"], $item["parliament"], $dbp, $config);

        // Process agenda item
        $item["agendaItem"]["id"] = processAgendaItem($item["agendaItem"], $item["session"]["SessionID"], $item["parliament"], $dbp, $config);

        // Process media item
        $nextID = processMediaItem(
            $item["media"],
            $item["agendaItem"]["id"],
            $item["session"]["SessionID"],
            $item["dateStart"],
            $item["dateEnd"],
            $item["parliament"],
            $dbp,
            $config
        );

        // Process text contents if confidence check passes
        if (($item["debug"]["confidence"] == 1) && (count($item["debug"]["linkedMediaIndexes"]) == 1)) {
            // Clear existing NER annotations
            $dbp->query(
                "DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationContext = ?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"],
                $nextID,
                "NER"
            );

            // Process text contents
            processTextContents($item["textContents"], $nextID, $item["parliament"], $dbp, $config);
        }

        // Process annotations
        if (isset($item["annotations"]) && is_array($item["annotations"])) {
            processAnnotations($item["annotations"], $nextID, $item["parliament"], $db, $dbp, $config);
        }

        // Process documents if provided
        if (isset($item["documents"]) && is_array($item["documents"])) {
            foreach ($item["documents"] as $document) {
                documentAdd($document, $db);
            }
        }

        return createApiSuccessResponse(["id" => $nextID]);

    } catch (Exception $e) {
        return createApiErrorResponse(
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
        return createApiErrorMissingParameter("id");
    }

    // Parse parliament from ID
    $IDInfos = getInfosFromStringID($parameter["id"]);
    if (!is_array($IDInfos) || !array_key_exists($IDInfos["parliament"], $config["parliament"])) {
        return createApiErrorInvalidID("Media");
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
        return createApiErrorNotFound("Media");
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
        return createApiErrorResponse(
            422,
            1,
            "messageErrorParameterMissingTitle",
            "messageErrorNoParameters"
        );
    }

    // Add last changed timestamp
    $updateParams[] = "MediaLastChanged=CURRENT_TIMESTAMP()";

    // Execute update
    $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE MediaID=?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
        $parameter["id"]
    );

    return createApiSuccessResponse();
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
    require_once (__DIR__."/../../../modules/search/functions.php");

    $filteredParameters = filterAllowedSearchParams($parameter, 'media');

    try {
        $search = searchSpeeches($filteredParameters);

        if (!isset($search["hits"]["hits"])) {
            return createApiErrorResponse(
                503,
                3,
                "messageErrorSearchGenericTitle",
                "messageErrorSearchRequestDetail",
                ["details" => json_encode($search)]
            );
        }

        $return = [
            "data" => [],
            "meta" => [
                "requestStatus" => "success",
                "attributes" => [
                    "speechFirstDateStr" => $search["aggregations"]["dateFirst"]["value_as_string"],
                    "speechFirstDateTimestamp" => $search["aggregations"]["dateFirst"]["value"],
                    "speechLastDateStr" => $search["aggregations"]["dateLast"]["value_as_string"],
                    "speechLastDateTimestamp" => $search["aggregations"]["dateLast"]["value"],
                    "resultsPerFaction" => [],
                    "days" => []
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

        // Process search hits
        foreach ($search["hits"]["hits"] as $hit) {
            $resultData = $hit["_source"];
            $resultData["_score"] = $hit["_score"];
            $resultData["_highlight"] = $hit["highlight"];
            $resultData["_finds"] = $hit["finds"];
            $resultData["highlight_count"] = $hit["highlight_count"];

            $return["data"][] = $resultData;
        }

        // Process aggregations
        foreach ($search["aggregations"]["types_count"]["factions"]["terms"]["buckets"] as $buckets) {
            $return["meta"]["attributes"]["resultsPerFaction"][$buckets["key"]] = $buckets["doc_count"];
        }

        foreach($search["aggregations"]["datesCount"]["buckets"] as $day) {
            $return["meta"]["attributes"]["days"][$day["key_as_string"]] = $day;
        }

        return createApiSuccessResponse(
            $return["data"],
            [
                "requestStatus" => "success",
                "attributes" => [
                    "speechFirstDateStr" => $search["aggregations"]["dateFirst"]["value_as_string"],
                    "speechFirstDateTimestamp" => $search["aggregations"]["dateFirst"]["value"],
                    "speechLastDateStr" => $search["aggregations"]["dateLast"]["value_as_string"],
                    "speechLastDateTimestamp" => $search["aggregations"]["dateLast"]["value"],
                    "resultsPerFaction" => $return["meta"]["attributes"]["resultsPerFaction"],
                    "days" => $return["meta"]["attributes"]["days"]
                ],
                "results" => [
                    "count" => ((gettype($search["hits"]["hits"]) == "array") || (gettype($search["hits"]["hits"]) == "object")) ? count($search["hits"]["hits"]) : 0,
                    "total" => $search["hits"]["total"]["value"],
                    "totalHits" => $search["hits"]["totalHits"] ?? 0
                ]
            ],
            [
                "self" => $config["dir"]["api"]."/search/media?".getURLParameterFromArray($filteredParameters)
            ]
        );

    } catch (Exception $e) {
        return createApiErrorResponse(
            503,
            3,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => json_encode($search)]
        );
    }
}

/**
 * Process electoral period data - creates or updates electoral period record
 * 
 * @param array $electoralPeriodData Electoral period data
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return string Returns the electoral period ID
 */
function processElectoralPeriod($electoralPeriodData, $parliament, $dbp, $config) {
    $tmpElectoralPeriod = $dbp->getRow(
        "SELECT * FROM ?n WHERE ElectoralPeriodNumber = ?i LIMIT 1", 
        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
        $electoralPeriodData["number"]
    );

    if (!$tmpElectoralPeriod) {
        // Create new electoral period
        $tmpElectoralPeriodID = $parliament . "-" . sprintf("%03d", $electoralPeriodData["number"]);
        $dbp->query(
            "INSERT INTO ?n
            SET
                ElectoralPeriodNumber = ?i,
                ElectoralPeriodID = ?s,
                ElectoralPeriodDateStart = ?s,
                ElectoralPeriodDateEnd = ?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
            $electoralPeriodData["number"],
            $tmpElectoralPeriodID,
            $electoralPeriodData["dateStart"],
            $electoralPeriodData["dateEnd"]
        );
    } else {
        // Update existing electoral period if needed
        $tmpElectoralPeriodID = $tmpElectoralPeriod["ElectoralPeriodID"];
        $updates = [];

        if (!$tmpElectoralPeriod["ElectoralPeriodDateStart"] && $electoralPeriodData["dateStart"]) {
            $updates[] = $dbp->parse("ElectoralPeriodDateStart=?s", $electoralPeriodData["dateStart"]);
        }

        if (!$tmpElectoralPeriod["ElectoralPeriodDateEnd"] && $electoralPeriodData["dateEnd"]) {
            $updates[] = $dbp->parse("ElectoralPeriodDateEnd=?s", $electoralPeriodData["dateEnd"]);
        }

        if ($updates) {
            $updateQuery = "UPDATE " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] 
                        . " SET " . implode(", ", $updates) 
                        . " WHERE ElectoralPeriodID = ?s";
            $dbp->query($updateQuery, $tmpElectoralPeriodID);
        }
    }

    return $tmpElectoralPeriodID;
}

?>
