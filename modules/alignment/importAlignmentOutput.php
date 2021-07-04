<?php

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

			echo "<pre>";
			print_r(json_decode($updatedTextContents));
			echo "</pre>";

			//unlink("output/".$file);
		}
	}
	/*****************************************
	* ToDo: Write Output to MySQL DB
	*****************************************/

}

?>