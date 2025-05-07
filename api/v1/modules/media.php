<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.conflicts.php");
require_once (__DIR__."/../../../modules/utilities/functions.entities.php");
require_once (__DIR__."/../../../modules/utilities/textArrayConverters.php");

/**
 * @param string $id MediaID
 * @return array
 */
function mediaGetByID($id = false, $db = false, $dbp = false) {

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
        $errorarray["detail"] = "Could not parse ID";
        array_push($return["errors"], $errorarray);
        return $return;

    }



    if (!$id) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request is missing";
        array_push($return["errors"], $errorarray);

        return $return;

    } elseif (!array_key_exists($parliament,$config["parliament"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "MediaID could not be associated with a parliament";
        array_push($return["errors"], $errorarray);

        return $return;

    } else {

        if (!$db) {
            try {

                $db = new SafeMySQL(array(
                    'host'	=> $config["platform"]["sql"]["access"]["host"],
                    'user'	=> $config["platform"]["sql"]["access"]["user"],
                    'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                    'db'	=> $config["platform"]["sql"]["db"]
                ));

            } catch (exception $e) {

                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "503";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Database connection error";
                $errorarray["detail"] = "Connecting to platform database failed";
                array_push($return["errors"], $errorarray);
                return $return;

            }
        }


        if (!$dbp) {
            try {

                $dbp = new SafeMySQL(array(
                    'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
                    'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
                    'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
                    'db'	=> $config["parliament"][$parliament]["sql"]["db"]
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

            if (($item["MediaPublic"] == 0) && ($_SESSION["userdata"]["role"] != "admin") && (!is_cli())) {

                $return["meta"]["requestStatus"] = "error";
                $return["errors"] = array();
                $errorarray["status"] = "511";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Media not allowed to access";
                $errorarray["detail"] = "The media is not public";
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
            $return["data"]["attributes"]["public"] = (($item["MediaPublic"] === "1") ? true : false);
            $return["data"]["attributes"]["aligned"] = (($item["MediaAligned"] === "1") ? true : false);
            $return["data"]["attributes"]["dateStartTimestamp"] = strtotime($item["MediaDateStart"]);
            $return["data"]["attributes"]["dateStart"] = $item["MediaDateStart"];
            $return["data"]["attributes"]["dateEnd"] = $item["MediaDateEnd"];
            $return["data"]["attributes"]["duration"] = (float)$item["MediaDuration"];
            $return["data"]["attributes"]["videoFileURI"] = $item["MediaVideoFileURI"];
            $return["data"]["attributes"]["audioFileURI"] = $item["MediaAudioFileURI"];
            $return["data"]["attributes"]["sourcePage"] = $item["MediaSourcePage"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["MediaThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["MediaThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["MediaThumbnailLicense"];
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["MediaAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["MediaLastChanged"];
            $return["data"]["attributes"]["lastChangedTimestamp"] = strtotime($item["MediaLastChanged"]);
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

            $return["data"]["attributes"]["textContentsCount"] = count($return["data"]["attributes"]["textContents"]);

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

            $return["data"]["relationships"]["people"]["data"] = array();
            $return["data"]["relationships"]["organisations"]["data"] = array();
            $return["data"]["relationships"]["documents"]["data"] = array();
            $return["data"]["relationships"]["terms"]["data"] = array();

            //$return["data"]["relationships"]["annotations"]["data"] = array();

            $annotations = $dbp->getAll("SELECT * FROM ?n WHERE AnnotationMediaID=?s",$config["parliament"][$parliament]["sql"]["tbl"]["Annotation"],$item["MediaID"]);
            /*
             SELECT * FROM `annotation` WHERE AnnotationMediaID = "" ORDER BY CASE
                WHEN AnnotationContext = 'main-speaker' THEN 1
                WHEN AnnotationContext = 'president' THEN 2
                WHEN AnnotationContext = 'vice-president' THEN 3
                WHEN AnnotationType = 'main-faction' THEN 4
                WHEN AnnotationType = 'person' THEN 5
                WHEN AnnotationType = 'organisation' THEN 6
                ELSE 7
                end,
                AnnotationContext ASC, AnnotationType ASC;
             */
            $annotations = annotationRawSortByMainSpeaker($annotations);

            $tmpResources = array();
            foreach ($annotations as $annotation) {

                $annotationAttributes = array(
                    "timeStart" =>  $annotation["AnnotationTimeStart"],
                    "timeEnd" =>  $annotation["AnnotationTimeEnd"],
                    "context" =>  $annotation["AnnotationContext"],
                    "additionalInformation" =>  json_decode($annotation["AnnotationAdditionalInformation"],true)
                );

                $tmpAnnotationItem = array();
                /*if (in_array($annotation["AnnotationResourceID"],$tmpResources)) {
                    continue;
                }
                array_push($tmpResources,$annotation["AnnotationResourceID"]);
                */
                switch ($annotation["AnnotationType"]) {


                    case "document":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i LIMIT 1", $config["platform"]["sql"]["tbl"]["Document"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "document";
                        $tmpAnnotationItem["id"] = $annotation["AnnotationResourceID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["DocumentType"];
                        $tmpAnnotationItem["attributes"]["label"] = str_replace(array("\r","\n"), " ", $ditem["DocumentLabel"]);
                        //$tmpAnnotationItem["attributes"]["labelAlternative"] = str_replace(array("\r","\n"), " ", $ditem["DocumentLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = json_decode($ditem["DocumentLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["DocumentThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["DocumentThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["DocumentThumbnailLicense"];
                        
                        $documentAdditionalinformation = json_decode($ditem["DocumentAdditionalInformation"],true);
                        $annotationAdditionalInformation = json_decode($annotation["AnnotationAdditionalInformation"],true);
                        //$tmpAnnotationItem["attributes"]["additionalInformation"] = array_merge_recursive((is_array($documentAdditionalinformation) ? $documentAdditionalinformation : array()), (is_array($annotationAdditionalInformation) ? $annotationAdditionalInformation : array()));
                        $tmpAnnotationItem["attributes"]["additionalInformation"] = (is_array($documentAdditionalinformation) ? $documentAdditionalinformation : array());

                        $tmpAnnotationItem["attributes"]["sourceURI"] = $ditem["DocumentSourceURI"];
                        $tmpAnnotationItem["attributes"]["embedURI"] = $ditem["DocumentEmbedURI"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];

                        $annotationAttributes["additionalInformation"]["originID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["originID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["originID"] : array());
                        //$annotationAttributes["additionalInformation"]["creator"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["creator"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["creator"] : array());
                        $annotationAttributes["additionalInformation"]["procedureIDs"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["procedureIDs"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["procedureIDs"] : array());

                        if (!in_array($annotation["AnnotationResourceID"],$tmpResources)) {

                            array_push($tmpResources,$annotation["AnnotationResourceID"]);
                            array_push($return["data"]["relationships"]["documents"]["data"], $tmpAnnotationItem);

                        }

                    break;


                    case "organisation":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s LIMIT 1", $config["platform"]["sql"]["tbl"]["Organisation"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "organisation";

                        $tmpAnnotationItem["id"] = $annotation["AnnotationResourceID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["OrganisationType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["OrganisationLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = json_decode($ditem["OrganisationLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["OrganisationThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["OrganisationThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["OrganisationThumbnailLicense"];
                        
                        $organisationAdditionalinformation = json_decode(($ditem["OrganisationAdditionalInformation"] ?? ""),true);
                        $annotationAdditionalInformation = json_decode($annotation["AnnotationAdditionalInformation"],true);
                        //$tmpAnnotationItem["attributes"]["additionalInformation"] = array_merge_recursive((is_array($organisationAdditionalinformation) ? $organisationAdditionalinformation : array()), (is_array($annotationAdditionalInformation) ? $annotationAdditionalInformation : array()));
                        $tmpAnnotationItem["attributes"]["additionalInformation"] = (is_array($organisationAdditionalinformation) ? $organisationAdditionalinformation : array());

                        $tmpAnnotationItem["attributes"]["color"] = $ditem["OrganisationColor"];
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];


                        $annotationAttributes["additionalInformation"]["fragDenStaatID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"] : "");
                        $annotationAttributes["additionalInformation"]["abgeordnetenwatchID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"] : "");


                        if (!in_array($annotation["AnnotationResourceID"],$tmpResources)) {

                            array_push($tmpResources, $annotation["AnnotationResourceID"]);
                            array_push($return["data"]["relationships"]["organisations"]["data"], $tmpAnnotationItem);

                        }

                    break;



                    case "term":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE TermID=?s LIMIT 1", $config["platform"]["sql"]["tbl"]["Term"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "term";
                        $tmpAnnotationItem["id"] = $annotation["AnnotationResourceID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["TermType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["TermLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = json_decode($ditem["TermLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["websiteURI"] = $ditem["TermWebsiteURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["TermThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["TermThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["TermThumbnailLicense"];
                        
                        $termAdditionalinformation = json_decode($ditem["TermAdditionalInformation"],true);
                        $annotationAdditionalInformation = json_decode($annotation["AnnotationAdditionalInformation"],true);
                        //$tmpAnnotationItem["attributes"]["additionalInformation"] = array_merge_recursive((is_array($termAdditionalinformation) ? $termAdditionalinformation : array()), (is_array($annotationAdditionalInformation) ? $annotationAdditionalInformation : array()));
                        $tmpAnnotationItem["attributes"]["additionalInformation"] = (is_array($termAdditionalinformation) ? $termAdditionalinformation : array());

                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];

                        $annotationAttributes["additionalInformation"]["fragDenStaatID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"] : "");
                        $annotationAttributes["additionalInformation"]["abgeordnetenwatchID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"] : "");

                        if (!in_array($annotation["AnnotationResourceID"],$tmpResources)) {

                            array_push($tmpResources, $annotation["AnnotationResourceID"]);
                            array_push($return["data"]["relationships"]["terms"]["data"], $tmpAnnotationItem);
                        }

                    break;



                    case "person":

                        //TODO: What if there is no party or no faction?
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
                        //$tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $pitem["PersonType"];
                        $tmpAnnotationItem["attributes"]["label"] = $pitem["PersonLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = json_decode($pitem["PersonLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["degree"] = $pitem["PersonDegree"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $pitem["PersonThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $pitem["PersonThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $pitem["PersonThumbnailLicense"];
                        
                        $personAdditionalinformation = json_decode($pitem["PersonAdditionalInformation"],true);
                        $annotationAdditionalInformation = json_decode($annotation["AnnotationAdditionalInformation"],true);
                        //$tmpAnnotationItem["attributes"]["additionalInformation"] = array_merge_recursive((is_array($personAdditionalinformation) ? $personAdditionalinformation : array()), (is_array($annotationAdditionalInformation) ? $annotationAdditionalInformation : array()));
                        $tmpAnnotationItem["attributes"]["additionalInformation"] = (is_array($personAdditionalinformation) ? $personAdditionalinformation : array());

                        $tmpAnnotationItem["attributes"]["party"]["id"] = $pitem["PartyID"];
                        $tmpAnnotationItem["attributes"]["party"]["label"] = $pitem["PartyLabel"];
                        $tmpAnnotationItem["attributes"]["party"]["labelAlternative"] = json_decode($pitem["PartyLabelAlternative"]);
                        $tmpAnnotationItem["attributes"]["faction"]["id"] = $pitem["FactionID"];
                        $tmpAnnotationItem["attributes"]["faction"]["label"] = $pitem["FactionLabel"];
                        $tmpAnnotationItem["attributes"]["faction"]["labelAlternative"] = json_decode($pitem["FactionLabelAlternative"]);
                        $tmpAnnotationItem["links"]["self"] = $config["dir"]["api"]."/".$tmpAnnotationItem["type"]."/".$tmpAnnotationItem["id"];

                        $annotationAttributes["additionalInformation"]["fragDenStaatID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["fragDenStaatID"] : "");
                        $annotationAttributes["additionalInformation"]["abgeordnetenwatchID"] = (!empty($tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) ? $tmpAnnotationItem["attributes"]["additionalInformation"]["abgeordnetenwatchID"] : "");
                        $annotationAttributes["additionalInformation"]["originID"] = (!empty($pitem["PersonOriginID"]) ? $pitem["PersonOriginID"] : "");


                        if (!in_array($annotation["AnnotationResourceID"],$tmpResources)) {

                            array_push($tmpResources, $annotation["AnnotationResourceID"]);
                            array_push($return["data"]["relationships"]["people"]["data"], $tmpAnnotationItem);

                        }

                    break;
                }



                $return["data"]["annotations"]["data"][] = array(
                    "type"      =>  $annotation["AnnotationType"],
                    "id" =>  $annotation["AnnotationResourceID"],
                    "attributes" => $annotationAttributes
                );


            }

            $return["data"]["relationships"]["documents"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=document";
            $return["data"]["relationships"]["organisations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=organisation";
            $return["data"]["relationships"]["terms"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=term";
            $return["data"]["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"]."&type=person";

            //$return["data"]["relationships"]["annotations"]["links"]["self"] = $config["dir"]["api"]."/search/annotations?mediaID=".$return["data"]["id"];

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Media not found";
            $errorarray["detail"] = "Media with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}


function mediaSearch($parameter, $db = false, $dbp = false) {


    global $config;

    require_once (__DIR__."/../../../modules/utilities/functions.php");


    require_once (__DIR__."/../../../modules/search/functions.php");

    $filteredParameters = filterAllowedSearchParams($parameter, 'media');

    try {
        $search = searchSpeeches($filteredParameters);

        if (isset($search["hits"]["hits"])) {
            foreach ($search["hits"]["hits"] as $hit) {

                $resultData = $hit["_source"];
                $resultData["_score"] = $hit["_score"];
                $resultData["_highlight"] = $hit["highlight"];
                $resultData["_finds"] = $hit["finds"];
                $resultData["highlight_count"] = $hit["highlight_count"];

                $return["data"][] = $resultData;

            }

            $return["meta"]["attributes"]["speechFirstDateStr"] = $search["aggregations"]["dateFirst"]["value_as_string"];
            $return["meta"]["attributes"]["speechFirstDateTimestamp"] = $search["aggregations"]["dateFirst"]["value"];
            $return["meta"]["attributes"]["speechLastDateStr"] = $search["aggregations"]["dateLast"]["value_as_string"];
            $return["meta"]["attributes"]["speechLastDateTimestamp"] = $search["aggregations"]["dateLast"]["value"];
            foreach ($search["aggregations"]["types_count"]["factions"]["terms"]["buckets"] as $buckets) {
                $return["meta"]["attributes"]["resultsPerFaction"][$buckets["key"]] = $buckets["doc_count"];
            }
            foreach($search["aggregations"]["datesCount"]["buckets"] as $day) {
                $return["meta"]["attributes"]["days"][$day["key_as_string"]] = $day;
            }

            $return["meta"]["requestStatus"] = "success";

            //TODO: Check if this makes sense here
            $return["meta"]["results"]["count"] = ((gettype($search["hits"]["hits"]) == "array") || (gettype($search["hits"]["hits"]) == "object")) ? count($search["hits"]["hits"]) : 0;
            $return["meta"]["results"]["total"] = $search["hits"]["total"]["value"];
            $return["meta"]["results"]["totalHits"] = $search["hits"]["totalHits"] ?? 0;

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "3";
            $errorarray["title"] = "OpenSearch Error";
            $errorarray["detail"] = json_encode($search);
            array_push($return["errors"], $errorarray);

        }

    } catch (Exception $e) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "3";
        $errorarray["title"] = "OpenSearch Error";
        $errorarray["detail"] = json_encode($search);
        array_push($return["errors"], $errorarray);
    }

    $return["links"]["self"] = $config["dir"]["api"]."/"."search/media?".getURLParameterFromArray($filteredParameters);

    return $return;


}

/**
 * Add Media
 */

function mediaAdd($item = false, $db = false, $dbp = false, $entityDump = false) {

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
        $errorarray["detail"] = "Missing parameter 'parliament'";
        array_push($return["errors"], $errorarray);

    }

    if (!is_numeric($item["electoralPeriod"]["number"])) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'electoralPeriod[number]'";
        array_push($return["errors"], $errorarray);
    }

    if (!is_numeric($item["session"]["number"])) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'session[number]'";
        array_push($return["errors"], $errorarray);
    }
    /**
     *
     * TODO:
     *
     *  Temp because parser/merger seems to have a problem with providing both parameter.
     *  So here we fix it:
     * If officialTitle fails (or is emtpy) it gets the title value.
     * If title fails (or is emtpy) it gets the officialTitle value.
     */
    $item["agendaItem"]["officialTitle"] = ($item["agendaItem"]["officialTitle"] ? $item["agendaItem"]["officialTitle"] : $item["agendaItem"]["title"]);
    $item["agendaItem"]["title"] = ($item["agendaItem"]["title"] ? $item["agendaItem"]["title"] : $item["agendaItem"]["officialTitle"]);


    if (!$item["agendaItem"]["officialTitle"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'agendaItem[officialTitle]'";
        array_push($return["errors"], $errorarray);
    }

    if (!$item["agendaItem"]["title"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'agendaItem[title]'";
        array_push($return["errors"], $errorarray);
    }

    if (!$item["media"]["videoFileURI"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'media[videoFileURI]'";
        array_push($return["errors"], $errorarray);
    }

    if (!$item["media"]["sourcePage"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'media[sourcePage]'";
        array_push($return["errors"], $errorarray);
    }

    if (!$item["dateStart"]) {
        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing parameter";
        $errorarray["detail"] = "Missing parameter 'dateStart'";
        array_push($return["errors"], $errorarray);
    }




    if (count($return["errors"]) > 0) {

        reportConflict("Media","mediaAdd failed - required fields missing","","","Item: ".json_encode($item)." ||| Errors: ".json_encode($return),$db);

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
            $errorarray["detail"] = "Connecting to platform database failed #1";
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
            $errorarray["detail"] = "Connecting to parliament database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    include_once(__DIR__."/person.php");
    include_once(__DIR__."/organisation.php");
    include_once(__DIR__."/term.php");



    /**
     * Electoral Period
     */

    $tmpElectoralPeriod = $dbp->getRow("SELECT * FROM ?n WHERE ElectoralPeriodNumber = ?i LIMIT 1", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["ElectoralPeriod"], $item["electoralPeriod"]["number"]);

    if (!$tmpElectoralPeriod) {

        $tmpElectoralPeriodID = $item["parliament"]."-".sprintf("%03d", $item["electoralPeriod"]["number"]);
        $dbp->query("INSERT INTO ?n
                        SET
                            ElectoralPeriodNumber = ?i,
                            ElectoralPeriodID = ?s,
                            ElectoralPeriodDateStart=?s,
                            ElectoralPeriodDateEnd=?s",
                    $config["parliament"][$item["parliament"]]["sql"]["tbl"]["ElectoralPeriod"],
                    $item["electoralPeriod"]["number"],
                    $tmpElectoralPeriodID,
                    $item["electoralPeriod"]["dateStart"],
                    $item["electoralPeriod"]["dateEnd"]);



    } else {

        $tmpElectoralPeriodID = $tmpElectoralPeriod["ElectoralPeriodID"];
        $tmpElectoralPeriodUpdate = array();
        if ((!$tmpElectoralPeriod["ElectoralPeriodDateStart"]) && ($item["electoralPeriod"]["dateStart"])) {

            $tmpElectoralPeriodUpdate[] = $dbp->parse("ElectoralPeriodDateStart=?s",$item["electoralPeriod"]["dateStart"]);

        }

        if ((!$tmpElectoralPeriod["ElectoralPeriodDateEnd"]) && ($item["electoralPeriod"]["dateEnd"])) {

            $tmpElectoralPeriodUpdate[] = $dbp->parse("ElectoralPeriodDateEnd=?s",$item["electoralPeriod"]["dateEnd"]);

        }

        if ($tmpElectoralPeriodUpdate) {
            $tmpElectoralPeriodUpdate = "UPDATE ".$config["parliament"][$item["parliament"]]["sql"]["tbl"]["ElectoralPeriod"]." SET " . implode(", ", $tmpElectoralPeriodUpdate) ." WHERE ElectoralPeriodID = ?s";
            $dbp->query($tmpElectoralPeriodUpdate,$tmpElectoralPeriodID);
        }

    }


    $item["electoralPeriod"] = $tmpElectoralPeriodID;





    /**
     * Session of Electoral Period
     */

    $item["session"]["SessionID"] = $item["electoralPeriod"].sprintf("%04d", $item["session"]["number"]);

    $tmpSession = $dbp->getRow("SELECT * FROM ?n WHERE SessionID = ?s LIMIT 1", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Session"], $item["session"]["SessionID"]);

    //Medias without proceedings texts have no infos about session start/end. To fix that, we reference the calculated start/end from the session files meta here.
    $item["session"]["dateStart"] = ($item["session"]["dateStart"] ? $item["session"]["dateStart"] : $item["meta"]["dateStart"]);
    $item["session"]["dateEnd"] = ($item["session"]["dateEnd"] ? $item["session"]["dateEnd"] : $item["meta"]["dateEnd"]);
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
                        $item["electoralPeriod"],
                        $item["session"]["dateStart"],
                        $item["session"]["dateEnd"]);

    } else {
        $tmpSessionUpdate = array();
        if ((!$tmpSession["SessionDateStart"]) && ($item["session"]["dateStart"])) {

            $tmpSessionUpdate[] = $dbp->parse("SessionDateStart=?s",$item["session"]["dateStart"]);

        }

        if ((!$tmpSession["SessionDateEnd"]) && ($item["session"]["dateEnd"])) {

            $tmpSessionUpdate[] = $dbp->parse("SessionDateEnd=?s",$item["session"]["dateEnd"]);

        }

        if ($tmpSessionUpdate) {
            $tmpSessionUpdate = "UPDATE ".$config["parliament"][$item["parliament"]]["sql"]["tbl"]["Session"]." SET " . implode(", ", $tmpSessionUpdate) ." WHERE SessionID = ?s";
            $dbp->query($tmpSessionUpdate,$item["session"]["SessionID"]);
        }

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

        if ((!$tmpAgendaItem["AgendaItemOrder"]) && ($item["agendaItem"]["order"])) {

            $dbp->query("UPDATE ?n SET
                        AgendaItemOrder=?i
                        WHERE
                        AgendaItemID=?i",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["AgendaItem"],
                $item["agendaItem"]["order"],
                $item["agendaItem"]["id"]);

        }

    }





    /**
     * Media Add
     */

    $tmpMediaItem = $dbp->getRow("SELECT * FROM ?n WHERE MediaSourcePage = ?s", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Media"], $item["media"]["sourcePage"]);

    if (!$tmpMediaItem) {

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
                            MediaAligned=?i,
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
                            ($item["media"]["aligned"] ? 1 : 0),
                            $item["dateStart"],
                            $item["dateEnd"],
                            ($item["media"]["duration"] ? (float)$item["media"]["duration"] : 0),
                            $item["media"]["videoFileURI"],
                            $item["media"]["audioFileURI"],
                            $item["media"]["sourcePage"],
                            $item["media"]["thumbnailURI"],
                            $item["media"]["thumbnailCreator"],
                            $item["media"]["thumbnailLicense"],
                            json_encode($item["media"]["additionalInformation"]));
        } catch (exception $e) {

            reportConflict("Media","mediaAdd failed","","","Could not add Media with ID: originID: ".$item["media"]["originID"].", originMediaID: ".$item["media"]["originMediaID"]." (new id:".$nextID.") Error:".$e->getMessage(),$db);

            echo $e->getMessage();

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "2";
            $errorarray["title"] = "Database error";
            $errorarray["detail"] = "Adding media to database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }

    } else {

        $nextID = $tmpMediaItem["MediaID"];
        $tmpMediaItemUpdate = array();
        if ((!$tmpMediaItem["MediaOriginID"]) && ($item["media"]["originID"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaOriginID=?s",$item["media"]["originID"]);
        }
        if ((!$tmpMediaItem["MediaOriginMediaID"]) && ($item["media"]["originMediaID"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaOriginMediaID=?s",$item["media"]["originMediaID"]);
        }
        //TODO: Check which order position is right
        if ((!$tmpMediaItem["MediaOrder"]) && ($item["media"]["order"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaOrder=?s",$item["media"]["order"]);
        }
        if ((!$tmpMediaItem["MediaOrder"]) && ($item["order"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaOrder=?i",$item["order"]);
        }
        if ((!$tmpMediaItem["MediaAligned"]) && ($item["media"]["aligned"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaAligned=?i",(int)$item["media"]["aligned"]);
        }
        if ((!$tmpMediaItem["MediaDateStart"]) && ($item["dateStart"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaDateStart=?s",$item["dateStart"]);
        }
        if ((!$tmpMediaItem["MediaDateEnd"]) && ($item["dateEnd"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaDateEnd=?s",$item["dateEnd"]);
        }
        if ((!$tmpMediaItem["MediaDuration"]) && ($item["media"]["duration"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaDuration=?i",$item["media"]["duration"]);
        }
        if ((!$tmpMediaItem["MediaVideoFileURI"]) && ($item["media"]["videoFileURI"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaVideoFileURI=?s",$item["media"]["videoFileURI"]);
        }
        if ((!$tmpMediaItem["MediaAudioFileURI"]) && ($item["media"]["audioFileURI"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaAudioFileURI=?s",$item["media"]["audioFileURI"]);
        }
        if ((!$tmpMediaItem["MediaThumbnailURI"]) && ($item["media"]["thumbnailURI"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaThumbnailURI=?s",$item["media"]["thumbnailURI"]);
        }
        if ((!$tmpMediaItem["MediaThumbnailCreator"]) && ($item["media"]["thumbnailCreator"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaThumbnailCreator=?s",$item["media"]["thumbnailCreator"]);
        }
        if ((!$tmpMediaItem["MediaThumbnailLicense"]) && ($item["media"]["thumbnailCreator"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaThumbnailLicense=?s",$item["media"]["thumbnailLicense"]);
        }
        if ((!$tmpMediaItem["MediaAdditionalInformation"]) && ($item["media"]["additionalInformation"])) {
            $tmpMediaItemUpdate[] = $dbp->parse("MediaAdditionalInformation=?s",json_encode($item["media"]["additionalInformation"]));
        }

        if ($tmpMediaItemUpdate) {
            $tmpMediaItemUpdate = "UPDATE ".$config["parliament"][$item["parliament"]]["sql"]["tbl"]["Media"]." SET " . implode(", ", $tmpMediaItemUpdate) ." WHERE MediaID = ?s";
            $dbp->query($tmpMediaItemUpdate,$tmpMediaItem["MediaID"]);
        }


    }



    /**
     * Text
     *
     *
     *
     */

    /**
     * TODO TEMP
     * as long the speech["debug"]["confidence"] parameter is missing, we need to check if there is a person with ["context"] = "main-proceeding-speaker"
     */

    /*
    if ((!$item["people"]) || (gettype($item["people"]) != "array")) {
        $item["people"] = array();
    }

    $confidence = 1;

    foreach ($item["people"] as $person) {
        if ($person["context"] == "main-proceeding-speaker") {
            $confidence = 0.5;
        }
    }
    */

    //TODO: As long as NER is mapping factions to wrong wids this is a hotfix
    ob_start();
    include(__DIR__."/../../../data/ner-matching.php");
    $todoMapping = json_decode(ob_get_clean(),true);


    if (!$entityDump) {

        require_once (__DIR__."/../../../data/entity-dump/function.entityDump.php");
        $entityDump = getEntityDump(array("type"=>"all","wiki"=>true,"wikikeys"=>"true"),$db);

    }

    if (($item["debug"]["confidence"] == 1) && (count($item["debug"]["linkedMediaIndexes"]) == 1)) {

        $dbp->query("DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationContext = ?s", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $nextID, "NER");

        foreach ($item["textContents"] as $textContentKey => $textContent) {

            if (gettype($textContent["textBody"]) == "string") {

                $textContent["textBody"] = json_decode($textContent["textBody"],true);

            }

            /*
             $tmpTextItem = $dbp->getRow("SELECT * FROM ?n WHERE TextMediaID = ?s AND TextType=?s AND TextLanguage=?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"], $nextID, $textContent["type"], $textContent["language"]);
            */

            foreach ($textContent["textBody"] as $textBodyIndex => $textBodyItem) {

                //$textContent["textBody"][$textBodyIndex]["text"] = simpleTextBodyArrayToHTMLString($textBodyItem);

                $outputHTML = '<p data-type="'.$textBodyItem['type'].'">';

                foreach ($textBodyItem['sentences'] as $sentenceKey => $sentence) {

                    $entities = array();

                    if (is_array($sentence["entities"]) && count($sentence["entities"]) > 0) {

                        foreach ($sentence["entities"] as $entity) {


                            if (!$entity["wid"]) {
                                continue;
                            }


                            //TODO: As long as NER is mapping factions to wrong wids this is a hotfix
                            if (array_key_exists($entity["wid"], $todoMapping)) {

                                $entity["wid"] = $todoMapping[$entity["wid"]]["routing"];

                            }

                            if (!array_key_exists($entity["wid"],$entityDump["data"])) {

                                reportEntitySuggestion($entity["wid"], $entity["wtype"], $entity["label"], json_encode($entity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $nextID, $db);

                            } else {

                                $entity["additionalInformation"]["confidence"] = $entity["score"];

                                $tmpAnnotation = array(
                                    "AnnotationMediaID" => $nextID,
                                    "AnnotationType" => $entityDump["data"][$entity["wid"]]["type"],
                                    "AnnotationResourceID" => (($entityDump["data"][$entity["wid"]]["type"] == "document") ? $entityDump["data"][$entity["wid"]]["optvID"] : $entity["wid"]),
                                    "AnnotationContext" => (($entity["context"]) ? $entity["context"] : "NER"),  //TODO: Context?
                                    "AnnotationFrametrailType" => (($entity["frametrailType"]) ? $entity["frametrailType"] : "Annotation"), //TODO Type?
                                    "AnnotationTimeStart" => $sentence["timeStart"],
                                    "AnnotationTimeEnd" => $sentence["timeEnd"],
                                    "AnnotationCreator" => "NER",
                                    "AnnotationTags" => $entity["tags"],
                                    "AnnotationAdditionalInformation" => json_encode($entity["additionalInformation"])
                                );

                                $entities[] = $entity["wid"];

                                try {

                                    $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotation);

                                } catch (exception $e) {

                                    reportConflict("Media", "mediaAdd Annotation", $nextID, "", "Could not add Annotation to Media. TextItem: " . $tmpAnnotation . " ||| Error:" . $e->getMessage(), $db);

                                }

                            }


                        }

                    }


                    //Delete entities from text
                    unset($textContent["textBody"][$textBodyIndex]["sentences"][$sentenceKey]["entities"]);
                    //unset($sentence["entities"]);

                    $timeAttributes = '';

                    if (isset($sentence['timeStart']) && isset($sentence['timeEnd'])) {

                        $timeAttributes = ' class="timebased" data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';

                    }

                    // If entities should be at the html als data-attribute - uncomment this.
                    // $tempEntityAttribute = ((count($entities) > 0) ? ' data-entities="'.json_encode($entities).'"' : "");

                    $tempEntityAttribute = "";

                    $sentenceText = (is_array($sentence)) ? $sentence['text'] : $sentence;
                    $outputHTML .= '<span'.$timeAttributes.$tempEntityAttribute.'>'.$sentenceText.' </span>';

                }

                $outputHTML .= '</p>';
                $textContent["textBody"][$textBodyIndex]["text"] = $outputHTML;
            }

            $textHash = hash("sha256",json_encode($textContent["textBody"]));

            //TODO Temp Fix - add hash afterwards

            /*
            $tmpTextItem = $dbp->getRow("SELECT * FROM ?n WHERE TextMediaID = ?s AND TextHash = ?s AND TextType=?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"], $nextID, $textHash, $textContent["type"]);
            */
            $tmpTextItem = $dbp->getRow("SELECT * FROM ?n WHERE TextMediaID = ?s AND TextType=?s AND TextLanguage=?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"], $nextID, $textContent["type"], $textContent["language"]);

            if (!$tmpTextItem) {

                try {
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
                        $textHash,
                        $textContent["language"]);
                } catch (exception $e) {

                    reportConflict("Media", "mediaAdd TextContent Add failed", $nextID, "", "Could not add Text to Media. TextItem: ". $textContent . " ||| Error:" . $e->getMessage(), $db);

                }

            } else {

                /*
                 * TODO Check whats up here
                $tmpTextItemUpdate = array();
                if ((!$tmpTextItem["TextOriginTextID"]) && ($textContent["originTextID"])) {
                    $tmpTextItemUpdate[] = $dbp->parse("TextOriginTextID=?s", $textContent["originTextID"]);
                }
                if ((!$tmpTextItem["TextSourceURI"]) && ($textContent["sourceURI"])) {
                    $tmpTextItemUpdate[] = $dbp->parse("TextSourceURI=?s", $textContent["sourceURI"]);
                }

                if ($tmpTextItemUpdate) {
                    $tmpTextItemUpdate = "UPDATE " . $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"] . " SET " . implode(", ", $tmpTextItemUpdate) . " WHERE TextID = ?i";
                    $dbp->query($tmpTextItemUpdate, $textContent["TextID"]);
                }
                */

                try {
                    $dbp->query("UPDATE ?n SET
                               TextOriginTextID=?s,
                               TextBody=?s,
                               TextSourceURI=?s,
                               TextCreator=?s,
                               TextLicense=?s,
                               TextHash=?s
                               WHERE TextID=?i",
                        $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Text"],
                        $textContent["originTextID"],
                        json_encode($textContent["textBody"]),
                        $textContent["sourceURI"],
                        $textContent["creator"],
                        $textContent["license"],
                        $textHash,
                        $tmpTextItem["TextID"]);
                } catch (exception $e) {

                    reportConflict("Media", "mediaAdd TextContent Add failed", $nextID, "", "Could not update Text of Media. TextItem: ". $textContent . " ||| Error:" . $e->getMessage(), $db);

                }

            }

        }



        /**
         * Document Add
         */

        include_once(__DIR__."/document.php");
        foreach ($item["documents"] as $document) {

            if (!$document["sourceURI"]) {

                reportConflict("Media","mediaAdd document no sourceURI",$nextID,"","Could not add Document to Database because sourceURI was missing for MediaID ".$nextID." personJSON: ".json_encode($document),$db);

                continue;

            } else {

                $tmpDocument = $db->getRow("SELECT * FROM ?n WHERE DocumentSourceURI = ?s", $config["platform"]["sql"]["tbl"]["Document"], $document["sourceURI"]);

                if (!$tmpDocument) {

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
                        (is_array($document["labelAlternative"]) ? json_encode($document["labelAlternative"]) : "[".$document["labelAlternative"]."]"),
                        (($document["abstract"]) ? $document["abstract"] : "undefined"),
                        $document["thumbnailURI"],
                        $document["thumbnailCreator"],
                        $document["thumbnailLicense"],
                        $document["sourceURI"],
                        $document["embedURI"],
                        json_encode($document["additionalInformation"]));
                    $tmpDocumentID = $db->insertId();

                } else {

                    $tmpDocumentID = $tmpDocument["DocumentID"];
                    $tmpDocumentUpdate = array();
                    if ((!$tmpDocument["DocumentWikidataID"]) && ($document["wikidataID"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentWikidataID=?s", $document["wikidataID"]);
                    }
                    if ((!$tmpDocument["DocumentLabelAlternative"]) && ($document["labelAlternative"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentLabelAlternative=?s", (is_array($document["labelAlternative"]) ? json_encode($document["labelAlternative"]) : "[".$document["labelAlternative"]."]"));
                    }
                    if (((!$tmpDocument["DocumentAbstract"]) || ($tmpDocument["DocumentAbstract"] == "undefined")) && ($document["abstract"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentAbstract=?s", $document["abstract"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailURI"]) && ($document["thumbnailURI"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailURI=?s", $document["thumbnailURI"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailCreator"]) && ($document["thumbnailCreator"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailCreator=?s", $document["thumbnailCreator"]);
                    }
                    if ((!$tmpDocument["DocumentThumbnailLicense"]) && ($document["thumbnailLicense"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentThumbnailLicense=?s", $document["thumbnailLicense"]);
                    }
                    if ((!$tmpDocument["DocumentEmbedURI"]) && ($document["embedURI"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentEmbedURI=?s", $document["embedURI"]);
                    }
                    if ((!$tmpDocument["DocumentAdditionalInformation"]) && ($document["additionalInformation"])) {
                        $tmpDocumentUpdate[] = $dbp->parse("DocumentAdditionalInformation=?s", json_encode($document["additionalInformation"]));
                    }

                    if ($tmpDocumentUpdate) {
                        $tmpDocumentUpdate = "UPDATE " . $config["platform"]["sql"]["tbl"]["Document"] . " SET " . implode(", ", $tmpDocumentUpdate) . " WHERE DocumentID = ?i";
                        $db->query($tmpDocumentUpdate, $tmpDocumentID);
                    }

                }

                $document["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";
                $document["context"] = ($document["context"]) ? $document["context"] : "proceedingsReference";
                $document["frametrailType"] = ($document["frametrailType"]) ? $document["frametrailType"] : "Annotation";


                $tmpDocumentAnnotation = $dbp->getRow("SELECT * FROM ?n WHERE AnnotationMediaID=?s AND AnnotationResourceID=?s AND AnnotationType=?s",
                    $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"],
                    $nextID,
                    $tmpDocumentID,
                    "document");

                if (!$tmpDocumentAnnotation) {

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
                        "AnnotationAdditionalInformation" => json_encode($document["additionalInformation"])
                    );

                    $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);

                }


            }

        }


    }

    //TODO: Think about a better way to handle speeches without people

    $dbp->query("DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationType = ?s AND AnnotationContext != ?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"],
                $nextID,
                "person",
                "NER");

    $dbp->query("DELETE FROM ?n WHERE AnnotationMediaID = ?s AND AnnotationType = ?s AND AnnotationContext != ?s",
                $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"],
                $nextID,
                "organisation",
                "NER");

    if ((!$item["people"]) || (gettype($item["people"]) != "array")) {
        $item["people"] = array();
    }

    if (count($item["people"]) < 1) {

        reportConflict("Media","mediaAdd no person",$nextID,"","Media has no people.",$db);

    }

    foreach ($item["people"] as $person) {


        if ($person["context"] == "main-proceeding-speaker") {
            reportConflict("Media","mediaAdd main-proceeding-speaker found",$nextID,"",$person["wid"]." This person was not added because it has context main-proceeding-speaker: ".json_encode($person,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),$db);
            continue;
        }

        if ((!$person["wid"]) || (!preg_match("/(Q|P)\d+/i", $person["wid"]))) {
            reportConflict("Media","mediaAdd person has no WikidataID",$nextID,"",$person["wid"]."This person has no or incorrect WikidataID:".json_encode($person,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),$db);
            continue;
        }

        if (!array_key_exists($person["wid"],$entityDump["data"])) {
            reportEntitySuggestion($person["wid"], "PERSON", $person["label"], json_encode($person,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $nextID, $db);
            continue;
        }

        //Add Person Annotation
        $person["creator"] = ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system";

        if (!$person["context"]) {
            reportConflict("Media", "mediaAdd person without context", $nextID, "", "Person has no context - personJSON: " . json_encode($person), $db);
            $person["context"] = "unknown";
        }

        $person["additionalInformation"]["role"] = $person["role"];

        $tmpAnnotationPerson = array(
            "AnnotationMediaID" => $nextID,
            "AnnotationType" => "person",
            "AnnotationResourceID" => $person["wid"],
            "AnnotationContext" => $person["context"],
            "AnnotationFrametrailType" => (($person["frametrailType"]) ? $person["frametrailType"] : "Annotation"),
            "AnnotationTimeStart" => $person["timeStart"],
            "AnnotationTimeEnd" => $person["timeEnd"],
            "AnnotationCreator" => $person["creator"],
            "AnnotationTags" => $person["tags"],
            "AnnotationAdditionalInformation" => json_encode($person["additionalInformation"])
        );

        $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationPerson);


        if ((array_key_exists("faction",$person)) && (is_array($person["faction"]))) {

            if ((!$person["faction"]["wid"]) || (!preg_match("/(Q|P)\d+/i", $person["faction"]["wid"]))) {
                reportConflict("Media", "faction has no WikidataID", $nextID, "", "This faction has no or incorrect WikidataID:" . json_encode($person, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $db);
                continue;
            }


            if (!array_key_exists($person["faction"]["wid"], $entityDump["data"])) {
                reportEntitySuggestion($person["faction"]["wid"], "FACTION", $person["faction"]["label"], json_encode($person["faction"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $nextID, $db);
                continue;
            }

            $tmpAnnotationItem = array(
                "AnnotationMediaID" => $nextID,
                "AnnotationType" => "organisation",
                "AnnotationResourceID" => $person["faction"]["wid"],
                "AnnotationContext" => $person["context"]."-faction",
                "AnnotationFrametrailType" => "Annotation",
                //"AnnotationTimeStart" => "",
                //"AnnotationTimeEnd" => "",
                "AnnotationCreator" => ($_SESSION["userdata"]["UserID"]) ? $_SESSION["userdata"]["UserID"] : "system",
                "AnnotationTags" => "",
                "AnnotationAdditionalInformation" => ""
            );

            $dbp->query("INSERT INTO ?n SET ?u", $config["parliament"][$item["parliament"]]["sql"]["tbl"]["Annotation"], $tmpAnnotationItem);

        }

    }

    $return["meta"]["requestStatus"] = "success";
    $return["data"]["id"] = $nextID;

    return $return;


}

function mediaChange($parameter) {
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
        $errorarray["title"] = "Invalid MediaID";
        $errorarray["detail"] = "MediaID could not be associated with a parliament";
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

    // Check if media exists
    $media = $dbp->getRow("SELECT * FROM ".$config["parliament"][$parliament]["sql"]["tbl"]["Media"]." WHERE MediaID=?s LIMIT 1", $parameter["id"]);
    if (!$media) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Media not found";
        $errorarray["detail"] = "Media with the given ID was not found in database";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Define allowed parameters
    $allowedParams = array(
        "MediaOriginID", "MediaOriginMediaID", "MediaCreator", "MediaLicense",
        "MediaOrder", "MediaPublic", "MediaAligned", "MediaDateStart", "MediaDateEnd",
        "MediaDuration", "MediaVideoFileURI", "MediaAudioFileURI", "MediaSourcePage",
        "MediaThumbnailURI", "MediaThumbnailCreator", "MediaThumbnailLicense",
        "MediaAdditionalInformation"
    );

    // Filter parameters
    $params = $dbp->filterArray($parameter, $allowedParams);
    $updateParams = array();

    // Process each parameter
    foreach ($params as $key => $value) {
        if ($key === "MediaAdditionalInformation") {
            // Handle JSON fields
            if (is_array($value)) {
                $updateParams[] = $dbp->parse("?n=?s", $key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        } else if ($key === "MediaPublic" || $key === "MediaAligned") {
            // Convert boolean values to integers
            $updateParams[] = $dbp->parse("?n=?i", $key, ($value === true || $value === "true" || $value === "1") ? 1 : 0);
        } else if ($key === "MediaOrder") {
            // Convert to integer
            $updateParams[] = $dbp->parse("?n=?i", $key, (int)$value);
        } else if ($key === "MediaDuration") {
            // Convert to float
            $updateParams[] = $dbp->parse("?n=?f", $key, (float)$value);
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
        $errorarray["detail"] = "No valid parameters for updating media data were provided";
        array_push($return["errors"], $errorarray);
        return $return;
    }

    // Add last changed timestamp
    $updateParams[] = "MediaLastChanged=CURRENT_TIMESTAMP()";

    // Execute update
    $dbp->query("UPDATE ?n SET " . implode(", ", $updateParams) . " WHERE MediaID=?s", 
        $config["parliament"][$parliament]["sql"]["tbl"]["Media"], 
        $parameter["id"]
    );

    $return["meta"]["requestStatus"] = "success";
    return $return;
}

?>
