<?php

require_once (__DIR__."./../../../config.php");
require_once ("config.php");
require_once (__DIR__."./../../../modules/utilities/functions.php");
require_once (__DIR__."./../../../modules/utilities/safemysql.class.php");

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

        $item = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Document"]." WHERE DocumentID=?s",$id);

        if ($item) {

            $return["meta"]["requestStatus"] = "success";
            $return["data"]["type"] = "document";
            $return["data"]["id"] = $item["DocumentID"];
            $return["data"]["attributes"]["type"] = $item["DocumentType"];
            $return["data"]["attributes"]["wikidataID"] = $item["DocumentWikidataID"];
            $return["data"]["attributes"]["label"] = $item["DocumentLabel"];
            $return["data"]["attributes"]["labelAlternative"] = $item["DocumentLabelAlternative"];
            $return["data"]["attributes"]["abstract"] = $item["DocumentAbstract"];
            $return["data"]["attributes"]["thumbnailURI"] = $item["DocumentThumbnailURI"];
            $return["data"]["attributes"]["thumbnailCreator"] = $item["DocumentThumbnailCreator"];
            $return["data"]["attributes"]["thumbnailLicense"] = $item["DocumentThumbnailLicense"];
            $return["data"]["attributes"]["sourceURI"] = $item["DocumentSourceURI"];
            $return["data"]["attributes"]["embedURI"] = $item["DocumentEmbedURI"];
            $return["data"]["attributes"]["additionalInformation"] = json_decode($item["DocumentAdditionalInformation"],true);
            $return["data"]["attributes"]["lastChanged"] = $item["DocumentLastChanged"];
            $return["data"]["links"]["self"] = $config["dir"]["api"].$return["data"]["type"]."/".$return["data"]["id"];
            $return["data"]["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."searchMedia?documentID=".$return["data"]["id"];

        } else {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "404";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Document not found";
            $errorarray["detail"] = "Document with the given ID was not found in database"; //TODO: Description
            array_push($return["errors"], $errorarray);

        }

        return $return;

    }
}

?>
