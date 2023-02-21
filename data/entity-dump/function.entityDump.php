<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__."/../../modules/utilities/safemysql.class.php");

function getEntityDump($parameter, $db = false) {

    global $config;

    if (!$db) {
        try {

            $db = new SafeMySQL(array(
                'host' => $config["platform"]["sql"]["access"]["host"],
                'user' => $config["platform"]["sql"]["access"]["user"],
                'pass' => $config["platform"]["sql"]["access"]["passwd"],
                'db' => $config["platform"]["sql"]["db"]
            ));

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to platform database failed. ".$e->getMessage();
            array_push($return["errors"], $errorarray);

            return $return;
        }
    }
    $response["meta"] = array();
    $response["data"] = array();

    if (($parameter["type"] == "person") || ($parameter["type"] == "all")) {

        $people = $db->getAll("SELECT PersonID as id, PersonLabel as label, PersonLabelAlternative as labelAlternative, PersonFirstName as firstname, PersonLastName as lastname, 'person' as 'type', PersonType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Person"]);
        //echo $db->parse("SELECT PersonID as id, PersonLabel as label, PersonLabelAlternative as labelAlternative, PersonFirstName as firstname, PersonLastName as lastname, 'person' as 'type' FROM ?n",$config["platform"]["sql"]["tbl"]["Person"]);
        //print_r($people);
        foreach ($people as $k=>$person) {
            $people[$k]["labelAlternative"] = json_decode($person["labelAlternative"]);
        }
        $response["meta"]["people_count"] = count($people);
        $response["data"] = array_merge($response["data"], $people);

    }

    if (($parameter["type"] == "organisation") || ($parameter["type"] == "all")) {

        $organisations = $db->getAll("SELECT OrganisationID as id, OrganisationLabel as label, OrganisationLabelAlternative as labelAlternative, 'organisation' as 'type', OrganisationType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Organisation"]);
        //TODO: When labelAlternative is already a json, dont do the following:
        foreach ($organisations as $k=>$organisation) {
            $organisations[$k]["labelAlternative"] = (($organisation["labelAlternative"]!=null) ? array($organisation["labelAlternative"]) : array());
        }
        $response["meta"]["organisations_count"] = count($organisations);
        $response["data"] = array_merge($response["data"], $organisations);

    }

    if (($parameter["type"] == "term") || ($parameter["type"] == "all")) {

        $terms = $db->getAll("SELECT TermWikidataID as id, TermLabel as label, TermLabelAlternative as labelAlternative, TermID as optvID, 'term' as 'type', TermType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Term"]);
        //TODO: When labelAlternative is already a json, dont do the following:
        foreach ($terms as $k=>$term) {
            $terms[$k]["labelAlternative"] = array($term["labelAlternative"]);
        }
        $response["meta"]["terms_count"] = count($terms);
        $response["data"] = array_merge($response["data"], $terms);

    }

    if (($parameter["type"] == "document") || (($parameter["type"] == "all") && (!$parameter["wiki"]))) {

        $documents = $db->getAll("SELECT DocumentWikidataID as id, DocumentLabel as label, DocumentLabelAlternative as labelAlternative, DocumentID as optvID, 'document' as 'type', DocumentType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Document"]);
        //TODO: When labelAlternative is already a json, dont do the following:
        foreach ($documents as $k=>$document) {
            $documents[$k]["labelAlternative"] = array($document["labelAlternative"]);
        }
        $response["meta"]["documents_count"] = count($documents);
        $response["data"] = array_merge($response["data"], $documents);

    }

    if ($parameter["wiki"]) {
        $items = array();
        foreach ($response["data"] as $item) {
            if (!preg_match("~Q[0-9]?~",$item["id"])) {
                continue;
            } else {
                if ($parameter["wikikeys"]) {
                    $items[$item["id"]] = $item;
                } else {
                    array_push($items,$item);
                }
            }
        }
        $response["data"] = $items;

    }

    $response["meta"]["total_count"] = count($response["data"]);
    return $response;


}
?>