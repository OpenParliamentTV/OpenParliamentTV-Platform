<?php

require_once(__DIR__.'/../../vendor/autoload.php');

/*
 * TODO: REMOVE IF OTHER WORKS
 *
$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();
*/
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../utilities/functions.entities.php');

$ESClientBuilder = Elasticsearch\ClientBuilder::create();

if ($config["ES"]["hosts"]) {
    $ESClientBuilder->setHosts($config["ES"]["hosts"]);
}
if ($config["ES"]["BasicAuthentication"]["user"]) {
    $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"],$config["ES"]["BasicAuthentication"]["passwd"]);
}
if ($config["ES"]["SSL"]["pem"]) {
    $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
}
//print_r($ESClientBuilder);

$ESClient = $ESClientBuilder->build();

function getPrevDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "openparliamenttv_*", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"attributes.timestamp" => array(
								"lt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"attributes.timestamp" => array(
					"order" => "desc"
				)
			)
		));
	
	try {
		$result = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$result = null;
	}

	return json_encode($result["hits"]["hits"][0], true);
}

function getNextDocument($currentDocumentTimestamp) {
	
	global $ESClient;
	
	$searchParams = array("index" => "openparliamenttv_*", 
		"body" => array(
			"size" => 1,
			"query" => array(
				"constant_score" => array(
					"filter" => array(
						"range" => array(
							"attributes.timestamp" => array(
								"gt" => $currentDocumentTimestamp
							)
						)
					)
				)
			),
			"sort" => array(
				"attributes.timestamp" => array(
					"order" => "asc"
				)
			)
		));
	
	try {
		$result = $ESClient->search($searchParams);
	} catch(Exception $e) {
		print_r($e->getMessage());
		$result = null;
	}

	return json_encode($result["hits"]["hits"][0], true);
}

function getDocument($documentID) {
	global $ESClient;
	
	$docParams = array("index" => "openparliamenttv_*", 
		"id" => $documentID, 
		"_source" => true);
	
	try {
		$doc = $ESClient->get($docParams);
	} catch(Exception $e) {
		//print_r($e->getMessage());
		$doc = null;
	}

	return json_encode($doc, true);
}


function getFrametrailAnnotations($annotations, $relationships, $mediaSource) {

    global $config;

    if (!$annotations || !$relationships || !$mediaSource) {
        return false;
    }

    $return = array();

    foreach($annotations as $annotation) {

        if (!$annotation["attributes"]["timeStart"] || !$annotation["attributes"]["timeEnd"]) {
            continue;
        }

        $entity = $annotation;
        ob_start();
        include __DIR__."/../../content/components/entity.preview.php";
        $annotationHTML = ob_get_clean();

        if ($annotation["type"] == "person") {
            $tmpType = "people";
        } elseif ($annotation["type"] == "organisation") {
            $tmpType = "organisations";
        } elseif ($annotation["type"] == "term") {
            $tmpType = "terms";
        } elseif ($annotation["type"] == "document") {
            $tmpType = "documents";
        }

        foreach ($relationships[$tmpType]["data"] as $relationship) {
            if ($annotation["id"] == $relationship["id"]) {
                $tmpItem = array();
                $tmpItem["@context"][0]                     = "http://www.w3.org/ns/anno.jsonld";
                $tmpItem["@context"][1]["frametrail"]       = "http://frametrail.org/ns/";
                $tmpItem["creator"]["nickname"]             = "system";
                $tmpItem["creator"]["type"]                 = "system";
                $tmpItem["creator"]["id"]                   = "0";
                $tmpItem["created"]                         = "";
                $tmpItem["type"]                            = "Annotation";
                $tmpItem["frametrail:type"]                 = "Annotation";
                $tmpItem["frametrail:tags"]                 = array($annotation["type"],$relationship["attributes"]["type"]);
                $tmpItem["target"]["type"]                  = "video";
                $tmpItem["target"]["source"]                = $mediaSource;
                $tmpItem["target"]["selector"]["confirmsTo"] = "http://www.w3.org/TR/media-frags/";
                $tmpItem["target"]["selector"]["type"]      = "FragmentSelector";
                $tmpItem["target"]["selector"]["value"]     = "t=".$annotation["attributes"]["timeStart"].",".$annotation["attributes"]["timeEnd"];
                $tmpItem["body"]["type"]                    = "TextualBody";
                $tmpItem["body"]["frametrail:type"]         = "text";
                $tmpItem["body"]["format"]                  = "text/html";
                $tmpItem["body"]["value"]                   = "";
                $tmpItem["body"]["frametrail:name"]         = $relationship["attributes"]["label"];
                $tmpItem["body"]["frametrail:thumb"]        = $relationship["attributes"]["thumbnailURI"];
                $tmpItem["body"]["frametrail:resourceId"]   = null;
                $tmpItem["body"]["frametrail:attributes"]["text"] = htmlentities($annotationHTML);
                array_push($return, $tmpItem);
                break;

            }
        }
    }

    return $return;

}

function countNERfrequency($annotations, $id) {
    $return = 0;
    foreach ($annotations as $annotation) {
        if (($annotation["id"] == $id) && ($annotation["attributes"]["context"] == "NER")) {
            $return++;
        }
    }
    return $return;

}


?>