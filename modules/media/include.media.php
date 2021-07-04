<?php
	
	require_once(__DIR__."/../../api/v1/api.php");
	
	$apiInput = $_REQUEST;
	$apiInput["action"] = "search";
	$apiInput["a"] = "search";
	$apiInput["itemType"] = "media";
	$apiResult = apiV1($apiInput);

	$autoplayResults = boolval($_REQUEST['playresults']);

	foreach ($apiResult["data"] as $index=>$result_item) {
	    if ($result_item["id"] == $_REQUEST["id"]) {
	        $speech = $result_item;
	        $speechIndex = $index;
	        break;
	    }
	}

	if (isset($speech["_highlight"])) {
		$textContentsHTML = $speech["_highlight"]["attributes.textContents.textHTML"][0];
	} else {
		$textContentsHTML = textObjectToHTMLString(json_encode($speech["attributes"]['textContents'][0]), $speech["attributes"]['videoFileURI'], $speech["id"]);
	}

	$prevResult = ($speechIndex > 0) ? array_values(array_slice($apiResult["data"], $speechIndex-1, 1))[0] : null;
	$nextResult = ($speechIndex < count($apiResult["data"])) ? array_values(array_slice($apiResult["data"], $speechIndex+1, 1))[0] : null;

	$prevSpeech = json_decode(getPrevDocument($speech["attributes"]["timestamp"]), true);
	$nextSpeech = json_decode(getNextDocument($speech["attributes"]["timestamp"]), true);

	$formattedDate = date("d.m.Y", strtotime($speech["attributes"]["dateStart"]));

	$speechTitleShort = 'Speech '.$speech["relationships"]["people"]['data'][0]['attributes']['label'].', '.$speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'].'  ('.$formattedDate.')';

	$speechTitle = '<div class="speechMeta">'.$formattedDate.' | '.$speech["relationships"]["electoralPeriod"]['data']['attributes']['number'].'. Electoral Period | Session '.$speech["relationships"]["session"]['data']['attributes']['number'].' | '.$speech["relationships"]["agendaItem"]["data"]['attributes']["officialTitle"].'</div>'.$speech["relationships"]["people"]['data'][0]['attributes']['label'].' <span class="partyIndicator" data-party="'.$speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'].'">'.$speech["relationships"]["people"]['data'][0]['attributes']['party']['labelAlternative'].'</span><div class=\"speechTOPs\">'.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].'</div>';

?>