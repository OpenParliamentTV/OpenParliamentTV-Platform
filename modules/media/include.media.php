<?php
	require_once("functions.php");
	require_once(__DIR__."/../utilities/functions.entities.php");
	require_once(__DIR__."/../../api/v1/api.php");

    require_once(__DIR__."/../../modules/utilities/language.php");

	$apiInput = $_REQUEST;
	unset($apiInput["page"]);
	unset($apiInput["t"]);
	unset($apiInput["f"]);
	unset($apiInput["theme"]);
	$apiInput["action"] = "search";
	$apiInput["a"] = "search";
	$apiInput["itemType"] = "media";
	//print_r($apiInput);
	$apiResult = apiV1($apiInput);

	if (!$apiResult || $apiResult["meta"]["results"]["total"] == 0) {

        $emptyResult = 1;

    } else {


        $autoplayResults = boolval($_REQUEST['playresults']);

        $speech = $apiResult["data"][0];

        $mainSpeaker = getMainSpeakerFromPeopleArray($speech["annotations"]["data"],$speech["relationships"]["people"]['data']);
        $mainFaction = getMainFactionFromOrganisationsArray($speech["annotations"]['data'], $speech["relationships"]["organisations"]['data']);

        $lastTextContents = end($speech["attributes"]['textContents']);
        if ($lastTextContents) {
            if (isset($speech["_highlight"])) {
                $textContentsHTML = $speech["_highlight"]["attributes.textContents.textHTML"][0];
            } else {
                $textContentsHTML = textObjectToHTMLString(json_encode($speech["attributes"]['textContents'][(count($speech["attributes"]['textContents'])-1)]), $speech["attributes"]['videoFileURI'], $speech["id"]);
            }
            $textContentsHTML = str_replace("\n", "\\n", $textContentsHTML);
        }

        $formattedDate = date("d.m.Y", strtotime($speech["attributes"]["dateStart"]));

        $speechTitleShort = $mainSpeaker['attributes']['label'] . ', ' . $mainFaction['attributes']['label'] . ' | ' . $formattedDate . ' | ' . $speech["relationships"]["agendaItem"]["data"]['attributes']["title"];

        $speechTitle = '<div class="speechMeta">' . $formattedDate . ' | ' . $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] . '. Electoral Period | Session ' . $speech["relationships"]["session"]['data']['attributes']['number'] . ' | ' . $speech["relationships"]["agendaItem"]["data"]['attributes']["officialTitle"] . '</div>' . $mainSpeaker['attributes']['label'] . ' <span class="partyIndicator" data-party="' . $mainFaction['id'] . '">' . $mainFaction['attributes']['label'] . '</span><div class=\"speechTOPs\">' . $speech["relationships"]["agendaItem"]["data"]['attributes']["title"] . '</div>';
    }

?>