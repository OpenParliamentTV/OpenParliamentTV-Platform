<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");

/**
 * @param string $id SessionID
 * @return array
 */
function sessionGetByID($id = false) {
    global $config;

    if (!$id) {
        return createApiErrorMissingParameter('id');
    }

    $idInfo = getInfosFromStringID($id);
    if (!$idInfo || $idInfo["type"] !== "session") {
        return createApiErrorInvalidFormat('id', 'session');
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("Session");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    try {
        $item = $db->getRow("SELECT
                            sess.*,
                            ep.*
                          FROM ?n AS sess
                          LEFT JOIN ?n as ep
                          ON
                            sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                          WHERE sess.SessionID=?s",
                            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                            $id);

        if (!$item) {
            return createApiErrorNotFound("Session");
        }

        $sessionNumericPartForAgendaLookup = $idInfo["id_part"];
        $agendaItems = $db->getAll("SELECT * FROM ?n WHERE AgendaItemSessionID=?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
            $id // Use the full session ID string
        );

        $sessionData = [
            "type" => "session",
            "id" => $id,
            "attributes" => [
                "number" => (int)$item["SessionNumber"],
                "dateStart" => $item["SessionDateStart"],
                "dateEnd" => $item["SessionDateEnd"],
                "parliament" => $parliament,
                "parliamentLabel" => $parliamentLabel
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/session/".$id
            ],
            "relationships" => [
                "media" => [
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/media?sessionID=".$id
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

        if ($agendaItems) {
            $sessionData["relationships"]["agendaItems"] = [
                "data" => array_map(function($agendaItem) use ($config, $parliament) {
                    // Harmonize AgendaItemID construction with agendaItem.php (use "-")
                    $fullAgendaItemID = $parliament . "-" . $agendaItem["AgendaItemID"];
                    return [
                        "type" => "agendaItem",
                        "id" => $agendaItem["AgendaItemID"],
                        "attributes" => [
                            "officialTitle" => $agendaItem["AgendaItemOfficialTitle"],
                            "title" => $agendaItem["AgendaItemTitle"],
                            "order" => $agendaItem["AgendaItemOrder"]
                        ],
                        "links" => [
                            "self" => $config["dir"]["api"]."/agendaItem/".$fullAgendaItemID
                        ]
                    ];
                }, $agendaItems),
                "links" => [
                    "self" => $config["dir"]["api"]."/search/agendaItem?sessionID=".$id
                ]
            ];
        }

        return createApiSuccessResponse($sessionData);

    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

/**
 * Get an overview of sessions
 * 
 * @param string $id SessionID or "all"
 * @param int $limit Limit the number of results
 * @param int $offset Offset for pagination
 * @param string $search Search term
 * @param string $sort Sort field
 * @param string $order Sort order (ASC or DESC)
 * @param object $db Database connection
 * @param string $electoralPeriodID Filter by electoral period ID
 * @return array Raw data array with 'total' count and 'data' results
 */
function sessionGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false, $electoralPeriodID = false) {
    global $config;
    
    $allResults = array();
    $totalCount = 0;
    
    $targetParliament = null;
    if ($id !== "all") {
        $idInfo = getInfosFromStringID($id); // Used for $targetParliament
        if (!$idInfo || !isset($config["parliament"][$idInfo["parliament"]])) {
            return [
                "total" => 0,
                "data" => []
            ];
        }
        $targetParliament = $idInfo["parliament"];
    }
    
    $parliaments = $targetParliament ? array($targetParliament) : array_keys($config["parliament"]);
    
    foreach ($parliaments as $parliament) {
        $db_connection = getApiDatabaseConnection('parliament', $parliament); // Renamed to avoid conflict with $db param
        if (!is_object($db_connection)) {
            continue; 
        }
        
        try {
            $conditions = [];
            $query_params = [];

            if ($id === "all") {
                $conditions[] = "1";
            } else {
                if ($parliament === $targetParliament) {
                    $conditions[] = "sess.SessionID=?s"; // Query Session.SessionID as string
                    $query_params[] = $id; // Use full $id string
                } else {
                    continue; 
                }
            }
            
            if (!empty($search)) {
                $conditions[] = "(sess.SessionNumber LIKE ?s)";
                $query_params[] = "%" . $search . "%";
            }

            if (!empty($electoralPeriodID)) {
                $conditions[] = "sess.SessionElectoralPeriodID=?s"; // Query as string
                $query_params[] = $electoralPeriodID;
            }
            
            $whereClauseSQL = implode(" AND ", $conditions);
            
            $countQueryBase = "SELECT COUNT(sess.SessionID) as count FROM ?n AS sess";
            $current_query_params_for_count = [$config["parliament"][$parliament]["sql"]["tbl"]["Session"]];
            if (!empty($whereClauseSQL) && $whereClauseSQL !== "1") {
                $countQuery = $countQueryBase . " WHERE " . $whereClauseSQL;
                $current_query_params_for_count = array_merge($current_query_params_for_count, $query_params);
            } else {
                $countQuery = $countQueryBase;
            }
            $parliamentCount = $db_connection->getOne($countQuery, ...$current_query_params_for_count);
            $totalCount += (int)$parliamentCount;

            $itemsQuerySQL = "SELECT
                sess.SessionID,
                sess.SessionNumber,
                sess.SessionDateStart,
                sess.SessionDateEnd,
                sess.SessionElectoralPeriodID,
                ep.ElectoralPeriodNumber,
                ep.ElectoralPeriodDateStart,
                ep.ElectoralPeriodDateEnd
                FROM ?n AS sess
                LEFT JOIN ?n as ep
                    ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID";
            
            $current_query_params_for_items = [
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"]
            ];

            if (!empty($whereClauseSQL) && $whereClauseSQL !== "1") {
                $itemsQuerySQL .= " WHERE " . $whereClauseSQL;
                $current_query_params_for_items = array_merge($current_query_params_for_items, $query_params);
            }

            if (!empty($sort)) {
                $itemsQuerySQL .= " ORDER BY ?n " . ($order === 'DESC' ? 'DESC' : 'ASC');
                $current_query_params_for_items[] = $sort; 
            }
            
            if ($limit != 0) { // Use $limit from function params, not hardcoded
                $itemsQuerySQL .= " LIMIT ?i, ?i";
                $current_query_params_for_items[] = $offset;
                $current_query_params_for_items[] = $limit;
            }
            
            $items = $db_connection->getAll($itemsQuerySQL, ...$current_query_params_for_items);
            
            foreach ($items as $item) {
                $allResults[] = [
                    "SessionID" => $item["SessionID"], // Use SessionID from DB as is (it's full string)
                    "SessionNumber" => $item["SessionNumber"],
                    "SessionDateStart" => $item["SessionDateStart"],
                    "SessionDateEnd" => $item["SessionDateEnd"],
                    "SessionElectoralPeriodID" => $item["SessionElectoralPeriodID"], // Use as is (full string)
                    "ElectoralPeriodNumber" => $item["ElectoralPeriodNumber"],
                    "ElectoralPeriodDateStart" => $item["ElectoralPeriodDateStart"],
                    "ElectoralPeriodDateEnd" => $item["ElectoralPeriodDateEnd"],
                    "Parliament" => $parliament,
                    "ParliamentLabel" => $config["parliament"][$parliament]["label"]
                ];
            }
            
        } catch (exception $e) {
            error_log("Error in sessionGetItemsFromDB for parliament $parliament: " . $e->getMessage());
            continue; 
        }
    }
    
    return [
        "total" => $totalCount,
        "data" => $allResults
    ];
}

function sessionChange($params) {
    global $config;

    if (!isset($params["id"])) {
        return createApiErrorMissingParameter("id");
    }

    $idInfo = getInfosFromStringID($params["id"]); // Still useful for $parliament and $idInfo["id_part"] if needed for SessionNumber context
    if (!$idInfo || $idInfo["type"] !== "session") {
        return createApiErrorInvalidFormat('id', 'session');
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("Session");
    }

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return createApiErrorDatabaseConnection();
    }

    // Get the session to validate it exists. Query Session.SessionID as string.
    $session = $db->getRow("SELECT * FROM ?n WHERE SessionID = ?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
        $params["id"] // Use full $params["id"] string
    );
    if (!$session) {
        return createApiErrorNotFound("Session");
    }

    $updates = [];
    $values = [];

    if (isset($params['SessionNumber'])) {
        $numberValidation = validateApiNumber($params['SessionNumber'], 'SessionNumber');
        if ($numberValidation !== true) {
            return $numberValidation;
        }

        // Check for duplicates within the same electoral period. SessionID is string.
        // SessionID in DB is full string. $session['SessionElectoralPeriodID'] is full string.
        $existing = $db->getRow(
            "SELECT SessionID FROM ?n WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?s AND SessionID != ?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
            (int)$params['SessionNumber'],
            $session['SessionElectoralPeriodID'], // This is the full string EP ID from the fetched session
            $params['id'] // Use full $params["id"] string for comparison
        );
        if ($existing) {
            return createApiErrorDuplicate('session', 'number');
        }

        $updates[] = "SessionNumber = ?i";
        $values[] = (int)$params['SessionNumber'];
    }

    if (isset($params['SessionDateStart']) || isset($params['SessionDateEnd'])) {
        $startDate = isset($params['SessionDateStart']) ? $params['SessionDateStart'] : $session['SessionDateStart'];
        $endDate = isset($params['SessionDateEnd']) ? $params['SessionDateEnd'] : $session['SessionDateEnd'];

        $dateValidation = validateApiDateRange($startDate, $endDate, 'SessionDateStart');
        if ($dateValidation !== true) {
            return $dateValidation;
        }

        if (isset($params['SessionDateStart'])) {
            $updates[] = "SessionDateStart = ?s";
            $values[] = $params['SessionDateStart'];
        }
        if (isset($params['SessionDateEnd'])) {
            $updates[] = "SessionDateEnd = ?s";
            $values[] = $params['SessionDateEnd'];
        }
    }

    if (isset($params['SessionElectoralPeriodID'])) {
        // $params['SessionElectoralPeriodID'] is expected to be a full string ID
        $epInfo = getInfosFromStringID($params['SessionElectoralPeriodID']);
        if (!$epInfo || $epInfo["type"] !== "electoralPeriod" || $epInfo["parliament"] !== $parliament) {
            return createApiErrorInvalidFormat('SessionElectoralPeriodID', 'electoralPeriod');
        }

        $epExists = $db->getOne(
            "SELECT 1 FROM ?n WHERE ElectoralPeriodID = ?s", // ElectoralPeriodID is string
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
            $params['SessionElectoralPeriodID'] // Use full string ID
        );
        if (!$epExists) {
            return createApiErrorNotFound("ElectoralPeriod");
        }

        $updates[] = "SessionElectoralPeriodID = ?s"; // Store full string ID
        $values[] = $params['SessionElectoralPeriodID'];
    }

    if (empty($updates)) {
        return createApiErrorNoParameters();
    }

    // Update query: WHERE SessionID = ?s using full string $params["id"]
    $query = "UPDATE ?n SET " . implode(", ", $updates) . " WHERE SessionID = ?s";
    array_unshift($values, $config["parliament"][$parliament]["sql"]["tbl"]["Session"]);
    $values[] = $params["id"]; // Use full $params["id"] for the WHERE clause

    try {
        $result = $db->query($query, ...$values);
        if (!$result) {
            return createApiErrorDatabaseError("Failed to update session");
        }

        return createApiSuccessResponse(null, ["message" => "Session updated successfully"]);
    } catch (exception $e) {
        return createApiErrorDatabaseError($e->getMessage());
    }
}

?>
