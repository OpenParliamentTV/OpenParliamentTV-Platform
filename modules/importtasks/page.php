<?php


require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
require_once(__DIR__."/../utilities/uniqueFreeString.php");


$inputDir = __DIR__."/input/";
$doneDir = __DIR__."/done/";

if (!is_dir(__DIR__."/done/")) {
	mkdir(__DIR__."/done/");
}

$inputFiles = scandir($inputDir);

$updateSessionDuration = false;
$updateSessions = array();

foreach ($inputFiles as $file) {

	if ((is_dir($inputDir.$file)) || (!is_file($inputDir.$file)) || (!preg_match('/.*\.json$/DA', $file))) {
		continue;
	}


	$json = json_decode(file_get_contents($inputDir.$file),true);


	if (!$db) {
		$opts = array(
			'host'	=> $config["sql"]["access"]["host"],
			'user'	=> $config["sql"]["access"]["user"],
			'pass'	=> $config["sql"]["access"]["passwd"],
			'db'	=> $config["sql"]["db"]
		);
		$db = new SafeMySQL($opts);
	}

	foreach ($json as $spKey=>$speech) {

		//Check if speech already exists based on original media url
		//$mediaSource = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$speech["_source"]["meta"]['mediaID'].'/'.$speech["_source"]["meta"]['mediaID'].'_h264_720_400_2000kb_baseline_de_2192.mp4';

		$originalMediaURL = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$speech['mediaID'].'/'.$speech['mediaID'].'_h264_720_400_2000kb_baseline_de_2192.mp4';
		$dbspeech = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Speech"]." WHERE SpeechOriginalMediaURL = ?s LIMIT 1",$originalMediaURL);

		if ($dbspeech) {
			echo $dbspeech["SpeechID"]." (SpeechOriginalID: ".$dbspeech["SpeechOriginalID"].") skipped (already exists in DB) - file ".$file."<br>";
			continue;
		}



		//Check if MediaID exists.
		if (!$speech['mediaID']) {
			echo "!PROBLEM! in file ".$file." Item '".$speech["agendaItemTitle"].", ".$speech["agendaItemSecondTitle"]."' by '".$speech["speakerFirstName"]." ".$speech["speakerLastName"]." SKIPPED. No mediaID available.<br>";
			continue;
		}




		// Party
		if ($speech["speakerParty"]) {
			$party = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Party"]." WHERE PartyLabel = ?s LIMIT 1",$speech["speakerParty"]);
			if (!$party) {
				//echo "noParty: ".$party." -> ".$speech["speakerParty"]."<br>";
				$db->query("INSERT INTO ".$config["sql"]["tbl"]["Party"]." SET PartyLabel = ?s",$speech["speakerParty"]);
				$party = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Party"]." WHERE PartyLabel = ?s LIMIT 1",$speech["speakerParty"]);
			}
		} else {
			$party = false;
		}





		//Speaker
		$speaker = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Speaker"]." WHERE SpeakerOriginalID = ?s LIMIT 1",$speech["speakerID"]);
		if (!$speaker) {
			$db->query("INSERT INTO ".$config["sql"]["tbl"]["Speaker"]." SET SpeakerSurname = ?s, SpeakerLastname = ?s, SpeakerDegree = ?s, SpeakerOriginalID = ?s, SpeakerPartyID = ?i",$speech["speakerFirstName"], $speech["speakerLastName"], $speech["speakerDegree"], $speech["speakerID"], ((is_array($party)) ? $party["PartyID"] : NULL));
			$speaker = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Speaker"]." WHERE SpeakerOriginalID = ?s LIMIT 1",$speech["speakerID"]);
		}






		//ElectoralPeriod
		$ep = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["ElectoralPeriod"]." WHERE ElectoralPeriodNumber = ?i LIMIT 1",$speech["electoralPeriod"]);
		if (!$ep) {
			$db->query("INSERT INTO ".$config["sql"]["tbl"]["ElectoralPeriod"]." SET ElectoralPeriodNumber = ?i",$speech["electoralPeriod"]);
			$ep = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["ElectoralPeriod"]." WHERE ElectoralPeriodNumber = ?i LIMIT 1",$speech["electoralPeriod"]);
		}






		//Session
		$sess = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Session"]." WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1",$speech["sessionNumber"],$ep["ElectoralPeriodID"]);
		if (!$sess) {
			$db->query("INSERT INTO ".$config["sql"]["tbl"]["Session"]." SET SessionNumber = ?i, SessionElectoralPeriodID = ?i",$speech["sessionNumber"],$ep["ElectoralPeriodID"]);
			$sess = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Session"]." WHERE SessionNumber = ?i AND SessionElectoralPeriodID = ?i LIMIT 1",$speech["sessionNumber"],$ep["ElectoralPeriodID"]);
		}
		$updateSessions[] = $sess;






		//AgendaItem
		//TODO: AgendaItemNumber is not in the original Data. Regex for "Tagesordnungspunkt $"?
		//$agendaItem = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemSessionID = ?i AND AgendaItemNumber = ?i AND AgendaItemTitle = ?s LIMIT 1",$sess["SessionID"],NULL,$speech["agendaItemTitle"]);
		//TODO: if agendaItemSecondTitle is available, check if a row for agendaItemTitle exist (if not, add it) and add agendaItemSecondTitle to its children

		$agendaItem = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1",$sess["SessionID"],$speech["agendaItemTitle"],$speech["agendaItemSecondTitle"]);
		if (!$agendaItem) {
			$db->query("INSERT INTO ".$config["sql"]["tbl"]["AgendaItem"]." SET AgendaItemSessionID = ?i, AgendaItemTitle = ?s, AgendaItemSubTitle = ?s",$sess["SessionID"],$speech["agendaItemTitle"],$speech["agendaItemSecondTitle"]);
			$agendaItem = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["AgendaItem"]." WHERE AgendaItemSessionID = ?i AND AgendaItemTitle = ?s AND AgendaItemSubTitle = ?s LIMIT 1",$sess["SessionID"],$speech["agendaItemTitle"],$speech["agendaItemSecondTitle"]);
		}


		$dbData = array(
			"SpeechOriginalID"=>$speech["id"],
			"SpeechAligned"=>$speech["aligned"],
			"SpeechDateStart"=>$speech["date"],
			"SpeechDateEnd"=>$speech["date"],
			"SpeechMediaDuration"=>$speech["duration"],
			"SpeechOriginalMediaID"=>$speech["mediaID"],
			"SpeechMediaURL"=>$originalMediaURL,
			"SpeechOriginalMediaURL"=>$originalMediaURL,
			"SpeechAgendaItemID"=>$agendaItem["AgendaItemID"],
			"SpeechSpeakerID"=>$speaker["SpeakerID"],
			"SpeechOriginalSpeakerID"=>$speech["speakerID"],
			"SpeechOriginalSpeakerParty"=>$speech["speakerParty"],
			"SpeechOriginalSpeakerRole"=>$speech["speakerRole"],
			"SpeechOriginalSpeakerLastName"=>$speech["speakerLastName"],
			"SpeechHash"=>getUniqueFreeString($config["sql"]["tbl"]["Speech"],"SpeechHash")
		);
		$db->query("INSERT INTO ".$config["sql"]["tbl"]["Speech"]." SET ?u",$dbData);

		$dbspeech = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["Speech"]." WHERE SpeechOriginalMediaURL = ?s LIMIT 1",$originalMediaURL);






		//Documents
		foreach($speech["documents"] as $doc) {

			$tmpdoc = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["SpeechAnnotation"]." WHERE SpeechAnnotationSpeechID = ?i AND SpeechAnnotationBody = ?s AND SpeechAnnotationType = ?s LIMIT 1",$dbspeech["SpeechID"],$doc,"document");
			if (!$tmpdoc) {
				$db->query("INSERT INTO ".$config["sql"]["tbl"]["SpeechAnnotation"]." SET SpeechAnnotationSpeechID = ?i, SpeechAnnotationBody = ?s, SpeechAnnotationType = ?s",$dbspeech["SpeechID"],$doc,"document");
			}

		}






		//Content
		$content = $db->getRow("SELECT * FROM ".$config["sql"]["tbl"]["SpeechContent"]." WHERE SpeechContentSpeechID = ?i AND SpeechContentType = ?s LIMIT 1",$dbspeech["SpeechID"],"transcript");
		if (!$content) {
			$db->query("INSERT INTO ".$config["sql"]["tbl"]["SpeechContent"]." SET SpeechContentSpeechID = ?i, SpeechContentType = ?s, SpeechContentBody = ?s",$dbspeech["SpeechID"],"transcript",$speech["content"]);
		}

		echo $dbspeech["SpeechID"]." (SpeechOriginalID: ".$dbspeech["SpeechOriginalID"].") added - file ".$file."<br>";



	}


	rename($inputDir.$file, $doneDir.$file);


	/*
	echo "<pre>";
	print_r(getUniqueFreeString($config["sql"]["tbl"]["Speech"],"SpeechHash"));
	echo "</pre>";
	*/

}

//TODO: Collect all affected Sessions and set its start and end time according to all linked speeches


?>