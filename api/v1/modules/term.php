<?php

require_once (__DIR__."./../../../config.php");
require_once (__DIR__."./../../../modules/utilities/functions.php");
require_once (__DIR__."./../../../modules/utilities/safemysql.class.php");

/**
 * @param string $id TermID
 * @return array
 */
function termGetByID($id = false) {

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

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Term"]." WHERE TermID=?i",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "document";
            $return["data"]["id"] = $item["TermID"];
            $return["data"]["attributes"]["type"] = $item["TermType"];
            $return["data"]["attributes"]["wikidataID"] = $item["TermWikidataID"];
            $return["data"]["attributes"]["label"] = $item["TermLabel"];
            $return["data"]["attributes"]["labelAlternative"] = $item["TermLabelAlternative"];
            $return["data"]["attributes"]["abstract"] = $item["TermAbstract"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["TermThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["TermThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["TermThumbnailLicense"];
            $return["data"]["attributes"]["sourceURI"] = $item["TermSourceURI"];
            $return["data"]["attributes"]["embedURI"] = $item["TermEmbedURI"];
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["TermAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["TermLastChanged"];
            $return["data"]["links"]["self"] = ""; //TODO: Link
            $return["data"]["relationships"]["media"]["links"]["self"] = ""; //TODO: Link - "self"?

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Term not found";
            $errorarray["detail"] = "Term with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
