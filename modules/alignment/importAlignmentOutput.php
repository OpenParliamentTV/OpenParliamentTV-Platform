<?php

session_start();
include_once(__DIR__ . '/../../modules/utilities/auth.php');

/**
 * Example:
 * $config["ES_Offset"] = " LIMIT 5,1000000";
 * will continue with the 6th Media Item and will get 1000000 items in total (or less).
 */

$config["alignment"]["platform"] = "DE";
#$config["alignment"]["startID"] = "DE-0190160184";



$auth = auth($_SESSION["userdata"]["id"], "elasticSearch", "updateIndex");
//$auth["meta"]["requestStatus"] = "success";
if (($auth["meta"]["requestStatus"] != "success") && (php_sapi_name() != "cli")) {

    $alertText = $auth["errors"][0]["detail"];
    echo $alertText;


} else {


    require __DIR__ . '/../../vendor/autoload.php';

    require_once(__DIR__ . "/../../config.php");

    $ESClientBuilder = Elasticsearch\ClientBuilder::create();

    if ($config["ES"]["hosts"]) {
        $ESClientBuilder->setHosts($config["ES"]["hosts"]);
    }
    if ($config["ES"]["BasicAuthentication"]["user"]) {
        $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"], $config["ES"]["BasicAuthentication"]["passwd"]);
    }
    if ($config["ES"]["SSL"]["pem"]) {
        $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
    }
    $ESClient = $ESClientBuilder->build();


    require_once(__DIR__ . '/../../api/v1/api.php');
    require_once(__DIR__ . "/../utilities/safemysql.class.php");
    require_once(__DIR__ . "/../utilities/textArrayConverters.php");

    if ($config["alignment"]["platform"]) {
        try {

            $dbp = new SafeMySQL(array(
                'host'	=> $config["parliament"][$config["alignment"]["platform"]]["sql"]["access"]["host"],
                'user'	=> $config["parliament"][$config["alignment"]["platform"]]["sql"]["access"]["user"],
                'pass'	=> $config["parliament"][$config["alignment"]["platform"]]["sql"]["access"]["passwd"],
                'db'	=> $config["parliament"][$config["alignment"]["platform"]]["sql"]["db"]
            ));

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to parliament database failed"; //TODO: Description
            array_push($return["errors"], $errorarray);
            return $return;

        }
    } else {
        $dbp = false;
    }

    try {

        $db = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));

    } catch (exception $e) {

        $return["meta"]["requestStatus"] = "error";
        $return["errors"] = array();
        $errorarray["status"] = "503";
        $errorarray["code"] = "1";
        $errorarray["title"] = "Database connection error";
        $errorarray["detail"] = "Connecting to platform database failed"; //TODO: Description
        array_push($return["errors"], $errorarray);
        return $return;

    }



    importAlignmentOutput($db,$dbp);

}

function importAlignmentOutput($db = false, $dbp = false) {
	
	global $config;

	$outputFiles = array_values(array_diff(scandir(__DIR__ . '/output'), array('.', '..', '.DS_Store', '.gitkeep', '.gitignore')));



	foreach($outputFiles as $file) {
	    if (is_dir(__DIR__."/output/".$file)) {
	        continue;
        }

		$fileNameArray = preg_split("/[\\_|\\.]/", $file);
		$mediaID = $fileNameArray[0];
		$textType = $fileNameArray[1];

        if ($config["alignment"]["startID"]) {
            if ($mediaID < $config["alignment"]["startID"]) {
                echo "Skipping ".$mediaID." \n";
                continue;
            }
        }

		$file_contents = file_get_contents(__DIR__ . '/output/'.$file);

		$mediaData = apiV1([
			"action"=>"getItem", 
			"itemType"=>"media", 
			"id"=>$mediaID
		],$db,$dbp);

		$mediaTextContentsArray = $mediaData["data"]["attributes"]["textContents"];

		foreach ($mediaTextContentsArray as $textContentItem) {
			if ($textContentItem["type"] == $textType) {
				$mediaTextContents = json_encode($textContentItem,  JSON_UNESCAPED_UNICODE);
				break;
			}
		}

		if (isset($mediaTextContents)) {
			$updatedTextContents = mergeAlignmentOutputWithTextObject($file_contents, $mediaTextContents);

			updateData($mediaID, json_decode($updatedTextContents, true), $db, $dbp);

			//unlink("output/".$file);
		}
	}
}

function updateData($mediaID = false, $updatedTextContentsArray = false,$db = false, $dbp = false) {

	global $ESClient;
	global $config;

	if ((!$mediaID) || (!$updatedTextContentsArray)) {
	    return false;
    }

	$infos = getInfosFromStringID($mediaID);

    if (!$dbp) {

        try {

            $dbp = new SafeMySQL(array(
                'host'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["host"],
                'user'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["user"],
                'pass'	=> $config["parliament"][$infos["parliament"]]["sql"]["access"]["passwd"],
                'db'	=> $config["parliament"][$infos["parliament"]]["sql"]["db"]
            ));

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
    ], $db, $dbp);

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