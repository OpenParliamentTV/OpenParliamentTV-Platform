<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/entities/session.php");

/**
 * Envelope wrapper over sessionGetNode() (modules/entities/session.php).
 *
 * @param string $id SessionID
 * @return array
 */
function sessionGetByID($id = false) {

    if (!$id) {
        return apiErrorMissingParameter('id');
    }

    try {
        $node = sessionGetNode($id);
    } catch (OptvInvalidIdException $e) {
        return $e->getReason() === "parliament"
            ? apiErrorInvalidId("Session")
            : apiErrorInvalidFormat('id', 'session');
    } catch (OptvEntityException $e) {
        return apiErrorDatabaseConnection();
    } catch (Exception $e) {
        return apiErrorDatabaseError($e->getMessage());
    }

    if ($node === null) {
        return apiErrorNotFound("Session");
    }

    return apiSuccessResponse($node);
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
        $idInfo = optvParseStringId($id); // Used for $targetParliament
        if (!$idInfo || !isset($config["parliament"][$idInfo["parliament"]])) {
            return [
                "total" => 0,
                "data" => []
            ];
        }
        $targetParliament = $idInfo["parliament"];
    }
    
    $parliaments = $targetParliament ? array($targetParliament) : array_keys($config["parliament"]);

    $connectionFailed = false;
    foreach ($parliaments as $parliament) {
        $db_connection = getApiDatabaseConnection('parliament', $parliament); // Renamed to avoid conflict with $db param
        if (!is_object($db_connection)) {
            $connectionFailed = true;
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

    // If nothing could be loaded because a parliament DB was unreachable,
    // surface it as a 503 error rather than a silent empty success so the UI
    // can tell the user the database is down instead of showing empty tables.
    if (empty($allResults) && $connectionFailed) {
        return apiErrorDatabaseConnection('parliament');
    }

    return [
        "total" => $totalCount,
        "data" => $allResults
    ];
}

function sessionChange($params) {
    global $config;

    if (!isset($params["id"])) {
        return apiErrorMissingParameter("id");
    }

    $idInfo = optvParseStringId($params["id"]); // Still useful for $parliament and $idInfo["id_part"] if needed for SessionNumber context
    if (!$idInfo || $idInfo["type"] !== "session") {
        return apiErrorInvalidFormat('id', 'session');
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        return apiErrorInvalidId("Session");
    }

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return apiErrorDatabaseConnection();
    }

    // Get the session to validate it exists. Query Session.SessionID as string.
    $session = $db->getRow("SELECT * FROM ?n WHERE SessionID = ?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
        $params["id"] // Use full $params["id"] string
    );
    if (!$session) {
        return apiErrorNotFound("Session");
    }

    $updates = [];
    $values = [];

    if (isset($params['SessionNumber'])) {
        $numberValidation = apiValidateNumber($params['SessionNumber'], 'SessionNumber');
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
            return apiErrorDuplicate('session', 'number');
        }

        $updates[] = "SessionNumber = ?i";
        $values[] = (int)$params['SessionNumber'];
    }

    if (isset($params['SessionDateStart']) || isset($params['SessionDateEnd'])) {
        $startDate = isset($params['SessionDateStart']) ? $params['SessionDateStart'] : $session['SessionDateStart'];
        $endDate = isset($params['SessionDateEnd']) ? $params['SessionDateEnd'] : $session['SessionDateEnd'];

        $dateValidation = apiValidateDateRange($startDate, $endDate, 'SessionDateStart');
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
        $epInfo = optvParseStringId($params['SessionElectoralPeriodID']);
        if (!$epInfo || $epInfo["type"] !== "electoralPeriod" || $epInfo["parliament"] !== $parliament) {
            return apiErrorInvalidFormat('SessionElectoralPeriodID', 'electoralPeriod');
        }

        $epExists = $db->getOne(
            "SELECT 1 FROM ?n WHERE ElectoralPeriodID = ?s", // ElectoralPeriodID is string
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
            $params['SessionElectoralPeriodID'] // Use full string ID
        );
        if (!$epExists) {
            return apiErrorNotFound("ElectoralPeriod");
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
            return apiErrorDatabaseError("Failed to update session");
        }

        return apiSuccessResponse(null, ["message" => "Session updated successfully"]);
    } catch (exception $e) {
        return apiErrorDatabaseError($e->getMessage());
    }
}

?>
