<?php

require_once (__DIR__."./../../../config.php");
require_once (__DIR__."./../../../modules/utilities/functions.php");
require_once (__DIR__."./../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id PersonID (= WikidataID)
 * @return array
 */
function personGetByID($id = false) {

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

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Person"]." WHERE PersonID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "person";
            $return["data"]["id"] = $item["PersonID"];
            $return["data"]["attributes"]["type"] = $item["PersonType"];
            $return["data"]["attributes"]["wikidataID"] = $item["PersonID"];
            $return["data"]["attributes"]["label"] = $item["PersonLabel"];
            $return["data"]["attributes"]["firstName"] = $item["PersonFirstName"];
            $return["data"]["attributes"]["lastName"] = $item["PersonLastName"];
            $return["data"]["attributes"]["degree"] = $item["PersonDegree"];
            $return["data"]["attributes"]["birthDate"] = $item["PersonBirthDate"];
            $return["data"]["attributes"]["gender"] = $item["PersonGender"];
            $return["data"]["attributes"]["abstract"] = $item["PersonAbstract"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["PersonThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["PersonThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["PersonThumbnailLicense"];
            $return["data"]["attributes"]["embedURI"] = $item["PersonEmbedURI"];
            $return["data"]["attributes"]["websiteURI"] = $item["PersonWebsiteURI"];
            $return["data"]["attributes"]["originID"] = $item["PersonOriginID"];
            $return["data"]["attributes"]["socialMediaIDs"] = json_decode($item["PersonSocialMediaIDs"],true);
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["PersonAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["PersonLastChanged"];
            $return["data"]["links"]["self"] = ""; //TODO: Link

            if ($item["PersonPartyOrganisationID"]) {

                $itemParty = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonPartyOrganisationID"]);

                if ($itemParty) {

                    $return["data"]["relationships"]["party"]["data"]["type"] = "organisation";
                    $return["data"]["relationships"]["party"]["data"]["id"] = $itemParty["OrganisationID"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["label"] = $itemParty["OrganisationLabel"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["labelAlternative"] = $itemParty["OrganisationLabelAlternative"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["thumbnailURI"] = $itemParty["OrganisationThumbnailURI"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["thumbnailCreator"] = $itemParty["OrganisationThumbnailCreator"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["thumbnailLicense"] = $itemParty["OrganisationThumbnailLicense"];
                    $return["data"]["relationships"]["party"]["data"]["attributes"]["websiteURI"] = $itemParty["OrganisationWebsiteURI"];
                    $return["data"]["relationships"]["party"]["links"]["self"] = ""; //TODO: Link

                } else {

                    $return["data"]["relationships"]["party"] = array();

                }

            } else {

                $return["data"]["relationships"]["party"] = array();

            }

            if ($item["PersonFactionOrganisationID"]) {

                $itemFaction = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonFactionOrganisationID"]);

                if ($itemFaction) {

                    $return["data"]["relationships"]["faction"]["data"]["type"] = "organisation";
                    $return["data"]["relationships"]["faction"]["data"]["id"] = $itemFaction["OrganisationID"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["label"] = $itemFaction["OrganisationLabel"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] = $itemFaction["OrganisationLabelAlternative"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["thumbnailURI"] = $itemFaction["OrganisationThumbnailURI"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["thumbnailCreator"] = $itemFaction["OrganisationThumbnailCreator"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["thumbnailLicense"] = $itemFaction["OrganisationThumbnailLicense"];
                    $return["data"]["relationships"]["faction"]["data"]["attributes"]["websiteURI"] = $itemFaction["OrganisationWebsiteURI"];
                    $return["data"]["relationships"]["faction"]["links"]["self"] = ""; //TODO: Link

                } else {

                    $return["data"]["relationships"]["faction"] = array();

                }

            } else {

                $return["data"]["relationships"]["faction"] = array();

            }

            $return["data"]["relationships"]["media"]["links"]["self"] = ""; //TODO: Link - "self"?

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Person not found";
            $errorarray["detail"] = "Person with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
