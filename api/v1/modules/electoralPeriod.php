<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id ElectoralPeriodID
 * @return array
 */
function electoralPeriodGetByID($id = false) {

    global $config;

    $IDInfos = getInfosFromStringID($id);

    if (is_array($IDInfos) && ($IDInfos["type"] == "electoralPeriod")) {

        $parliament = $IDInfos["parliament"];
        $parliamentLabel = $config["parliament"][$parliament]["label"];

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "500";
        $errorarray["code"] = "1";
        $errorarray["title"] = "ID Error";
        $errorarray["detail"] = "Could not parse ElectoralPeriodID";
        array_push($return["errors"], $errorarray);
        return $return;

    }

    if (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid ElectoralPeriodID";
        $errorarray["detail"] = "ElectoralPeriodID could not be associated with a parliament";
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
            $errorarray["detail"] = "Connecting to parliament database failed #1";
            array_push($return["errors"], $errorarray);
            return $return;

        }

        try {

            $item = $dbp->getRow("SELECT * FROM ?n WHERE ElectoralPeriodID=?s", $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], $id);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database error";
            $errorarray["detail"] = "Database error #2";
            array_push($return["errors"], $errorarray);
            return $return;

        }

        if ($item) {


            $sessionItems = $dbp->getAll("SELECT sess.*, COUNT(ai.AgendaItemID) AS AgendaItemCount
                                            FROM ?n AS sess 
                                            LEFT JOIN ?n as ai
                                                ON ai.AgendaItemSessionID=sess.SessionID
                                            WHERE SessionElectoralPeriodID=?s
                                            GROUP BY sess.SessionID",
                                            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                                            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                                            $id);

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "electoralPeriod";
            $return["data"]["id"] = $item["ElectoralPeriodID"];
            $return["data"]["attributes"]["number"] = (int)$item["ElectoralPeriodNumber"];
            $return["data"]["attributes"]["dateStart"] = $item["ElectoralPeriodDateStart"];
            $return["data"]["attributes"]["dateEnd"] = $item["ElectoralPeriodDateEnd"];
            $return["data"]["attributes"]["parliament"] = $parliament;
            $return["data"]["attributes"]["parliamentLabel"] = $parliamentLabel;
            $return["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["type"]."/".$return["data"]["id"];
            $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/"."search/media?electoralPeriodID=".$return["data"]["id"];
            foreach ($sessionItems as $sessionItem) {
                $tmpItem = array();
                $tmpItem["data"]["type"] = "session";
                $tmpItem["data"]["id"] = $sessionItem["SessionID"];
                $tmpItem["data"]["attributes"]["dateStart"] = $sessionItem["SessionDateStart"];
                $tmpItem["data"]["attributes"]["dateEnd"] = $sessionItem["SessionDateEnd"];
                $tmpItem["data"]["attributes"]["number"] = $sessionItem["SessionNumber"];
                $tmpItem["data"]["attributes"]["agendaItemCount"] = $sessionItem["AgendaItemCount"];
                $tmpItem["data"]["links"]["self"] = $config["dir"]["api"]."/session/".$sessionItem["SessionID"];
                $return["data"]["relationships"]["sessions"]["data"][] = $tmpItem;
            }
            $return["data"]["relationships"]["sessions"]["links"]["self"] = $config["dir"]["api"]."/search/session?electoralPeriodID=".$return["data"]["id"];


        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "ElectoralPeriod not found";
            $errorarray["detail"] = "ElectoralPeriod with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

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
 * @param bool $getCount Whether to return the total count
 * @param object $db Database connection
 * @return array
 */
function electoralPeriodGetItemsFromDB($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $getCount = false, $db = false) {
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
            $queryPart .= $dbp->parse("ElectoralPeriodID=?s", $id);
        }
        
        if (!empty($search)) {
            $queryPart .= $dbp->parse(" AND (ElectoralPeriodNumber LIKE ?s)", "%".$search."%");
        }
        
        if (!empty($sort)) {
            $queryPart .= $dbp->parse(" ORDER BY ?n ".$order, $sort);
        }
        
        if ($limit != 0) {
            $queryPart .= $dbp->parse(" LIMIT ?i, ?i", $offset, $limit);
        }
        
        try {
            if ($getCount == true) {
                $count = $dbp->getOne("SELECT COUNT(ElectoralPeriodID) as count FROM ?n WHERE ?p", 
                    $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
                    $queryPart);
                $totalCount += $count;
                
                $results = $dbp->getAll("SELECT
                    ElectoralPeriodID,
                    ElectoralPeriodNumber,
                    ElectoralPeriodDateStart,
                    ElectoralPeriodDateEnd
                    FROM ?n
                    WHERE ?p", 
                    $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
                    $queryPart);
                
                foreach ($results as $result) {
                    $result["ElectoralPeriodLabel"] = "Electoral Period " . $result["ElectoralPeriodNumber"];
                    $result["ElectoralPeriodType"] = "electoralPeriod";
                    $result["Parliament"] = $parliament;
                    $result["ParliamentLabel"] = $config["parliament"][$parliament]["label"];
                    $allResults[] = $result;
                }
            } else {
                $results = $dbp->getAll("SELECT
                    ElectoralPeriodID,
                    ElectoralPeriodNumber,
                    ElectoralPeriodDateStart,
                    ElectoralPeriodDateEnd
                    FROM ?n
                    WHERE ?p", 
                    $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
                    $queryPart);
                
                foreach ($results as $result) {
                    $result["ElectoralPeriodLabel"] = "Electoral Period " . $result["ElectoralPeriodNumber"];
                    $result["ElectoralPeriodType"] = "electoralPeriod";
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

function electoralPeriodChange($parameter) {
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
        $errorarray["title"] = "Invalid ElectoralPeriodID";
        $errorarray["detail"] = "ElectoralPeriodID could not be associated with a parliament";
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

    // Check if electoral period exists
    $electoralPeriod = $dbp->getRow("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"]." WHERE ElectoralPeriodID=?s LIMIT 1", $IDInfos["id_part"]);
    if (!$electoralPeriod) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "ElectoralPeriod not found";
        $errorarray["detail"] = "ElectoralPeriod with the given ID was not found in database";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Define allowed parameters
    $allowedParams = array(
        "ElectoralPeriodNumber", "ElectoralPeriodDateStart", "ElectoralPeriodDateEnd"
    );

    // Filter parameters
    $params = $dbp->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "ElectoralPeriodNumber") {
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
        $errorarray["detail"] = "No valid parameters for updating electoral period data were provided";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Execute update
    $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE ElectoralPeriodID=?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"], 
        $IDInfos["id_part"]
    );

    $return["meta"]["requestStatus"] = "success";
    return $return;
}

?>
