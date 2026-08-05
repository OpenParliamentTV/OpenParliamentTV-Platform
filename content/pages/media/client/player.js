// The queue is whatever the player is currently playing through: the current
// search's results today, a stored playlist later. resultList holds the ordered
// items ({ id } at minimum; the queue panel fills in display attributes as it
// scrolls), and everything downstream — prev/next, autoplay, the panel — reads
// only this shape, so swapping the data source swaps nothing else.
var playerData,
	currentMediaID,
	resultListAjax = null,
	resultList = null,
	queueSource = 'virtual',
	queuePlaylist = null,
	queueTotal = 0,
	currentQueueIndex = null,
	prevResultURL = null,
	nextResultURL = null,
	prevResultID = null,
	nextResultID = null;

$(document).ready( function() {

	window.onpopstate = function(event) {
		var regExResult = /media\/([a-zA-Z0-9_-]+)/.exec(location.pathname);
		if (regExResult && regExResult.length > 1) {
			var searchParams = removeQuoteParamsFromURL(window.location.search.toString());
			updateContents(regExResult[1] + searchParams);
		}
	}

	updateAutoplayState();

	updatePlayer();
	$('#quoteDisplayModal, #nerModal').appendTo('body');

	getQueue(function(queue) {
		resultList = queue.items;
		queueSource = queue.source;
		queuePlaylist = queue.playlist;
		queueTotal = queue.total;

		updatePrevNext();
		updateQueueButtons();

		document.dispatchEvent(new CustomEvent('optvQueueReady', { detail: queue }));
	});

	$('#toggleAutoplayResults').click(function() {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			autoplayResults = false;
			updateQuery();
		} else {
			$(this).addClass('active');
			autoplayResults = true;
			updateQuery();
			if (OPTV_Player.codeSnippets.length > 1) {
				goToNextResultSnippet();
			}
		}
	});

	$('#prevResultSnippetButton').click(goToPrevResultSnippet);
	$('#nextResultSnippetButton').click(goToNextResultSnippet);

	// Skip a whole video, bypassing the snippet-by-snippet walk the single
	// arrows do.
	$('#prevResultVideoButton').click(function() {
		if (prevResultID) { updateQuery(prevResultID); }
	});
	$('#nextResultVideoButton').click(function() {
		if (nextResultID) { updateQuery(nextResultID); }
	});

});

/**
 * Fetch the queue the player is playing through.
 *
 * Today that is always the current search's result set, carried over from
 * location.search exactly as the search page built it. fields=id keeps the
 * response to an ordered id list — the queue panel fetches display metadata for
 * the rows it actually shows.
 */
function getQueue(queueCallback) {
	if(resultListAjax && resultListAjax.readyState != 4){
		resultListAjax.abort();
	}
	var resultURLParts = window.location.search.split('?'),
		ajaxURLParams = '';
	if (resultURLParts[1]) {
		ajaxURLParams += '&' + resultURLParts[1]; // Ensure leading & for subsequent params
	}

	var apiUrl = config.dir.root + "/api/v1/search/media?fields=id&getAllResults=1" + ajaxURLParams;

	function emptyQueue() {
		return { source: 'virtual', playlist: null, items: [], total: 0 };
	}

	resultListAjax = $.ajax({
		method: "GET",
		url: apiUrl
	}).done(function(apiResponse) {
		if (apiResponse && apiResponse.data) {
			queueCallback({
				source: 'virtual',
				playlist: null,
				items: apiResponse.data,
				total: (apiResponse.meta && apiResponse.meta.results) ? apiResponse.meta.results.total : apiResponse.data.length
			});
		} else {
			console.error("Error fetching or parsing media ID list from API:", apiResponse);
			queueCallback(emptyQueue());
		}
	}).fail(function(err) {
		console.error("Failed to get media ID list:", err);
		queueCallback(emptyQueue());
	});
}

/**
 * Enable/disable the queue navigation buttons for the current position.
 *
 * The snippet arrows stay enabled whenever an adjacent result exists, because
 * they fall through to it once they run out of snippets; the video arrows are
 * purely positional.
 */
function updateQueueButtons() {
	$('#prevResultSnippetButton, #prevResultVideoButton').attr("disabled", prevResultID ? false : true);
	$('#nextResultSnippetButton, #nextResultVideoButton').attr("disabled", nextResultID ? false : true);
}

function updateContents(resultURL) {
	//$('.loadingIndicator').show();
	
	$('#content').removeClass('ready');
	$('#quoteDisplayModal').remove();

	if(updateAjax && updateAjax.readyState != 4){
		updateAjax.abort();
	}
	var resultURLParts = resultURL.split('?'),
		ajaxURLParams = 'a=media&id='+ resultURLParts[0];
	if (resultURLParts[1]) {
		ajaxURLParams += '&'+ resultURLParts[1];
	}
	updateAjax = $.ajax({
		method: "POST",
		url: "../content/pages/media/components/player.php?"+ ajaxURLParams
	}).done(function(data) {
		$('#content').html($(data));
		updatePlayer();
		$('#quoteDisplayModal').appendTo('body');
		updateAutoplayState();
		$('.loadingIndicator').hide();
	}).fail(function(err) {
		console.log(err);
	});
}

function updatePrevNext() {
	if (!resultList || !currentMediaID) { return; }

	var currentMediaIndex;
	for (let index = 0; index < resultList.length; index++) {
		if (resultList[index].id === currentMediaID) {
			currentMediaIndex = index;
			break;
		}
	}

	var prevResultItem = resultList[currentMediaIndex-1],
		nextResultItem = resultList[currentMediaIndex+1];

	prevResultID = (prevResultItem) ? prevResultItem.id : null;
	nextResultID = (nextResultItem) ? nextResultItem.id : null;

	// undefined when the current speech isn't part of the queue at all (e.g. a
	// media page opened without a search) — the panel reads this to decide
	// whether it has a position to show.
	currentQueueIndex = (currentMediaIndex === undefined) ? null : currentMediaIndex;

	var searchParams = removeQuoteParamsFromURL(window.location.search.toString());

	prevResultURL = (prevResultID) ? 'media/'+ prevResultID + searchParams : null;
	nextResultURL = (nextResultID) ? 'media/'+ nextResultID + searchParams : null;

	document.dispatchEvent(new CustomEvent('optvQueueItemChanged', {
		detail: { id: currentMediaID, index: currentQueueIndex }
	}));
}

function updatePlayer() {

	//console.log(playerData);
	$('#content').removeClass('ready');

	if (window.OPTV_Player && typeof window.OPTV_Player.destroy == "function") {
		window.OPTV_Player.destroy();
		$('.mediaContainer > .d-flex').after('<div id="OPTV_Player" class="frametrail-body" data-frametrail-theme="openparliamenttv"></div>');
	}

	if (resultList) {
		updatePrevNext();
		updateQueueButtons();
	}

	var speechCodeSnippets = [];

	for (var i = 0; i < playerData.finds.length; i++) {
		if (i == playerData.finds.length-1) {
			speechCodeSnippets.push({
				"@context": [
					"http://www.w3.org/ns/anno.jsonld",
					{
						"frametrail": "http://frametrail.org/ns/"
					}
				],
				"creator": {
					"nickname": "demo",
					"type": "Person",
					"id": "1"
				},
				"created": "Wed Mar 14 2018 11:33:19 GMT+0100 (CET)",
				"type": "Annotation",
				"frametrail:type": "CodeSnippet",
				"target": {
					"type": "Video",
					"source": playerData.mediaSource,
					"selector": {
						"conformsTo": "http://www.w3.org/TR/media-frags/",
						"type": "FragmentSelector",
						"value": "t="+ playerData.finds[i].end +""
					}
				},
				"body": {
					"type": "TextualBody",
					"frametrail:type": "codesnippet",
					"format": "text/javascript",
					"value": "if(autoplayResults){OPTV_Player.pause();if(nextResultURL){updateQuery(nextResultID);}}",
					"frametrail:name": "Custom Code Snippet",
					"frametrail:thumb": null,
					"frametrail:resourceId": null
				},
				"frametrail:attributes": {}
			});
		} else {
			speechCodeSnippets.push({
				"@context": [
					"http://www.w3.org/ns/anno.jsonld",
					{
						"frametrail": "http://frametrail.org/ns/"
					}
				],
				"creator": {
					"nickname": "demo",
					"type": "Person",
					"id": "1"
				},
				"created": "Wed Mar 14 2018 11:33:19 GMT+0100 (CET)",
				"type": "Annotation",
				"frametrail:type": "CodeSnippet",
				"target": {
					"type": "Video",
					"source": playerData.mediaSource,
					"selector": {
						"conformsTo": "http://www.w3.org/TR/media-frags/",
						"type": "FragmentSelector",
						"value": "t="+ playerData.finds[i].end +""
					}
				},
				"body": {
					"type": "TextualBody",
					"frametrail:type": "codesnippet",
					"format": "text/javascript",
					"value": "if(autoplayResults){OPTV_Player.pause();OPTV_Player.currentTime="+ playerData.finds[i+1].start +";OPTV_Player.play();}",
					"frametrail:name": "Custom Code Snippet",
					"frametrail:thumb": null,
					"frametrail:resourceId": null
				},
				"frametrail:attributes": {}
			});
			
		}
		
	}

	// Add video source attribution
	/*
	speechCodeSnippets.push({
		"@context": [
			"http://www.w3.org/ns/anno.jsonld",
			{
				"frametrail": "http://frametrail.org/ns/"
			}
		],
		"creator": {
			"nickname": "demo",
			"type": "Person",
			"id": "1"
		},
		"created": "Wed Mar 14 2018 11:33:19 GMT+0100 (CET)",
		"type": "Annotation",
		"frametrail:type": "Overlay",
		"target": {
			"type": "Video",
			"source": playerData.mediaSource,
			"selector": {
				"conformsTo": "http://www.w3.org/TR/media-frags/",
				"type": "FragmentSelector",
				"value": "t=0,1000&xywh=percent:40,80,60,20"
			}
		},
		"body": {
            "type": "TextualBody",
            "frametrail:type": "text",
            "format": "text/html",
            "value": "",
            "frametrail:name": "",
            "frametrail:attributes": {
                "text": "&lt;div class=&quot;sourceAttribution&quot;&gt;&lt;span&gt;Quelle: Deutscher Bundestag / &lt;a href=&quot;https://dbtv.de/12345&quot; target=&quot;_blank&quot;&gt;https://dbtv.de/12345&lt;/a&gt;&lt;/span&gt;&lt;/div&gt;"
            }
        },
		"frametrail:attributes": {}
	});
	*/

	var areaBottomContents = [];

	if (config.display.ner) {
		areaBottomContents = [
			{
				"type": "TimedContent",
				"contentSize": "small",
				"name": localizedLabels.automaticallyDetected + " <a class='betaWarning' data-bs-toggle='modal' data-bs-target='#nerModal' href='#'><span class='icon-attention me-1'></span><u>beta</u></a>",
				"description": "",
				"cssClass": "",
				"collectionFilter": {
					"tags": [
						"otherOrganisation", 
						"government", 
						"ngo",
						"company",
						"person", 
						"legalDocument",
						"term"
					],
					"types": [],
					"users": [],
					"text": ""
				},
				"onClickContentItem": "",
				"html": "",
				"transcriptSource": ""
			}
		];
	}
	
	window.OPTV_Player = FrameTrail.init({
		target:             '#OPTV_Player',
		fullscreenTarget:   '#content',
		contentTargets:     {},
		contents:           [{
			hypervideo: {
				"meta": {
					"name": playerData.title,
					"thumb": "",
					"creator": "Open Parliament TV Platform",
					"creatorId": "0",
					"created": 1519713627469,
					"lastchanged": 1521025330334
				},
				"config": {
					"slidingMode": "adjust",
					"slidingTrigger": "key",
					"autohideControls": false,
					"captionsVisible": false,
					"clipTimeVisible": false,
					"hidden": false,
					"layoutArea": {
						"areaTop": [],
						"areaBottom": areaBottomContents,
						"areaLeft": [
							{
								"type": "CustomHTML",
								"contentSize": "large",
								"name": "<span class=\"icon-doc-text-1\"></span>",
								"description": "",
								"cssClass": "",
								"collectionFilter": {
									"tags": [],
									"types": [],
									"users": [],
									"text": ""
								},
								"onClickContentItem": "",
								"html": playerData.transcriptHTML,
								"transcriptSource": ""
							}
						],
						"areaRight": []
					}
				},
				"clips": [
					{
						"resourceId": null,
						"src": playerData.mediaSource,
						"duration": 0,
						"start": 0,
						"end": 0,
						"in": 0,
						"out": 0
					}
				],
				"globalEvents": {
					"onReady": "",
					"onPlay": "",
					"onPause": "",
					"onEnded": ""
				},
				"customCSS": "",
				"contents": speechCodeSnippets,
				"subtitles": {}
			},
			annotations: ((playerData.annotations.length == 0) ? false : playerData.annotations)
			//annotations: ["<?=$annotationSource?>"]
		}],
		startID: '0',
		resources: [{
			label: "Choose Resources",
			data: {},
			type: "frametrail"
		}],
		tagdefinitions: {},
		config: {
			"updateServiceURL": null,
			"autoUpdate": false,
			"defaultUserRole": "user",
			"captureUserTraces": false,
			"userTracesStartAction": "",
			"userTracesEndAction": "",
			"userNeedsConfirmation": true,
			"alwaysForceLogin": false,
			"allowCollaboration": false,
			"allowUploads": false,
			"theme": "openparliamenttv",
			"defaultHypervideoHidden": false,
			"userColorCollection": [
				"597081",
				"339966",
				"16a09c",
				"cd4436",
				"0073a6",
				"8b5180",
				"999933",
				"CC3399",
				"7f8c8d",
				"ae764d",
				"cf910d",
				"b85e02"
			],
			"videoFit": "contain",
			"defaultLanguage": document.documentElement.lang
		},
		users: {}
	}, 'PlayerLauncher');

	OPTV_Player.on('ready', function() {

		$('#videoAttribution').appendTo('.frametrail-body .hypervideo').show();

		$('#content').addClass('ready');
		processQuery();

		// Re-sync fullscreen state: when switching results the browser stays
		// fullscreen on '#content', but this freshly re-init'd FrameTrail instance
		// doesn't know it. Restore the button/background state on the new player.
		if (document.fullscreenElement) {
			$('#OPTV_Player').addClass('inFullscreen');
			$('#OPTV_Player .fullscreenButton').addClass('active');
		}
		/*
		var downloadOptions = $('<div class="downloadOptions">'
							+       '<div class="icon icon-download"></div>'
							+   '</div>');

		var prevSpeaker = <?= ($prevSpeech) ? json_encode($prevSpeech["_source"]["meta"]['speakerDegree'].' '.$prevSpeech["_source"]["meta"]['speakerFirstName'].' '.$prevSpeech["_source"]["meta"]['speakerLastName'].' <span class="partyIndicator" data-party="'.htmlspecialchars($prevSpeech["_source"]["meta"]['speakerParty'], ENT_QUOTES).'">', JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) : 'null' ?>;

		var nextSpeaker = <?= ($nextSpeech) ? json_encode($nextSpeech["_source"]["meta"]['speakerDegree'].' '.$nextSpeech["_source"]["meta"]['speakerFirstName'].' '.$nextSpeech["_source"]["meta"]['speakerLastName'].' <span class="partyIndicator" data-party="'.htmlspecialchars($nextSpeech["_source"]["meta"]['speakerParty'], ENT_QUOTES).'">', JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) : 'null' ?>;
		
		var navigationOptions = $('<div class="navigationOptions"></div>');
		var prevSpeakerURL = '?id=' + <?= json_encode($prevSpeech["_source"]["meta"]['id'] ?? '') ?>.replace(/\s/g, '+');
		if (prevSpeaker) {
			navigationOptions.append('<a href='+ prevSpeakerURL +' class="prevSpeech"><b>Vorheriger Redebeitrag</b><br>'+ prevSpeaker +'</a>');
		}
		var nextSpeakerURL = '?id=<?= $nextSpeech["_source"]["meta"]['id'] ?>'.replace(/\s/g, '+');
		if (nextSpeaker) {
			navigationOptions.append('<a href='+ nextSpeakerURL +' class="nextSpeech"><b>Nächster Redebeitrag</b><br>'+ nextSpeaker +'</a>');
		}
							
		var playerOptions = $('<div class="playerOptions"></div>');
		playerOptions.append(navigationOptions, downloadOptions);

		$('.frametrail-body .titlebar').append(playerOptions);
		*/
		window.setTimeout(function() {
			
			var queryTime = getQueryVariable('t');
			if (queryTime) {
				if (queryTime.indexOf(',') !== -1) {
					queryTimeParts = queryTime.split(',');
					OPTV_Player.currentTime = queryTimeParts[0];
				} else {
					OPTV_Player.currentTime = queryTime;
				}
				
			}

			if (autoplayResults && playerData.finds.length > 0) {
				OPTV_Player.currentTime = playerData.finds[0].start;
			}
			/*
			<?php
			if ($speech['finds']) {
			?>
				if(autoplayResults) {
					OPTV_Player.currentTime = <?= $speech['finds'][0]['data-start'] ?>;
				}
			<?php
			}
			?>
			*/
			OPTV_Player.play();
		}, 600);
	});

	OPTV_Player.on('pause', function() {
		//$('.videoStartOverlay').removeClass('inactive').show();
	});

	
	OPTV_Player.on('ended', function() {
		if (autoplayResults && nextResultURL) {
			updateQuery(nextResultID);
		}
	});

	window.setTimeout(function() {
		$('#content').addClass('ready');
	}, 2000);
	
}

function updateAutoplayState() {
	$('a.prevSpeech, a.nextSpeech').each(function() {
		$(this).attr('href', $(this).attr('href').replace(/(&playresults=[0-1])/, ''));
		if (autoplayResults) {
			$(this).attr('href', $(this).attr('href') + '&playresults=1');
		}
	});
}

function updateQuery(resultID) {
	
	var thisResultID;
	if (!resultID) {
		var regExResult = /media\/([a-zA-Z0-9_-]+)/.exec(location.pathname);
		if (regExResult && regExResult.length > 1) {
			thisResultID = regExResult[1];
		}
	} else {
		thisResultID = resultID;
	}

	var searchParams = removeQuoteParamsFromURL(window.location.search.toString());
	var locationString = 'media/'+ thisResultID + searchParams.replace(/(&playresults=[0-1])/, ''),
		prevResultURLString = (prevResultURL) ? prevResultURL.replace(/(&playresults=[0-1])/, '') : null,
		nextResultURLString = (nextResultURL) ? nextResultURL.replace(/(&playresults=[0-1])/, '') : null;
	
	if (autoplayResults) {
		locationString += '&playresults=1';
		prevResultURLString += '&playresults=1';
		nextResultURLString += '&playresults=1';
	}

	prevResultURL = prevResultURLString;
	nextResultURL = nextResultURLString;
	
	if (resultID) {
		locationString = locationString.replace(/(media\/[a-zA-Z0-9_-]+)/, resultID);
		history.pushState(null, "", locationString);
		updateContents(locationString);
	} else {
		locationString = locationString.replace(/(media\/)/, '');
		history.replaceState(null, "", locationString);
		updateAutoplayState();
	}
	
}

function goToPrevResultSnippet() {
	var currentVideoTime = (OPTV_Player.currentTime) ? OPTV_Player.currentTime : 0;
	var closestTime = null;

	if (OPTV_Player.codeSnippets.length > 1) {
		var referenceTime = 0;

		for (var i=0; i < OPTV_Player.codeSnippets.length; i++) {
			if (OPTV_Player.codeSnippets[i].data.start < currentVideoTime-2 && OPTV_Player.codeSnippets[i].data.start > referenceTime) {
				referenceTime = closestTime = OPTV_Player.codeSnippets[i].data.start;
			}
		}
	}

	if (closestTime) {
		OPTV_Player.currentTime = closestTime-2;
		$('#nextResultSnippetButton').attr("disabled", false);
	} else if (prevResultID) {
		updateQuery(prevResultID);
	} else {
		$('#prevResultSnippetButton').attr("disabled", true);
	}
}

function goToNextResultSnippet() {
	var currentVideoTime = (OPTV_Player.currentTime) ? OPTV_Player.currentTime : 0;
	var closestTime = null;

	if (OPTV_Player.codeSnippets.length > 1) {
		var referenceTime = OPTV_Player.duration;

		for (var i=0; i < OPTV_Player.codeSnippets.length; i++) {
			if (OPTV_Player.codeSnippets[i].data.start > currentVideoTime+2 && OPTV_Player.codeSnippets[i].data.start < referenceTime) {
				referenceTime = closestTime = OPTV_Player.codeSnippets[i].data.start;
			}
		}
	}

	if (closestTime) {
		OPTV_Player.currentTime = closestTime-2;
		$('#prevResultSnippetButton').attr("disabled", false);
	} else if (nextResultID) {
		updateQuery(nextResultID);
	} else {
		$('#nextResultSnippetButton').attr("disabled", true);
	}
}

function getQueryVariable(variable) {

	var query = window.location.search.substring(1),
		vars = query.split("&"),
		pair;

	for (var i = 0; i < vars.length; i++) {
		pair = vars[i].split("=");
		if (pair[0] == variable) {
			return pair[1];
		}
	}

}

function removeQuoteParamsFromURL(string) {
	var returnString = string;
	var regEx = /[?|&][t|f|c]=[^&]*/g;
	returnString = returnString.replace(regEx, '');
	if (returnString.charAt(0) == '&') {
		returnString = '?'+ returnString.substring(1);
	}
	return returnString;
}