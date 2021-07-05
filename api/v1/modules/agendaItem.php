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
        $errorarray["detail"] = "Could not parse AgendaItemID"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }

    if (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "AgendaItemID could not be associated with a parliament"; //TODO: Description
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
            $errorarray["detail"] = "Connecting to parliament database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

        try {

            //$item = $dbp->getRow("SELECT * FROM ?n WHERE AgendaItemID=?s", $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"], $IDInfos["id_part"]);
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
            $errorarray["detail"] = "Database error"; //TODO: Description
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
            $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?agendaItemID=".$return["data"]["id"]; //TODO: Check Link and Parameter
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

            //TODO: Session Relation?

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "AgendaItem not found";
            $errorarray["detail"] = "AgendaItem with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
