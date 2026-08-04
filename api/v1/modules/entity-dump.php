<?php
require_once(__DIR__."/../../../config.php");
require_once(__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once(__DIR__."/../../../modules/utilities/functions.php");

/**
 * Bulk export of every platform entity (people, organisations, terms,
 * documents) as a flat list of id/label/labelAlternative/type records.
 *
 * Two consumers:
 *  - the NEL stage of the Tools pipeline, which fetches it over HTTP
 *    (action=entityDump) and writes it to its metadata/entities.json;
 *  - the importer, which calls this function directly to resolve a NER wid
 *    to its entity type (bin/import.php, mediaAdd()).
 *
 * @param array $parameter Selection: type (person|organisation|term|document|all),
 *                         exclude_<type> alongside type=all, wiki (Wikidata-id
 *                         items only), wikikeys (key the list by Wikidata id).
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 */
function entityDumpGet($parameter, $db = false) {

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

    if (($parameter["type"] == "person") || (($parameter["type"] == "all") && (!$parameter["exclude_person"]))) {

        $people = $db->getAll("SELECT PersonID as id, PersonLabel as label, PersonLabelAlternative as labelAlternative, PersonFirstName as firstname, PersonLastName as lastname, 'person' as 'type', PersonType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Person"]);
        //echo $db->parse("SELECT PersonID as id, PersonLabel as label, PersonLabelAlternative as labelAlternative, PersonFirstName as firstname, PersonLastName as lastname, 'person' as 'type' FROM ?n",$config["platform"]["sql"]["tbl"]["Person"]);
        //print_r($people);
        foreach ($people as $k=>$person) {
            $people[$k]["labelAlternative"] = json_decode($person["labelAlternative"]);
        }
        $response["meta"]["people_count"] = count($people);
        $response["data"] = array_merge($response["data"], $people);

    }

    if (($parameter["type"] == "organisation") || (($parameter["type"] == "all") && (!$parameter["exclude_organisation"]))) {

        $organisations = $db->getAll("SELECT OrganisationID as id, OrganisationLabel as label, OrganisationLabelAlternative as labelAlternative, 'organisation' as 'type', OrganisationType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Organisation"]);

        foreach ($organisations as $k=>$organisation) {
            if (optvIsJson($organisation["labelAlternative"])) {
                $organisations[$k]["labelAlternative"] = json_decode($organisation["labelAlternative"]);
            } elseif ($organisation["labelAlternative"]!=null) {
                $organisations[$k]["labelAlternative"] = array($organisation["labelAlternative"]);
            } else {
                $organisations[$k]["labelAlternative"] = array();
            }
            //$organisations[$k]["labelAlternative"] = (($organisation["labelAlternative"]!=null) ? array($organisation["labelAlternative"]) : array());
        }
        $response["meta"]["organisations_count"] = count($organisations);
        $response["data"] = array_merge($response["data"], $organisations);

    }

    if (($parameter["type"] == "term") || (($parameter["type"] == "all") && (!$parameter["exclude_term"]))) {

        $terms = $db->getAll("SELECT TermID as id, TermLabel as label, TermLabelAlternative as labelAlternative, 'term' as 'type', TermType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Term"]);

        foreach ($terms as $k=>$term) {
            if (optvIsJson($term["labelAlternative"])) {
                $terms[$k]["labelAlternative"] = json_decode($term["labelAlternative"]);
            } elseif ($term["labelAlternative"]!=null) {
                $terms[$k]["labelAlternative"] = array($term["labelAlternative"]);
            } else {
                $terms[$k]["labelAlternative"] = array();
            }
            //$terms[$k]["labelAlternative"] = array($term["labelAlternative"]);
        }
        $response["meta"]["terms_count"] = count($terms);
        $response["data"] = array_merge($response["data"], $terms);

    }

    if (($parameter["type"] == "document") || (($parameter["type"] == "all") && (!$parameter["exclude_document"]))) {

        //TODO SELECT DocumentWikidataID as id, DocumentLabel as label, DocumentLabelAlternative as labelAlternative, DocumentID as optvID, 'document' as 'type', DocumentType as subType FROM document WHERE DocumentWikidataID LIKE "Q%";
        $documents = $db->getAll("SELECT DocumentWikidataID as id, DocumentLabel as label, DocumentLabelAlternative as labelAlternative, DocumentID as optvID, 'document' as 'type', DocumentType as subType FROM ?n",$config["platform"]["sql"]["tbl"]["Document"]);

        foreach ($documents as $k=>$document) {
            if (optvIsJson($document["labelAlternative"])) {
                $documents[$k]["labelAlternative"] = json_decode($document["labelAlternative"]);
            } elseif ($term["labelAlternative"]!=null) {
                $documents[$k]["labelAlternative"] = array($document["labelAlternative"]);
            } else {
                $documents[$k]["labelAlternative"] = array();
            }
            //$documents[$k]["labelAlternative"] = array($document["labelAlternative"]);
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

/**
 * Serves the dump directly and exits, bypassing the standard JSON envelope and
 * encoder — the body is a bare {meta, data} object, byte-for-byte what the
 * pre-API /data/entity-dump endpoint returned (same key order, same encoder
 * flags). NEL pipelines parse it verbatim, so the shape is fixed.
 *
 * The entity selector arrives either as the path segment mapped to itemType
 * (/api/v1/entityDump/person) or as the legacy ?type= query parameter that the
 * /data/entity-dump redirect preserves; ?type= wins when both are present.
 *
 * @param array $parameter The merged API request.
 * @param SafeMySQL|false $db Platform connection, when the caller already has one.
 */
function entityDumpServe($parameter, $db = false)
{
    $parameter["type"] = (isset($parameter["type"]) && $parameter["type"] !== "")
        ? $parameter["type"]
        : ($parameter["itemType"] ?? "all");

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        entityDumpGet($parameter, $db),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT | JSON_HEX_APOS
    );
    exit;
}
?>