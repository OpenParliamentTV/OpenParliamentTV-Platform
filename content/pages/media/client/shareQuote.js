/**************************************************
 * Adapted from Hyperaudio Lite (MIT License): 
 * https://github.com/hyperaudio/hyperaudio-lite/
 *************************************************/

var shareThis = window.ShareThis,
	start, end, prefix, suffix, shareURL;

$(document).ready( function() {
	initShareQuote();
});

function initShareQuote() {
	
	$('#shareQuoteModal .sharePreview').click(function() {
		$('#shareQuoteModal .sharePreview').removeClass('active');
		$(this).addClass('active');
		var thisTheme = $(this).data('theme');
		shareURL = shareURL.replace(/c=\w+/, 'c='+thisTheme);
		$('#shareQuoteModal #shareURL').val(shareURL);
	});
	
	processQuery();

	const selectionShare = shareThis({
		selector: "#proceedings",
		sharers: [{
			'render': function() {
				return '<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#shareQuoteModal"><span class="icon-share"></span> '+ localizedLabels.shareQuote +'</button>'
			},
			'name': 'OPTV' 
		}]
	});

	selectionShare.init();

	$(window).on('mouseup touchend touchcancel', function(evt) {
		if ($(evt.target).hasClass('timebased')) {
			getSelectionMediaFragment();
		}
	});

	$('#shareURL').click(function() {
		$(this).select();
	});
}

function processQuery() {
	var queryT = getQueryVariable('t'),
		queryF = getQueryVariable('f');
	if (typeof queryT === "string") {
		var t = queryT.split(",");
		start = t[0];
		if (t.length > 1) end = t[1];
	} else {
		start = null;
		end = null;
	}
	if (typeof queryF === "string") {
		var a = queryF.split(",");
		prefix = a[0];
		suffix = a[1];
	} else {
		prefix = null;
		suffix = null;
	}

	var words = document.querySelectorAll("[data-start]");

	if (start && end) {
		for (var i = 1; i < words.length; i++) {
			var wordStart = parseFloat(words[i].getAttribute("data-start"));
			if (wordStart >= start && end > wordStart) {
				words[i].classList.add("share-match");
			}
		}

		if (prefix && suffix) {
			// console.log(prefix, suffix);
			var matches = Array.from(document.querySelectorAll(".share-match"));
			var matchesHash = matches
				.map(function(t) {
					var root = t.innerText
						.trim()
						.replace(/[^\w\s]|_/g, "")
						.replace(/\s+/g, "")
						.toLowerCase()
						.trim();
					return root.substr(0, 1).toUpperCase() + root.substr(1, 3);
				})
				.join("");

			// console.log(matchesHash);

			var prefixMatch = matchesHash.indexOf(prefix);
			if (prefixMatch > 0) {
				matches
					.slice(
						0,
						matchesHash.substring(0, prefixMatch).split(/(?=[A-Z])/)
						.length
					)
					.forEach(function(m) {
						m.classList.add("share-mismatch");
						m.classList.remove("share-match");
					});
			}

			var suffixMatch = matchesHash.indexOf(suffix);
			if (suffixMatch < matchesHash.length - 1 - suffix.length) {
				// matches.slice(0, matchesHash.substring(suffixMatch).split(/(?=[A-Z])/).length - 1).forEach(function (m) {
				matches
					.slice(
						matches.length -
						matchesHash.substring(suffixMatch).split(/(?=[A-Z])/).length +
						suffix.split(/(?=[A-Z])/).length
					)
					.forEach(function(m) {
						m.classList.add("share-mismatch");
						m.classList.remove("share-match");
					});
			}

			// console.log(prefixMatch, suffixMatch);
		}
	}
}

function getHashVariable(variable) {
	var hash = window.location.hash.substring(1),
		vars = hash.split("&"),
		pair,
		returnValues = null;
	for (var i = 0; i < vars.length; i++) {
		pair = vars[i].split("=");
		
		pair[0] = decodeURIComponent(pair[0]);
		pair[1] = decodeURIComponent(pair[1]).replace(/\+/g, ' ');
		
		if (pair[0].indexOf('[]') != -1) {
			if (pair[0].replace('[]', '') == variable) {
				if (!returnValues) returnValues = [];
				returnValues.push(pair[1]);
			}
		} else if (pair[0] == variable) {
			returnValues = pair[1];
		}
	}

	return returnValues;
}

function getSelectionMediaFragment() {
	
	var fragment = "",
		selection;

	if (window.getSelection) {
		selection = window.getSelection();
	} else if (document.selection) {
		selection = document.selection.createRange();
	}

	if (selection.toString() !== "") {
		
		if (OPTV_Player){ 
			OPTV_Player.pause();
		}
		$('.share-match').removeClass('share-match');

		var searchParams = removeQuoteParamsFromURL(window.location.search.toString());
		var locationString = currentMediaID + searchParams;
		history.pushState(null, "", locationString);

		var fNode = selection.focusNode.parentNode;
		var aNode = selection.anchorNode.parentNode;

		if (aNode.getAttribute("data-start") == null) {
			aNode = aNode.nextElementSibling;
		}

		if (fNode.getAttribute("data-start") == null) {
			fNode = fNode.previousElementSibling;
		}

		var range = selection.getRangeAt(0);
		if (aNode) range.setStartBefore(aNode);
		if (fNode) range.setEndAfter(fNode);

		if (!aNode) {
			return;
		}

		var aNodeStart = parseFloat(aNode.getAttribute("data-start"));
		var aNodeEnd = parseFloat(aNode.getAttribute("data-end"));
		//var aNodeDuration = parseInt(aNode.getAttribute("data-d"), 10);
		var fNodeStart ;
		//var fNodeDuration;

		if (fNode != null && fNode.getAttribute("data-start") != null) {
			fNodeStart = parseFloat(fNode.getAttribute("data-start"));
			fNodeEnd = parseFloat(fNode.getAttribute("data-end"));
			//fNodeDuration = parseInt(fNode.getAttribute("data-d"), 10);
		}

		if (!aNodeStart || !fNodeEnd) {
			return;
		}
		var nodeStart = aNodeStart;
		var nodeDuration = fNodeEnd - aNodeStart;

		if (aNodeStart >= fNodeStart) {
			nodeStart = fNodeStart;
			nodeDuration = aNodeEnd - fNodeStart;
		}

		if (nodeDuration == 0 || nodeDuration == null || isNaN(nodeDuration)) {
			nodeDuration = 10; // arbitary for now
		}

		prefix = selection
			.toString()
			.trim()
			.split(" ")
			.slice(0, 3)
			.map(function (t) {
				var root = t
					.replace(/[^\w\s]|_/g, "")
					.replace(/\s+/g, "")
					.toLowerCase()
					.trim();
				return root.substr(0, 1).toUpperCase() + root.substr(1, 3);
			})
			.join("");

		suffix = selection
			.toString()
			.trim()
			.split(" ")
			.reverse()
			.slice(0, 3)
			.reverse()
			.map(function (t) {
				var root = t
					.replace(/[^\w\s]|_/g, "")
					.replace(/\s+/g, "")
					.toLowerCase()
					.trim();
				return root.substr(0, 1).toUpperCase() + root.substr(1, 3);
			})
			.join("");

		// fragment = "#t=" + nodeStart + "," + (Math.round((nodeStart + nodeDuration) * 10) / 10);
		var nodeEnd = nodeStart + nodeDuration;
		if (prefix && suffix) {
			
			var baseURL = window.location.toString(),
				currentTheme = $('#shareQuoteModal .sharePreview.active').data('theme');
			if (baseURL.indexOf('?') > 0) {
				baseURL = baseURL.substring(0, baseURL.indexOf('?'));
			}
			shareURL = baseURL +'?t='+ nodeStart + ',' + nodeEnd + '&f='+ prefix + ',' + suffix + '&c='+ currentTheme;

			var currentPreviewSrc = $('#shareQuoteModal .sharePreview[data-theme="l"] img').attr('src').split('?'),
				newPreviewSrc = currentPreviewSrc[0] + '?id='+ currentMediaID +'&t='+ nodeStart + ',' + nodeEnd + '&f='+ prefix + ',' + suffix;
			$('#shareQuoteModal .sharePreview[data-theme="l"] img').attr('src', newPreviewSrc + '&c=l');
			$('#shareQuoteModal .sharePreview[data-theme="d"] img').attr('src', newPreviewSrc + '&c=d');
			$('#shareQuoteModal #shareURL').val(shareURL);
		}
		
	} 

	return fragment;
}
