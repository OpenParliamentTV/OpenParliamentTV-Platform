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
        $return["attributes"]["labelAlternative"] = json_decode($item["PersonLabelAlternative"],true);
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
                $return["relationships"]["party"]["data"]["attributes"]["labelAlternative"] = json_decode($itemParty["OrganisationLabelAlternative"],true);
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
                $return["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] = json_decode($itemFaction["OrganisationLabelAlternative"],true);
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

    $filteredParameters = filterAllowedSearchParams($parameter, 'person');

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

    if (array_key_exists("fragDenStaatID", $filteredParameters) && (mb_strlen($filteredParameters["fragDenStaatID"], "UTF-8") < 1)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "1";
        $errorarray["title"] = "fragDenStaatID not valid.";
        $errorarray["detail"] = "fragDenStaatID too short."; //
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

            $conditions[] = $db->parse("MATCH(p.PersonLabel, p.PersonFirstName, p.PersonLastName) AGAINST (?s IN BOOLEAN MODE)", "*".str_replace("-", " ",$para)."*");

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

        if ($k == "fragDenStaatID") {

            $conditions[] = $db->parse("JSON_EXTRACT(p.PersonAdditionalInformation, '$.fragDenStaatID') = ?s", $para);

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

function personAdd($item, $db = false) {

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
            $errorarray["detail"] = "Connecting to platform database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    if ((!$item["id"]) || (!preg_match("/(Q|P)\d+/i", $item["id"]))) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "ID seems to be wrong or missing";
        $errorarray["label"] = "id";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ((!$item["label"]) || (strlen($item["label"]) < 3)) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Label seems to be too short or missing";
        $errorarray["label"] = "label";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ((!$item["type"]) || (strlen($item["type"]) < 3)) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Type seems to be too short or missing";
        $errorarray["label"] = "type";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ($return["errors"]) {
        $return["meta"]["requestStatus"] = "error";
        return $return;
    } else {

        $itemTmp = $db->getRow("SELECT PersonID FROM ".$config["platform"]["sql"]["tbl"]["Person"]." WHERE PersonID=?s",$item["id"]);

        if ($itemTmp) {
            $return["meta"]["requestStatus"] = "error";
            $errorarray["status"] = "422"; //todo
            $errorarray["code"] = "2";
            $errorarray["title"] = "Item with ID already exists in Database";
            $errorarray["label"] = "error_info";
            $errorarray["detail"] = "Item already exists in Database";
            $return["errors"][] = $errorarray;
            return $return;

        } else {

            try {

                $socialMedia = array();
                if ($item["socialMediaIDsLabel"]) {
                    foreach ($item["socialMediaIDsLabel"] as $k=>$v) {
                        //just add when both is not empty
                        if ($v && $item["socialMediaIDsValue"][$k]) {
                            $socialMedia[] = array("label" => $v, "id" => $item["socialMediaIDsValue"][$k]);
                        }
                    }
                }

                $labelAlternative = array();
                if (is_array($item["labelAlternative"])) {
                    foreach ($item["labelAlternative"] as $v) {
                        if ($v) {
                            $labelAlternative[] = $v;
                        }
                    }
                }

                $tmpNewPerson = array(
                    "PersonID" => $item["id"],
                    "PersonType" => $item["type"],
                    "PersonLabel" => $item["label"],
                    "PersonLabelAlternative" => (is_array($labelAlternative) ? json_encode($labelAlternative,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : "[".$item["labelAlternative"]."]"),
                    "PersonFirstName"=>$item["firstname"],
                    "PersonLastName"=>$item["lastname"],
                    "PersonDegree"=>$item["degree"],
                    "PersonBirthDate" => date('Y-m-d', strtotime($item["birthdate"])),
                    "PersonGender" => $item["gender"],
                    "PersonAbstract" => $item["abstract"],
                    "PersonThumbnailURI" => $item["thumbnailuri"],
                    "PersonThumbnailCreator" => $item["thumbnailcreator"],
                    "PersonThumbnailLicense" => $item["thumbnaillicense"],
                    "PersonEmbedURI" => $item["embeduri"],
                    "PersonWebsiteURI" => ($item["websiteuri"] ? $item["websiteuri"] : ""),
                    "PersonOriginID" => $item["originid"],
                    "PersonPartyOrganisationID" => $item["party"],
                    "PersonFactionOrganisationID" => $item["faction"],
                    "PersonSocialMediaIDs" => json_encode($socialMedia, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    "PersonAdditionalInformation" => $item["additionalinformation"]
                );

                $db->query("INSERT INTO ?n SET ?u",$config["platform"]["sql"]["tbl"]["Person"],$tmpNewPerson);

                $return["meta"]["requestStatus"] = "success";
                $return["meta"]["itemID"] = $db->insertId();

            } catch (exception $e) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "422"; //todo
                $errorarray["code"] = "2";
                $errorarray["title"] = "Add to database failed";
                $errorarray["label"] = "error_info";
                $errorarray["detail"] = $e->getMessage();
                $return["errors"][] = $errorarray;

            }

        }
    }

    return $return;

}


function personGetOverview($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $getCount = false, $db = false) {

    global $config;

    if (!$db) {
        $db = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));
    }

    $queryPart = "";

    if ($id == "all") {
        $queryPart .= "1";
    } else {
        $queryPart .= $db->parse("PersonID=?s",$id);
    }


    if (!empty($search)) {
        $queryPart .= $db->parse(" AND (LOWER(PersonLabel) LIKE LOWER(?s) OR LOWER(PersonLabelAlternative) LIKE LOWER(?s) OR PersonID LIKE ?s)", "%".$search."%", "%".$search."%", "%".$search."%");
    }

    if (!empty($sort)) {

        $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);

    }


    if ($limit != 0) {

        $queryPart .= $db->parse(" LIMIT ?i, ?i",$offset,$limit);

    }

    if ($getCount == true) {

        $return["total"] = $db->getOne("SELECT COUNT(PersonID) as count FROM  ?n", $config["platform"]["sql"]["tbl"]["Person"]);
        $return["rows"] = $db->getAll("SELECT
            per.PersonID,
            per.PersonType,
            per.PersonLabel,
            per.PersonLabelAlternative,
            per.PersonGender,
            per.PersonPartyOrganisationID,
            per.PersonFactionOrganisationID,
            per.PersonLastChanged,
            party.OrganisationLabel as PartyLabel,
            faction.OrganisationLabel as FactionLabel
            FROM ?n AS per
            LEFT JOIN ?n as party
                ON party.OrganisationID = per.PersonPartyOrganisationID
            LEFT JOIN ?n as faction
                ON faction.OrganisationID = per.PersonFactionOrganisationID WHERE ?p", $config["platform"]["sql"]["tbl"]["Person"], $config["platform"]["sql"]["tbl"]["Organisation"], $config["platform"]["sql"]["tbl"]["Organisation"], $queryPart);
        /*
         *
         * To add annotation count uncomment. But be aware this will take some time
         *
         *
                foreach ($config["parliament"] as $parliamentShort=>$parliament) {
                    $dbp[$parliamentShort] = new SafeMySQL(array(
                        'host'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["host"],
                        'user'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["user"],
                        'pass'	=> $config["parliament"][$parliamentShort]["sql"]["access"]["passwd"],
                        'db'	=> $config["parliament"][$parliamentShort]["sql"]["db"]
                    ));
                }

                foreach ($return["rows"] as $k=>$person) {
                    $return["rows"][$k]["annotationcount"] = 0;
                    foreach ($config["parliament"] as $parliamentShort=>$parliament) {
                        $tmpCnt = $dbp[$parliamentShort]->getOne("SELECT COUNT(AnnotationID) as cnt FROM `annotation` WHERE AnnotationResourceID = ?s AND AnnotationType=?s",$person["PersonID"],"person");
                        //$return["rows"][$k]["annotationcount"] = $tmpCnt+$return["rows"][$k]["annotationcount"];
                        $return["rows"][$k]["annotationcount"] += $tmpCnt;
                    }
                }*/

    } else {
        $return = $db->getAll("SELECT
            per.PersonID,
            per.PersonType,
            per.PersonLabel,
            per.PersonGender,
            per.PersonPartyOrganisationID,
            per.PersonFactionOrganisationID,
            per.PersonLastChanged,
            party.OrganisationLabel as PartyLabel,
            faction.OrganisationLabel as FactionLabel
            FROM ?n AS per
            LEFT JOIN ?n as party
                ON party.OrganisationID = per.PersonPartyOrganisationID
            LEFT JOIN ?n as faction
                ON faction.OrganisationID = per.PersonFactionOrganisationID WHERE ?p; ", $config["platform"]["sql"]["tbl"]["Person"], $config["platform"]["sql"]["tbl"]["Organisation"], $config["platform"]["sql"]["tbl"]["Organisation"], $queryPart);

    }


    return $return;

}


?>
