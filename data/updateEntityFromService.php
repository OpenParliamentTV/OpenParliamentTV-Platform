<?php

require_once (__DIR__."/../config.php");
require_once(__DIR__."/../modules/utilities/safemysql.class.php");

function updateEntityFromService($type, $id, $serviceAPI, $key, $language = "de", $db = false) {

    if (($id == "Q2415493") || ($id == "Q4316268")) {
        //TODO: Add Blacklist
        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"blacklisted");
        return $return;

    }

    global $config;

    $allowedTypes = array("memberOfParliament", "person", "organisation", "legalDocument", "officialDocument", "term");

    /**
     * Parameter validation
     */
    if ((empty($type) || (!in_array($type, $allowedTypes)))) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"wrong or missing parameter", "field"=>"type");
        return $return;

    }


    if (empty($db)) {

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


    if ($type == "officialDocument") {

        $idLabelPlatform = "DocumentSourceURI";
        $idLabelAPI = "sourceURI";
        $table = $config["platform"]["sql"]["tbl"]["Document"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "legalDocument") {

        $idLabelPlatform = "DocumentWikidataID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Document"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "organisation") {

        $idLabelPlatform = "OrganisationID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Organisation"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif ($type == "term") {

        $idLabelPlatform = "TermID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Term"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } elseif (($type == "person") || ($type == "memberOfParliament")) {

        $idLabelPlatform = "PersonID";
        $idLabelAPI = "wikidataID";
        $table = $config["platform"]["sql"]["tbl"]["Person"];
        $where = $db->parse("?n = ?s", $idLabelPlatform, $id);

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"wrong or missing parameter", "field"=>"type");
        return $return;

    }

    try {
        $platformItem = $db->getRow("SELECT * FROM ?n WHERE " . $where, $table);
    } catch (Exception $e) {
        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from DB".$e->getMessage());
        return $return;
    }



    if (empty($platformItem)) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from DB");
        return $return;

    }


    try {

        $apiItem = json_decode(file_get_contents($serviceAPI . "?key=" . $key . "&type=" . $type . "&" . $idLabelAPI . "=" . $id), true);

    } catch (Exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from AdditionalDataServiceAPI".$e->getMessage());
        return $return;

    }

    if (empty($apiItem) || $apiItem["meta"]["requestStatus"] != "success" || empty($apiItem["data"])) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not get Item from AdditionalDataServiceAPI");
        return $return;

    }

    if ($type == "officialDocument") {

        $updateArray = array(
            "DocumentLabel"=>$apiItem["data"]["label"],
            "DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>"",
            "DocumentAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );


    } elseif ($type == "legalDocument") {
        $updateArray = array(
            //"DocumentLabel"=>$apiItem["data"]["label"],
            //"DocumentLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "DocumentAbstract"=>$apiItem["data"]["abstract"],
            "DocumentAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "organisation") {

        //TODO: Color?
        $updateArray = array(
            //"OrganisationLabel"=>$apiItem["data"]["label"],
            //"OrganisationLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "OrganisationAbstract"=>$apiItem["data"]["abstract"],
            "OrganisationThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "OrganisationThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "OrganisationThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "OrganisationWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "OrganisationSocialMediaIDs"=>json_encode($apiItem["data"]["socialMediaIDs"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "OrganisationAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "term") {
        $updateArray = array(
            //"TermLabel"=>$apiItem["data"]["label"],
            //"TermLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "TermAbstract"=>$apiItem["data"]["abstract"],
            "TermThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "TermThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "TermThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "TermWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "TermAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "person") {
        $updateArray = array(
            "PersonLabel"=>$apiItem["data"]["label"],
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>$apiItem["data"]["firstName"],
            "PersonLastName"=>$apiItem["data"]["lastName"],
            "PersonDegree"=>$apiItem["data"]["degree"],
            "PersonBirthDate"=>date('Y-m-d', strtotime($apiItem["data"]["birthDate"])),
            "PersonGender"=>$apiItem["data"]["gender"],
            "PersonAbstract"=>$apiItem["data"]["abstract"],
            "PersonThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "PersonThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "PersonThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "PersonWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "PersonSocialMediaIDs"=>json_encode($apiItem["data"]["socialMediaIDs"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    } elseif ($type == "memberOfParliament") {
        $updateArray = array(
            "PersonLabel"=>$apiItem["data"]["label"],
            "PersonLabelAlternative"=>json_encode(($apiItem["data"]["labelAlternative"] ?: array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonFirstName"=>$apiItem["data"]["firstName"],
            "PersonLastName"=>$apiItem["data"]["lastName"],
            "PersonDegree"=>$apiItem["data"]["degree"],
            "PersonBirthDate"=>date('Y-m-d', strtotime($apiItem["data"]["birthDate"])),
            "PersonGender"=>$apiItem["data"]["gender"],
            "PersonAbstract"=>$apiItem["data"]["abstract"],
            "PersonThumbnailURI"=>$apiItem["data"]["thumbnailURI"],
            "PersonThumbnailCreator"=>$apiItem["data"]["thumbnailCreator"],
            "PersonThumbnailLicense"=>$apiItem["data"]["thumbnailLicense"],
            "PersonWebsiteURI"=>$apiItem["data"]["websiteURI"],
            "PersonSocialMediaIDs"=>json_encode($apiItem["data"]["socialMediaIDs"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonAdditionalInformation"=>json_encode($apiItem["data"]["additionalInformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "PersonPartyOrganisationID"=>$apiItem["data"]["partyID"],
            "PersonFactionOrganisationID"=>$apiItem["data"]["factionID"]
        );

    }

    try {

        $query = $db->query("UPDATE ?n SET ?u WHERE " . $where, $table, $updateArray);

    } catch (Exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"][] = array("info"=>"Could not update Item in database ".$type." ".$id.": ".$e->getMessage());
        return $return;

    }

    $return["meta"]["requestStatus"] = "success";
    $return["text"][] = array("info"=>"Item has been updated: ".$type." ".$id);
    return $return;


}

?>