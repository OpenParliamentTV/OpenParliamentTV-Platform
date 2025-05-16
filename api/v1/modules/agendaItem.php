<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * @param string $id AgendaID
 * @return array
 */
function agendaItemGetByID($id = false) {
    global $config;

    if (!$id) {
        return createApiErrorMissingParameter('id');
    }

    $idInfo = getInfosFromStringID($id);
    if (!$idInfo) {
        return createApiErrorInvalidFormat('id', 'agendaItem');
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("AgendaItem");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT ai.*,
                                   sess.*,
                                   ep.*
                            FROM ?n AS ai
                            LEFT JOIN ?n AS sess
                                ON ai.AgendaItemSessionID=sess.SessionID
                            LEFT JOIN ?n AS ep
                                ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                            WHERE AgendaItemID=?i",
                            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                            $idInfo["id_part"]);

        if (!$item) {
            return createApiErrorNotFound("AgendaItem");
        }

        $agendaItemData = [
            "type" => "agendaItem",
            "id" => $parliament."-".$item["AgendaItemID"], // Preserve special ID format
            "attributes" => [
                "officialTitle" => $item["AgendaItemOfficialTitle"],
                "title" => $item["AgendaItemTitle"],
                "order" => (int)$item["AgendaItemOrder"],
                "parliament" => $parliament,
                "parliamentLabel" => $parliamentLabel
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/agendaItem/".$parliament."-".$item["AgendaItemID"]
            ],
            "relationships" => [
                "media" => [
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/media?agendaItemID=".$parliament."-".$item["AgendaItemID"]
                    ]
                ],
                "session" => [
                    "data" => [
                        "type" => "session",
                        "id" => $item["SessionID"],
                        "attributes" => [
                            "number" => $item["SessionNumber"],
                            "dateStart" => $item["SessionDateStart"],
                            "dateEnd" => $item["SessionDateEnd"]
                        ]
                    ],
                    "links" => [
                        "self" => $config["dir"]["api"]."/session/".$item["SessionID"]
                    ]
                ],
                "electoralPeriod" => [
                    "data" => [
                        "type" => "electoralPeriod",
                        "id" => $item["ElectoralPeriodID"],
                        "attributes" => [
                            "number" => $item["ElectoralPeriodNumber"],
                            "dateStart" => $item["ElectoralPeriodDateStart"],
                            "dateEnd" => $item["ElectoralPeriodDateEnd"]
                        ]
                    ],
                    "links" => [
                        "self" => $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"]
                    ]
                ]
            ]
        ];

        return createApiSuccessResponse($agendaItemData);

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Get an overview of agenda items
 * 
 * @param string $id AgendaItemID or "all"
 * @param int $limit Limit the number of results
 * @param int $offset Offset for pagination
 * @param string $search Search term
 * @param string $sort Sort field
 * @param string $order Sort order (ASC or DESC)
 * @param object $db Database connection
 * @param string $electoralPeriodID Filter by electoral period ID
 * @param string $sessionID Filter by session ID
 * @return array Raw data array with 'total' count and 'data' results
 */
function agendaItemGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false, $electoralPeriodID = false, $sessionID = false) {
    global $config;
    
    $allResults = [];
    $totalCount = 0;
    
    // If ID is not "all", get parliament and numeric ID using getInfosFromStringID
    $targetParliament = null;
    $numericID = $id;
    if ($id !== "all") {
        $idInfo = getInfosFromStringID($id);
        if (!$idInfo || !isset($config["parliament"][$idInfo["parliament"]])) {
            return [
                "total" => 0,
                "data" => []
            ];
        }
        $targetParliament = $idInfo["parliament"];
        $numericID = (int)$idInfo["id_part"]; // Convert to integer
    }
    
    // Get parliaments to search in
    $parliaments = $targetParliament ? [$targetParliament] : array_keys($config["parliament"]);
    
    foreach ($parliaments as $parliament) {
        $db = getApiDatabaseConnection('parliament', $parliament);
        if (!is_object($db)) {
            continue; // Skip this parliament if connection fails
        }
        
        try {
            // Build query conditions
            $conditions = [];
            
            if ($id === "all") {
                $conditions[] = "1";
            } else {
                $conditions[] = $db->parse("ai.AgendaItemID=?i", $numericID);
            }
            
            if (!empty($search)) {
                $conditions[] = $db->parse("(ai.AgendaItemTitle LIKE ?s OR ai.AgendaItemOfficialTitle LIKE ?s)", 
                    "%".$search."%", 
                    "%".$search."%"
                );
            }
            
            if (!empty($electoralPeriodID)) {
                $conditions[] = $db->parse("sess.SessionElectoralPeriodID=?s", $electoralPeriodID);
            }
            
            if (!empty($sessionID)) {
                // Revert to using the full string sessionID for comparison, assuming ai.AgendaItemSessionID is a full string ID.
                $conditions[] = $db->parse("ai.AgendaItemSessionID=?s", $sessionID);
            }
            
            $whereClause = implode(" AND ", $conditions);
            
            // Add sorting
            if (!empty($sort)) {
                $whereClause .= $db->parse(" ORDER BY ?n ".$order, $sort);
            }
            
            // Add pagination
            if ($limit != 0) {
                $whereClause .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
            }
            
            // Get total count for this parliament
            $parliamentCount = $db->getOne("SELECT COUNT(ai.AgendaItemID) as count FROM ?n AS ai LEFT JOIN ?n AS sess ON ai.AgendaItemSessionID=sess.SessionID LEFT JOIN ?n AS ep ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID WHERE ?p",
                $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                implode(" AND ", $conditions) // Use the same conditions for count
            );
            $totalCount += (int)$parliamentCount;
            
            // Get results for this parliament
            $items = $db->getAll("SELECT
                ai.AgendaItemID,
                ai.AgendaItemTitle,
                ai.AgendaItemOfficialTitle,
                ai.AgendaItemOrder,
                ai.AgendaItemSessionID,
                sess.SessionNumber,
                sess.SessionDateStart,
                sess.SessionDateEnd,
                ep.ElectoralPeriodID,
                ep.ElectoralPeriodNumber,
                ep.ElectoralPeriodDateStart,
                ep.ElectoralPeriodDateEnd
                FROM ?n AS ai
                LEFT JOIN ?n AS sess
                    ON ai.AgendaItemSessionID=sess.SessionID
                LEFT JOIN ?n AS ep
                    ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                WHERE ?p",
                $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                $whereClause
            );
            
            // Transform results to include parliament info
            foreach ($items as $item) {
                $fullAgendaItemID = $parliament . "-" . $item["AgendaItemID"];
                $allResults[] = [
                    "AgendaItemID" => $fullAgendaItemID,
                    "AgendaItemTitle" => $item["AgendaItemTitle"],
                    "AgendaItemOfficialTitle" => $item["AgendaItemOfficialTitle"],
                    "AgendaItemOrder" => $item["AgendaItemOrder"] === null ? "" : (int)$item["AgendaItemOrder"],
                    "AgendaItemSessionID" => $item["AgendaItemSessionID"],
                    "SessionNumber" => $item["SessionNumber"],
                    "SessionDateStart" => $item["SessionDateStart"],
                    "SessionDateEnd" => $item["SessionDateEnd"],
                    "ElectoralPeriodID" => $item["ElectoralPeriodID"],
                    "ElectoralPeriodNumber" => $item["ElectoralPeriodNumber"],
                    "ElectoralPeriodDateStart" => $item["ElectoralPeriodDateStart"],
                    "ElectoralPeriodDateEnd" => $item["ElectoralPeriodDateEnd"],
                    "Parliament" => $parliament,
                    "ParliamentLabel" => $config["parliament"][$parliament]["label"]
                ];
            }
            
        } catch (exception $e) {
            error_log("Error in agendaItemGetItemsFromDB for parliament $parliament: " . $e->getMessage());
            continue; // Skip this parliament on error
        }
    }
    
    return [
        "total" => $totalCount,
        "data" => $allResults
    ];
}

function agendaItemChange($params) {
    global $config;

    if (!isset($params["id"])) {
        return createApiErrorMissingParameter("id");
    }

    $idInfo = getInfosFromStringID($params["id"]);
    if (!$idInfo || !isset($config["parliament"][$idInfo["parliament"]])) {
        return createApiErrorInvalidID("AgendaItem");
    }

    $parliament = $idInfo["parliament"];
    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Get the numeric part of the agenda item ID for database operations
    $numericAgendaItemID = (int)$idInfo["id_part"];

    // Get current agenda item data to check existence and for default values
    $agendaItemDataResult = agendaItemGetItemsFromDB($params["id"]);
    if (empty($agendaItemDataResult["data"])) {
        return createApiErrorNotFound("AgendaItem");
    }
    $currentAgendaItem = $agendaItemDataResult["data"][0]; // Get the first (and only) result

    // Define allowed parameters
    $allowedParams = array(
        "AgendaItemTitle",
        "AgendaItemOfficialTitle",
        "AgendaItemOrder",
        "AgendaItemSessionID"
    );

    // Filter parameters
    $params = $db->filterArray($params, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        // Validate AgendaItemOrder
        if ($key === "AgendaItemOrder") {
            if ($value === "") {
                // Allow empty value to set NULL in database
                $updateParams[] = $db->parse("AgendaItemOrder=NULL");
            } else if (!is_numeric($value) || (int)$value <= 0) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageErrorInvalidNumber",
                    "messageErrorInvalidNumber",
                    ["type" => "AgendaItem"],
                    "[name='AgendaItemOrder']"
                );
            } else {
                // Check for uniqueness within session
                $sessionID = isset($params["AgendaItemSessionID"]) ? $params["AgendaItemSessionID"] : $currentAgendaItem["AgendaItemSessionID"];
                $existing = $db->getRow("SELECT AgendaItemID FROM ?n WHERE AgendaItemOrder = ?i AND AgendaItemSessionID = ?s AND AgendaItemID != ?i",
                    $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                    (int)$value,
                    $sessionID,
                    $numericAgendaItemID // Use numeric ID for comparison
                );
                if ($existing) {
                    return createApiErrorDuplicate('agenda item', 'AgendaItemOrder');
                }
                $updateParams[] = $db->parse("AgendaItemOrder=?i", (int)$value);
            }
        }

        // Validate session reference
        if ($key === "AgendaItemSessionID") {
            $session = $db->getRow("SELECT SessionID FROM ?n WHERE SessionID = ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $value
            );
            if (!$session) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageErrorInvalidSession",
                    "messageErrorInvalidSession",
                    null,
                    "[name='AgendaItemSessionID']"
                );
            }
            $updateParams[] = $db->parse("AgendaItemSessionID=?s", $value);
        }

        // Handle text fields
        if ($key === "AgendaItemTitle" || $key === "AgendaItemOfficialTitle") {
            $updateParams[] = $db->parse("?n=?s", $key, $value);
        }
    }

    if (empty($updateParams)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorNoParameters",
            "messageErrorNoParameters"
        );
    }

    try {
        // Execute update
        $result = $db->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE AgendaItemID=?i",
            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
            $numericAgendaItemID // Use numeric ID for the WHERE clause
        );

        if (!$result) {
            return createApiErrorResponse(
                500,
                1,
                "messageErrorDatabaseGeneric",
                "messageErrorDatabaseRequest"
            );
        }

        return createApiSuccessResponse([
            "message" => "Agenda item updated successfully"
        ]);
    } catch (exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorDatabaseGeneric",
            "messageErrorDatabaseRequest"
        );
    }
}

?>
