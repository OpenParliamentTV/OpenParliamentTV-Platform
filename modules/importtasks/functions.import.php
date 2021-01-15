<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/../utilities/conflict.php");
require_once(__DIR__."/../player/functions.media.php");

function importParliamentMedia($type, $parliament, $meta, $data="", $dbPlatform = false) {

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}


	$dbParliament = new SafeMySQL(array(
		'host'	=> $config["parliament"][$parliament]["sql"]["access"]["host"],
		'user'	=> $config["parliament"][$parliament]["sql"]["access"]["user"],
		'pass'	=> $config["parliament"][$parliament]["sql"]["access"]["passwd"],
		'db'	=> $config["parliament"][$parliament]["sql"]["db"]
	));

	if (!$dbParliament) {
		$return["success"] = "false";
		$return["txt"] = "Not connectable to Parliament Database";
		return $return;
	}

	switch ($type) {
		case "jsonfiles":

			if ((!is_dir($meta["inputDir"])) || (!array_key_exists($parliament,$config["parliament"]))) {

				$return["success"] = "false";
				$return["txt"] = "Missing parameter";
				return $return;

			}

			if (($meta["preserveFiles"] == true) && (!is_dir($meta["doneDir"]))) {
				$return["success"] = "false";
				$return["txt"] = "Preserve Directory does not exist.";
				return $return;
			}

			$inputFiles = scandir($meta["inputDir"]);

			if (count(array_diff($inputFiles, array('..', '.'))) < 1) {
				$return["success"] = "false";
				$return["txt"] = "No Inputfiles";
				return $return;
			}
			foreach ($inputFiles as $file) {

				if ((is_dir($meta["inputDir"] . $file)) || (!is_file($meta["inputDir"] . $file)) || (!preg_match('/.*\.json$/DA', $file))) {
					continue;
				}


				$json = json_decode(file_get_contents($meta["inputDir"] . $file), true);

				foreach ($json as $spKey=>$media) {

					//TODO: Prepare for more then one contributing person by having an anonymous array
					$media["mediaOriginalURL"] = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/' . $media['mediaID'] . '/' . $media['mediaID'] . '_h264_720_400_2000kb_baseline_de_2192.mp4';
					$media["mediaURL"] = $media["mediaOriginalURL"];
					$media["MediaPersonRole"] = "speaker";
					$media["MediaDateStart"] = $media["date"];
					$media["MediaDateEnd"] = $media["date"];
					$media["aligned"] = ($media["aligned"]) ? 1 : 0;
					$media["duration"] = ($media["duration"] === NULL) ? "" : $media["duration"];
					$media["agendaItemSecondTitle"] = ($media["agendaItemSecondTitle"] === null) ? "" : $media["agendaItemSecondTitle"];
					$media["content"] = ($media["content"] === null) ? "" : $media["content"];

					echo mediaAdd($media,$parliament,$dbParliament,$dbPlatform)."<br><br>";

				}

				if ($meta["preserveFiles"] == true) {
					rename($meta["inputDir"] . $file, $meta["doneDir"] . $file);
				} else {
					unlink($meta["inputDir"] . $file);
				}

			}

		break;
	}


}

function mediaAdd($media, $parliament, $dbParliament, $dbPlatform) {

	global $config;

	$returnConflicts = "<br>";


	$duplicatesHashes = $dbParliament->getAll("SELECT MediaHash FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] . " WHERE MediaURL = ?s", $media["mediaURL"]);
	foreach ($duplicatesHashes as $dhash) {

		$foreignMedia = getMedia($dhash["MediaHash"],$parliament,false,$dbPlatform,$dbParliament);

		/*
		echo "forHash:".$dhash["MediaHash"]."<br>";
		echo "id :".($media["id"] == $foreignMedia["MediaOriginalID"])."<br>";
		echo "aligned :".($media["aligned"] == $foreignMedia["MediaAligned"])."<br>";
		echo "MediaDateStart :".($media["MediaDateStart"] == $foreignMedia["MediaDateStart"])."<br>";
		echo "MediaDateEnd :".($media["MediaDateEnd"] == $foreignMedia["MediaDateEnd"])."<br>";
		echo "duration :".(((string)$media["duration"] == (string)$foreignMedia["MediaDuration"]) ? "1" : "<span style='color:#ff0000'>".(string)$media["duration"]." ".(string)$foreignMedia["MediaDuration"]."</span>")."<br>";
		echo "mediaID :".($media["mediaID"] == $foreignMedia["MediaOriginalMediaID"])."<br>";
		echo "mediaURL :".($media["mediaURL"] == $foreignMedia["MediaURL"])."<br>";
		echo "agendaItemTitle :".($media["agendaItemTitle"] == $foreignMedia["AgendaItemTitle"])."<br>";
		echo "agendaItemSecondTitle :".($media["agendaItemSecondTitle"] == $foreignMedia["AgendaItemSubTitle"])."<br>";
		echo "electoralPeriod :".($media["electoralPeriod"] == $foreignMedia["ElectoralPeriodNumber"])."<br>";
		*/

		if (($media["id"] == $foreignMedia["MediaOriginalID"])
			&& ($media["aligned"] == $foreignMedia["MediaAligned"])
			&& ($media["MediaDateStart"] == $foreignMedia["MediaDateStart"])
			&& ($media["MediaDateEnd"] == $foreignMedia["MediaDateEnd"])
			&& ($media["duration"] == $foreignMedia["MediaDuration"])
			&& ($media["mediaID"] == $foreignMedia["MediaOriginalMediaID"])
			&& ($media["mediaURL"] == $foreignMedia["MediaURL"])
			&& ($media["agendaItemTitle"] == $foreignMedia["AgendaItemTitle"])
			&& ($media["agendaItemSecondTitle"] == $foreignMedia["AgendaItemSubTitle"])
			&& ($media["electoralPeriod"] == $foreignMedia["ElectoralPeriodNumber"])) {

			//echo "sameFields:Yes<br>";


			$different = 0;

			foreach ($media["documents"] as $doc) {

				if (array_search($doc, array_column($foreignMedia["mediaannotation"], 'MediaAnnotationBody')) === false) {
					$different++;
				}

			}

			//TODO Have >1 content?
			$mediaContentHash = hash("sha256",$media["content"]);
			if (array_search($mediaContentHash, array_column($foreignMedia["mediacontent"], 'MediaContentHash')) === false) {
				$different++;

			}



			$personFindings = 0;
			//TODO have >1 person as input
			foreach ($foreignMedia["mediaperson"] as $mp) {
				if (($mp["MediaPersonOriginalPersonLastName"] == $media["speakerLastName"])
					&& ($mp["MediaPersonOriginalPersonID"] == $media["speakerID"])
					&& ($mp["MediaPersonOriginalPersonPartyLabel"] == $media["speakerParty"])) {
					$personFindings++;
				}
			}

			//return "Different:".$different."<br>"."personFindings:".$personFindings."<br>mediaContentHash:".$mediaContentHash."<br><br>";

			if (($different === 0) && ($personFindings > 0)) {

				//reportConflict("Media", "mediaAdd duplicated entry","","", "Media ".$media["id"]." (".$media["mediaID"].") was not added because the same dataset already exists with MediaHash ".$foreignMedia["MediaHash"]." ",$dbPlatform);
				//TODO: Put info into logger
				return "Media ".$media["id"]." (".$media["mediaID"].") was not added because the same dataset already exists with MediaHash ".$foreignMedia["MediaHash"]." ";

			}

		}
	}


	//TODO: Maybe check if $dbPlatform and $dbParliament exists

	//ElectoralPeriod
	$electoralPeriod = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " WHERE ElectoralPeriodNumber = ?i LIMIT 1", $media["electoralPeriod"]);
	if (!$electoralPeriod) {
		$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " SET ElectoralPeriodNumber = ?i", $media["electoralPeriod"]);
		$electoralPeriod = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " WHERE ElectoralPeriodNumber = ?i LIMIT 1", $media["electoralPeriod"]);
	}


	//Session
	$sess = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1", $media["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
	if (!$sess) {
		$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " SET SessionNumber = ?i, SessionElectoralPeriodID = ?i", $media["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
		$sess = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1", $media["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
	}


	//AgendaItem
	//TODO: AgendaItemNumber is not in the original Data. Regex for "Tagesordnungspunkt $"?
	//$agendaItem = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemSessionID = ?i AND AgendaItemNumber = ?i AND AgendaItemTitle = ?s LIMIT 1",$sess["SessionID"],NULL,$speech["agendaItemTitle"]);
	//TODO: if agendaItemSecondTitle is available, check if a row for agendaItemTitle exist (if not, add it) and add agendaItemSecondTitle to its children

	$agendaItem = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1", $sess["SessionID"], $media["agendaItemTitle"], $media["agendaItemSecondTitle"]);
	//echo $dbParliament->parse("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1", $sess["SessionID"], $media["agendaItemTitle"], $media["agendaItemSecondTitle"]);
	if (!$agendaItem) {
		$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " SET AgendaItemSessionID = ?i, AgendaItemTitle = ?s, AgendaItemSubTitle = ?s", $sess["SessionID"], $media["agendaItemTitle"], $media["agendaItemSecondTitle"]);
		$agendaItem = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1", $sess["SessionID"], $media["agendaItemTitle"], $media["agendaItemSecondTitle"]);
	}

	$mediaHashFirstPart = $parliament."-".sprintf('%03d', $electoralPeriod["ElectoralPeriodNumber"]).sprintf('%04d', $sess["SessionNumber"]);

	$mediaHashLatest = $dbParliament->getOne("SELECT MediaHash FROM " . $config["parliament"]["bt"]["sql"]["tbl"]["Media"] . " WHERE MediaHash LIKE ?s ORDER BY MediaHash DESC LIMIT 1", $mediaHashFirstPart."%");
	if (!$mediaHashLatest) {
		$mediaHashLatest = $mediaHashFirstPart."000";
	}

	$mediaHash = $mediaHashFirstPart.sprintf('%03d',(intval(substr($mediaHashLatest,-3))+1));

	$dbData = array(
		"MediaOriginalID" => $media["id"],
		"MediaAligned" => $media["aligned"],
		"MediaDateStart" => $media["MediaDateStart"],
		"MediaDateEnd" => $media["MediaDateEnd"],
		"MediaDuration" => $media["duration"],
		"MediaOriginalMediaID" => $media["mediaID"],
		"MediaURL" => $media["mediaURL"],
		"MediaOriginalMediaURL" => $media["mediaOriginalURL"],
		"MediaAgendaItemID" => $agendaItem["AgendaItemID"],
		"MediaHash" => $mediaHash
	);

	try {
		$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] . " SET ?u", $dbData);
	} catch (Exception $e) {
		reportConflict("Media","mediaAdd failed","","","Could not add Media with ID: ".$media["id"]." Error:".$e->getMessage(),$dbPlatform);
		return "Could not add Media with ID: ".$media["id"]." Error:".$e->getMessage();
	}

	$dbMedia = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] . " WHERE MediaHash = ?s LIMIT 1", $mediaHash);

	if (!$dbMedia["MediaOriginalID"]) {
		reportConflict("Media", "mediaAdd missing mediaID",$dbMedia["MediaHash"],"", "MediaID was not given in the raw data. This item can be found by its Hash (".$dbMedia["MediaHash"].") or its ID (".$dbMedia["MediaID"].")",$dbPlatform);
		$returnConflicts .= "MediaID was not given in the raw data. This item can be found by its Hash (".$dbMedia["MediaHash"].") or its ID (".$dbMedia["MediaID"].")<br>";
	}


	//TODO Check if its still needed after duplicate check at beginning
	$duplicates = $dbParliament->getAll("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Media"] . " WHERE MediaURL = ?s AND MediaHash != ?s", $dbMedia["MediaURL"], $dbMedia["MediaHash"]);

	if ($duplicates) {
		foreach ($duplicates as $tmp_duplicate) {
			reportConflict("Media","mediaAdd duplicate MediaURL",$dbMedia["MediaHash"],$tmp_duplicate["MediaHash"], "MediaURL of the added Item (".$dbMedia["MediaHash"].", ".$dbMedia["MediaID"].") is the same as for the rival item (".$tmp_duplicate["MediaHash"].", ".$tmp_duplicate["MediaID"].")",$dbPlatform);
			$returnConflicts .= "MediaURL of the added Item (".$dbMedia["MediaHash"].", ".$dbMedia["MediaID"].") is the same as for the rival item (".$tmp_duplicate["MediaHash"].")<br>";
			//$returnConflicts .= "ContentHash = (".hash("sha256",$media["content"]).")";
		}
	}


	//TODO: Prepare for more then one contributing person by having an anonymous array

	// Party
	if ($media["speakerParty"]) {
		$party = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Party"] . " WHERE PartyLabel = ?s LIMIT 1", $media["speakerParty"]);
		if (!$party) {
			$dbPlatform->query("INSERT INTO " . $config["platform"]["sql"]["tbl"]["Party"] . " SET PartyLabel = ?s", $media["speakerParty"]);
			$party = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Party"] . " WHERE PartyLabel = ?s LIMIT 1", $media["speakerParty"]);
			reportConflict("Party","New Party added: ".$party["PartyLabel"],$party["PartyID"],"", "New Party has been added: ".json_encode($party),$dbPlatform);
		}
	} else {
		$party = false;
	}


	//Person for Platform
	$person = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Person"] . " WHERE PersonOriginalID = ?s LIMIT 1", $media["speakerID"]);
	if (!$person) {
		$dbPlatform->query("INSERT INTO " . $config["platform"]["sql"]["tbl"]["Person"] . " SET PersonFirstname = ?s, PersonLastname = ?s, PersonDegree = ?s, PersonOriginalID = ?s, PersonPartyID = ?i", $media["speakerFirstName"], $media["speakerLastName"], $media["speakerDegree"], $media["speakerID"], ((is_array($party)) ? $party["PartyID"] : NULL));
		$person = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Person"] . " WHERE PersonOriginalID = ?s LIMIT 1", $media["speakerID"]);
	}

	//Person for Media
	$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["MediaPerson"] . " SET MediaPersonMediaID = ?i, MediaPersonPersonID = ?i, MediaPersonRole = ?s, MediaPersonOriginalPersonID = ?s, MediaPersonOriginalPersonParty = ?s, MediaPersonOriginalPersonPartyLabel = ?s, MediaPersonOriginalPersonRole=?s, MediaPersonOriginalPersonLastName=?s", $dbMedia["MediaID"], $person["PersonID"], $media["MediaPersonRole"], $media["speakerID"], ((is_array($party)) ? $party["PartyID"] : NULL), $media["speakerParty"], $media["speakerRole"], $media["speakerLastName"]);


	//Documents
	foreach ($media["documents"] as $doc) {

		$tmpdoc = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["MediaAnnotation"] . " WHERE MediaAnnotationMediaID = ?i AND MediaAnnotationBody = ?s AND MediaAnnotationType = ?s LIMIT 1", $dbMedia["MediaID"], $doc, "document");
		if (!$tmpdoc) {
			$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["MediaAnnotation"] . " SET MediaAnnotationMediaID = ?i, MediaAnnotationBody = ?s, MediaAnnotationType = ?s", $dbMedia["MediaID"], $doc, "document");
		}

	}


	//Content
	//TODO Have >1 content?
	$content = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["MediaContent"] . " WHERE MediaContentMediaID = ?i AND MediaContentType = ?s LIMIT 1", $dbMedia["MediaID"], "transcript");
	if (!$content) {
		$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["MediaContent"] . " SET MediaContentMediaID = ?i, MediaContentType = ?s, MediaContentBody = ?s, MediaContentHash=?s", $dbMedia["MediaID"], "transcript", $media["content"],hash("sha256",$media["content"]));
	}

	return $dbMedia["MediaID"] . " (MediaOriginalID: " . $dbMedia["MediaOriginalID"] . "; MediaHash: " . $dbMedia["MediaHash"] . ") added.".$returnConflicts;

}






?>