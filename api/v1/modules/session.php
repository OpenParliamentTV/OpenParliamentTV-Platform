<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id SessionID
 * @return array
 */
function sessionGetByID($id = false) {

    global $config;

    $IDInfos = getInfosFromStringID($id);

    if (is_array($IDInfos) && ($IDInfos["type"] == "session")) {

        $parliament = $IDInfos["parliament"];
        $parliamentLabel = $config["parliament"][$parliament]["label"];

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "500";
        $errorarray["code"] = "1";
        $errorarray["title"] = "ID Error";
        $errorarray["detail"] = "Could not parse SessionID";
        array_push($return["errors"], $errorarray);
        return $return;

    }

    if (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid SessionID";
        $errorarray["detail"] = "SessionID could not be associated with a parliament";
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

            $item = $dbp->getRow("SELECT
                                    sess.*,
                                    ep.*
                                  FROM ?n AS sess
                                  LEFT JOIN ?n as ep
                                  ON
                                    sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                                  WHERE SessionID=?s",
                                    $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                                    $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                                    $id);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database error";
            $errorarray["detail"] = "Database request failed.";
            array_push($return["errors"], $errorarray);
            return $return;

        }



        if ($item) {

            $agendaItems = $dbp->getAll("SELECT * FROM ?n WHERE AgendaItemSessionID=?s",$config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],$id);

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "session";
            $return["data"]["id"] = $item["SessionID"];
            $return["data"]["attributes"]["number"] = (int)$item["SessionNumber"];
            $return["data"]["attributes"]["dateStart"] = $item["SessionDateStart"];
            $return["data"]["attributes"]["dateEnd"] = $item["SessionDateEnd"];
            $return["data"]["attributes"]["parliament"] = $parliament;
            $return["data"]["attributes"]["parliamentLabel"] = $parliamentLabel;
            $return["data"]["attributes"]["dateEnd"] = $item["SessionDateEnd"];
            $return["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["type"]."/".$return["data"]["id"];
            $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?sessionID=".$return["data"]["id"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["type"] = "electoralPeriod";
            $return["data"]["relationships"]["electoralPeriod"]["data"]["id"] = $item["ElectoralPeriodID"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] = $item["ElectoralPeriodNumber"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["dateStart"] = $item["ElectoralPeriodDateStart"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["dateEnd"] = $item["ElectoralPeriodDateEnd"];
            $return["data"]["relationships"]["electoralPeriod"]["links"]["self"] = $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"];
            foreach ($agendaItems as $agendaItem) {
                $tmpItem = array();
                $tmpItem["data"]["type"] = "agendaItem";
                $tmpItem["data"]["id"] = $agendaItem["AgendaItemID"];
                $tmpItem["data"]["attributes"]["officialTitle"] = $agendaItem["AgendaItemOfficialTitle"];
                $tmpItem["data"]["attributes"]["title"] = $agendaItem["AgendaItemTitle"];
                $tmpItem["data"]["attributes"]["order"] = $agendaItem["AgendaItemOrder"];
                $tmpItem["data"]["links"]["self"] = $config["dir"]["api"]."/agendaItem/".$agendaItem["AgendaItemID"];
                $return["data"]["relationships"]["agendaItems"]["data"][] = $tmpItem;
            }
            $return["data"]["relationships"]["agendaItems"]["links"]["self"] = $config["dir"]["api"]."/search/agendaItem?sessionID=".$return["data"]["id"];

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Session not found";
            $errorarray["detail"] = "Session with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
