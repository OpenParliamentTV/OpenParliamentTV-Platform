<?php
/**
 * Media node builder and its annotation helpers.
 *
 * Plain data, no envelope (D2): mediaGetNode() returns the JSON:API node or
 * null when there is no such media item, and throws for the cases it cannot
 * answer at all. The envelope for `getItem&itemType=media` is applied by
 * mediaGetByID() in api/v1/modules/media.php.
 *
 * The non-public check lives here rather than in the wrapper on purpose: every
 * consumer of a media node (transcript, export, IIIF, feeds) depends on it, and
 * one of them serving a hidden speech would be a data leak, not a cosmetic
 * inconsistency. It reads $_SESSION, which is why it signals through
 * OptvEntityAccessException instead of returning something the caller has to
 * remember to test.
 *
 * Thumbnail URIs of annotated entities go through imageCacheUrl() here, inside
 * the builders, so a caller taking the plain node still gets proxied images.
 * (Media's own thumbnailURI is not proxied — it never was.)
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvParseStringId(), optvIsCli()
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../images/image-cache.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()

/**
 * @param string $id MediaID, e.g. "DE-0200172058".
 * @param SafeMySQL|false $db  Platform connection; opened here when not supplied.
 * @param SafeMySQL|false $dbp Parliament connection; opened here when not supplied.
 * @return array|null The node, or null when no such media item exists.
 * @throws OptvInvalidIdException|OptvEntityException|OptvEntityAccessException
 */
function mediaGetNode($id, $db = false, $dbp = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing media id.");
    }

    $IDInfos = optvParseStringId($id);
    if (!$IDInfos) {
        throw new OptvInvalidIdException("Could not parse media id {$id}.");
    }

    $parliament = $IDInfos["parliament"];
    if (!array_key_exists($parliament, $config["parliament"])) {
        throw new OptvInvalidIdException("Unknown parliament in media id {$id}.", "parliament");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    if (!is_object($db)) {
        try {
            $db = optvDbConnection('platform');
        } catch (OptvDbException $e) {
            throw new OptvEntityException("Platform database unavailable.", "platform");
        }
    }

    if (!is_object($dbp)) {
        try {
            $dbp = optvDbConnection('parliament', $parliament);
        } catch (OptvDbException $e) {
            throw new OptvEntityException("Parliament database unavailable.", "parliament");
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
        return null;
    }

    // Check media access permissions
    $userRole = null;
    if (isset($_SESSION) && isset($_SESSION["userdata"]) && isset($_SESSION["userdata"]["role"])) {
        $userRole = $_SESSION["userdata"]["role"];
    }

    if (($item["MediaPublic"] == 0) && ($userRole != "admin") && (!optvIsCli())) {
        throw new OptvEntityAccessException("Media {$id} is not public.");
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
    $annotations = mediaSortAnnotations($annotations);

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
                    $tmpAnnotationItem = mediaAnnotationDocument($annotation, $db);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["documents"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "organisation":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = mediaAnnotationOrganisation($annotation, $db);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["organisations"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "term":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = mediaAnnotationTerm($annotation, $db);
                    if ($tmpAnnotationItem) {
                        array_push($tmpResources, $annotation["AnnotationResourceID"]);
                        array_push($data["relationships"]["terms"]["data"], $tmpAnnotationItem);
                    }
                }
                break;

            case "person":
                if (!in_array($annotation["AnnotationResourceID"], $tmpResources)) {
                    $tmpAnnotationItem = mediaAnnotationPerson($annotation, $db);
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

    return $data;
}

/**
 * Build the node for a document annotation.
 *
 * @param array $annotation The annotation row.
 * @param SafeMySQL $db Platform database connection.
 * @return array|false The annotation node, or false when the document is gone.
 */
function mediaAnnotationDocument($annotation, $db) {
    global $config;

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
 * Build the node for an organisation annotation.
 *
 * @param array $annotation The annotation row.
 * @param SafeMySQL $db Platform database connection.
 * @return array|false The annotation node, or false when the organisation is gone.
 */
function mediaAnnotationOrganisation($annotation, $db) {
    global $config;

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
 * Build the node for a term annotation.
 *
 * @param array $annotation The annotation row.
 * @param SafeMySQL $db Platform database connection.
 * @return array|false The annotation node, or false when the term is gone.
 */
function mediaAnnotationTerm($annotation, $db) {
    global $config;

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
 * Build the node for a person annotation. Unlike the other three this one also
 * carries the person's party and faction, which is why it joins twice.
 *
 * @param array $annotation The annotation row.
 * @param SafeMySQL $db Platform database connection.
 * @return array|false The annotation node, or false when the person is gone.
 */
function mediaAnnotationPerson($annotation, $db) {
    global $config;

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
 * Order raw annotation rows so the main speaker comes first, then the chair,
 * then everything else; people before other types within a rank. This is what
 * makes relationships.people[0] the speaker of the speech.
 *
 * @param array $array Annotation rows as read from the annotation table.
 * @return array
 */
function mediaSortAnnotations($array = array()){
    usort($array, function($a, $b) {
        $a_val = 0;
        $b_val = 0;

        if(isset($a['AnnotationContext'])) {
            if($a['AnnotationContext'] === 'main-speaker') {
                $a_val = 2;
            } elseif($a['AnnotationContext'] === 'president' || $a['AnnotationContext'] === 'vice-president') {
                $a_val = 1;
            }
        }

        if(isset($b['AnnotationContext'])) {
            if($b['AnnotationContext'] === 'main-speaker') {
                $b_val = 2;
            } elseif(($b['AnnotationContext'] === 'vice-president') || ($b['AnnotationContext'] === 'president')) {
                $b_val = 1;
            }
        }

        if($a_val === $b_val) {
            if($a['AnnotationType'] === 'person' && $b['AnnotationType'] === 'person') {
                $a_id = isset($a['id']) ? $a['id'] : '';
                $b_id = isset($b['id']) ? $b['id'] : '';
                return strcmp($a_id, $b_id);
            } elseif($a['AnnotationType'] === 'person') {
                return -1;
            } elseif($b['AnnotationType'] === 'person') {
                return 1;
            } else {
                return 0;
            }
        } else {
            return $b_val - $a_val;
        }
    });
    return $array;
}

/**
 * The person a media node's `main-speaker` annotation points at.
 *
 * Pure array lookup over an already-built node — no database — so templates and
 * renderers can call it on a search hit as well as on a getItem node.
 *
 * @param array $annotationsArray  node["annotations"]["data"]
 * @param array $peopleArray       node["relationships"]["people"]["data"]
 * @return array|null
 */
function mediaMainSpeaker($annotationsArray, $peopleArray) {

    if (!is_array($annotationsArray) || !is_array($peopleArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker") {
            foreach ($peopleArray as $person) {
                if ($person["id"] == $annotation["id"]) {
                    return $person;
                }
            }
        }
    }

    return null;
}

/**
 * The organisation a media node's `main-speaker-faction` annotation points at.
 *
 * @param array $annotationsArray    node["annotations"]["data"]
 * @param array $organisationsArray  node["relationships"]["organisations"]["data"]
 * @return array|null
 */
function mediaMainFaction($annotationsArray, $organisationsArray) {

    if (!is_array($annotationsArray) || !is_array($organisationsArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker-faction") {
            foreach ($organisationsArray as $organisation) {
                if ($organisation["id"] == $annotation["id"]) {
                    return $organisation;
                }
            }
        }
    }

    return null;
}

/**
 * The role recorded on the `main-speaker` annotation ("interim-president",
 * "Bundeskanzler", …), or null when it carries none. Render it through
 * translateContextValue().
 *
 * @param array $annotationsArray node["annotations"]["data"]
 * @return string|null
 */
function mediaMainSpeakerRole($annotationsArray) {

    if (!is_array($annotationsArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker") {
            if (isset($annotation["attributes"]["additionalInformation"]["role"]) &&
                !empty($annotation["attributes"]["additionalInformation"]["role"])) {
                return $annotation["attributes"]["additionalInformation"]["role"];
            }
        }
    }

    return null;
}

/**
 * Convert a search hit's `_finds` into the player's segment shape.
 *
 * Finds arrive from OpenSearch highlighting as {data-start, data-end, class,
 * context} with string timecodes; the player wants {start, end} floats. The
 * 0.3s pulled off the end reproduces what player.php has always done, so a
 * segment stored from a search hit plays back exactly like the search hit did.
 *
 * @param array $finds node["_finds"]
 * @return array|null Ordered [{start, end}, …], or null when there are none
 */
function mediaFindsToSegments($finds) {

    if (!is_array($finds) || empty($finds)) {
        return null;
    }

    $segments = [];
    foreach ($finds as $find) {
        if (!isset($find["data-start"]) || $find["data-start"] === null) {
            continue;
        }
        $segments[] = [
            "start" => (float)$find["data-start"],
            "end" => (float)$find["data-end"] - 0.3,
        ];
    }

    if (empty($segments)) {
        return null;
    }

    usort($segments, function ($a, $b) { return $a["start"] <=> $b["start"]; });

    return $segments;
}

/**
 * Build the compact queue node behind `fields=queue`.
 *
 * One row of the player's queue panel: who spoke, for which faction, on which
 * agenda item, how long, when — plus the segments to jump between. Everything
 * the full search result carries beyond that (transcripts above all) is dropped
 * here rather than shipped to the browser.
 *
 * Consumed by mediaSearch() for a search-derived queue and by
 * playlistResolveItems() for a stored one, so both render identically.
 *
 * @param array $hit A processed search hit (projected _source plus _finds)
 * @return array Queue node
 */
function mediaQueueNode($hit) {

    $annotations = $hit["annotations"]["data"] ?? [];
    $speaker = mediaMainSpeaker($annotations, $hit["relationships"]["people"]["data"] ?? []);
    $faction = mediaMainFaction($annotations, $hit["relationships"]["organisations"]["data"] ?? []);
    $role = mediaMainSpeakerRole($annotations);
    $segments = mediaFindsToSegments($hit["_finds"] ?? []);

    // No colour here on purpose: faction colours are generated as CSS keyed on
    // data-faction (content/head.php), so consumers colour themselves from
    // factionID the same way .partyIndicator does everywhere else.
    return [
        "type" => "media",
        "id" => $hit["id"] ?? null,
        "attributes" => [
            "parliament" => $hit["attributes"]["parliament"] ?? null,
            "dateStart" => $hit["attributes"]["dateStart"] ?? null,
            "duration" => isset($hit["attributes"]["duration"]) ? (float)$hit["attributes"]["duration"] : null,
            "title" => $hit["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?? null,
            "speaker" => $speaker["attributes"]["label"] ?? null,
            "speakerID" => $speaker["id"] ?? null,
            "speakerRole" => $role === null ? null : translateContextValue($role),
            "faction" => $faction["attributes"]["label"] ?? null,
            "factionID" => $faction["id"] ?? null,
            "findCount" => $segments === null ? 0 : count($segments),
        ],
        "segments" => $segments,
    ];
}
