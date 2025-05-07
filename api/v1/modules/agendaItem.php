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
 * @param bool $getCount Whether to return the total count
 * @param object $db Database connection
 * @param string $electoralPeriodID Filter by electoral period ID
 * @param string $sessionID Filter by session ID
 * @return array
 */
function agendaItemGetOverview($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $getCount = false, $db = false, $electoralPeriodID = false, $sessionID = false) {
    global $config;
    
    // Get all parliaments
    $parliaments = array_keys($config["parliament"]);
    $allResults = array();
    $totalCount = 0;
    
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
            $queryPart .= $dbp->parse("ai.AgendaItemID=?s", $id);
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
            if ($getCount == true) {
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
                    $result["AgendaItemLabel"] = $result["AgendaItemTitle"];
                    $result["AgendaItemType"] = "agendaItem";
                    $result["SessionLabel"] = "Session " . $result["SessionNumber"];
                    $result["ElectoralPeriodLabel"] = "Electoral Period " . $result["ElectoralPeriodNumber"];
                    $result["Parliament"] = $parliament;
                    $result["ParliamentLabel"] = $config["parliament"][$parliament]["label"];
                    $allResults[] = $result;
                }
            } else {
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
                    $result["AgendaItemLabel"] = $result["AgendaItemTitle"];
                    $result["AgendaItemType"] = "agendaItem";
                    $result["SessionLabel"] = "Session " . $result["SessionNumber"];
                    $result["ElectoralPeriodLabel"] = "Electoral Period " . $result["ElectoralPeriodNumber"];
                    $result["Parliament"] = $parliament;
                    $result["ParliamentLabel"] = $config["parliament"][$parliament]["label"];
                    $allResults[] = $result;
                }
            }
        } catch (exception $e) {
            // Skip this parliament if query fails
            continue;
        }
    }
    
    $return = array();
    
    if ($getCount == true) {
        $return["total"] = $totalCount;
        $return["rows"] = $allResults;
    } else {
        $return = $allResults;
    }
    
    return $return;
}

function agendaItemChange($parameter) {
    global $config;

    if (!$parameter["id"]) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter (id) is missing";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Parse parliament from ID
    $IDInfos = getInfosFromStringID($parameter["id"]);
    if (!is_array($IDInfos) || !array_key_exists($IDInfos["parliament"], $config["parliament"])) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid AgendaItemID";
        $errorarray["detail"] = "AgendaItemID could not be associated with a parliament";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    $parliament = $IDInfos["parliament"];

    try {
        $dbp = new SafeMySQL(array(
            'host'  => $config["parliament"][$parliament]["sql"]["access"]["host"],
            'user'  => $config["parliament"][$parliament]["sql"]["access"]["user"],
            'pass'  => $config["parliament"][$parliament]["sql"]["access"]["passwd"],
            'db'    => $config["parliament"][$parliament]["sql"]["db"]
        ));
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

    // Check if agenda item exists
    $agendaItem = $dbp->getRow("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemID=?s LIMIT 1", $IDInfos["id_part"]);
    if (!$agendaItem) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "AgendaItem not found";
        $errorarray["detail"] = "AgendaItem with the given ID was not found in database";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Define allowed parameters
    $allowedParams = array(
        "AgendaItemOfficialTitle", "AgendaItemTitle", "AgendaItemOrder", "AgendaItemSessionID"
    );

    // Filter parameters
    $params = $dbp->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "AgendaItemOrder") {
            // Convert to integer
            $updateParams[] = $dbp->parse("?n=?i", $key, (int)$value);
        } else {
            $updateParams[] = $dbp->parse("?n=?s", $key, $value);
        }
    }

    if (empty($updateParams)) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "No parameters";
        $errorarray["detail"] = "No valid parameters for updating agenda item data were provided";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Execute update
    $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE AgendaItemID=?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"], 
        $IDInfos["id_part"]
    );

    $return["meta"]["requestStatus"] = "success";
    return $return;
}

?>
