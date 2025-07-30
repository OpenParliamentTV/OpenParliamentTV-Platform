<?php
	require_once("functions.php");
	require_once(__DIR__."/../utilities/functions.entities.php");
	require_once(__DIR__."/../../api/v1/api.php");

    require_once(__DIR__."/../i18n/language.php");

	$apiInput = $_REQUEST;
	// Remove page parameter to ensure we get the specific media item regardless of pagination
	unset($apiInput["page"]);
	unset($apiInput["t"]);
	unset($apiInput["f"]);
	unset($apiInput["theme"]);
	$apiInput["action"] = "search";
	$apiInput["a"] = "search";
	$apiInput["itemType"] = "media";
	
	$apiResult = apiV1($apiInput);

	$emptyResult = 0;
	if (!$apiResult || $apiResult["meta"]["results"]["total"] == 0) {
        $emptyResult = 1;
    } else {
        $autoplayResults = boolval($_REQUEST['playresults'] ?? false);

        $speech = $apiResult["data"][0];

        $mainSpeaker = getMainSpeakerFromPeopleArray($speech["annotations"]["data"] ?? [], $speech["relationships"]["people"]['data'] ?? []);
        $mainFaction = getMainFactionFromOrganisationsArray($speech["annotations"]['data'] ?? [], $speech["relationships"]["organisations"]['data'] ?? []);

        $textContents = $speech["attributes"]['textContents'] ?? [];
        $lastTextContents = end($textContents);
        if ($lastTextContents) {
            if (isset($speech["_highlight"]) && isset($speech["_highlight"]["attributes.textContents.textHTML"])) {
                $textContentsHTML = $speech["_highlight"]["attributes.textContents.textHTML"][0];
            } else {
                // The API returns textContents with structure: {id, type, textBody}
                // But textObjectToHTMLString expects just the textBody structure
                if (isset($lastTextContents['textBody'])) {
                    $textBodyData = array('textBody' => $lastTextContents['textBody']);
                    $jsonToPass = json_encode($textBodyData);
                    $textContentsHTML = textObjectToHTMLString($jsonToPass, $speech["attributes"]['videoFileURI'], $speech["id"]);
                } else {
                    $textContentsHTML = '';
                }
            }
            $textContentsHTML = str_replace("\n", "\\n", $textContentsHTML);
        } else {
            $textContentsHTML = '';
        }

        $formattedDate = date("d.m.Y", strtotime($speech["attributes"]["dateStart"]));

        $speechTitleShort = (isset($mainSpeaker['attributes']['label']) ? $mainSpeaker['attributes']['label'] : 'Unknown') . ', ' . (isset($mainFaction['attributes']['label']) ? $mainFaction['attributes']['label'] : 'Unknown') . ' | ' . $formattedDate . ' | ' . (isset($speech["relationships"]["agendaItem"]["data"]['attributes']["title"]) ? $speech["relationships"]["agendaItem"]["data"]['attributes']["title"] : 'Unknown');

        $speechTitle = '<div class="speechMeta">' . $formattedDate . ' | ' . (isset($speech["relationships"]["electoralPeriod"]['data']['attributes']['number']) ? $speech["relationships"]["electoralPeriod"]['data']['attributes']['number'] : 'Unknown') . '. Electoral Period | Session ' . (isset($speech["relationships"]["session"]['data']['attributes']['number']) ? $speech["relationships"]["session"]['data']['attributes']['number'] : 'Unknown') . ' | ' . (isset($speech["relationships"]["agendaItem"]["data"]['attributes']["officialTitle"]) ? $speech["relationships"]["agendaItem"]["data"]['attributes']["officialTitle"] : 'Unknown') . '</div>' . (isset($mainSpeaker['attributes']['label']) ? $mainSpeaker['attributes']['label'] : 'Unknown') . ' <span class="partyIndicator" data-party="' . (isset($mainFaction['id']) ? $mainFaction['id'] : '') . '">' . (isset($mainFaction['attributes']['label']) ? $mainFaction['attributes']['label'] : 'Unknown') . '</span><div class=\"speechTOPs\">' . (isset($speech["relationships"]["agendaItem"]["data"]['attributes']["title"]) ? $speech["relationships"]["agendaItem"]["data"]['attributes']["title"] : 'Unknown') . '</div>';
    }

?>