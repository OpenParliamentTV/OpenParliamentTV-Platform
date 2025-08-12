<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");

/**
 * @param string $id ElectoralPeriodID
 * @return array
 */
function electoralPeriodGetByID($id = false) {
    global $config;

    // Parse and validate ID
    $idInfo = getInfosFromStringID($id);
    if (!$idInfo || $idInfo["type"] !== "electoralPeriod") {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorIDParseError",
            "messageErrorIDParseError",
            ["type" => "ElectoralPeriod"]
        );
    }

    // Validate parliament
    $parliament = $idInfo["parliament"];
    if (!isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("ElectoralPeriod");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    // Get database connection
    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    try {
        // Get electoral period data
        $item = $db->getRow("SELECT * FROM ?n WHERE ElectoralPeriodID=?s", 
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
            $id
        );

        if (!$item) {
            return createApiErrorNotFound("ElectoralPeriod");
        }

        // Get associated sessions
        $sessionItems = $db->getAll("SELECT sess.*, COUNT(ai.AgendaItemID) AS AgendaItemCount
            FROM ?n AS sess 
            LEFT JOIN ?n as ai
                ON ai.AgendaItemSessionID=sess.SessionID
            WHERE SessionElectoralPeriodID=?s
            GROUP BY sess.SessionID",
            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
            $id
        );

        // Build response data
        $data = [
            "type" => "electoralPeriod",
            "id" => $item["ElectoralPeriodID"],
            "attributes" => [
                "number" => (int)$item["ElectoralPeriodNumber"],
                "dateStart" => $item["ElectoralPeriodDateStart"],
                "dateEnd" => $item["ElectoralPeriodDateEnd"],
                "parliament" => $parliament,
                "parliamentLabel" => $parliamentLabel
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"]
            ],
            "relationships" => [
                "media" => [
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/media?electoralPeriodID=".$item["ElectoralPeriodID"]
                    ]
                ],
                "sessions" => [
                    "data" => [],
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/session?electoralPeriodID=".$item["ElectoralPeriodID"]
                    ]
                ]
            ]
        ];

        // Add session data
        foreach ($sessionItems as $sessionItem) {
            $data["relationships"]["sessions"]["data"][] = [
                "data" => [
                    "type" => "session",
                    "id" => $sessionItem["SessionID"],
                    "attributes" => [
                        "dateStart" => $sessionItem["SessionDateStart"],
                        "dateEnd" => $sessionItem["SessionDateEnd"],
                        "number" => $sessionItem["SessionNumber"],
                        "agendaItemCount" => $sessionItem["AgendaItemCount"]
                    ],
                    "links" => [
                        "self" => $config["dir"]["api"]."/session/".$sessionItem["SessionID"]
                    ]
                ]
            ];
        }

        return createApiSuccessResponse($data);

    } catch (exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorDatabaseGeneric",
            "messageErrorDatabaseRequest"
        );
    }
}

/**
 * Get an overview of electoral periods
 * 
 * @param string $id ElectoralPeriodID or "all"
 * @param int $limit Limit the number of results
 * @param int $offset Offset for pagination
 * @param string $search Search term
 * @param string $sort Sort field
 * @param string $order Sort order (ASC or DESC)
 * @param object $db Database connection
 * @return array Raw data array with 'total' count and 'data' results
 */
function electoralPeriodGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false) {
    global $config;
    
    $allResults = [];
    $totalCount = 0;
    
    $targetParliament = null;
    if ($id !== "all") {
        $idInfo = getInfosFromStringID($id);
        if (!$idInfo || !isset($config["parliament"][$idInfo["parliament"]])) {
            return [
                "total" => 0,
                "data" => []
            ];
        }
        $targetParliament = $idInfo["parliament"];
    }
    
    $parliaments = $targetParliament ? [$targetParliament] : array_keys($config["parliament"]);
    
    foreach ($parliaments as $parliament) {
        $db_connection = getApiDatabaseConnection('parliament', $parliament);
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
                    $conditions[] = "ElectoralPeriodID=?s";
                    $query_params[] = $id;
                } else {
                    continue;
                }
            }
            
            if (!empty($search)) {
                $conditions[] = "(ElectoralPeriodNumber LIKE ?s)";
                $query_params[] = "%" . $search . "%";
            }
            
            $whereClauseSQL = implode(" AND ", $conditions);
            
            $countQueryBase = "SELECT COUNT(ElectoralPeriodID) as count FROM ?n";
            $current_query_params_for_count = [$config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"]];
            if(!empty($whereClauseSQL) && $whereClauseSQL !== "1"){
                $countQuery = $countQueryBase . " WHERE " . $whereClauseSQL;
                $current_query_params_for_count = array_merge($current_query_params_for_count, $query_params);
            } else {
                $countQuery = $countQueryBase;
            }
            $count = $db_connection->getOne($countQuery, ...$current_query_params_for_count);
            $totalCount += (int)$count;

            $itemsQuerySQL = "SELECT ElectoralPeriodID, ElectoralPeriodNumber, ElectoralPeriodDateStart, ElectoralPeriodDateEnd FROM ?n";
            $current_query_params_for_items = [$config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"]];
            if(!empty($whereClauseSQL) && $whereClauseSQL !== "1"){
                $itemsQuerySQL .= " WHERE " . $whereClauseSQL;
                $current_query_params_for_items = array_merge($current_query_params_for_items, $query_params);
            }
            
            if (!empty($sort)) {
                $allowedSortFields = ["ElectoralPeriodNumber", "ElectoralPeriodDateStart", "ElectoralPeriodDateEnd"];
                if (in_array($sort, $allowedSortFields)) {
                    $orderSafe = strtoupper($order) === "DESC" ? "DESC" : "ASC";
                    $itemsQuerySQL .= " ORDER BY ?n " . $orderSafe;
                    $current_query_params_for_items[] = $sort;
                }
            }
            
            if ($limit != 0) {
                $itemsQuerySQL .= " LIMIT ?i, ?i";
                $current_query_params_for_items[] = $offset;
                $current_query_params_for_items[] = $limit;
            }
            
            $results = $db_connection->getAll($itemsQuerySQL, ...$current_query_params_for_items);
            
            foreach ($results as $result) {
                $result["id"] = $result["ElectoralPeriodID"];
                $result["ElectoralPeriodNumber"] = (int)$result["ElectoralPeriodNumber"];
                $result["Parliament"] = $parliament;
                $result["ParliamentLabel"] = $config["parliament"][$parliament]["label"];
                $allResults[] = $result;
            }
            
        } catch (exception $e) {
            error_log("Error in electoralPeriodGetItemsFromDB for parliament {$parliament}: " . $e->getMessage());
            continue;
        }
    }
    
    return [
        "total" => $totalCount,
        "data" => $allResults
    ];
}

function electoralPeriodChange($params) {
    global $config;

    // Check if ID is provided
    if (!isset($params["id"])) {
        return createApiErrorMissingParameter("id");
    }

    // Get parliament from ID
    $idInfo = getInfosFromStringID($params["id"]);
    if (!$idInfo || $idInfo["type"] !== "electoralPeriod") {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorIDParseError",
            "messageErrorIDParseError",
            ["type" => "ElectoralPeriod"]
        );
    }

    $parliament = $idInfo["parliament"];
    if (!isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("ElectoralPeriod");
    }

    // Get database connection
    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    // Get electoral period
    $electoralPeriod = electoralPeriodGetItemsFromDB($params["id"]);
    if (empty($electoralPeriod["data"])) {
        return createApiErrorNotFound("ElectoralPeriod");
    }
    $electoralPeriod = $electoralPeriod["data"][0]; // Get the first (and only) result

    // Define allowed parameters
    $allowedParams = [
        "ElectoralPeriodNumber",
        "ElectoralPeriodDateStart",
        "ElectoralPeriodDateEnd"
    ];

    // Filter parameters
    $params = $db->filterArray($params, $allowedParams);
    $updateParams = [];

    // Process each parameter
    foreach ($params as $key => $value) {
        // Validate ElectoralPeriodNumber
        if ($key === "ElectoralPeriodNumber") {
            if (!is_numeric($value) || (int)$value <= 0) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageErrorInvalidNumber",
                    "messageErrorInvalidNumber",
                    [],
                    "[name='ElectoralPeriodNumber']"
                );
            }

            // Check for uniqueness within parliament
            $existing = $db->getRow("SELECT ElectoralPeriodID FROM ?n WHERE ElectoralPeriodNumber = ?i AND ElectoralPeriodID != ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                (int)$value,
                $electoralPeriod["ElectoralPeriodID"]
            );
            if ($existing) {
                return createApiErrorDuplicate('electoral period', 'ElectoralPeriodNumber');
            }
            $updateParams[] = $db->parse("ElectoralPeriodNumber=?i", (int)$value);
        }

        // Validate dates
        if ($key === "ElectoralPeriodDateStart" || $key === "ElectoralPeriodDateEnd") {
            if (!empty($value) && !strtotime($value)) {
                return createApiErrorResponse(
                    422,
                    1,
                    "messageInvalidDate",
                    "messageInvalidDateFormat",
                    [],
                    "[name='".$key."']"
                );
            }
            // Convert empty strings to NULL for MySQL DATE fields
            $updateParams[] = $db->parse("?n=?s", $key, empty($value) ? null : $value);
        }
    }

    if (empty($updateParams)) {
        return createApiErrorResponse(
            422,
            1,
            "messageNoParameter",
            "messageNoParameterForElectoralPeriodChange"
        );
    }

    // Validate date range if both dates are provided
    if (!empty($params["ElectoralPeriodDateStart"]) && !empty($params["ElectoralPeriodDateEnd"])) {
        $startDate = strtotime($params["ElectoralPeriodDateStart"]);
        $endDate = strtotime($params["ElectoralPeriodDateEnd"]);
        if ($startDate > $endDate) {
            return createApiErrorResponse(
                422,
                1,
                "messageInvalidDateRange",
                "messageStartDateMustBeBeforeEndDate",
                [],
                "[name='ElectoralPeriodDateStart']"
            );
        }
    }

    try {
        // Execute update
        $db->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE ElectoralPeriodID=?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
            $electoralPeriod["ElectoralPeriodID"]
        );

        return createApiSuccessResponse(
            ["message" => "Electoral period updated successfully"]
        );
    } catch (exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorDatabaseGeneric",
            "messageErrorDatabaseRequest"
        );
    }
}

function getElectoralPeriod($id) {
    $idInfo = getInfosFromStringID($id);
    
    if (!$idInfo || $idInfo["type"] !== "electoralPeriod") {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorIDParseError",
            "messageErrorIDParseError",
            ["type" => "ElectoralPeriod"]
        );
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament) {
        return createApiErrorInvalidID("ElectoralPeriod");
    }

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return $db; // Error response from getApiDatabaseConnection
    }

    $result = $db->getRow("SELECT * FROM electoral_periods WHERE id = ?s", $id);
    if (!$result) {
        return createApiErrorNotFound("ElectoralPeriod");
    }

    return createApiSuccessResponse($result);
}

function updateElectoralPeriod($id, $params) {
    if (empty($id)) {
        return createApiErrorMissingParameter("id");
    }

    $idInfo = getInfosFromStringID($id);
    if (!$idInfo || $idInfo["type"] !== "electoralPeriod") {
        return createApiErrorInvalidID("ElectoralPeriod");
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        return createApiErrorInvalidID("ElectoralPeriod");
    }

    $db = getApiDatabaseConnection('parliament', $parliament);
    if (!is_object($db)) {
        return $db; 
    }

    $electoralPeriodTable = $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"];
    $result = $db->getRow("SELECT * FROM ?n WHERE ElectoralPeriodID = ?s", $electoralPeriodTable, $id);
    if (!$result) {
        return createApiErrorNotFound("ElectoralPeriod");
    }

    $updates = [];
    $values = [];

    if (isset($params['number'])) {
        $numberValidation = validateApiNumber($params['number'], 'number');
        if ($numberValidation !== true) {
            return $numberValidation;
        }

        $existing = $db->getRow(
            "SELECT ElectoralPeriodID FROM ?n WHERE ElectoralPeriodNumber = ?i AND ElectoralPeriodID != ?s",
            $electoralPeriodTable,
            (int)$params['number'],
            $id
        );
        if ($existing) {
            return createApiErrorDuplicate('electoral period', 'ElectoralPeriodNumber');
        }

        $updates[] = "ElectoralPeriodNumber = ?i";
        $values[] = (int)$params['number'];
    }

    if (isset($params['dateStart']) || isset($params['dateEnd'])) {
        $startDate = isset($params['dateStart']) ? $params['dateStart'] : $result['ElectoralPeriodDateStart'];
        $endDate = isset($params['dateEnd']) ? $params['dateEnd'] : $result['ElectoralPeriodDateEnd'];

        $dateValidation = validateApiDateRange($startDate, $endDate);
        if ($dateValidation !== true) {
            return $dateValidation;
        }

        if (isset($params['dateStart'])) {
            $updates[] = "ElectoralPeriodDateStart = ?s";
            $values[] = $params['dateStart'];
        }
        if (isset($params['dateEnd'])) {
            $updates[] = "ElectoralPeriodDateEnd = ?s";
            $values[] = $params['dateEnd'];
        }
    }

    if (empty($updates)) {
        return createApiErrorResponse(
            422,
            1,
            "messageErrorNoParameters",
            "messageErrorNoParameters"
        );
    }

    $query = "UPDATE ?n SET " . implode(", ", $updates) . " WHERE ElectoralPeriodID = ?s";
    array_unshift($values, $electoralPeriodTable);
    $values[] = $id;

    $update_result = $db->query($query, ...$values);
    if (!$update_result) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorDatabaseGeneric",
            "messageErrorDatabaseRequest"
        );
    }

    return createApiSuccessResponse(
        ["message" => "Electoral period updated successfully"]
    );
}

?>
