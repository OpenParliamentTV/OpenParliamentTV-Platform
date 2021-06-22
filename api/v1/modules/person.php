<?php

require_once (__DIR__."./../../../config.php");
require_once ("config.php");
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
            $personDataObj = personGetDataObject($item, $db);
            $return = array_replace_recursive($return, $personDataObj);


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

function personGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["data"]["type"] = "person";
        $return["data"]["id"] = $item["PersonID"];
        $return["data"]["attributes"]["type"] = $item["PersonType"];
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
        $return["data"]["links"]["self"] = $config["dir"]["api"].$return["data"]["type"]."/".$return["data"]["id"];

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
                $return["data"]["relationships"]["party"]["links"]["self"] = $config["dir"]["api"]."organisation/".$return["data"]["relationships"]["party"]["data"]["id"];

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
                $return["data"]["relationships"]["faction"]["links"]["self"] = $config["dir"]["api"]."organisation/".$return["data"]["relationships"]["faction"]["data"]["id"];

            } else {

                $return["data"]["relationships"]["faction"] = array();

            }

        } else {

            $return["data"]["relationships"]["faction"] = array();

        }

        $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."searchMedia?personID=".$return["data"]["id"];

    } else {

        $return = false;

    }

    return $return;


}


function personSearch($parameter, $db = false) {

    global $config;

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

    $allowedFields = ["name", "party", "faction", "type"];

    $filteredParameters = array_filter(
        $parameter,
        function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        },
        ARRAY_FILTER_USE_KEY
    );


    if (array_key_exists("name", $filteredParameters) && (strlen($filteredParameters["name"])) < 3) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Name too short";
        $errorarray["detail"] = "Searching for name needs at least 3 characters."; //  due to database limitations TODO: Description
        array_push($return["errors"], $errorarray);

    }

    if (array_key_exists("party", $filteredParameters)) {

        if (is_array($filteredParameters["party"])) {

            foreach ($filteredParameters["party"] as $tmpParty) {
                if (strlen($tmpParty) < 1) {
                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Party too short";
                    $errorarray["detail"] = "Searching for party needs at least 3 characters."; //  TODO: Description
                    array_push($return["errors"], $errorarray);
                }
            }

        } else {

            if (strlen($filteredParameters["party"]) < 1) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Party too short";
                $errorarray["detail"] = "Searching for party needs at least 3 characters."; //  TODO: Description
                array_push($return["errors"], $errorarray);

            }

        }

    }

    if ($return["meta"]["requestStatus"] == "error") {

        return $return;

    }




    $query = "SELECT            p.*,
                                op.OrganisationID as PartyID,
                                op.OrganisationLabel as PartyLabel,
                                ofr.OrganisationLabel as FractionLabel,
                                ofr.OrganisationID as FractionID
                            FROM person AS p
                            LEFT JOIN organisation as op 
                                ON op.OrganisationID = p.PersonPartyOrganisationID
                            LEFT JOIN organisation as ofr 
                                ON ofr.OrganisationID = p.PersonFactionOrganisationID";

    $conditions = array();

    foreach ($filteredParameters as $k=>$para) {



        if ($k == "name") {

            $conditions[] = $db->parse("MATCH(p.PersonLabel, p.PersonFirstName, p.PersonLastName) AGAINST (?s IN BOOLEAN MODE)", "*".$para."*");

        }

        if ($k == "type") {

            $conditions[] = $db->parse("PersonType = ?s", $para);

        }

        if ($k == "degree") {

            $conditions[] = $db->parse("PersonDegree LIKE ?s", "%".$para."%");

        }

        if ($k == "degree") {

            $conditions[] = $db->parse("PersonGender LIKE ?s", "%".$para."%");

        }


        if ($k == "party") {
            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("(op.OrganisationLabel LIKE ?s OR op.OrganisationLabelAlternative LIKE ?s)", "%".$tmppara."%", "%".$tmppara."%");

                }

                $tmpStringArray  = " (".implode(" OR ",$tmpStringArray).")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("(op.OrganisationLabel LIKE ?s OR op.OrganisationLabelAlternative LIKE ?s)", "%".$para."%", "%".$para."%");

            }

        }


        if ($k == "faction") {

            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("(ofr.OrganisationLabel LIKE ?s OR ofr.OrganisationLabelAlternative LIKE ?s)", "%".$tmppara."%", "%".$tmppara."%");

                }

                $tmpStringArray  = " (".implode(" OR ",$tmpStringArray).")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("(ofr.OrganisationLabel LIKE ?s OR ofr.OrganisationLabelAlternative LIKE ?s)", "%".$para."%", "%".$para."%");

            }

        }

    }

    if (count($conditions) > 0) {

        $query .= " WHERE ".implode(" AND ",$conditions);
        //echo $db->parse($query);
        $findings = $db->getAll($query);

        $return["meta"]["requestStatus"] = "success";

        foreach ($findings as $finding) {
            //print_r($finding);
            $return["data"][] = personGetDataObject($finding,$db);
        }

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Too less parameter";
        $errorarray["detail"] = "Too less Parameter"; //TODO: Description
        array_push($return["errors"], $errorarray);

    }

    if (!array_key_exists("data", $return)) {
        $return["data"] = array();
    }


    $return["data"]["links"]["self"] = $config["dir"]["api"]."search/?type=people&".getURLParameterFromArray($filteredParameters);

    return $return;

}

?>
