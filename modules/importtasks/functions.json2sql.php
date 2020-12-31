<?php



function importParliamentSpeechJSONtoSQL($inputDir, $doneDir, $parliament, $dbPlatform = false) {

	require_once(__DIR__."/../../config.php");
	global $config;
	require_once(__DIR__."/../utilities/safemysql.class.php");
	require_once(__DIR__."/../utilities/uniqueFreeString.php");



	if (!is_dir($inputDir) || !is_dir($doneDir) || !array_key_exists($parliament,$config["parliament"])) {

		$return["success"] = "false";
		$return["txt"] = "Missing parameter";
		return $return;

	}

	$inputFiles = scandir($inputDir);

	if (count(array_diff($inputFiles, array('..', '.'))) < 1) {
		$return["success"] = "false";
		$return["txt"] = "No Inputfiles";
		return $return;
	}


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

	foreach ($inputFiles as $file) {

		if ((is_dir($inputDir . $file)) || (!is_file($inputDir . $file)) || (!preg_match('/.*\.json$/DA', $file))) {
			continue;
		}


		$json = json_decode(file_get_contents($inputDir . $file), true);


		foreach ($json as $spKey=>$speech) {

			//Check if speech already exists based on original media url
			//$mediaSource = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$speech["_source"]["meta"]['mediaID'].'/'.$speech["_source"]["meta"]['mediaID'].'_h264_720_400_2000kb_baseline_de_2192.mp4';

			$originalMediaURL = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/' . $speech['mediaID'] . '/' . $speech['mediaID'] . '_h264_720_400_2000kb_baseline_de_2192.mp4';
			$dbspeech = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Speech"] . " WHERE SpeechOriginalMediaURL = ?s LIMIT 1", $originalMediaURL);

			if ($dbspeech) {
				echo $dbspeech["SpeechID"] . " (SpeechOriginalID: " . $dbspeech["SpeechOriginalID"] . ") skipped (already exists in DB) - file " . $file . "<br>";
				//ob_flush();
				//flush();
				continue;
			}


			//Check if MediaID exists.
			if (!$speech['mediaID']) {
				echo "!PROBLEM! in file " . $file . " Item '" . $speech["agendaItemTitle"] . ", " . $speech["agendaItemSecondTitle"] . "' by '" . $speech["speakerFirstName"] . " " . $speech["speakerLastName"] . " SKIPPED. No mediaID available.<br>";
				//ob_flush();
				//flush();
				continue;
			}


			// Party
			if ($speech["speakerParty"]) {
				$party = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Party"] . " WHERE PartyLabel = ?s LIMIT 1", $speech["speakerParty"]);
				if (!$party) {
					$dbPlatform->query("INSERT INTO " . $config["platform"]["sql"]["tbl"]["Party"] . " SET PartyLabel = ?s", $speech["speakerParty"]);
					$party = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Party"] . " WHERE PartyLabel = ?s LIMIT 1", $speech["speakerParty"]);
				}
			} else {
				$party = false;
			}


			//Speaker
			$speaker = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Speaker"] . " WHERE SpeakerOriginalID = ?s LIMIT 1", $speech["speakerID"]);
			if (!$speaker) {
				$dbPlatform->query("INSERT INTO " . $config["platform"]["sql"]["tbl"]["Speaker"] . " SET SpeakerSurname = ?s, SpeakerLastname = ?s, SpeakerDegree = ?s, SpeakerOriginalID = ?s, SpeakerPartyID = ?i", $speech["speakerFirstName"], $speech["speakerLastName"], $speech["speakerDegree"], $speech["speakerID"], ((is_array($party)) ? $party["PartyID"] : NULL));
				$speaker = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Speaker"] . " WHERE SpeakerOriginalID = ?s LIMIT 1", $speech["speakerID"]);
			}


			//ElectoralPeriod
			$electoralPeriod = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " WHERE ElectoralPeriodNumber = ?i LIMIT 1", $speech["electoralPeriod"]);
			if (!$electoralPeriod) {
				$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " SET ElectoralPeriodNumber = ?i", $speech["electoralPeriod"]);
				$electoralPeriod = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"] . " WHERE ElectoralPeriodNumber = ?i LIMIT 1", $speech["electoralPeriod"]);
			}


			//Session
			$sess = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1", $speech["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
			if (!$sess) {
				$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " SET SessionNumber = ?i, SessionElectoralPeriodID = ?i", $speech["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
				$sess = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Session"] . " WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1", $speech["sessionNumber"], $electoralPeriod["ElectoralPeriodID"]);
			}


			//AgendaItem
			//TODO: AgendaItemNumber is not in the original Data. Regex for "Tagesordnungspunkt $"?
			//$agendaItem = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemSessionID = ?i AND AgendaItemNumber = ?i AND AgendaItemTitle = ?s LIMIT 1",$sess["SessionID"],NULL,$speech["agendaItemTitle"]);
			//TODO: if agendaItemSecondTitle is available, check if a row for agendaItemTitle exist (if not, add it) and add agendaItemSecondTitle to its children

			$agendaItem = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1", $sess["SessionID"], $speech["agendaItemTitle"], $speech["agendaItemSecondTitle"]);
			if (!$agendaItem) {
				$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " SET AgendaItemSessionID = ?i, AgendaItemTitle = ?s, AgendaItemSubTitle = ?s", $sess["SessionID"], $speech["agendaItemTitle"], $speech["agendaItemSecondTitle"]);
				$agendaItem = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"] . " WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1", $sess["SessionID"], $speech["agendaItemTitle"], $speech["agendaItemSecondTitle"]);
			}


			$dbData = array(
				"SpeechOriginalID" => $speech["id"],
				"SpeechAligned" => $speech["aligned"],
				"SpeechDateStart" => $speech["date"],
				"SpeechDateEnd" => $speech["date"],
				"SpeechMediaDuration" => $speech["duration"],
				"SpeechOriginalMediaID" => $speech["mediaID"],
				"SpeechMediaURL" => $originalMediaURL,
				"SpeechOriginalMediaURL" => $originalMediaURL,
				"SpeechAgendaItemID" => $agendaItem["AgendaItemID"],
				"SpeechSpeakerID" => $speaker["SpeakerID"],
				"SpeechOriginalSpeakerID" => $speech["speakerID"],
				"SpeechOriginalSpeakerParty" => $speech["speakerParty"],
				"SpeechOriginalSpeakerRole" => $speech["speakerRole"],
				"SpeechOriginalSpeakerLastName" => $speech["speakerLastName"],
				"SpeechHash" => getUniqueCRC($config["parliament"][$parliament]["sql"]["tbl"]["Speech"], "SpeechHash", $originalMediaURL, $parliament . "-", $dbParliament)
			);
			$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["Speech"] . " SET ?u", $dbData);

			$dbspeech = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["Speech"] . " WHERE SpeechOriginalMediaURL = ?s LIMIT 1", $originalMediaURL);


			//Documents
			foreach ($speech["documents"] as $doc) {

				$tmpdoc = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["SpeechAnnotation"] . " WHERE SpeechAnnotationSpeechID = ?i AND SpeechAnnotationBody = ?s AND SpeechAnnotationType = ?s LIMIT 1", $dbspeech["SpeechID"], $doc, "document");
				if (!$tmpdoc) {
					$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["SpeechAnnotation"] . " SET SpeechAnnotationSpeechID = ?i, SpeechAnnotationBody = ?s, SpeechAnnotationType = ?s", $dbspeech["SpeechID"], $doc, "document");
				}

			}


			//Content
			$content = $dbParliament->getRow("SELECT * FROM " . $config["parliament"][$parliament]["sql"]["tbl"]["SpeechContent"] . " WHERE SpeechContentSpeechID = ?i AND SpeechContentType = ?s LIMIT 1", $dbspeech["SpeechID"], "transcript");
			if (!$content) {
				$dbParliament->query("INSERT INTO " . $config["parliament"][$parliament]["sql"]["tbl"]["SpeechContent"] . " SET SpeechContentSpeechID = ?i, SpeechContentType = ?s, SpeechContentBody = ?s", $dbspeech["SpeechID"], "transcript", $speech["content"]);
			}

			echo $dbspeech["SpeechID"] . " (SpeechOriginalID: " . $dbspeech["SpeechOriginalID"] . ") added - file " . $file . "<br>";
			//ob_flush();
			//flush();
		}

		rename($inputDir.$file, $doneDir.$file);
	}

}



?>