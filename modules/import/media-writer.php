<?php
/**
 * The import write path: turns one session-file speech into rows in the
 * parliament database (electoral period, session, agenda item, media, text,
 * annotations) and the platform database (documents, people).
 *
 * importMediaWrite() is the entry point. It returns the new media id and throws
 * on failure (D2) — ImportValidationException when the speech is not importable,
 * ImportException or a database exception when the write itself fails. The
 * JSON:API envelope for `addItem&itemType=media` is applied by the thin
 * mediaAdd() wrapper in api/v1/modules/media.php; bin/import.php calls this
 * function directly.
 *
 * Reading media back out (mediaGetByID and its annotation builders) stays in the
 * API media module until 5d moves it to modules/entities/.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/text-array-converters.php");
require_once(__DIR__ . "/../../api/v1/utilities.php"); // reportConflict(), apiErrorResponse()

/**
 * Rebuild the JSON:API error envelope a validation failure used to return, for
 * the conflict record and for the API wrapper's response.
 */
function importValidationEnvelope(ImportValidationException $e)
{
    return apiErrorResponse(
        $e->getStatus(),
        $e->getErrorCode(),
        $e->getTitleKey(),
        $e->getDetailKey(),
        $e->getParams(),
        $e->getDomSelector()
    );
}

/**
 * Validates parameters for media addition
 * 
 * @param array $item Media item parameters to validate
 * @param array $config Global config
 * @throws ImportValidationException on the first problem found
 */
function importMediaValidate($item, $config) {
    // Validate parliament
    if (!array_key_exists($item["parliament"], $config["parliament"])) {
        throw ImportValidationException::missingParameter("parliament");
    }

    // Validate electoral period number
    if (!is_numeric($item["electoralPeriod"]["number"])) {
        throw ImportValidationException::missingParameter("electoralPeriod[number]");
    }

    // Validate session number
    if (!is_numeric($item["session"]["number"])) {
        throw ImportValidationException::missingParameter("session[number]");
    }

    // Handle title fallbacks
    $item["agendaItem"]["officialTitle"] = ($item["agendaItem"]["officialTitle"] ? $item["agendaItem"]["officialTitle"] : $item["agendaItem"]["title"]);
    $item["agendaItem"]["title"] = ($item["agendaItem"]["title"] ? $item["agendaItem"]["title"] : $item["agendaItem"]["officialTitle"]);

    // Validate agenda item titles
    if (!$item["agendaItem"]["officialTitle"]) {
        throw ImportValidationException::missingParameter("agendaItem[officialTitle]");
    }

    if (!$item["agendaItem"]["title"]) {
        throw ImportValidationException::missingParameter("agendaItem[title]");
    }

    // Validate media parameters
    if (!$item["media"]["videoFileURI"]) {
        throw ImportValidationException::missingParameter("media[videoFileURI]");
    }

    if (!$item["media"]["sourcePage"]) {
        throw ImportValidationException::missingParameter("media[sourcePage]");
    }

    // Validate date
    if (!$item["dateStart"]) {
        throw ImportValidationException::missingParameter("dateStart");
    }

    // Validate date range if both dates are provided
    if ($item["dateStart"] && $item["dateEnd"]) {
        // Same rule and same envelope fields as apiValidateDateRange().
        if (strtotime($item["dateStart"]) > strtotime($item["dateEnd"])) {
            throw new ImportValidationException(
                422, 1, "messageErrorInvalidDateRange", "messageErrorInvalidDateRange", [], "[name='dateStart']"
            );
        }
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
function importWriteElectoralPeriod($electoralPeriodData, $parliament, $dbp, $config) {
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
function importWriteSession($sessionData, $electoralPeriodID, $meta, $parliament, $dbp, $config) {
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
        // The session file wins: a session imported mid-sitting holds provisional
        // bounds (first/last speech so far), which later publications replace
        // with the official ones from the proceedings.
        $updates = [];

        if ($sessionData["dateStart"] && $tmpSession["SessionDateStart"] !== $sessionData["dateStart"]) {
            $updates[] = $dbp->parse("SessionDateStart=?s", $sessionData["dateStart"]);
        }

        if ($sessionData["dateEnd"] && $tmpSession["SessionDateEnd"] !== $sessionData["dateEnd"]) {
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
function importWriteAgendaItem($agendaItemData, $sessionID, $parliament, $dbp, $config) {
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
function importWriteMediaItem($mediaData, $agendaItemID, $sessionID, $dateStart, $dateEnd, $parliament, $dbp, $config) {
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
                json_encode($mediaData["additionalInformation"] ?? [])
            );
        } catch (Exception $e) {
            reportConflict([
                "type" => "media-import-error",
                "parliament" => $parliament,
                "mediaID" => "origin:" . ($mediaData["originID"] ?? '') . "/" . ($mediaData["originMediaID"] ?? ''),
                "entityKey" => "",
                "data" => [
                    "originID" => $mediaData["originID"] ?? null,
                    "originMediaID" => $mediaData["originMediaID"] ?? null,
                    "attemptedID" => $nextID ?? null,
                    "message" => $e->getMessage()
                ]
            ]);
            throw $e;
        }
    } else {
        $nextID = $tmpMediaItem["MediaID"];
        $updates = [];

        // The session file wins for the speech's start and end: rows written by
        // earlier imports can hold outdated values (e.g. without UTC offset),
        // which a re-import has to be able to correct.
        foreach ([["MediaDateStart", $dateStart], ["MediaDateEnd", $dateEnd]] as [$field, $value]) {
            if ($value && $tmpMediaItem[$field] !== $value) {
                $updates[] = $dbp->parse("?n=?s", $field, $value);
            }
        }

        // Every other field is only filled when still empty
        $updateFields = [
            ["MediaOriginID", $mediaData["originID"]],
            ["MediaOriginMediaID", $mediaData["originMediaID"]],
            ["MediaOrder", $mediaData["order"]],
            ["MediaAligned", $mediaData["aligned"]],
            ["MediaDuration", $mediaData["duration"]],
            ["MediaVideoFileURI", $mediaData["videoFileURI"]],
            ["MediaAudioFileURI", $mediaData["audioFileURI"]],
            ["MediaThumbnailURI", $mediaData["thumbnailURI"]],
            ["MediaThumbnailCreator", $mediaData["thumbnailCreator"]],
            ["MediaThumbnailLicense", $mediaData["thumbnailLicense"]],
            ["MediaAdditionalInformation", json_encode($mediaData["additionalInformation"] ?? [])]
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
 * Normalize stray formatting whitespace in a speech sentence.
 *
 * Source proceedings (e.g. the Bundestag's pretty-printed XML) can carry a
 * newline or tab plus indentation embedded mid-sentence (e.g.
 * "wir\n            auf", "mehrjährigen\t Bundesinvestitionsfonds"). This
 * collapses any whitespace run that contains a newline/CR/tab to a single
 * space, keeping every persisted copy (DB Text.TextBody, and thus the search
 * index and every downstream consumer: transcript, meta-image, WebVTT, feeds)
 * clean. Benign runs of plain spaces are left untouched (not the reported
 * defect, and they render fine). Safe for entities: NER annotations are keyed
 * by wid + time, not by character offsets into the text.
 *
 * @param string $text Raw sentence text
 * @return string Whitespace-normalized text
 */
function importNormalizeWhitespace($text) {
    return preg_replace('/[ \t]*[\r\n\t]+[ \t]*/', ' ', (string) $text);
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
function importRenderSentence($sentence, $entities = []) {
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
 * @param array $entityDump Entity dump
 * @param array $entityRemap Wikidata id corrections (source id => target id)
 * @return void
 */
function importWriteTextContent($textContent, $mediaID, $parliament, $dbp, $config, $entityDump, $entityRemap) {
    if (gettype($textContent["textBody"]) == "string") {
        $textContent["textBody"] = json_decode($textContent["textBody"], true);
    }

    foreach ($textContent["textBody"] as $textBodyIndex => $textBodyItem) {
        $outputHTML = '<p data-type="'.$textBodyItem['type'].'">';
        
        foreach ($textBodyItem['sentences'] as $sentenceKey => $sentence) {
            $entities = [];

            // Normalize source pretty-print artifacts (newlines + indentation
            // runs) in the sentence text, and write it back so both the stored
            // sentences[] array and the rebuilt paragraph HTML below are clean.
            if (is_array($sentence) && isset($sentence['text'])) {
                $sentence['text'] = importNormalizeWhitespace($sentence['text']);
                $textContent["textBody"][$textBodyIndex]["sentences"][$sentenceKey]["text"] = $sentence['text'];
            }

            // Process entities if they exist
            if (is_array($sentence["entities"]) && count($sentence["entities"]) > 0) {
                foreach ($sentence["entities"] as $entity) {
                    if (!$entity["wid"]) {
                        continue;
                    }

                    // Correct known NER mis-resolutions (party id where the
                    // faction is meant, regional variant where the platform
                    // tracks the national entity).
                    if (isset($entityRemap[$entity["wid"]])) {
                        $entity["wid"] = $entityRemap[$entity["wid"]];
                    }

                    if (!array_key_exists($entity["wid"], $entityDump["data"])) {
                        reportEntitySuggestion($entity["wid"], $entity["wtype"], $entity["label"], json_encode($entity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $mediaID, $db);
                    } else {
                        $entity["additionalInformation"]["confidence"] = $entity["score"];

                        $tmpAnnotation = array(
                            "AnnotationMediaID" => $mediaID,
                            "AnnotationType" => $entityDump["data"][$entity["wid"]]["type"],
                            "AnnotationResourceID" => (($entityDump["data"][$entity["wid"]]["type"] == "document") ? $entityDump["data"][$entity["wid"]]["optvID"] : $entity["wid"]),
                            "AnnotationContext" => (($entity["context"]) ? $entity["context"] : "NER"),  //TODO: Context?
                            "AnnotationFrametrailType" => (($entity["frametrailType"]) ? $entity["frametrailType"] : "Annotation"), //TODO Type?
                            "AnnotationTimeStart" => $sentence["timeStart"],
                            "AnnotationTimeEnd" => $sentence["timeEnd"],
                            "AnnotationCreator" => "NER",
                            "AnnotationTags" => $entity["tags"],
                            "AnnotationAdditionalInformation" => json_encode($entity["additionalInformation"])
                        );

                        $entities[] = $entity["wid"];

                        try {
                            $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"], $tmpAnnotation);
                        } catch (exception $e) {
                            reportConflict([
                                "type" => "annotation-import-error",
                                "parliament" => $parliament,
                                "mediaID" => $mediaID,
                                "entityKey" => "",
                                "data" => ["annotation" => $tmpAnnotation, "message" => $e->getMessage()]
                            ]);
                        }
                    }
                }
            }
            
            // Delete entities from text (maintaining exact behavior)
            unset($textContent["textBody"][$textBodyIndex]["sentences"][$sentenceKey]["entities"]);
            
            $outputHTML .= importRenderSentence($sentence, $entities);
        }
        
        $outputHTML .= '</p>';
        $textContent["textBody"][$textBodyIndex]["text"] = $outputHTML;
    }

    $textHash = hash("sha256", json_encode($textContent["textBody"]));

    //TODO Temp Fix - add hash afterwards

    /*
    $tmpTextItem = $dbp->getRow("SELECT * FROM ?n WHERE TextMediaID = ?s AND TextHash = ?s AND TextType=?s",
        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"], $nextID, $textHash, $textContent["type"]);
    */

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
            reportConflict([
                "type" => "text-import-error",
                "parliament" => $parliament,
                "mediaID" => $mediaID,
                "entityKey" => "",
                "data" => [
                    "op" => "add",
                    "textType" => $textContent["type"] ?? null,
                    "language" => $textContent["language"] ?? null,
                    "message" => $e->getMessage()
                ]
            ]);
            throw $e;
        }
    } else {
        
        /*
        * TODO Check whats up here
        $tmpTextItemUpdate = array();
        if ((!$tmpTextItem["TextOriginTextID"]) && ($textContent["originTextID"])) {
            $tmpTextItemUpdate[] = $dbp->parse("TextOriginTextID=?s", $textContent["originTextID"]);
        }
        if ((!$tmpTextItem["TextSourceURI"]) && ($textContent["sourceURI"])) {
            $tmpTextItemUpdate[] = $dbp->parse("TextSourceURI=?s", $textContent["sourceURI"]);
        }

        if ($tmpTextItemUpdate) {
            $tmpTextItemUpdate = "UPDATE " . $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"] . " SET " . implode(", ", $tmpTextItemUpdate) . " WHERE TextID = ?i";
            $dbp->query($tmpTextItemUpdate, $textContent["TextID"]);
        }
        */
        
        
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
            reportConflict([
                "type" => "text-import-error",
                "parliament" => $parliament,
                "mediaID" => $mediaID,
                "entityKey" => "",
                "data" => [
                    "op" => "update",
                    "textType" => $textContent["type"] ?? null,
                    "language" => $textContent["language"] ?? null,
                    "message" => $e->getMessage()
                ]
            ]);
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
 * @param array $entityDump Entity dump
 * @param array $entityRemap Wikidata id corrections (source id => target id)
 * @return void
 */
function importWriteTextContents($textContents, $mediaID, $parliament, $dbp, $config, $entityDump, $entityRemap) {
    if (!is_array($textContents)) {
        return;
    }

    foreach ($textContents as $textContent) {
        importWriteTextContent($textContent, $mediaID, $parliament, $dbp, $config, $entityDump, $entityRemap);
    }
}

/**
 * Process documents for a media item
 *
 * @param array $itemDocuments Array of documents
 * @param string $mediaID Media ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $db Platform database connection
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @return void
 */
function importWriteDocuments($itemDocuments, $mediaID, $parliament, $db, $dbp, $config) {
    if (is_array($itemDocuments)) {
        include_once(__DIR__."/../../api/v1/modules/document.php");

        foreach ($itemDocuments as $document) {
            if (!$document["sourceURI"]) {
                reportConflict([
                    "type" => "document-missing-source-uri",
                    "parliament" => $parliament,
                    "mediaID" => $mediaID,
                    "entityType" => "document",
                    "entityLabel" => $document["label"] ?? null,
                    "data" => $document
                ], $db);
                continue;
            } else {
                $tmpDocument = $db->getRow("SELECT * FROM ?n WHERE DocumentSourceURI = ?s", $config["platform"]["sql"]["tbl"]["Document"], $document["sourceURI"]);

                if (!$tmpDocument) {
                    $db->query("INSERT INTO ?n
                                SET
                                    DocumentType=?s,
                                    DocumentWikidataID=?s,
                                    DocumentLabel=?s,
                                    DocumentLabelAlternative=?s,
                                    DocumentAbstract=?s,
                                    DocumentThumbnailURI=?s,
                                    DocumentThumbnailCreator=?s,
                                    DocumentThumbnailLicense=?s,
                                    DocumentSourceURI=?s,
                                    DocumentEmbedURI=?s,
                                    DocumentAdditionalInformation=?s",
                        $config["platform"]["sql"]["tbl"]["Document"],
                        $document["type"],
                        $document["wikidataID"],
                        $document["label"],
                        (is_array($document["labelAlternative"]) ? json_encode($document["labelAlternative"]) : "[" . $document["labelAlternative"] . "]"),
                        (($document["abstract"]) ? $document["abstract"] : "undefined"),
                        $document["thumbnailURI"],
                        $document["thumbnailCreator"],
                        $document["thumbnailLicense"],
                        $document["sourceURI"],
                        $document["embedURI"],
                        json_encode($document["additionalInformation"]));
                    $tmpDocumentID = $db->insertId();
                } else {
                    $tmpDocumentID = $tmpDocument["DocumentID"];
                    $tmpDocumentUpdate = array();
                    if ((!$tmpDocument["DocumentWikidataID"]) && ($document["wikidataID"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentWikidataID=?s", $document["wikidataID"]);
                    }
                    if ((!$tmpDocument["DocumentLabelAlternative"]) && ($document["labelAlternative"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentLabelAlternative=?s", (is_array($document["labelAlternative"]) ? json_encode($document["labelAlternative"]) : "[" . $document["labelAlternative"] . "]"));
                    }
                    if (((!$tmpDocument["DocumentAbstract"]) || ($tmpDocument["DocumentAbstract"] == "undefined")) && ($document["abstract"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentAbstract=?s", $document["abstract"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailURI"]) && ($document["thumbnailURI"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailURI=?s", $document["thumbnailURI"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailCreator"]) && ($document["thumbnailCreator"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailCreator=?s", $document["thumbnailCreator"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailLicense"]) && ($document["thumbnailLicense"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailLicense=?s", $document["thumbnailLicense"]);
                    }
                    if ((!$tmpDocument["DocumentEmbedURI"]) && ($document["embedURI"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentEmbedURI=?s", $document["embedURI"]);
                    }
                    if ((!$tmpDocument["DocumentAdditionalInformation"]) && ($document["additionalInformation"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentAdditionalInformation=?s", json_encode($document["additionalInformation"]));
                    }

                    if ($tmpDocumentUpdate) {
                        $tmpDocumentUpdateQuery = "UPDATE " . $config["platform"]["sql"]["tbl"]["Document"] . " SET " . implode(", ", $tmpDocumentUpdate) . " WHERE DocumentID = ?i";
                        $db->query($tmpDocumentUpdateQuery, $tmpDocumentID);
                    }
                }

                $document["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";
                $document["context"] = ($document["context"]) ? $document["context"] : "proceedingsReference";
                $document["frametrailType"] = ($document["frametrailType"]) ? $document["frametrailType"] : "Annotation";

                $tmpDocumentAnnotation = $dbp->getRow("SELECT * FROM ?n WHERE AnnotationMediaID=?s AND AnnotationResourceID=?s AND AnnotationType=?s",
                    $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
                    $mediaID,
                    $tmpDocumentID,
                    "document");

                if (!$tmpDocumentAnnotation) {
                    $tmpAnnotationItem = array(
                        "AnnotationMediaID" => $mediaID,
                        "AnnotationType" => "document",
                        "AnnotationResourceID" => $tmpDocumentID,
                        "AnnotationContext" => $document["context"],
                        "AnnotationFrametrailType" => $document["frametrailType"],
                        "AnnotationTimeStart" => $document["timeStart"],
                        "AnnotationTimeEnd" => $document["timeEnd"],
                        "AnnotationCreator" => $document["creator"],
                        "AnnotationTags" => $document["tags"],
                        "AnnotationAdditionalInformation" => json_encode($document["additionalInformation"])
                    );

                    $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);
                }
            }
        }
    }
}

/**
 * Process people for a media item
 *
 * @param array $itemPeople Array of people
 * @param string $mediaID Media ID
 * @param string $parliament Parliament identifier
 * @param SafeMySQL $db Platform database connection
 * @param SafeMySQL $dbp Parliament database connection
 * @param array $config Global config
 * @param array $entityDump Entity dump
 * @return void
 */
function importWritePeople($itemPeople, $mediaID, $parliament, $db, $dbp, $config, $entityDump) {
    //TODO: Think about a better way to handle speeches without people

    $dbp->query("DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationType = ?s AND AnnotationContext != ?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
        $mediaID,
        "person",
        "NER");

    $dbp->query("DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationType = ?s AND AnnotationContext != ?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],
        $mediaID,
        "organisation",
        "NER");

    if (!is_array($itemPeople)) {
        $itemPeople = array();
    }

    if (count($itemPeople) < 1) {
        // reportConflict("Media", "importMediaWrite no person", $mediaID, "", "Media has no people.", $db); // This call was already commented out, so no change needed here
    }

    foreach ($itemPeople as $person) {
        // Media metadata and proceedings disagree on the main speaker (the
        // merger flags the proceedings-derived person as low-confidence) —
        // skip the person silently; deliberately not recorded as a conflict.
        if ($person["context"] == "main-proceeding-speaker") {
            continue;
        }

        if ((!$person["wid"]) || (!preg_match("/(Q|P)\d+/i", $person["wid"]))) {
            reportConflict([
                "type" => "person-missing-wikidata-id",
                "parliament" => $parliament,
                "mediaID" => $mediaID,
                "entityType" => "person",
                "entityLabel" => $person["label"] ?? null,
                "data" => $person
            ], $db);
            continue;
        }

        if (!array_key_exists($person["wid"], $entityDump["data"])) {
            // Assuming reportEntitySuggestion needs separate update or uses a compatible reportConflict
            reportEntitySuggestion($person["wid"], "PERSON", $person["label"], json_encode($person, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $mediaID, $db);
            continue;
        }

        //Add Person Annotation
        $person["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";

        if (!$person["context"]) {
            reportConflict([
                "type" => "person-missing-context",
                "parliament" => $parliament,
                "mediaID" => $mediaID,
                "entityType" => "person",
                "entityWid" => $person["wid"],
                "entityLabel" => $person["label"] ?? null,
                "data" => $person
            ], $db);
            $person["context"] = "unknown";
        }

        $person["additionalInformation"]["role"] = $person["role"];

        $tmpAnnotationPerson = array(
            "AnnotationMediaID" => $mediaID,
            "AnnotationType" => "person",
            "AnnotationResourceID" => $person["wid"],
            "AnnotationContext" => $person["context"],
            "AnnotationFrametrailType" => (($person["frametrailType"]) ? $person["frametrailType"] : "Annotation"),
            "AnnotationTimeStart" => $person["timeStart"],
            "AnnotationTimeEnd" => $person["timeEnd"],
            "AnnotationCreator" => $person["creator"],
            "AnnotationTags" => $person["tags"],
            "AnnotationAdditionalInformation" => json_encode($person["additionalInformation"])
        );

        $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"], $tmpAnnotationPerson);

        if ((array_key_exists("faction", $person)) && (is_array($person["faction"]))) {
            if ((!$person["faction"]["wid"]) || (!preg_match("/(Q|P)\d+/i", $person["faction"]["wid"]))) {
                // An entirely empty faction object (no wid, no label) means the
                // person legitimately has no faction here — e.g. speaking in a
                // government role (minister) — so it is not a conflict. Only a
                // named faction without a wid is one.
                $factionLabel = trim((string)($person["faction"]["label"] ?? ""));
                if ($factionLabel !== "") {
                    reportConflict([
                        "type" => "faction-missing-wikidata-id",
                        "parliament" => $parliament,
                        "mediaID" => $mediaID,
                        "entityType" => "organisation",
                        "entityLabel" => $factionLabel,
                        "data" => $person
                    ], $db);
                }
                continue;
            }

            if (!array_key_exists($person["faction"]["wid"], $entityDump["data"])) {
                // Assuming reportEntitySuggestion needs separate update or uses a compatible reportConflict
                reportEntitySuggestion($person["faction"]["wid"], "FACTION", $person["faction"]["label"], json_encode($person["faction"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $mediaID, $db);
                continue;
            }

            $tmpAnnotationItem = array(
                "AnnotationMediaID" => $mediaID,
                "AnnotationType" => "organisation",
                "AnnotationResourceID" => $person["faction"]["wid"],
                "AnnotationContext" => $person["context"] . "-faction",
                "AnnotationFrametrailType" => "Annotation",
                "AnnotationCreator" => ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system",
                "AnnotationTags" => "",
                "AnnotationAdditionalInformation" => ""
            );

            $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$parliament]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);
        }
    }
}

function importMediaWrite($item, $db, $dbp, $entityDump = false, $entityRemap = null) {
    global $config;

    // Connections are the caller's responsibility: the API wrapper opens them
    // (and owns the envelope for a failure to do so), the importer already has
    // them open for the whole run.
    if (!is_object($db) || !is_object($dbp)) {
        throw new ImportException("importMediaWrite() requires open platform and parliament connections.");
    }

    try {
        importMediaValidate($item, $config);
    } catch (ImportValidationException $e) {
        // Recorded so the admin conflict list shows why a speech was skipped.
        // Stored in envelope shape, which is what existing rows hold.
        $itemMedia = (is_array($item) && is_array($item["media"] ?? null)) ? $item["media"] : [];
        reportConflict([
            "type" => "media-validation-error",
            "parliament" => (is_array($item) ? ($item["parliament"] ?? '') : ''),
            "mediaID" => "origin:" . ($itemMedia["originID"] ?? '') . "/" . ($itemMedia["originMediaID"] ?? ''),
            "entityKey" => "",
            "data" => [
                "errors" => importValidationEnvelope($e),
                "originID" => $itemMedia["originID"] ?? null,
                "originMediaID" => $itemMedia["originMediaID"] ?? null,
                "agendaItemID" => (is_array($item) ? ($item["agendaItem"]["officialID"] ?? null) : null)
            ]
        ], $db);
        throw $e;
    }

    include_once(__DIR__."/../../api/v1/modules/person.php");
    include_once(__DIR__."/../../api/v1/modules/organisation.php");
    include_once(__DIR__."/../../api/v1/modules/term.php");

    {
        // Process electoral period
        $item["electoralPeriod"] = importWriteElectoralPeriod($item["electoralPeriod"], $item["parliament"], $dbp, $config);

        // Process session
        $item["session"]["SessionID"] = importWriteSession($item["session"], $item["electoralPeriod"], $item["meta"], $item["parliament"], $dbp, $config);

        // Process agenda item
        $item["agendaItem"]["id"] = importWriteAgendaItem($item["agendaItem"], $item["session"]["SessionID"], $item["parliament"], $dbp, $config);

        // Process media item
        $nextID = importWriteMediaItem($item["media"], $item["agendaItem"]["id"], $item["session"]["SessionID"], $item["dateStart"], $item["dateEnd"], $item["parliament"], $dbp, $config);

        // Auto-clean: remove this media from all import-conflict groups. The
        // processing below re-reports every conflict that still applies (which
        // re-adds the media); groups left without any media are deleted.
        require_once(__DIR__."/../../api/v1/modules/conflict.php");
        conflictClearMedia([
            $nextID,
            "origin:" . ($item["media"]["originID"] ?? '') . "/" . ($item["media"]["originMediaID"] ?? '')
        ], $db);

        /**
         * TODO TEMP
         * as long the speech["debug"]["confidence"] parameter is missing, we need to check if there is a person with ["context"] = "main-proceeding-speaker"
         */

        /*
        if ((!$item["people"]) || (gettype($item["people"]) != "array")) {
            $item["people"] = array();
        }

        $confidence = 1;

        foreach ($item["people"] as $person) {
            if ($person["context"] == "main-proceeding-speaker") {
                $confidence = 0.5;
            }
        }
        */

        // NER regularly returns a party id where the speech means the faction —
        // the entity_remap table corrects those before annotations are written.
        // An importer walking a whole session passes the map in; the null check
        // is deliberate, because an empty map is the normal case and testing for
        // falsiness would re-query the table for every speech.
        if ($entityRemap === null) {
            require_once (__DIR__."/../../api/v1/modules/entity-remap.php");
            $entityRemap = entityRemapGetMap($item["parliament"], $db);
        }

        if (!$entityDump) {
            require_once (__DIR__."/../../api/v1/modules/entity-dump.php");
            $entityDump = entityDumpGet(array("type"=>"all","wiki"=>true,"wikikeys"=>"true"),$db);
        }
        
        // DE alignment pipeline: only import text when media-speech link is confident.
        // Other sources (e.g. AT) omit debug.confidence — import text when textContents are present.
        $hasAlignmentMetadata = isset($item["debug"]["confidence"]) && array_key_exists("linkedMediaIndexes", $item["debug"]);
        $passesAlignmentCheck = $hasAlignmentMetadata
            && ($item["debug"]["confidence"] == 1)
            && (count($item["debug"]["linkedMediaIndexes"]) == 1);
        $shouldProcessText = !empty($item["textContents"]) && (!$hasAlignmentMetadata || $passesAlignmentCheck);

        if ($shouldProcessText) {
            // Clear existing NER annotations
            $dbp->query(
                "DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationContext = ?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"],
                $nextID,
                "NER"
            );

            // Process text contents
            importWriteTextContents($item["textContents"], $nextID, $item["parliament"], $dbp, $config, $entityDump, $entityRemap);
        }

        // Process documents if provided
        importWriteDocuments($item["documents"] ?? [], $nextID, $item["parliament"], $db, $dbp, $config);

        // Process people
        importWritePeople($item["people"] ?? [], $nextID, $item["parliament"], $db, $dbp, $config, $entityDump);

        return $nextID;
    }
}
