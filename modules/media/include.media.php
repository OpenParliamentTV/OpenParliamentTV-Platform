<?php
	require_once("functions.php");
	require_once(__DIR__."/../utilities/functions.entities.php");
	require_once(__DIR__."/../../api/v1/api.php");

	if (!function_exists("L")) {
		require_once(__DIR__."/../../i18n.class.php");
		$i18n = new i18n(__DIR__.'/../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../langcache/', 'de');
		$i18n->init();
	}

	$apiInput = $_REQUEST;
	unset($apiInput["page"]);
	unset($apiInput["t"]);
	unset($apiInput["f"]);
	unset($apiInput["theme"]);
	$apiInput["action"] = "search";
	$apiInput["a"] = "search";
	$apiInput["itemType"] = "media";
	$apiResult = apiV1($apiInput);

	/*
	echo '<pre>';
	print_r($apiResult);
	echo '</pre>';
	*/

	$autoplayResults = boolval($_REQUEST['playresults']);

	$speech = $apiResult["data"][0];

	$mainSpeaker = getMainSpeakerFromPeopleArray($speech["relationships"]["people"]['data']);
	$mainFaction = getMainFactionFromOrganisationsArray($speech["relationships"]["organisations"]['data']);

	if (isset($speech["attributes"]['textContents'][0])) {
		if (isset($speech["_highlight"])) {
			$textContentsHTML = $speech["_highlight"]["attributes.textContents.textHTML"][0];
		} else {
			$textContentsHTML = textObjectToHTMLString(json_encode($speech["attributes"]['textContents'][0]), $speech["attributes"]['videoFileURI'], $speech["id"]);
		}
		$textContentsHTML = str_replace("\n","\\n",$textContentsHTML);
	}

	$formattedDate = date("d.m.Y", strtotime($speech["attributes"]["dateStart"]));

	$speechTitleShort = $mainSpeaker['attributes']['label'].', '.$mainFaction['attributes']['labelAlternative'].' | '.$formattedDate.' | '.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"];

	$speechTitle = '<div class="speechMeta">'.$formattedDate.' | '.$speech["relationships"]["electoralPeriod"]['data']['attributes']['number'].'. Electoral Period | Session '.$speech["relationships"]["session"]['data']['attributes']['number'].' | '.$speech["relationships"]["agendaItem"]["data"]['attributes']["officialTitle"].'</div>'.$mainSpeaker['attributes']['label'].' <span class="partyIndicator" data-party="'.$mainFaction['id'].'">'.$mainFaction['attributes']['labelAlternative'].'</span><div class=\"speechTOPs\">'.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].'</div>';

?>