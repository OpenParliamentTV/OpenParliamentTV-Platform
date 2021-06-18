<?php

require_once (__DIR__."./../../../config.php");
require_once (__DIR__."./../../../modules/utilities/functions.php");
require_once (__DIR__."./../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id String of OrganisationID (= WikidataID)
 * @return array
 */
function organisationGetByID($id = false) {

    global $config;

    if (!$id) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request are missing"; //TODO: Description
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

        } catch (Exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "organisation";
            $return["data"]["id"] = $item["OrganisationID"];
            $return["data"]["attributes"]["type"] = $item["OrganisationType"];
            $return["data"]["attributes"]["label"] = $item["OrganisationLabel"];
            $return["data"]["attributes"]["labelAlternative"] = $item["OrganisationLabelAlternative"];
            $return["data"]["attributes"]["abstract"] = $item["OrganisationAbstract"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["OrganisationThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["OrganisationThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["OrganisationThumbnailLicense"];
            $return["data"]["attributes"]["embedURI"] = $item["OrganisationEmbedURI"];
            $return["data"]["attributes"]["websiteURI"] = $item["OrganisationWebsiteURI"];
            $return["data"]["attributes"]["socialMediaIDs"] = json_decode($item["OrganisationSocialMediaIDs"],true);
            $return["data"]["attributes"]["color"] = $item["OrganisationColor"];
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["OrganisationAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["OrganisationLastChanged"];
            $return["data"]["links"]["self"] = ""; //TODO: Link
            $return["data"]["relationships"]["media"]["links"]["self"] = ""; //TODO: Link - "self"?
            $return["data"]["relationships"]["people"]["links"]["self"] = ""; //TODO: Link - "self"?

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Organisation not found";
            $errorarray["detail"] = "Organisation with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
