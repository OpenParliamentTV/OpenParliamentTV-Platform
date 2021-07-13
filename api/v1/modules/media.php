<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.conflicts.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");

/**
 * @param string $id MediaID
 * @return array
 */
function mediaGetByID($id = false) {

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
        $errorarray["detail"] = "Could not parse ID"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }



    if (!$id) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
        array_push($return["errors"], $errorarray);

        return $return;

    } elseif (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "MediaID could not be associated with a parliament"; //TODO: Description
        array_push($return["errors"], $errorarray);

        return $return;

    } else {

        $opts = array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        );


        try {

            $db = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to platform database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }


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

        $item = $dbp->getRow("
                        SELECT
                            m.*,
                            ai.*,
                            sess.*,
                            ep.*
                        FROM ?n AS m
                        LEFT JOIN ?n AS ai
                            ON m.MediaAgendaItemID=ai.AgendaItemID
                        LEFT JOIN ?n AS sess
                            ON ai.AgendaItemSessionID=sess.SessionID
                        LEFT JOIN ?n AS ep
                            ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                        WHERE m.MediaID=?s",
            $config["parliament"][$parliament]["sql"]["tbl"]["Media"],
            $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
            $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
            $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
            $id);

        if ($item) {

            if (($item["MediaPublic"] == 0) && ($_SESSION["userdata"]["role"] != "admin")) {

                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "511";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Media not allowed to access";
                $errorarray["detail"] = "The media is not public"; //TODO: Description
                array_push($return["errors"], $errorarray);
                return $return;

            }

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "media";
            $return["data"]["id"] = $item["MediaID"];
            $return["data"]["attributes"]["originID"] = $item["MediaOriginID"];
            $return["data"]["attributes"]["originMediaID"] = $item["MediaOriginMediaID"];
            $return["data"]["attributes"]["creator"] = $item["MediaCreator"];
            $return["data"]["attributes"]["license"] = $item["MediaLicense"];
            $return["data"]["attributes"]["parliament"] = $parliament;
            $return["data"]["attributes"]["parliamentLabel"] = $parliamentLabel;
            $return["data"]["attributes"]["order"] = (int)$item["MediaOrder"];
            $return["data"]["attributes"]["aligned"] = (($item["MediaAligned"] === 1) ? true : false);
            $return["data"]["attributes"]["timestamp"] = strtotime($item["MediaDateStart"]);
            $return["data"]["attributes"]["dateStart"] = $item["MediaDateStart"];
            $return["data"]["attributes"]["dateEnd"] = $item["MediaDateEnd"];
            $return["data"]["attributes"]["duration"] = (float)$item["MediaDuration"];
            $return["data"]["attributes"]["videoFileURI"] = $item["MediaVideoFileURI"];
            $return["data"]["attributes"]["audioFileURI"] = $item["MediaAudioFileURI"];
            $return["data"]["attributes"]["sourcePage"] = $item["MediaSourcePage"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["MediaThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["MediaThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["MediaThumbnailLicense"];
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["PersonAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["MediaLastChanged"];
            $return["data"]["attributes"]["textContents"] = array();

            $itemTexts = $dbp->getAll("SELECT * FROM ?n WHERE TextMediaID=?s",$config["parliament"][$parliament]["sql"]["tbl"]["Text"], $id);

            foreach ($itemTexts as $itemText) {

                $tmpTextItem = array();
                $tmpTextItem["id"] = $itemText["TextID"];
                $tmpTextItem["type"] = $itemText["TextType"];
                $tmpTextItem["textBody"] = json_decode($itemText["TextBody"], true);

                //TODO: Check if this makes sense here (and if it makes sense at all)
                $textHTML = '';
                foreach ($tmpTextItem["textBody"] as $paragraph) {
                    $textHTML .= $paragraph["text"];
                }
                $tmpTextItem["textHTML"] = $textHTML;

                $tmpTextItem["sourceURI"] = $itemText["TextSourceURI"];
                $tmpTextItem["creator"] = $itemText["TextCreator"];
                $tmpTextItem["license"] = $itemText["TextLicense"];
                $tmpTextItem["language"] = $itemText["TextLanguage"];
                $tmpTextItem["originTextID"] = $itemText["TextOriginTextID"];
                $tmpTextItem["lastChanged"] = $itemText["TextLastChanged"];

                array_push($return["data"]["attributes"]["textContents"], $tmpTextItem);

            }

            $return["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["type"]."/".$return["data"]["id"];

            $return["data"]["relationships"]["electoralPeriod"]["data"]["type"] = "electoralPeriod";
            $return["data"]["relationships"]["electoralPeriod"]["data"]["id"] = $item["ElectoralPeriodID"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] = (int)$item["ElectoralPeriodNumber"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["relationships"]["electoralPeriod"]["data"]["type"]."/".$return["data"]["relationships"]["electoralPeriod"]["data"]["id"];

            $return["data"]["relationships"]["session"]["data"]["type"] = "session";
            $return["data"]["relationships"]["session"]["data"]["id"] = $item["SessionID"];
            $return["data"]["relationships"]["session"]["data"]["attributes"]["number"] = (int)$item["SessionNumber"];
            $return["data"]["relationships"]["session"]["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["relationships"]["session"]["data"]["type"]."/".$return["data"]["relationships"]["session"]["data"]["id"];

            $return["data"]["relationships"]["agendaItem"]["data"]["type"] = "agendaItem";
            $return["data"]["relationships"]["agendaItem"]["data"]["id"] = $item["AgendaItemID"];
            $return["data"]["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] = $item["AgendaItemOfficialTitle"];
            $return["data"]["relationships"]["agendaItem"]["data"]["attributes"]["title"] = $item["AgendaItemTitle"];
            $return["data"]["relationships"]["agendaItem"]["data"]["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["relationships"]["agendaItem"]["data"]["type"]."/".$parliament."-".$return["data"]["relationships"]["agendaItem"]["data"]["id"];

            $return["data"]["relationships"]["documents"]["data"] = array();
            $return["data"]["relationships"]["organisations"]["data"] = array();
            $return["data"]["relationships"]["terms"]["data"] = array();
            $return["data"]["relationships"]["people"]["data"] = array();

            $annotations = $dbp->getAll("SELECT * FROM ?n WHERE AnnotationMediaID=?s",$config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],$item["MediaID"]);

            foreach ($annotations as $annotation) {

                $tmpAnnotationItem = array();

                switch ($annotation["AnnotationType"]) {


                    case "document":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i LIMIT 1", $config["platform"]["sql"]["tbl"]["Document"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "document";
                        $tmpAnnotationItem["id"] = $annotation["AnnotationResourceID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["DocumentType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["DocumentLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = $ditem["DocumentLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["DocumentThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["DocumentThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["DocumentThumbnailLicense"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];
                        array_push($return["data"]["relationships"]["documents"]["data"], $tmpAnnotationItem);

                    break;


                    case "organisation":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s LIMIT 1", $config["platform"]["sql"]["tbl"]["Organisation"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "organisation";
                        //$tmpAnnotationItem["id"] = $annotation["AnnotationID"];
                        $tmpAnnotationItem["id"] = $annotation["AnnotationResourceID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["OrganisationType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["OrganisationLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = $ditem["OrganisationLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["OrganisationThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["OrganisationThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["OrganisationThumbnailLicense"];
                        $tmpAnnotationItem["attributes"]["color"] = $ditem["OrganisationColor"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];
                        array_push($return["data"]["relationships"]["organisations"]["data"], $tmpAnnotationItem);

                    break;



                    case "term":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE TermID=?i LIMIT 1", $config["platform"]["sql"]["tbl"]["Term"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "term";
                        $tmpAnnotationItem["id"] = $annotation["AnnotationID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["TermType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["TermLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = $ditem["TermLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["TermThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["TermThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["TermThumbnailLicense"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];
                        array_push($return["data"]["relationships"]["terms"]["data"], $tmpAnnotationItem);

                    break;



                    case "person":

                        //TODO: What if no party or no faction?
                        $pitem = $db->getRow("SELECT
                                p.*,
                                op.OrganisationID,
                                op.OrganisationLabel,
                                op.OrganisationID as PartyID,
                                op.OrganisationLabel as PartyLabel,
                                op.OrganisationLabelAlternative as PartyLabelAlternative,
                                ofr.OrganisationID as FactionID,
                                ofr.OrganisationLabel as FactionLabel,
                                ofr.OrganisationLabelAlternative as FactionLabelAlternative
                            FROM ?n AS p
                            LEFT JOIN ?n as op 
                                ON op.OrganisationID = p.PersonPartyOrganisationID
                            LEFT JOIN ?n as ofr 
                                ON ofr.OrganisationID = p.PersonFactionOrganisationID
                            WHERE PersonID=?s LIMIT 1",
                            $config["platform"]["sql"]["tbl"]["Person"],
                            $config["platform"]["sql"]["tbl"]["Organisation"],
                            $config["platform"]["sql"]["tbl"]["Organisation"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "person";
                        $tmpAnnotationItem["id"] = $pitem["PersonID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $pitem["PersonType"];
                        $tmpAnnotationItem["attributes"]["label"] = $pitem["PersonLabel"];
                        $tmpAnnotationItem["attributes"]["degree"] = $pitem["PersonDegree"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $pitem["PersonThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $pitem["PersonThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $pitem["PersonThumbnailLicense"];
                        $tmpAnnotationItem["attributes"]["party"]["id"] = $pitem["PartyID"];
                        $tmpAnnotationItem["attributes"]["party"]["label"] = $pitem["PartyLabel"];
                        $tmpAnnotationItem["attributes"]["party"]["labelAlternative"] = $pitem["PartyLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["faction"]["id"] = $pitem["FactionID"];
                        $tmpAnnotationItem["attributes"]["faction"]["label"] = $pitem["FactionLabel"];
                        $tmpAnnotationItem["attributes"]["faction"]["labelAlternative"] = $pitem["FactionLabelAlternative"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];
                        array_push($return["data"]["relationships"]["people"]["data"], $tmpAnnotationItem);

                    break;
                }

            }

            $return["data"]["relationships"]["documents"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=document";
            $return["data"]["relationships"]["organisations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=organisation";
            $return["data"]["relationships"]["terms"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=term";
            $return["data"]["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=person";

            $return["data"]["relationships"]["annotations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"];

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Media not found";
            $errorarray["detail"] = "Media with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}


function mediaSearch($parameter, $db = false, $dbp = false) {


    global $config;

    require_once (__DIR__."/../../../modules/utilities/functions.php");


    require_once (__DIR__."/../../../modules/search/functions.php");

    //print_r($parameter);

    //print_r(searchSpeeches($parameter));



    //return searchSpeeches($parameter);

    try {
        $search = searchSpeeches($parameter);
        foreach ($search["hits"]["hits"] as $hit) {

            $resultData = $hit["_source"];
            $resultData["_score"] = $hit["_score"];
            $resultData["_highlight"] = $hit["highlight"];
            $resultData["_finds"] = $hit["finds"];

            $return["data"][] = $resultData;

        }
        $return["meta"]["requestStatus"] = "success";

        //TODO: Check if this makes sense here
        $return["meta"]["results"]["count"] = count($search["hits"]["hits"]);
        $return["meta"]["results"]["total"] = $search["hits"]["total"]["value"];

        //TODO: $return["data"]["links"]["self"] = $config["dir"]["api"]."/"."search/organisations?".getURLParameterFromArray($filteredParameters);

    } catch (Exception $e) {

    }



    //$return["data"] = $search["hits"]["hits"]["_source"];


    return $return;


    //Find out what Parliament Database is meant

    if ($parameter["parliament"]) {

        $parliament = $parameter["parliament"];


    } else {

        if ((array_key_exists("electoralPeriod", $parameter)) && (gettype($parameter["electoralPeriod"]) == "string")) {

            $parliament = getInfosFromStringID($parameter["electoralPeriod"]);
            $parliament = $parliament["parliament"];

        } elseif ((array_key_exists("session", $parameter)) && (gettype($parameter["session"]) == "string")) {

            $parliament = getInfosFromStringID($parameter["session"]);
            $parliament = $parliament["parliament"];

        }
    }

    if (!array_key_exists($parliament,$config["parliament"])) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "MediaID could not be associated with a parliament"; //TODO: Description
        array_push($return["errors"], $errorarray);

        return $return;
    }

    if (!$db) {

        $opts = array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        );

        try {

            $db = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    if (!$dbp) {

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
            $errorarray["code"] = "2";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    $allowedFields = ["parliament", "electoralPeriod", "session", "dateFrom", "dateTo", "party", "partyID", "faction", "factionID", "person", "personID", "personOriginID", "personAbgeordnetenwatchID", "organisation", "organisationID"];

    $filteredParameters = array_filter(
        $parameter,
        function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        },
        ARRAY_FILTER_USE_KEY
    );




    /************ VALIDATION START ************/

    /************ External VALIDATION START ************/

    if ($filteredParameters["party"]) {
        $tmpParty["name"] = $filteredParameters["party"];
        $tmpParty["type"] = "party";
        require_once (__DIR__."/organisation.php");

        try {

            $partyResponse = organisationSearch($tmpParty, $db);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "2";
            $errorarray["title"] = "Error by getting data from platform";
            $errorarray["detail"] = "Party query failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }


        if (is_array($partyResponse)) {
            if ($partyResponse["meta"]["requestStatus"] == "error") {

                $partyResponse["errors"][0]["detail"] = "Party: ".$partyResponse["errors"][0]["detail"];
                $return["meta"]["requestStatus"] = "error";
                $return["errors"][] = $partyResponse["errors"][0];

            } else {
                foreach ($partyResponse["data"] as $tmpParty) {
                    $conditionsAnnotations[] = $db->parse("((AnnotationType = organisation) AND (AnnotationResourceID LIKE ?s))", $tmpParty["id"]);
                }
            }
        }

    }

    if ($return["meta"]["requestStatus"] == "error") {

        return $return;

    }

    $query = "SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"];

    $conditions = array();

    foreach ($filteredParameters as $k=>$para) {
        if ($k == "name") {
            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("(MATCH(OrganisationLabel, OrganisationLabelAlternative, OrganisationAbstract) AGAINST (?s IN BOOLEAN MODE))", "*" . $tmppara . "*");
                    //TODO: check if OR (Label LIKE ?s) is needed when more data is present
                }

                $tmpStringArray = " (" . implode(" OR ", $tmpStringArray) . ")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("MATCH(OrganisationLabel, OrganisationLabelAlternative, OrganisationAbstract) AGAINST (?s IN BOOLEAN MODE)", "*" . $para . "*");
                //TODO: check if OR (Label LIKE ?s) is needed when more data is present

            }
        }

        if ($k == "type") {

            $conditions[] = $db->parse("OrganisationType = ?s", $para);

        }

    }


    if (count($conditions) > 0) {

        $query .= " WHERE ".implode(" AND ",$conditions);
        //echo $db->parse($query);
        $findings = $db->getAll($query);

        $return["meta"]["requestStatus"] = "success";

        if (!$return["data"]) {
            $return["data"] = array();
        }

        foreach ($findings as $finding) {
            //print_r($finding);
            array_push($return["data"], organisationGetDataObject($finding,$db));
        }

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Not enough parameters";
        $errorarray["detail"] = "Not enough parameters"; //TODO: Description
        array_push($return["errors"], $errorarray);

    }

    if (!array_key_exists("data", $return)) {
        $return["data"] = array();
    }


    $return["data"]["links"]["self"] = $config["dir"]["api"]."/search/organisations?".getURLParameterFromArray($filteredParameters);

    return $return;



}




/**
 * Add Media
 */

function mediaAdd($item = false, $db = false, $dbp = false) {

    global $config;

    /**
     *
     *
     * VALIDATE AND PROCESS DATA
     *
     *
     */

    $return["errors"] = array();
    if (!array_key_exists($item["parliament"], $config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'parliament'"; //TODO: Description
        array_push($return["errors"], $errorarray);

    }

    if (!is_numeric($item["electoralPeriod"]["number"])) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'electoralPeriod[number]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!is_numeric($item["session"]["number"])) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'session[number]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!$item["agendaItem"]["officialTitle"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'agendaItem[officialTitle]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!$item["agendaItem"]["title"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'agendaItem[title]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!$item["media"]["videoFileURI"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'media[videoFileURI]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!$item["media"]["sourcePage"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'media[sourcePage]'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }

    if (!$item["dateStart"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'dateStart'"; //TODO: Description
        array_push($return["errors"], $errorarray);
    }




    if (count($return["errors"]) > 0) {

        return $return;

    }

    /**
     *
     *
     * DB-Connection
     *
     *
     */

    if (!$db) {

        $opts = array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        );

        try {

            $db = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    if (!$dbp) {

        $opts = array(
            'host'	=> $config["parliament"][$item["parliament"]]["sql"]["access"]["host"],
            'user'	=> $config["parliament"][$item["parliament"]]["sql"]["access"]["user"],
            'pass'	=> $config["parliament"][$item["parliament"]]["sql"]["access"]["passwd"],
            'db'	=> $config["parliament"][$item["parliament"]]["sql"]["db"]
        );

        try {

            $dbp = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "2";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }



    $dbcheck = $dbp->getRow("SELECT MediaID FROM ?n WHERE MediaSourcePage = ?s", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Media"], $item["media"]["sourcePage"]);

    if ($dbcheck) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "400";
        $errorarray["code"] = "4";
        $errorarray["title"] = "Mediaitem already exists";
        $errorarray["detail"] = "Mediaitem already exists with ID: ".$dbcheck["MediaID"]; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }




    /**
     * Electoral Period
     */

    $tmpElectoralPeriodID = $dbp->getRow("SELECT ElectoralPeriodID FROM ?n WHERE ElectoralPeriodNumber = ?i LIMIT 1", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["ElectoralPeriod"], $item["electoralPeriod"]["number"]);

    if (!$tmpElectoralPeriodID) {

        $tmpElectoralPeriodID = $item["parliament"]."-".sprintf("%02d", $item["electoralPeriod"]["number"]);
        $dbp->query("INSERT INTO ?n SET ElectoralPeriodNumber = ?i, ElectoralPeriodID = ?s", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["ElectoralPeriod"], $item["electoralPeriod"]["number"], $tmpElectoralPeriodID);

    }

    $item["electoralPeriod"] = $tmpElectoralPeriodID;





    /**
     * Session of Electoral Period
     */

    $item["session"]["SessionID"] = $item["electoralPeriod"]["ElectoralPeriodID"].sprintf("%04d", $item["session"]["number"]);

    $tmpSession = $dbp->getRow("SELECT * FROM ?n WHERE SessionID = ?s LIMIT 1", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Session"], $item["session"]["SessionID"]);

    if (!$tmpSession) {

        $dbp->query("INSERT INTO ?n SET
                        SessionID=?s,
                        SessionNumber=?s,
                        SessionElectoralPeriodID=?s,
                        SessionDateStart=?s,
                        SessionDateEnd=?s",
                        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Session"],
                        $item["session"]["SessionID"],
                        $item["session"]["number"],
                        $item["electoralPeriod"]["ElectoralPeriodID"],
                        $item["session"]["dateStart"],
                        $item["session"]["dateEnd"]);

    }




    /**
     * AgendaItem of Session of Electoral Period
     */

    $tmpAgendaItem = $dbp->getRow("SELECT * FROM ?n
                                    WHERE
                                        AgendaItemSessionID = ?s
                                    AND
                                        AgendaItemOfficialTitle LIKE ?s
                                    AND
                                        AgendaItemTitle LIKE ?s",
                                    $config["parliament"][$item["parliament"]]["sql"]["tbl"]["AgendaItem"],
                                    $item["session"]["SessionID"],
                                    $item["agendaItem"]["officialTitle"],
                                    $item["agendaItem"]["title"]);
    if (!$tmpAgendaItem) {

        $dbp->query("INSERT INTO ?n SET
                        AgendaItemSessionID=?s,
                        AgendaItemOfficialTitle=?s,
                        AgendaItemTitle=?s",
                        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["AgendaItem"],
                        $item["session"]["SessionID"],
                        $item["agendaItem"]["officialTitle"],
                        $item["agendaItem"]["title"]);

        $item["agendaItem"]["id"] = $dbp->insertId();

    } else {

        $item["agendaItem"]["id"] = $tmpAgendaItem["AgendaItemID"];

    }






    /**
     * Media Add
     */

    $nextID = $dbp->getOne("SELECT MediaID FROM ?n WHERE MediaID LIKE ?s ORDER BY MediaID DESC LIMIT 1",
                            $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Media"], $item["session"]["SessionID"]."%");

    if (!$nextID) {

        $nextID = $item["session"]["SessionID"].sprintf("%03d", 1);

    } else {
        $nextID = ((int)substr($nextID, -3))+1;
        $nextID = $item["session"]["SessionID"].sprintf("%03d", $nextID);

    }

    try {
        $dbp->query("INSERT INTO ?n SET
                        MediaID=?s,
                        MediaOriginID=?s,
                        MediaOriginMediaID=?s,
                        MediaAgendaItemID=?s,
                        MediaCreator=?s,
                        MediaLicense=?s,
                        MediaOrder=0,
                        MediaAligned=0,
                        MediaPublic=1,
                        MediaDateStart=?s,
                        MediaDateEnd=?s,
                        MediaDuration=?i,
                        MediaVideoFileURI=?s,
                        MediaAudioFileURI=?s,
                        MediaSourcePage=?s,
                        MediaThumbnailURI=?s,
                        MediaThumbnailCreator=?s,
                        MediaThumbnailLicense=?s,
                        MediaAdditionalInformation=?s",
                        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Media"],
                        $nextID,
                        $item["media"]["originID"],
                        $item["media"]["originMediaID"],
                        $item["agendaItem"]["id"],
                        $item["media"]["creator"],
                        $item["media"]["license"],
                        $item["dateStart"],
                        $item["dateEnd"],
                        $item["media"]["duration"],
                        $item["media"]["videoFileURI"],
                        $item["media"]["audioFileURI"],
                        $item["media"]["sourcePage"],
                        $item["media"]["thumbnailURI"],
                        $item["media"]["thumbnailCreator"],
                        $item["media"]["thumbnailLicense"],
                        $item["media"]["additionalInformation"]);
    } catch (exception $e) {

        reportConflict("Media","mediaAdd failed","","","Could not add Media with ID: originID: ".$item["media"]["originID"].", originMediaID: ".$item["media"]["originMediaID"]." (new id:".$nextID.") Error:".$e->getMessage(),$db);

        echo $e->getMessage();

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "2";
        $errorarray["title"] = "Database error";
        $errorarray["detail"] = "Adding media to database failed"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }

    foreach ($item["textContents"] as $textContent) {

        foreach ($textContent["textBody"] as $textBodyIndex => $textBodyItem) {
            $textContent["textBody"][$textBodyIndex]["text"] = simpleTextBodyArrayToHTMLString($textBodyItem);
        }

        $dbp->query("INSERT INTO ?n SET
                       TextOriginTextID=?s,
                       TextMediaID=?s,
                       TextType=?s,
                       TextBody=?s,
                       TextSourceURI=?s,
                       TextCreator=?s,
                       TextLicense=?s,
                       TextHash=?s,
                       TextLanguage=?s",
                        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"],
                        $textContent["originTextID"],
                        $nextID,
                        $textContent["type"],
                        json_encode($textContent["textBody"]),
                        $textContent["sourceURI"],
                        $textContent["creator"],
                        $textContent["license"],
                        hash("sha256",json_encode($textContent["textBody"])),
                        $textContent["language"]);

    }

    include_once(__DIR__."/document.php");
    foreach ($item["documents"] as $document) {

        if (!$document["sourceURI"]) {

            reportConflict("Media","mediaAdd document no sourceURI","","","Could not add Document to Database because sourceURI was missing for MediaID ".$nextID." personJSON: ".json_encode($document),$db);

            continue;


        } else {

            $tmpDocumentID = $db->getOne("SELECT DocumentID FROM ?n WHERE DocumentSourceURI = ?s", $config["platform"]["sql"]["tbl"]["Document"], $document["sourceURI"]);

            if (!$tmpDocumentID) {

                $db->query("INSERT INTO ?n
                            SET
                                DocumentType=?s,
                                DocumentWikidataID=?s,
                                DocumentLabel=?s,
                                DocumentLabelAlternative=?s,
                                DocumentAbstract=?s,
                                DocumentThumbnailURI=?s,
                                DocumentThumbnailCreator=?s,
                                DocumentThumbnailLicense=?s,
                                DocumentSourceURI=?s,
                                DocumentEmbedURI=?s,
                                DocumentAdditionalInformation=?s",
                            $config["platform"]["sql"]["tbl"]["Document"],
                                $document["type"],
                                $document["wikidataID"],
                                $document["label"],
                                $document["labelAlternative"],
                                (($document["abstract"]) ? $document["abstract"] : "undefined"),
                                $document["thumbnailURI"],
                                $document["thumbnailCreator"],
                                $document["thumbnailLicense"],
                                $document["sourceURI"],
                                $document["embedURI"],
                                $document["additionalInformation"]);
                $tmpDocumentID = $db->insertId();

            }

            $document["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";
            $document["context"] = ($document["context"]) ? $document["context"] : "proceedingsReference";
            $document["frametrailType"] = ($document["frametrailType"]) ? $document["frametrailType"] : "Annotation";

            $tmpAnnotationItem = array(
                "AnnotationMediaID" => $nextID,
                "AnnotationType" => "document",
                "AnnotationResourceID" => $tmpDocumentID,
                "AnnotationContext" => $document["context"],
                "AnnotationFrametrailType" => $document["frametrailType"],
                "AnnotationTimeStart" => $document["timeStart"],
                "AnnotationTimeEnd" => $document["timeEnd"],
                "AnnotationCreator" => $document["creator"],
                "AnnotationTags" => $document["tags"],
                "AnnotationAdditionalInformation" => $document["additionalInformation"]
            );

            $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);

        }

    }


    include_once(__DIR__."/person.php");
    include_once(__DIR__."/organisation.php");

    if (count($item["people"]) < 1) {

        reportConflict("Media","mediaAdd no person",$nextID,"","Media has no people.",$db);

    }

    foreach ($item["people"] as $person) {

        $personWD = apiV1(["action" => "wikidataService", "itemType" => "person", "str" => $person["label"]]);

        if (($personWD["meta"]["requestStatus"] == "success") && (count($personWD["data"]) > 0)) {
            //Person found in Wikidata

            if (gettype($personWD["data"][0]["factionID"]) == "array") {

                if (count($personWD["data"][0]["factionID"]) == 0) {
                    unset($personWD["data"][0]["factionID"]);
                } else {
                    foreach ($personWD["data"][0]["factionID"] as $tmpFactionID) {

                        if (preg_match("/(Q|P)\d+/i", $tmpFactionID)) {
                            $personWD["data"][0]["faction_wp"] = $tmpFactionID;
                            break;
                        }

                    }
                }



            } elseif (preg_match("/(Q|P)\d+/i", $personWD["data"][0]["factionID"])) {

                $personWD["data"][0]["faction_wp"] = $personWD["data"][0]["factionID"];

            }

            if (gettype($personWD["data"][0]["partyID"]) == "array") {


                if (count($personWD["data"][0]["partyID"]) == 0) {
                    unset($personWD["data"][0]["partyID"]);
                } else {

                    foreach ($personWD["data"][0]["partyID"] as $tmpPartyID) {

                        if (preg_match("/(Q|P)\d+/i", $tmpPartyID)) {
                            $personWD["data"][0]["party_wp"] = $tmpPartyID;
                            break;
                        }

                    }
                }

            } elseif (preg_match("/(Q|P)\d+/i", $personWD["data"][0]["partyID"])) {

                $personWD["data"][0]["party_wp"] = $personWD["data"][0]["partyID"];

            }

            $personDB = personGetByID($personWD["data"][0]["id"]);

            if (($personDB["meta"]["requestStatus"] != "success") || (!array_key_exists("id",$personDB["data"]))) {
                //Person does not exist in Database

                $tmpNewPerson = array(
                    "PersonID" => $personWD["data"][0]["id"],
                    "PersonType" => "memberOfParliament", //$person["type"] //TODO: fix input
                    "PersonLabel" => $personWD["data"][0]["label"],
                    //"PersonFirstName"=>$personDB["data"][0]["firstName"], //TODO: WikidataServiceDumps
                    //"PersonLastName"=>$personDB["data"][0]["lastName"], //TODO: WikidataServiceDumps
                    //"PersonDegree"=>$personDB["data"][0]["degree"], //TODO: WikidataServiceDumps
                    "PersonBirthDate" => $personWD["data"][0]["birthDate"],
                    "PersonGender" => $personWD["data"][0]["gender"],
                    "PersonAbstract" => $personWD["data"][0]["abstract"],
                    "PersonThumbnailURI" => ((gettype($personWD["data"][0]["thumbnailURI"]) == "array") ? $personWD["data"][0]["thumbnailURI"][0] : $personWD["data"][0]["thumbnailURI"]),
                    "PersonThumbnailCreator" => $personWD["data"][0]["thumbnailCreator"],
                    "PersonThumbnailLicense" => $personWD["data"][0]["thumbnailLicense"],
                    "PersonWebsiteURI" => ((gettype($personWD["data"][0]["websiteURI"]) == "array") ? $personWD["data"][0]["websiteURI"][0] : $personWD["data"][0]["websiteURI"]),
                    "PersonEmbedURI" => $personWD["data"][0]["embedURI"],
                    "PersonOriginID" => $personWD["data"][0]["originID"],
                    "PersonPartyOrganisationID" => $personWD["data"][0]["party_wp"],
                    "PersonFactionOrganisationID" => $personWD["data"][0]["faction_wp"],
                    "PersonSocialMediaIDs" => json_encode($personWD["data"][0]["socialMediaIDs"]),
                    "PersonAdditionalInformation" => json_encode($personWD["data"][0]["additionalInformation"])
                );

                try {

                    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Person"], $tmpNewPerson);

                } catch (exception $e) {

                    reportConflict("Media", "mediaAdd Person Error", $nextID, "", "Person could not be added - MediaID " . $nextID . ", personJSON: " . json_encode($person)." Error:".$e->getMessage(), $db);
                    echo "Error:".$e->getMessage();
                    continue;

                }

                $personDB = personGetByID($personWD["data"][0]["id"]);

            }

            //Add Person Annotation
            $person["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";

            if (!$person["context"]) {
                reportConflict("Media", "mediaAdd person without context", "", "", "Person has no context - MediaID " . $nextID . " personJSON: " . json_encode($person), $db);
                $person["context"] = "unknown";
            }

            $tmpAnnotationPerson = array(
                "AnnotationMediaID" => $nextID,
                "AnnotationType" => "person",
                "AnnotationResourceID" => $personDB["data"]["id"],
                "AnnotationContext" => $person["context"],
                "AnnotationFrametrailType" => (($person["frametrailType"]) ? $person["frametrailType"] : "Annotation"),
                "AnnotationTimeStart" => $person["timeStart"],
                "AnnotationTimeEnd" => $person["timeEnd"],
                "AnnotationCreator" => $person["creator"],
                "AnnotationTags" => $person["tags"],
                "AnnotationAdditionalInformation" => $person["additionalInformation"]
            );

            $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationPerson);


            //Faction Add

            if (preg_match("/(Q|P)\d+/i", $personWD["data"][0]["faction_wp"])) {

                $tmpFaction = organisationGetByID($personWD["data"][0]["faction_wp"]);

                if (($tmpFaction["meta"]["requestStatus"] != "success") || (!array_key_exists("id",$tmpFaction["data"]))) {
                    //Add Organisation Faction
                    $tmpOrganisationFactionWD = apiV1(["action" => "wikidataService", "itemType" => "faction", "str" => $personWD["data"][0]["faction_wp"]]);

                    if (($tmpOrganisationFactionWD["meta"]["requestStatus"] == "success") && (count($tmpOrganisationFactionWD["data"]) > 0)) {

                        $tmpFactionObj = array(
                            "OrganisationID" => $tmpOrganisationFactionWD["data"][0]["id"],
                            "OrganisationType" => $tmpOrganisationFactionWD["data"][0]["type"],
                            "OrganisationLabel" => $tmpOrganisationFactionWD["data"][0]["label"],
                            "OrganisationLabelAlternative" => $tmpOrganisationFactionWD["data"][0]["labelAlternative"],
                            "OrganisationAbstract" => $tmpOrganisationFactionWD["data"][0]["abstract"],
                            "OrganisationThumbnailURI" => $tmpOrganisationFactionWD["data"][0]["thumbnailURI"],
                            "OrganisationThumbnailCreator" => $tmpOrganisationFactionWD["data"][0]["thumbnailCreator"], //TODO WIKIDATA DUMP
                            "OrganisationThumbnailLicense" => $tmpOrganisationFactionWD["data"][0]["thumbnailLicense"], //TODO WIKIDATA DUMP
                            "OrganisationEmbedURI" => $tmpOrganisationFactionWD["data"][0]["embedURI"], //TODO WIKIDATA DUMP
                            "OrganisationWebsiteURI" => $tmpOrganisationFactionWD["data"][0]["websiteURI"],
                            "OrganisationSocialMediaIDs" => json_encode($tmpOrganisationFactionWD["data"][0]["socialMediaIDs"]),
                            "OrganisationColor" => $tmpOrganisationFactionWD["data"][0]["color"], //TODO WIKIDATA DUMP
                            "OrganisationAdditionalInformation" => $tmpOrganisationFactionWD["data"][0]["additionalInformation"] //TODO WIKIDATA DUMP
                        );
                        $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Organisation"], $tmpFactionObj);

                    } else {

                        reportConflict("Media", "mediaAdd faction not found in wikidataDump", "", "", "MediaID " . $nextID . " Person in DB: " . json_encode($personDB["data"]), $db);

                    }
                }
            }

            //Party Add

            if (preg_match("/(Q|P)\d+/i", $personWD["data"][0]["party_wp"])) {

                $tmpParty = organisationGetByID($personWD["data"][0]["party_wp"]);

                if (($tmpParty["meta"]["requestStatus"] != "success") || (!array_key_exists("id",$tmpParty["data"]))) {
                    //Add Organisation Faction
                    $tmpOrganisationPartyWD = apiV1(["action" => "wikidataService", "itemType" => "party", "str" => $personWD["data"][0]["party_wp"]]);

                    if (($tmpOrganisationPartyWD["meta"]["requestStatus"] == "success") && (count($tmpOrganisationPartyWD["data"]) > 0)) {

                        $tmpPartyObj = array(
                            "OrganisationID" => $tmpOrganisationPartyWD["data"][0]["id"],
                            "OrganisationType" => $tmpOrganisationPartyWD["data"][0]["type"],
                            "OrganisationLabel" => $tmpOrganisationPartyWD["data"][0]["label"],
                            "OrganisationLabelAlternative" => $tmpOrganisationPartyWD["data"][0]["labelAlternative"],
                            "OrganisationAbstract" => $tmpOrganisationPartyWD["data"][0]["abstract"],
                            "OrganisationThumbnailURI" => $tmpOrganisationPartyWD["data"][0]["thumbnailURI"],
                            "OrganisationThumbnailCreator" => $tmpOrganisationPartyWD["data"][0]["thumbnailCreator"], //TODO WIKIDATA DUMP
                            "OrganisationThumbnailLicense" => $tmpOrganisationPartyWD["data"][0]["thumbnailLicense"], //TODO WIKIDATA DUMP
                            "OrganisationEmbedURI" => $tmpOrganisationPartyWD["data"][0]["embedURI"], //TODO WIKIDATA DUMP
                            "OrganisationWebsiteURI" => $tmpOrganisationPartyWD["data"][0]["websiteURI"],
                            "OrganisationSocialMediaIDs" => json_encode($tmpOrganisationPartyWD["data"][0]["socialMediaIDs"]),
                            "OrganisationColor" => $tmpOrganisationPartyWD["data"][0]["color"], //TODO WIKIDATA DUMP
                            "OrganisationAdditionalInformation" => $tmpOrganisationPartyWD["data"][0]["additionalInformation"] //TODO WIKIDATA DUMP
                        );
                        $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Organisation"], $tmpPartyObj);

                    } else {

                        reportConflict("Media", "mediaAdd party not found in wikidataDump", "", "", "MediaID " . $nextID . " Person in DB: " . json_encode($personDB["data"]), $db);

                    }
                }
            }


        } else {
            //Person not found in Wikidata
            reportConflict("Media", "mediaAdd person not found", "", "", "Person not found in wikidata - MediaID " . $nextID . " personJSON: " . json_encode($person), $db);
        }



        //Current Faction Add

        if (array_key_exists("faction",$person)) {

            $tmpOrganisationFactionWD = apiV1(["action" => "wikidataService", "itemType" => "faction", "str" => $person["faction"]]);
            //echo "<pre>\n\napiSearch:<br>\n";
            //print_r($tmpOrganisationFactionWD);

            if (($tmpOrganisationFactionWD["meta"]["requestStatus"] == "success") && (count($tmpOrganisationFactionWD["data"])>0)) {

                $tmpFaction = organisationGetByID($tmpOrganisationFactionWD["data"][0]["id"]);

                if (($tmpFaction["meta"]["requestStatus"] != "success") || (!array_key_exists("id",$tmpFaction["data"]))) {
                    //Add Organisation Faction

                    $tmpFactionObj = array(
                        "OrganisationID" => $tmpOrganisationFactionWD["data"][0]["id"],
                        "OrganisationType" => $tmpOrganisationFactionWD["data"][0]["type"],
                        "OrganisationLabel" => $tmpOrganisationFactionWD["data"][0]["label"],
                        "OrganisationLabelAlternative" => $tmpOrganisationFactionWD["data"][0]["labelAlternative"],
                        "OrganisationAbstract" => $tmpOrganisationFactionWD["data"][0]["abstract"],
                        "OrganisationThumbnailURI" => $tmpOrganisationFactionWD["data"][0]["thumbnailURI"],
                        "OrganisationThumbnailCreator" => $tmpOrganisationFactionWD["data"][0]["thumbnailCreator"], //TODO WIKIDATA DUMP
                        "OrganisationThumbnailLicense" => $tmpOrganisationFactionWD["data"][0]["thumbnailLicense"], //TODO WIKIDATA DUMP
                        "OrganisationEmbedURI" => $tmpOrganisationFactionWD["data"][0]["embedURI"], //TODO WIKIDATA DUMP
                        "OrganisationWebsiteURI" => $tmpOrganisationFactionWD["data"][0]["websiteURI"],
                        "OrganisationSocialMediaIDs" => json_encode($tmpOrganisationFactionWD["data"][0]["socialMediaIDs"]),
                        "OrganisationColor" => $tmpOrganisationFactionWD["data"][0]["color"], //TODO WIKIDATA DUMP
                        "OrganisationAdditionalInformation" => $tmpOrganisationFactionWD["data"][0]["additionalInformation"] //TODO WIKIDATA DUMP
                    );
                    echo $db->parse("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Organisation"], $tmpFactionObj);
                    $db->query("INSERT INTO ?n SET ?u", $config["platform"]["sql"]["tbl"]["Organisation"], $tmpFactionObj);

                    //$tmpFaction["data"] = $tmpFactionObj;
                    $tmpFaction = organisationGetByID($tmpOrganisationFactionWD["data"][0]["id"]);

                }
                //echo "\nTmpFaction:\n";
                //print_r($tmpFaction);
                if (preg_match("/(Q|P)\d+/i", $tmpFaction["data"]["id"])) {

                    $annotationFaction["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";

                    $tmpAnnotationItem = array(
                        "AnnotationMediaID" => $nextID,
                        "AnnotationType" => "organisation",
                        "AnnotationResourceID" => $tmpFaction["data"]["id"],
                        "AnnotationContext" => $person["context"]."-faction",
                        "AnnotationFrametrailType" => "Annotation",
                        //"AnnotationTimeStart" => "",
                        //"AnnotationTimeEnd" => "",
                        "AnnotationCreator" => $annotationFaction["creator"],
                        "AnnotationTags" => "",
                        "AnnotationAdditionalInformation" => ""
                    );

                    $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);

                } else {

                    reportConflict("Media", "mediaAdd current faction did not match wikidata id scheme", $nextID, "", "MediaID " . $nextID . " Person: " . json_encode($person), $db);

                }

            } else {

                reportConflict("Media", "mediaAdd current faction not found in wikidataDump", $nextID, "", "MediaID " . $nextID . " Person: " . json_encode($person), $db);

            }
        }

    }




    $return["meta"]["requestStatus"] = "success";

    return $return;


}

?>
