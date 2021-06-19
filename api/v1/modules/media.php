<?php

require_once (__DIR__."./../../../config.php");
require_once (__DIR__."./../../../modules/utilities/functions.php");
require_once (__DIR__."./../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id MediaID
 * @return array
 */
function mediaGetByID($id = false) {

    global $config;

    $parliament = explode("-",$id);
    array_pop($parliament);
    if (is_array($parliament)) {
        $parliament = implode("-",$parliament);
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
            //$return["data"]["attributes"]["order"] = $item["MediaOrder"];
            $return["data"]["attributes"]["aligned"] = (($item["MediaAligned"] === 1) ? true : false);
            $return["data"]["attributes"]["dateStart"] = $item["MediaDateStart"];
            $return["data"]["attributes"]["dateEnd"] = $item["MediaDateEnd"];
            $return["data"]["attributes"]["duration"] = $item["MediaDuration"];
            $return["data"]["attributes"]["videoFileURI"] = $item["MediaFileURI"];
            $return["data"]["attributes"]["audioFileURI"] = $item["AudioFileURI"];
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
                $tmpTextItem["id"] = $itemTexts["TextID"];
                $tmpTextItem["type"] = $itemTexts["TextType"];
                $tmpTextItem["textBody"] = $itemTexts["TextBody"];
                $tmpTextItem["sourceURI"] = $itemTexts["TextSourceURI"];
                $tmpTextItem["creator"] = $itemTexts["TextCreator"];
                $tmpTextItem["license"] = $itemTexts["TextLicense"];
                $tmpTextItem["language"] = $itemTexts["TextLanguage"];
                $tmpTextItem["originTextID"] = $itemTexts["TextOriginTextID"];
                $tmpTextItem["lastChanged"] = $itemTexts["TextLastChanged"];

                array_push($return["data"]["attributes"]["textContents"], $tmpTextItem);

            }

            $return["data"]["links"]["self"] = ""; //TODO: Link

            $return["data"]["relationships"]["electoralPeriod"]["data"]["type"] = "electoralPeriod";
            $return["data"]["relationships"]["electoralPeriod"]["data"]["id"] = $item["ElectoralPeriodID"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] = $item["ElectoralPeriodNumber"];
            $return["data"]["relationships"]["electoralPeriod"]["data"]["links"]["self"] = ""; //TODO: Link/Uri

            $return["data"]["relationships"]["session"]["data"]["type"] = "session";
            $return["data"]["relationships"]["session"]["data"]["id"] = $item["SessionID"];
            $return["data"]["relationships"]["session"]["data"]["attributes"]["number"] = $item["SessionNumber"];
            $return["data"]["relationships"]["session"]["data"]["links"]["self"] = ""; //TODO: Link/Uri

            $return["data"]["relationships"]["agendaItem"]["data"]["type"] = "agendaItem";
            $return["data"]["relationships"]["agendaItem"]["data"]["id"] = $item["AgendaItemID"];
            $return["data"]["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"] = $item["AgendaItemOfficialTitle"];
            $return["data"]["relationships"]["agendaItem"]["data"]["attributes"]["title"] = $item["AgendaItemTitle"];
            $return["data"]["relationships"]["agendaItem"]["data"]["links"]["self"] = ""; //TODO: Link/Uri

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
                        $tmpAnnotationItem["id"] = $annotation["AnnotationID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["DocumentType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["DocumentLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = $ditem["DocumentLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["DocumentThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["DocumentThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["DocumentThumbnailLicense"];
                        $tmpAnnotationItem["links"]["self"] = ""; //TODO Link
                        array_push($return["data"]["relationships"]["documents"]["data"], $tmpAnnotationItem);

                    break;


                    case "organisation":

                        $ditem = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s LIMIT 1", $config["platform"]["sql"]["tbl"]["Organisation"], $annotation["AnnotationResourceID"]);
                        $tmpAnnotationItem["type"] = "organisation";
                        $tmpAnnotationItem["id"] = $annotation["AnnotationID"];
                        $tmpAnnotationItem["attributes"]["context"] = $annotation["AnnotationContext"];
                        $tmpAnnotationItem["attributes"]["type"] = $ditem["OrganisationType"];
                        $tmpAnnotationItem["attributes"]["label"] = $ditem["OrganisationLabel"];
                        $tmpAnnotationItem["attributes"]["labelAlternative"] = $ditem["OrganisationLabelAlternative"];
                        $tmpAnnotationItem["attributes"]["thumbnailURI"] = $ditem["OrganisationThumbnailURI"];
                        $tmpAnnotationItem["attributes"]["thumbnailCreator"] = $ditem["OrganisationThumbnailCreator"];
                        $tmpAnnotationItem["attributes"]["thumbnailLicense"] = $ditem["OrganisationThumbnailLicense"];
                        $tmpAnnotationItem["attributes"]["color"] = $ditem["OrganisationColor"];
                        $tmpAnnotationItem["links"]["self"] = ""; //TODO Link
                        array_push($return["data"]["relationships"]["organisation"]["data"], $tmpAnnotationItem);

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
                        $tmpAnnotationItem["links"]["self"] = ""; //TODO Link
                        array_push($return["data"]["relationships"]["term"]["data"], $tmpAnnotationItem);

                    break;



                    case "person":

                        //TODO: What if no party or no fraction?
                        $pitem = $db->getRow("SELECT
                                p.*,
                                op.OrganisationID,
                                op.OrganisationLabel,
                                op.OrganisationID as PartyID,
                                op.OrganisationLabel as PartyLabel,
                                ofr.OrganisationLabel as FractionLabel,
                                ofr.OrganisationID as FractionID
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
                        $tmpAnnotationItem["attributes"]["fraction"]["id"] = $pitem["FractionID"];
                        $tmpAnnotationItem["attributes"]["fraction"]["label"] = $pitem["FractionLabel"];
                        $tmpAnnotationItem["links"]["self"] = ""; //TODO Link
                        array_push($return["data"]["relationships"]["people"]["data"], $tmpAnnotationItem);

                    break;
                }

            }

            $return["data"]["relationships"]["documents"]["links"]["self"] = ""; //TODO LINK
            $return["data"]["relationships"]["organisations"]["links"]["self"] = ""; //TODO LINK
            $return["data"]["relationships"]["terms"]["links"]["self"] = ""; //TODO LINK
            $return["data"]["relationships"]["people"]["links"]["self"] = ""; //TODO LINK

            $return["data"]["relationships"]["annotations"]["links"]["self"] = ""; //TODO LINK

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

?>
