<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id AgendaID
 * @return array
 */
function agendaItemGetByID($id = false) {

    global $config;

    $IDInfos = getInfosFromStringID($id);

    if (is_array($IDInfos)) {

        $parliament = $IDInfos["parliament"];
        $parliamentLabel = $config["parliament"][$parliament]["label"];

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "500";
        $errorarray["code"] = "1";
        $errorarray["title"] = "ID Error";
        $errorarray["detail"] = "Could not parse AgendaItemID";
        array_push($return["errors"], $errorarray);
        return $return;

    }

    if (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "AgendaItemID could not be associated with a parliament";
        array_push($return["errors"], $errorarray);

        return $return;

    } else {


        $opts = array(
            'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
            'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
            'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
            'db'	=> $config["parliament"][$parliament]["sql"]["db"]
        );

        try {

            $dbp = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to parliament database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }

        try {

            $item = $dbp->getRow("SELECT ai.*,
                                         sess.*,
                                         ep.*
                                         FROM ?n AS ai
                                         LEFT JOIN ?n AS sess
                                            ON ai.AgendaItemSessionID=sess.SessionID
                                         LEFT JOIN ?n AS ep
                                            ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                                         WHERE AgendaItemID=?s",
                                         $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                                         $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                                         $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                                         $IDInfos["id_part"]);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database error";
            $errorarray["detail"] = "Database error #1";
            array_push($return["errors"], $errorarray);
            return $return;

        }

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "agendaItem";
            $return["data"]["id"] = $parliament."-".$item["AgendaItemID"];
            $return["data"]["attributes"]["officialTitle"] = $item["AgendaItemOfficialTitle"];
            $return["data"]["attributes"]["title"] = $item["AgendaItemTitle"];
            $return["data"]["attributes"]["order"] = (int)$item["AgendaItemOrder"];
            $return["data"]["attributes"]["parliament"] = $parliament;
            $return["data"]["attributes"]["parliamentLabel"] = $parliamentLabel;
            $return["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["type"]."/".$return["data"]["id"];
            $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?agendaItemID=".$return["data"]["id"];
            $return["data"]["relationships"]["session"]["data"]["type"] = "session";
            $return["data"]["relationships"]["session"]["data"]["id"] = $item["SessionID"];
            $return["data"]["relationships"]["session"]["data"]["attributes"]["number"] = $item["SessionNumber"];
            $return["data"]["relationships"]["session"]["data"]["attributes"]["dateStart"] = $item["SessionDateStart"];
            $return["data"]["relationships"]["session"]["data"]["attributes"]["dateEnd"] = $item["SessionDateEnd"];
            $return["data"]["relationships"]["session"]["links"]["self"] = $config["dir"]["api"]."/session/".$item["SessionID"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["type"] = "electoralPeriod";
            $return["data"]["relationships"]["electoralPeriod"]["data"]["id"] = $item["ElectoralPeriodID"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] = $item["ElectoralPeriodNumber"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["dateStart"] = $item["ElectoralPeriodDateStart"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["dateEnd"] = $item["ElectoralPeriodDateEnd"];
            $return["data"]["relationships"]["electoralPeriod"]["links"]["self"] = $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"];


        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "AgendaItem not found";
            $errorarray["detail"] = "AgendaItem with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

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
 * @return array
 */
function agendaItemGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $db = false, $electoralPeriodID = false, $sessionID = false) {
    global $config;
    
    // Get all parliaments
    $parliaments = array_keys($config["parliament"]);
    $allResults = array();
    $totalCount = 0;
    
    // If ID is not "all", get parliament and numeric ID using getInfosFromStringID
    $targetParliament = null;
    $numericID = $id;
    if ($id !== "all") {
        $IDInfos = getInfosFromStringID($id);
        if (is_array($IDInfos) && array_key_exists($IDInfos["parliament"], $config["parliament"])) {
            $targetParliament = $IDInfos["parliament"];
            $numericID = intval($IDInfos["id_part"]); // Convert to integer
        }
    }
    
    // If we have a target parliament, only search in that one
    if ($targetParliament) {
        $parliaments = array($targetParliament);
    }
    
    foreach ($parliaments as $parliament) {
        $opts = array(
            'host' => $config["parliament"][$parliament]["sql"]["access"]["host"],
            'user' => $config["parliament"][$parliament]["sql"]["access"]["user"],
            'pass' => $config["parliament"][$parliament]["sql"]["access"]["passwd"],
            'db' => $config["parliament"][$parliament]["sql"]["db"]
        );
        
        try {
            $dbp = new SafeMySQL($opts);
        } catch (exception $e) {
            continue; // Skip this parliament if connection fails
        }
        
        $queryPart = "";
        
        if ($id == "all") {
            $queryPart .= "1";
        } else {
            $queryPart .= $dbp->parse("ai.AgendaItemID=?i", $numericID); // Use ?i for integer
        }
        
        if (!empty($search)) {
            $queryPart .= $dbp->parse(" AND (ai.AgendaItemTitle LIKE ?s OR ai.AgendaItemOfficialTitle LIKE ?s)", "%".$search."%", "%".$search."%");
        }
        
        if (!empty($electoralPeriodID)) {
            $queryPart .= $dbp->parse(" AND sess.SessionElectoralPeriodID=?s", $electoralPeriodID);
        }
        
        if (!empty($sessionID)) {
            $queryPart .= $dbp->parse(" AND ai.AgendaItemSessionID=?s", $sessionID);
        }
        
        if (!empty($sort)) {
            $queryPart .= $dbp->parse(" ORDER BY ?n ".$order, $sort);
        }
        
        if ($limit != 0) {
            $queryPart .= $dbp->parse(" LIMIT ?i, ?i", $offset, $limit);
        }
        
        try {
            $count = $dbp->getOne("SELECT COUNT(ai.AgendaItemID) as count 
                FROM ?n AS ai
                LEFT JOIN ?n AS sess
                ON ai.AgendaItemSessionID=sess.SessionID
                WHERE ?p", 
                $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $queryPart);
            $totalCount += $count;
            
            $results = $dbp->getAll("SELECT
                ai.AgendaItemID,
                ai.AgendaItemTitle,
                ai.AgendaItemOfficialTitle,
                ai.AgendaItemOrder,
                ai.AgendaItemSessionID,
                sess.SessionNumber,
                sess.SessionDateStart,
                sess.SessionDateEnd,
                sess.SessionElectoralPeriodID,
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
                $queryPart);
            
            foreach ($results as $result) {
                // Add parliament prefix to ID
                $result["id"] = $parliament . "-" . str_pad($result["AgendaItemID"], 3, "0", STR_PAD_LEFT);
                $result["type"] = "agendaItem";
                $result["Parliament"] = $parliament;
                $result["AgendaItemLabel"] = $result["AgendaItemTitle"] ?: $result["AgendaItemOfficialTitle"];
                $allResults[] = $result;
            }
        } catch (exception $e) {
            // Skip this parliament if query fails
            continue;
        }
    }
    
    $return = array();
    
    $return["total"] = $totalCount;
    $return["data"] = $allResults;
    
    return $return;
}

function agendaItemChange($params) {
    global $config;
    $return = array();
    $return["meta"]["requestStatus"] = "error";
    $return["errors"] = array();

    // Check if ID is provided
    if (!isset($params["id"])) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter (id) is missing";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Get parliament from ID
    $parliament = getInfosFromStringID($params["id"])["parliament"];
    if (!isset($config["parliament"][$parliament])) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid AgendaItemID";
        $errorarray["detail"] = "AgendaItemID could not be associated with a parliament";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Get agenda item using agendaItemGetItemsFromDB
    $agendaItem = agendaItemGetItemsFromDB($params["id"]);
    if (empty($agendaItem["data"])) {
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Agenda Item not found";
        $errorarray["detail"] = "Agenda item with the given ID was not found in database";
        array_push($return["errors"], $errorarray);
        return $return;
    }
    $agendaItem = $agendaItem["data"][0]; // Get the first (and only) result

    // Define allowed parameters
    $allowedParams = array(
        "AgendaItemTitle",
        "AgendaItemOfficialTitle",
        "AgendaItemOrder",
        "AgendaItemSessionID"
    );

    // Filter parameters
    $dbp = new SafeMySQL($config["parliament"][$parliament]["sql"]);
    $params = $dbp->filterArray($params, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        // Validate AgendaItemOrder
        if ($key === "AgendaItemOrder") {
            if ($value === "") {
                // Allow empty value to set NULL in database
                $updateParams[] = $dbp->parse("AgendaItemOrder=NULL");
            } else if (!is_numeric($value) || (int)$value <= 0) {
                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Invalid Agenda Item Order";
                $errorarray["detail"] = "Agenda Item Order must be a positive number or empty";
                $errorarray["meta"]["domSelector"] = "[name='AgendaItemOrder']";
                array_push($return["errors"], $errorarray);
                return $return;
            } else {
                // Check for uniqueness within session
                $sessionID = isset($params["AgendaItemSessionID"]) ? $params["AgendaItemSessionID"] : $agendaItem["AgendaItemSessionID"];
                $existing = $dbp->getRow("SELECT AgendaItemID FROM ?n WHERE AgendaItemOrder = ?i AND AgendaItemSessionID = ?s AND AgendaItemID != ?i",
                    $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                    (int)$value,
                    $sessionID,
                    $agendaItem["AgendaItemID"]
                );
                if ($existing) {
                    $errorarray["status"] = "422";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Duplicate Agenda Item Order";
                    $errorarray["detail"] = "An agenda item with this order already exists in this session";
                    $errorarray["meta"]["domSelector"] = "[name='AgendaItemOrder']";
                    array_push($return["errors"], $errorarray);
                    return $return;
                }
                $updateParams[] = $dbp->parse("AgendaItemOrder=?i", (int)$value);
            }
        }

        // Validate session reference
        if ($key === "AgendaItemSessionID") {
            $session = $dbp->getRow("SELECT SessionID FROM ?n WHERE SessionID = ?s",
                $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                $value
            );
            if (!$session) {
                $errorarray["status"] = "422";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Invalid Session";
                $errorarray["detail"] = "The specified session does not exist";
                $errorarray["meta"]["domSelector"] = "[name='AgendaItemSessionID']";
                array_push($return["errors"], $errorarray);
                return $return;
            }
            $updateParams[] = $dbp->parse("AgendaItemSessionID=?s", $value);
        }

        // Handle text fields
        if ($key === "AgendaItemTitle" || $key === "AgendaItemOfficialTitle") {
            $updateParams[] = $dbp->parse("?n=?s", $key, $value);
        }
    }

    if (empty($updateParams)) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "No parameters";
        $errorarray["detail"] = "No valid parameters for updating agenda item data were provided";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Execute update
    $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE AgendaItemID=?i",
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
        $agendaItem["AgendaItemID"]
    );

    // Return success
    $return["meta"]["requestStatus"] = "success";
    $return["data"] = array(
        "message" => "Agenda item updated successfully"
    );
    return $return;
}

?>
