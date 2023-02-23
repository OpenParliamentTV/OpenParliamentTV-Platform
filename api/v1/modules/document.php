<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");




/**
 * @param string $id documentID
 * @return array
 */
function documentGetByID($id = false) {

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


        try {

            $db = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed #1";
            array_push($return["errors"], $errorarray);
            return $return;

        }

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Document"]." WHERE DocumentID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $documentDataObj["data"] = documentGetDataObject($item, $db);
            $return = array_replace_recursive($return, $documentDataObj);

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Document not found";
            $errorarray["detail"] = "Document with the given ID was not found in database";
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

function documentGetDataObject($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "document";
        $return["id"] = $item["DocumentID"];
        $return["attributes"]["type"] = $item["DocumentType"];
        $return["attributes"]["wikidataID"] = $item["DocumentWikidataID"];
        $return["attributes"]["label"] = str_replace(array("\r","\n"), " ", $item["DocumentLabel"]);
        //$return["attributes"]["labelAlternative"] = str_replace(array("\r","\n"), " ", $item["DocumentLabelAlternative"]);
        $return["attributes"]["labelAlternative"] = json_decode($item["DocumentLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["DocumentAbstract"];
        $return["attributes"]["thumbnailURI"] = $item["DocumentThumbnailURI"];
        $return["attributes"]["thumbnailCreator"] = $item["DocumentThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["DocumentThumbnailLicense"];
        $return["attributes"]["sourceURI"] = $item["DocumentSourceURI"];
        $return["attributes"]["embedURI"] = $item["DocumentEmbedURI"];
        $return["attributes"]["additionalInformation"] = json_decode($item["DocumentAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["DocumentLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?documentID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;
}

function documentSearch($parameter, $db = false) {

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
            $errorarray["detail"] = "Connecting to database failed #2";
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    $allowedFields = ["label", "type", "wikidataID"];

    $filteredParameters = array_filter(
        $parameter,
        function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        },
        ARRAY_FILTER_USE_KEY
    );




    /************ VALIDATION START ************/

    if (array_key_exists("label", $filteredParameters)) {

        if (is_array($filteredParameters["label"])) {

            foreach ($filteredParameters["label"] as $tmpNameID) {

                if (mb_strlen($tmpNameID, "UTF-8") < 3) {

                    $return["meta"]["requestStatus"] = "error";
                    $errorarray["status"] = "400";
                    $errorarray["code"] = "1";
                    $errorarray["title"] = "label too short";
                    $errorarray["detail"] = "Searching for label needs at least 3 characters.";
                    $return["errors"][] = $errorarray;

                }

            }

        } else {

            if (mb_strlen($filteredParameters["label"], "UTF-8") < 3) {

                $return["meta"]["requestStatus"] = "error";
                $errorarray["status"] = "400";
                $errorarray["code"] = "1";
                $errorarray["title"] = "label too short";
                $errorarray["detail"] = "Searching for label needs at least 3 characters.";
                $return["errors"][] = $errorarray;

            }
        }
    }



    if (array_key_exists("type", $filteredParameters) && (mb_strlen($filteredParameters["type"], "UTF-8") < 2)) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "2";
        $errorarray["title"] = "type too short";
        $errorarray["detail"] = "Searching for type needs at least 2 characters.";
        $return["errors"][] = $errorarray;

    }



    if (array_key_exists("wikidataID", $filteredParameters) && (!preg_match("/(Q|P)\d+/i", $filteredParameters["wikidataID"]))) {

        $return["meta"]["requestStatus"] = "error";
        $errorarray["status"] = "400";
        $errorarray["code"] = "2";
        $errorarray["title"] = "wrong wikidataID";
        $errorarray["detail"] = "wikidataID doesn't match the pattern.";
        $return["errors"][] = $errorarray;

    }


    /************ VALIDATION END ************/





    if ($return["meta"]["requestStatus"] == "error") {

        return $return;

    }

    $query = "SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Document"];

    $conditions = array();

    foreach ($filteredParameters as $k=>$para) {
        if ($k == "label") {
            if (is_array($para)) {

                $tmpStringArray = array();

                foreach ($para as $tmppara) {

                    $tmpStringArray[] = $db->parse("((MATCH(DocumentLabel, DocumentLabelAlternative, DocumentAbstract) AGAINST (?s IN BOOLEAN MODE)) OR (DocumentLabel LIKE ?s))", "*".$tmppara."*", "%".$tmppara."%");
                }

                $tmpStringArray = " (" . implode(" OR ", $tmpStringArray) . ")";
                $conditions[] = $tmpStringArray;

            } else {

                $conditions[] = $db->parse("(MATCH(DocumentLabel, DocumentLabelAlternative, DocumentAbstract) AGAINST (?s IN BOOLEAN MODE) OR (DocumentLabel LIKE ?s))", "*".$para."*", "%".$para."%");

            }
        }

        if ($k == "type") {

            $conditions[] = $db->parse("DocumentType = ?s", $para);

        }

        if ($k == "wikidataID") {

            $conditions[] = $db->parse("DocumentWikidataID = ?s", $para);

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

            $itemObj = documentGetDataObject($finding,$db);
            unset($itemObj["meta"]);
            array_push($return["data"], $itemObj);
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


    $return["links"]["self"] = $config["dir"]["api"]."/search/documents?".getURLParameterFromArray($filteredParameters);

    return $return;



}


function documentAdd($item, $db = false) {

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

    if (!$item["type"]) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Type is missing";
        $errorarray["label"] = "type";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ((!$item["label"]) || strlen($item["label"]) < 3) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Label is missing or <3";
        $errorarray["label"] = "label";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ((!$item["abstract"]) || strlen($item["abstract"]) < 5) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Abstract is missing or <5";
        $errorarray["label"] = "abstract";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ((!$item["sourceuri"]) || strlen($item["sourceuri"]) < 5) {
        $errorarray["status"] = "422";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Source URI is missing or too short";
        $errorarray["label"] = "sourceuri";
        $errorarray["detail"] = "Required parameter of the request is missing";
        $return["errors"][] = $errorarray;
    }

    if ($return["errors"]) {
        $return["meta"]["requestStatus"] = "error";
        return $return;
    } else {

        if ($item["id"]) {
            $wikicondition = $db->parse(" OR DocumentWikidataID=?s",$item["id"]);
        }

        $itemTmp = $db->getRow("SELECT DocumentID FROM ?n WHERE DocumentSourceURI=?s".$wikicondition,$config["platform"]["sql"]["tbl"]["Document"],$item["sourceuri"]);

        if ($itemTmp) {
            $return["meta"]["requestStatus"] = "error";
            $errorarray["status"] = "422"; //todo
            $errorarray["code"] = "2";
            $errorarray["title"] = "An item with same WikidataID or SourceURI already exists in Database";
            $errorarray["label"] = "error_info";
            $errorarray["detail"] = "Item already exists in Database";
            $return["errors"][] = $errorarray;
            return $return;

        } else {

            try {

                $labelAlternative = array();
                if (is_array($item["labelAlternative"])) {
                    foreach ($item["labelAlternative"] as $v) {
                        if ($v) {
                            $labelAlternative[] = $v;
                        }
                    }
                }


                $db->query("INSERT INTO ?n SET ".
                    "DocumentType=?s, ".
                    "DocumentWikidataID=?s, ".
                    "DocumentLabel=?s, ".
                    "DocumentLabelAlternative=?s, ".
                    "DocumentAbstract=?s, ".
                    "DocumentThumbnailURI=?s, ".
                    "DocumentThumbnailCreator=?s, ".
                    "DocumentThumbnailLicense=?s, ".
                    "DocumentSourceURI=?s, ".
                    "DocumentEmbedURI=?s, ".
                    "DocumentAdditionalInformation=?s",

                    $config["platform"]["sql"]["tbl"]["Document"],
                    $item["type"],
                    $item["id"],
                    $item["label"],
                    (is_array($labelAlternative) ? json_encode($labelAlternative,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : "[".$item["labelAlternative"]."]"),
                    $item["abstract"],
                    $item["thumbnailuri"],
                    $item["thumbnailcreator"],
                    $item["thumbnaillicense"],
                    $item["sourceuri"],
                    $item["embeduri"],
                    (is_array($item["additionalinformation"]) ? json_encode($item["additionalinformation"],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $item["additionalinformation"])
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

?>
