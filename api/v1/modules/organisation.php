<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id String of OrganisationID (= WikidataID)
 * @return array
 */
function organisationGetByID($id = false, $db = false) {

    global $config;


    if (!preg_match("/(Q|P)\d+/i", $id)) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Missing request parameter";
        $errorarray["detail"] = "Required parameter of the request is missing";
        array_push($return["errors"], $errorarray);

        return $return;

    } else {

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

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $organisationDataObj["data"] = organisationGetDataObject($item, $db);
            $return = array_replace_recursive($return, $organisationDataObj);

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Organisation not found";
            $errorarray["detail"] = "Organisation with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

function organisationGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "organisation";
        $return["id"] = $item["OrganisationID"];
        $return["attributes"]["type"] = $item["OrganisationType"];
        $return["attributes"]["label"] = $item["OrganisationLabel"];
        $return["attributes"]["labelAlternative"] = json_decode($item["OrganisationLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["OrganisationAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["OrganisationThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = $item["OrganisationThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["OrganisationThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["OrganisationEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["OrganisationWebsiteURI"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["OrganisationSocialMediaIDs"],true);
        $return["attributes"]["color"] = $item["OrganisationColor"];
        $return["attributes"]["additionalInformation"] = json_decode($item["OrganisationAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["OrganisationLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/"."search/media?organisationID=".$return["id"];
        $return["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/"."search/people?organisationID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;

}

function organisationSearch($parameter, $db = false, $noLimit = false) {

    global $config;

    //TODO: Write real no limit logic
    $outputLimit = ($noLimit ? 10000 :25);

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

    $filteredParameters = filterAllowedSearchParams($parameter, 'organisation');

    /************ VALIDATION START ************/

    if (array_key_exists("name", $filteredParameters)) {

        if (is_array($filteredParameters["name"])) {

            foreach ($filteredParameters["name"] as $tmpNameID) {

                if (mb_strlen($tmpNameID, "UTF-8") < 3) {

                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "name too short";
                    $errorarray["detail"] = "Searching for name needs at least 3 characters.";
                    $return["errors"][] = $errorarray;

                }

            }

        } else {

            if (mb_strlen($filteredParameters["name"], "UTF-8") < 3) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "name too short";
                $errorarray["detail"] = "Searching for name needs at least 3 characters.";
                $return["errors"][] = $errorarray;

            }
        }
    }



    if (array_key_exists("type", $filteredParameters) && (mb_strlen($filteredParameters["type"], "UTF-8") < 2)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "2";
        $errorarray["title"] = "type too short";
        $errorarray["detail"] = "Searching for type needs at least 2 characters."; //  due to database limitations
        $return["errors"][] = $errorarray;

    }


    /************ VALIDATION END ************/





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

                    $tmpStringArray[] = $db->parse("((MATCH(OrganisationLabel, OrganisationLabelAlternative, OrganisationAbstract) AGAINST (?s IN BOOLEAN MODE)) OR (OrganisationLabel LIKE ?s))", "*". $tmppara ."*", "%".$tmppara."%");

                }

                $tmpStringArray = " (" . implode(" OR ", $tmpStringArray) . ")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("((MATCH(OrganisationLabel, OrganisationLabelAlternative, OrganisationAbstract) AGAINST (?s IN BOOLEAN MODE)) OR (OrganisationLabel LIKE ?s))", "*". $para."*", "%". $para."%");

            }
        }

        if ($k == "type") {

            $conditions[] = $db->parse("OrganisationType = ?s", $para);

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

            array_push($return["data"], organisationGetDataObject($finding,$db));
        }

    } else {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "404";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Not enough parameters";
        $errorarray["detail"] = "Not enough parameters";
        array_push($return["errors"], $errorarray);

    }

    if (!array_key_exists("data", $return)) {
        $return["data"] = array();
    }


    $return["links"]["self"] = $config["dir"]["api"]."/search/organisations?".getURLParameterFromArray($filteredParameters);

    return $return;



}

function organisationAdd($item, $db = false) {

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
            $errorarray["code"] = "2";
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

    if (!$item["type"]) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Type is missing";
        $errorarray["label"] = "type";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if (!$item["label"]) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Label is missing";
        $errorarray["label"] = "label";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ($return["errors"]) {
        $return["meta"]["requestStatus"] = "error";
        return $return;
    } else {

        $itemTmp = $db->getRow("SELECT OrganisationID FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["id"]);

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


                $db->query("INSERT INTO ?n SET ".
                                    "OrganisationID=?s, ".
                                    "OrganisationType=?s, ".
                                    "OrganisationLabel=?s, ".
                                    "OrganisationLabelAlternative=?s, ".
                                    "OrganisationAbstract=?s, ".
                                    "OrganisationThumbnailURI=?s, ".
                                    "OrganisationThumbnailCreator=?s, ".
                                    "OrganisationThumbnailLicense=?s, ".
                                    "OrganisationEmbedURI=?s, ".
                                    "OrganisationWebsiteURI=?s, ".
                                    "OrganisationSocialMediaIDs=?s, ".
                                    "OrganisationColor=?s, ".
                                    "OrganisationAdditionalInformation=?s",

                                    $config["platform"]["sql"]["tbl"]["Organisation"],
                                    $item["id"],
                                    $item["type"],
                                    $item["label"],
                                    (is_array($labelAlternative) ? json_encode($labelAlternative, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : "[".$item["labelAlternative"]."]"),
                                    $item["abstract"],
                                    $item["thumbnailuri"],
                                    $item["thumbnailcreator"],
                                    $item["thumbnaillicense"],
                                    $item["embeduri"],
                                    $item["websiteuri"],
                                    json_encode($socialMedia),
                                    $item["color"],
                                    (is_array($item["additionalinformation"]) ? json_encode($item["additionalinformation"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $item["additionalinformation"])
                );
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

function organisationGetOverview($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $getCount = false, $db = false) {

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
        $queryPart .= $db->parse("OrganisationID=?s",$id);
    }


    if (!empty($search)) {
        $queryPart .= $db->parse(" AND (OrganisationLabel LIKE ?s OR OrganisationLabelAlternative LIKE ?s OR OrganisationID LIKE ?s)", "%".$search."%", "%".$search."%", "%".$search."%");
    }

    if (!empty($sort)) {

        $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);

    }


    if ($limit != 0) {

        $queryPart .= $db->parse(" LIMIT ?i, ?i",$offset,$limit);

    }

    if ($getCount == true) {

        $return["total"] = $db->getOne("SELECT COUNT(OrganisationID) as count FROM ?n", $config["platform"]["sql"]["tbl"]["Organisation"]);
        $return["rows"] = $db->getAll("SELECT
            OrganisationID,
             OrganisationType,
             OrganisationLabel,
             OrganisationLabelAlternative,
             OrganisationLastChanged,
             OrganisationThumbnailURI
             FROM ?n
             WHERE ?p", $config["platform"]["sql"]["tbl"]["Organisation"], $queryPart);

    } else {
        $return = $db->getAll("SELECT
            OrganisationID,
             OrganisationType,
             OrganisationLabel,
             OrganisationLabelAlternative,
             OrganisationLastChanged,
             OrganisationThumbnailURI
             FROM ?n
             WHERE ?p", $config["platform"]["sql"]["tbl"]["Organisation"], $queryPart);
    }


    return $return;

}
?>
