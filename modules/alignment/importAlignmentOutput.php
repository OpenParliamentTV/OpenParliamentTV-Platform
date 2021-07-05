<?php

require __DIR__.'/../../vendor/autoload.php';

$hosts = ["https://@localhost:9200"];
$ESClient = Elasticsearch\ClientBuilder::create()
    ->setHosts($hosts)
    ->setBasicAuthentication("admin","admin")
    ->setSSLVerification(realpath(__DIR__."/../../opensearch-root-ssl.pem"))
    ->build();

require_once (__DIR__."/../../config.php");
require_once(__DIR__.'/../../api/v1/api.php');
require_once (__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/textArrayConverters.php");

importAlignmentOutput();

function importAlignmentOutput() {
	
	global $config;

	$outputFiles = array_values(array_diff(scandir('output'), array('.', '..', '.DS_Store', '.gitkeep', '.gitignore')));

	foreach($outputFiles as $file) {

		$fileNameArray = preg_split("/[\\_|\\.]/", $file);
		$mediaID = $fileNameArray[0];
		$textType = $fileNameArray[1];

		$file_contents = file_get_contents('output/'.$file);

		$mediaData = apiV1([
			"action"=>"getItem", 
			"itemType"=>"media", 
			"id"=>$mediaID
		]);

		$mediaTextContentsArray = $mediaData["data"]["attributes"]["textContents"];

		foreach ($mediaTextContentsArray as $textContentItem) {
			if ($textContentItem["type"] == $textType) {
				$mediaTextContents = json_encode($textContentItem,  JSON_UNESCAPED_UNICODE);
				break;
			}
		}

		if (isset($mediaTextContents)) {
			$updatedTextContents = mergeAlignmentOutputWithTextObject($file_contents, $mediaTextContents);

			updateData($mediaID, json_decode($updatedTextContents, true));

			//unlink("output/".$file);
		}
	}
}

function updateData($mediaID = false, $updatedTextContentsArray = false, $dbp = false) {

	global $ESClient;
	global $config;

	if ((!$mediaID) || ($updatedTextContentsArray)) {
	    return false;
    }

	$infos = getInfosFromStringID($mediaID);

    if (!$dbp) {

        $opts = array(
            'host'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["host"],
            'user'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["user"],
            'pass'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["passwd"],
            'db'	=> $config["parliament"][$infos["parliament"]]["sql"]["db"]
        );

        try {

            $dbp = new SafeMySQL($opts);

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "2";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }

    }

    $dbp->query("UPDATE ?n SET TextBody=?s WHERE TextType=?s AND TextMediaID=?s",
                $config["parliament"][$infos["parliament"]]["sql"]["tbl"]["Text"],
                json_encode($updatedTextContentsArray["textBody"]),
                "proceedings",
                $mediaID);

    $dbp->query("UPDATE ?n SET MediaAligned=?i WHERE MediaID=?s LIMIT 1",
                $config["parliament"][$infos["parliament"]]["sql"]["tbl"]["Media"],
                1,
                $mediaID);


    
    $data = apiV1([
        "action"=>"getItem",
        "itemType"=>"media",
        "id"=>$mediaID
    ]);

    $docParams = array(
        "index" => "openparliamenttv_de",
        "id" => $mediaID,
        "body" => json_encode($data["data"])
    );

    try {
        $result = $ESClient->index($docParams);
    } catch(Exception $e) {
        $result = $e->getMessage();
    }

    echo '<pre>';
    print_r($result);
    echo '</pre>';

	/*
	
	TODO: 

	1. Update DB table text, where: 
	"TextMediaID": $mediaID
	
	Update Fields: 
	"TextBody": json_encode($updatedTextContentsArray["textBody"])
	
	2. Update OpenSearch Index like:

	$data = apiV1([
		"action"=>"getItem", 
		"itemType"=>"media", 
		"id"=>$mediaID
	]);
	
	$docParams = array(
		"index" => "openparliamenttv_de", 
		"id" => $mediaID, 
		"body" => json_encode($data["data"])
	);
	
	try {
		$result = $ESClient->index($docParams);
	} catch(Exception $e) {
		$result = $e->getMessage();
	}

	echo '<pre>';
	print_r($result);
	echo '</pre>';
	
	*/


}

?>