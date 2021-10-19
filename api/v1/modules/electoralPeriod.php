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

?>
