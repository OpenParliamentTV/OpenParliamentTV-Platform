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
        $mainSpeakerRole = getRoleFromMainSpeakerAnnotation($speech["annotations"]["data"] ?? []);

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

        $speakerLabel = $mainSpeaker ? $mainSpeaker['attributes']['label'] : '';
        $factionLabel = $mainFaction ? $mainFaction['attributes']['label'] : '';
        $roleLabel = $mainSpeakerRole ? translateContextValue($mainSpeakerRole) : '';
        
        // For short title: show faction if available, otherwise show role
        $affiliationLabel = $factionLabel ? $factionLabel : $roleLabel;
        
        $speechTitleShort = $speakerLabel . ($speakerLabel && $affiliationLabel ? ', ' : '') . $affiliationLabel . ($speakerLabel || $affiliationLabel ? ' | ' : '') . $formattedDate . ' | ' . (isset($speech["relationships"]["agendaItem"]["data"]['attributes']["title"]) ? $speech["relationships"]["agendaItem"]["data"]['attributes']["title"] : '');
    }

?>