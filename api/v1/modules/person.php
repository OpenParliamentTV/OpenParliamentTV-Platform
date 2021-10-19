<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");



/**
 * @param string $id PersonID (= WikidataID)
 * @return array
 */
function personGetByID($id = false, $db = false) {

    global $config;

    if (!$id) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request is missing";
        array_push($return["errors"], $errorarray);

        return $return;

    } else {

        $opts = array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        );

        if (!$db) {
            try {

                $db = new SafeMySQL($opts);

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

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Person"]." WHERE PersonID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $personDataObj["data"] = personGetDataObject($item, $db);
            $return = array_replace_recursive($return, $personDataObj);


        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Person not found";
            $errorarray["detail"] = "Person with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }

}

function personGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "person";
        $return["id"] = $item["PersonID"];
        $return["attributes"]["type"] = $item["PersonType"];
        $return["attributes"]["label"] = $item["PersonLabel"];
        $return["attributes"]["firstName"] = $item["PersonFirstName"];
        $return["attributes"]["lastName"] = $item["PersonLastName"];
        $return["attributes"]["degree"] = $item["PersonDegree"];
        $return["attributes"]["birthDate"] = $item["PersonBirthDate"];
        $return["attributes"]["gender"] = $item["PersonGender"];
        $return["attributes"]["abstract"] = $item["PersonAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["PersonThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = htmlentities($item["PersonThumbnailCreator"]);
        $return["attributes"]["thumbnailLicense"] = $item["PersonThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["PersonEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["PersonWebsiteURI"];
        $return["attributes"]["originID"] = $item["PersonOriginID"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["PersonSocialMediaIDs"],true);
        $return["attributes"]["additionalInformation"] = json_decode($item["PersonAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["PersonLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];

        if ($item["PersonPartyOrganisationID"]) {

            $itemParty = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonPartyOrganisationID"]);

            if ($itemParty) {

                $return["relationships"]["party"]["data"]["type"] = "organisation";
                $return["relationships"]["party"]["data"]["id"] = $itemParty["OrganisationID"];
                $return["relationships"]["party"]["data"]["attributes"]["label"] = $itemParty["OrganisationLabel"];
                $return["relationships"]["party"]["data"]["attributes"]["labelAlternative"] = $itemParty["OrganisationLabelAlternative"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailURI"] = $itemParty["OrganisationThumbnailURI"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailCreator"] = $itemParty["OrganisationThumbnailCreator"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailLicense"] = $itemParty["OrganisationThumbnailLicense"];
                $return["relationships"]["party"]["data"]["attributes"]["websiteURI"] = $itemParty["OrganisationWebsiteURI"];
                $return["relationships"]["party"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["party"]["data"]["id"];

            } else {

                $return["relationships"]["party"] = array();

            }

        } else {

            $return["relationships"]["party"] = array();

        }

        if ($item["PersonFactionOrganisationID"]) {

            $itemFaction = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonFactionOrganisationID"]);

            if ($itemFaction) {

                $return["relationships"]["faction"]["data"]["type"] = "organisation";
                $return["relationships"]["faction"]["data"]["id"] = $itemFaction["OrganisationID"];
                $return["relationships"]["faction"]["data"]["attributes"]["label"] = $itemFaction["OrganisationLabel"];
                $return["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] = $itemFaction["OrganisationLabelAlternative"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailURI"] = $itemFaction["OrganisationThumbnailURI"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailCreator"] = $itemFaction["OrganisationThumbnailCreator"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailLicense"] = $itemFaction["OrganisationThumbnailLicense"];
                $return["relationships"]["faction"]["data"]["attributes"]["websiteURI"] = $itemFaction["OrganisationWebsiteURI"];
                $return["relationships"]["faction"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["faction"]["data"]["id"];

            } else {

                $return["relationships"]["faction"] = array();

            }

        } else {

            $return["relationships"]["faction"] = array();

        }

        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?personID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;


}


function personSearch($parameter, $db = false) {

    global $config;

    $outputLimit = 25;

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
            $errorarray["detail"] = "Connecting to platform database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    $allowedFields = ["name", "type", "party", "partyID", "faction", "factionID", "organisationID", "degree", "gender", "originID", "abgeordnetenwatchID"];

    $filteredParameters = array_filter(
        $parameter,
        function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        },
        ARRAY_FILTER_USE_KEY
    );




    /************ VALIDATION START ************/

    if (array_key_exists("name", $filteredParameters) && (mb_strlen($filteredParameters["name"], "UTF-8")< 3)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Name too short";
        $errorarray["detail"] = "Searching for name needs at least 3 characters."; //  due to database limitations
        $return["errors"][] = $errorarray;

    }


    if (array_key_exists("type", $filteredParameters) && (!in_array($filteredParameters["type"], array("memberOfParliament", "unknown")))) { //TODO: Docs & import

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "type invalid";
        $errorarray["detail"] = "type needs to be 'memberOfParliament' or 'unknown'"; // TODO: Define what types exist
        $return["errors"][] = $errorarray;

    }



    if (array_key_exists("party", $filteredParameters)) {

        if (is_array($filteredParameters["party"])) {

            foreach ($filteredParameters["party"] as $tmpParty) {
                if (mb_strlen($tmpParty, "UTF-8") < 1) {
                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Party input too short";
                    $errorarray["detail"] = "Searching for party needs at least 1 character.";
                    $return["errors"][] = $errorarray;
                }
            }

        } else {

            if (mb_strlen($filteredParameters["party"], "UTF-8") < 1) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Party input too short";
                $errorarray["detail"] = "Searching for party needs at least 1 character.";
                $return["errors"][] = $errorarray;

            }

        }

    }



    if (array_key_exists("partyID", $filteredParameters)) {

        if (is_array($filteredParameters["party"])) {

            foreach ($filteredParameters["party"] as $tmpPartyID) {

                if (!preg_match("/(Q|P)\d+/i", $tmpPartyID)) {

                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Wrong partyID";
                    $errorarray["detail"] = "partyID doesn't match the pattern.";
                    $return["errors"][] = $errorarray;

                }

            }

        } else {

            if (!preg_match("/(Q|P)\d+/i", $filteredParameters["partyID"])) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Wrong partyID";
                $errorarray["detail"] = "partyID doesn't match the pattern.";
                $return["errors"][] = $errorarray;

            }

        }

    }



    if (array_key_exists("faction", $filteredParameters)) {

        if (is_array($filteredParameters["faction"])) {

            foreach ($filteredParameters["faction"] as $tmpParty) {
                if (mb_strlen($tmpParty, "UTF-8") < 1) {
                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Faction too short";
                    $errorarray["detail"] = "Searching for faction needs at least 1 character.";
                    $return["errors"][] = $errorarray;
                }
            }

        } else {

            if (mb_strlen($filteredParameters["faction"], "UTF-8") < 1) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "faction too short";
                $errorarray["detail"] = "Searching for faction needs at least 1 character.";
                $return["errors"][] = $errorarray;

            }

        }

    }

    if (array_key_exists("factionID", $filteredParameters)) {

        if (is_array($filteredParameters["factionID"])) {

            foreach ($filteredParameters["factionID"] as $tmpFactionID) {

                if (!preg_match("/(Q|P)\d+/i", $tmpFactionID)) {

                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "Wrong factionID";
                    $errorarray["detail"] = "factionID doesn't match the pattern.";
                    $return["errors"][] = $errorarray;

                }

            }

        } else {

            if (!preg_match("/(Q|P)\d+/i", $filteredParameters["factionID"])) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "Wrong factionID";
                $errorarray["detail"] = "factionID doesn't match the pattern.";
                $return["errors"][] = $errorarray;

            }

        }

    }




    if (array_key_exists("organisationID", $filteredParameters)) {

        if (!preg_match("/(Q|P)\d+/i", $filteredParameters["organisationID"])) {
            $return["meta"]["requestStatus"] = "error";
            $errorarray["status"] = "400";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Wrong organisationID";
            $errorarray["detail"] = "organisationID doesn't match the pattern.";
            $return["errors"][] = $errorarray;
        }

    }



    if (array_key_exists("degree", $filteredParameters) && (mb_strlen($filteredParameters["degree"], "UTF-8") < 1))  {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "degree too short";
        $errorarray["detail"] = "Searching for degree needs at least 1 character.";
        $return["errors"][] = $errorarray;

    }



    //TODO: Gender to $config
    if (array_key_exists("gender", $filteredParameters) && (!in_array($filteredParameters["gender"], array("male", "female", "nonbinary")))) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "gender not valid.";
        $errorarray["detail"] = "Searching for gender had invalid value.";
        $return["errors"][] = $errorarray;

    }



    if (array_key_exists("originID", $filteredParameters) && (mb_strlen($filteredParameters["originID"], "UTF-8") < 1)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "originID not valid.";
        $errorarray["detail"] = "originID too short."; //
        $return["errors"][] = $errorarray;

    }



    if (array_key_exists("abgeordnetenwatchID", $filteredParameters) && (mb_strlen($filteredParameters["abgeordnetenwatchID"], "UTF-8") < 1)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "abgeordnetenwatchID not valid.";
        $errorarray["detail"] = "abgeordnetenwatchID too short."; //
        $return["errors"][] = $errorarray;

    }


    /************ VALIDATION END ************/




    if ($return["meta"]["requestStatus"] == "error") {

        return $return;

    }



    $query = "SELECT            p.*,
                                op.OrganisationID as PartyID,
                                op.OrganisationLabel as PartyLabel,
                                ofr.OrganisationID as FactionID,
                                ofr.OrganisationLabel as FactionLabel
                            FROM ".$config["platform"]["sql"]["tbl"]["Person"]." AS p
                            LEFT JOIN ".$config["platform"]["sql"]["tbl"]["Organisation"]." as op 
                                ON op.OrganisationID = p.PersonPartyOrganisationID
                            LEFT JOIN ".$config["platform"]["sql"]["tbl"]["Organisation"]." as ofr 
                                ON ofr.OrganisationID = p.PersonFactionOrganisationID";

    $conditions = array();

    foreach ($filteredParameters as $k=>$para) {



        if ($k == "name") {

            $conditions[] = $db->parse("MATCH(p.PersonLabel, p.PersonFirstName, p.PersonLastName) AGAINST (?s IN BOOLEAN MODE)", "*".$para."*");

        }

        if ($k == "type") {

            $conditions[] = $db->parse("PersonType = ?s", $para);

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


        if ($k == "partyID") {

            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("op.OrganisationID = ?s", $tmppara);

                }

                $tmpStringArray  = " (".implode(" OR ",$tmpStringArray).")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("op.OrganisationID = ?s", $para);

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



        if ($k == "factionID") {

            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("p.PersonFactionOrganisationID = ?s", $tmppara);

                }

                $tmpStringArray  = " (".implode(" OR ",$tmpStringArray).")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("p.PersonFactionOrganisationID = ?s", $para);

            }

        }



        if ($k == "organisationID") {

            $conditions[] = $db->parse("(p.PersonFactionOrganisationID = ?s OR p.PersonPartyOrganisationID=?s)", $para, $para);

        }


        if ($k == "degree") {

            $conditions[] = $db->parse("PersonDegree LIKE ?s", "%".$para."%");

        }


        if ($k == "gender") {

            $conditions[] = $db->parse("PersonGender LIKE ?s", $para);

        }


        if ($k == "originID") {

            $conditions[] = $db->parse("PersonOriginID = ?s", $para);

        }


        if ($k == "abgeordnetenwatchID") {

            $conditions[] = $db->parse("JSON_EXTRACT(p.PersonAdditionalInformation, '$.abgeordnetenwatchID') = ?s", $para);

        }

    }

    if (count($conditions) > 0) {

        $query .= " WHERE ".implode(" AND ",$conditions);

        $totalCount = $db->getAll($query);

        $query .= " LIMIT ";

        if ($parameter["page"]) {

            $query .= ($parameter["page"]-1)*$outputLimit.",";

        } else {

            $parameter["page"] = 1;

        }

        $query .= $outputLimit;



        $findings = $db->getAll($query);

        $return["meta"]["requestStatus"] = "success";
        $return["meta"]["page"] = $parameter["page"];
        $return["meta"]["pageTotal"] = ceil(count($totalCount)/$outputLimit);

        if (!$return["data"]) {
            $return["data"] = array();
        }

        foreach ($findings as $finding) {
            array_push($return["data"],personGetDataObject($finding,$db));
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


    $return["links"]["self"] = $config["dir"]["api"]."/search/people?".getURLParameterFromArray($filteredParameters);

    return $return;

}

?>
