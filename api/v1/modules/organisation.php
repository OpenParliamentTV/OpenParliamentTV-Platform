<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");

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
            $organisationDataObj["data"] = organisationGetDataObject($item, $db);
            $return = array_replace_recursive($return, $organisationDataObj);

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

function organisationGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "organisation";
        $return["id"] = $item["OrganisationID"];
        $return["attributes"]["type"] = $item["OrganisationType"];
        $return["attributes"]["label"] = $item["OrganisationLabel"];
        $return["attributes"]["labelAlternative"] = $item["OrganisationLabelAlternative"];
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
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["data"]["type"]."/".$return["data"]["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/"."search/media?organisationID=".$return["data"]["id"];
        $return["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/"."search/people?organisationID=".$return["data"]["id"];

    } else {

        $return = false;

    }

    return $return;

}

function organisationSearch($parameter, $db = false) {

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

    $allowedFields = ["name", "type"];

    $filteredParameters = array_filter(
        $parameter,
        function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        },
        ARRAY_FILTER_USE_KEY
    );




    /************ VALIDATION START ************/

    if (array_key_exists("name", $filteredParameters)) {

        if (is_array($filteredParameters["name"])) {

            foreach ($filteredParameters["name"] as $tmpNameID) {

                if (mb_strlen($tmpNameID, "UTF-8") < 3) {

                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "name too short";
                    $errorarray["detail"] = "Searching for name needs at least 3 characters."; //  TODO: Description
                    $return["errors"][] = $errorarray;

                }

            }

        } else {

            if (mb_strlen($filteredParameters["name"], "UTF-8") < 3) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "name too short";
                $errorarray["detail"] = "Searching for name needs at least 3 characters."; //  TODO: Description
                $return["errors"][] = $errorarray;

            }
        }
    }



    if (array_key_exists("type", $filteredParameters) && (mb_strlen($filteredParameters["type"], "UTF-8") < 2)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "2";
        $errorarray["title"] = "type too short";
        $errorarray["detail"] = "Searching for type needs at least 2 characters."; //  due to database limitations TODO: Description
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


    $return["links"]["self"] = $config["dir"]["api"]."/search/organisations?".getURLParameterFromArray($filteredParameters);

    return $return;



}

?>
