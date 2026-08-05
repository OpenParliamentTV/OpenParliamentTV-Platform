<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/entities.php");
require_once (__DIR__."/../../../modules/utilities/text-array-converters.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/entities/media.php");
require_once (__DIR__."/../../../modules/images/image-cache.php");

/**
 * Envelope wrapper over mediaGetNode() (modules/entities/media.php).
 *
 * The catch mapping reproduces the pre-extraction envelopes exactly: an
 * unparseable id and an id naming an unknown parliament are two different 422s,
 * and the two database connections report separately.
 *
 * @param string $id MediaID
 * @param object|false $db Optional platform database connection
 * @param object|false $dbp Optional parliament database connection
 * @return array Response in JSON:API format
 */
function mediaGetByID($id = false, $db = false, $dbp = false) {

    if (!$id) {
        return apiErrorResponse(
            422,
            1,
            "messageErrorMissingParameter",
            "messageErrorMissingParameter",
            ["parameter" => "id"]
        );
    }

    try {
        $node = mediaGetNode($id, $db, $dbp);
    } catch (OptvInvalidIdException $e) {
        return apiErrorResponse(
            422,
            1,
            $e->getReason() === "parliament" ? "messageErrorInvalidID" : "messageErrorIDParseError",
            $e->getReason() === "parliament" ? "messageErrorInvalidID" : "messageErrorIDParseError",
            ["type" => "Media"]
        );
    } catch (OptvEntityAccessException $e) {
        return apiErrorResponse(
            511,
            1,
            "messageAuthNotPermittedTitle",
            "messageErrorItemNotPublic"
        );
    } catch (OptvEntityException $e) {
        return apiErrorDatabaseConnection($e->getReason());
    }

    if ($node === null) {
        return apiErrorNotFound("Media");
    }

    return apiSuccessResponse($node, null, null, null);
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

            $updatedMediaNode = mediaGetNode($parameter["id"], $db, $dbp);

            if ($updatedMediaNode !== null) {
                require_once(__DIR__ . "/../../../modules/search-index/jobs.php");
                searchIndexUpdateItems($parliament, [["data" => $updatedMediaNode]]);
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

        // Page geometry for the pagination links, mirroring calculatePagination()
        // in modules/search/opensearch.php.
        $pageSize = $config["display"]["speechesPerPage"];
        if (isset($filteredParameters["limit"]) && is_numeric($filteredParameters["limit"]) && intval($filteredParameters["limit"]) > 0) {
            $pageSize = intval($filteredParameters["limit"]);
        }
        $currentPage = isset($filteredParameters["page"]) ? max(1, (int)$filteredParameters["page"]) : 1;

        if (isset($filteredParameters["id"]) && strlen($filteredParameters["id"]) > 3) {
            // calculatePagination() pins from=0 for an id lookup, so this is one page.
            $currentPage = 1;
            $totalPages = 1;
        } else {
            // OpenSearch rejects from+size past the result window, so never advertise
            // a page that would fail.
            $maxReachablePage = max(1, (int)floor(OPTV_SEARCH_MAX_RESULT_WINDOW / $pageSize));
            $totalPages = min((int)ceil(($search["hits"]["total"]["value"] ?? 0) / $pageSize), $maxReachablePage);
        }

        // getAllResults returns one complete set, so pagination links are meaningless there.
        $links = $getAllResults
            ? ["self" => $config["dir"]["api"]."/search/media?".getURLParameterFromArray($filteredParameters)]
            : apiPaginationLinks($config["dir"]["api"]."/search/media", $filteredParameters, $currentPage, $totalPages);

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
            return apiSuccessResponse(
                $idList,
                $metaForIds,
                $links
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
            "links" => $links
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
