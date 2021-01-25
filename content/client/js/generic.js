var updateAjax,
	partyColors = {
		"DIE LINKE": "#bc3475",
		"DIE GRÜNEN": "#4a932b",
		"CDU": "#000000",
		"CSU": "#000000",
		"SPD": "#df0b25",
		"FDP": "#feeb34",
		"AfD": "#1a9fdd"
	};

function delay(callback, ms) {
	var timer = 0;
	return function() {
		var context = this, args = arguments;
		clearTimeout(timer);
		timer = setTimeout(function () {
			callback.apply(context, args);
		}, ms || 0);
	};
}

function getQueryVariable(variable) {
	var query = window.location.search.substring(1),
		vars = query.split("&"),
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

