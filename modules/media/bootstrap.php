<?php
	require_once(__DIR__."/media.php");
	require_once(__DIR__."/../utilities/entities.php");
	require_once(__DIR__."/../entities/media.php");
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

        // Playing through a playlist: the stored segments for *this* speech take
        // the place of the search finds, in the same shape, so snippet arrows,
        // FrameTrail code snippets and autoplay all behave exactly as they do for
        // a search. An item stored without segments yields no finds at all, which
        // is already the player's "play the whole speech" case.
        //
        // data-end carries the +0.3 that player.php subtracts again, so a segment
        // captured from a search hit plays back over the identical range.
        if (!empty($_REQUEST['playlist'])) {
            require_once(__DIR__."/../playlist/storage.php");

            $playlistResult = apiV1([
                "action" => "playlist",
                "itemType" => "resolve",
                "token" => $_REQUEST['playlist'],
            ]);

            $speech['_finds'] = [];
            if (($playlistResult["meta"]["requestStatus"] ?? '') === 'success') {
                foreach ($playlistResult["data"] as $playlistItem) {
                    if ($playlistItem["id"] !== $speech["id"]) {
                        continue;
                    }
                    foreach (($playlistItem["segments"] ?? []) as $segment) {
                        $speech['_finds'][] = [
                            "data-start" => $segment["start"],
                            "data-end" => $segment["end"] + 0.3,
                        ];
                    }
                    break;
                }
            }
        }

        $mainSpeaker = mediaMainSpeaker($speech["annotations"]["data"] ?? [], $speech["relationships"]["people"]['data'] ?? []);
        $mainFaction = mediaMainFaction($speech["annotations"]['data'] ?? [], $speech["relationships"]["organisations"]['data'] ?? []);
        $mainSpeakerRole = mediaMainSpeakerRole($speech["annotations"]["data"] ?? []);

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
            // Keep raw HTML (real newlines) for server-side use / JSON-LD transcript,
            // before escaping newlines for JS embedding.
            $textContentsHTMLRaw = $textContentsHTML;
            $textContentsHTML = str_replace("\n", "\\n", $textContentsHTML);
        } else {
            $textContentsHTML = '';
            $textContentsHTMLRaw = '';
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