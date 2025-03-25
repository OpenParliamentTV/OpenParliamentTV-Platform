var updateAjax,
	factionColors = {
		"DIE LINKE": "#bc3475",
		"BÜNDNIS 90/DIE GRÜNEN": "#4a932b",
		"CDU/CSU": "#000000",
		"SPD": "#df0b25",
		"FDP": "#feeb34",
		"AfD": "#1a9fdd",
		"BSW": "#792150"
	};
	factionIDColors = {
		"Q1826856": "#bc3475", //linke
		"Q1007353": "#4a932b", //gruene
		"Q1023134": "#000000", //cdu/csu
		"Q2207512": "#df0b25", //spd
		"Q1387991": "#feeb34", //fdp
		"Q42575708": "#1a9fdd", //afd
		"Q127785176": "#792150" //bsw
	};

$(document).ready(function() {

	$(".langswitch").on("click",function(e) {
		e.preventDefault();
		$.ajax({
			url:config["dir"]["root"]+"/server/ajaxServer.php",
			data: {
				a:"lang",
				lang:$(this).data("lang")
			},
			method: "POST",
			success: function() {
				location.reload();
			}
		})
	});

	$('#toggleDarkmode').click(function(evt) {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			$('body').removeClass('darkmode');
			setCookie('color_scheme', 'light', 30);
		} else {
			$(this).addClass('active');
			$('body').addClass('darkmode');
			setCookie('color_scheme', 'dark', 30);
		}
		evt.stopPropagation();
	});

	window.setTimeout(function() {
		$('body').addClass('ready');
	}, 900);

	$(document).ajaxComplete(function( event,request, settings ) {
	    updateLinkTransitions();
	});

	updateLinkTransitions();

});

function updateLinkTransitions() {
	$('a[href^="/"], a[href^="./"], a[href^="../"], a[href^="'+ config.dir.root +'"]').not('a[target="_blank"]').click(function(evt) {
		if (evt.shiftKey || evt.ctrlKey || evt.altKey || evt.metaKey) {
			// click with meta key down
		} else {
			$('body').removeClass('ready');
			$('body > main').hide();
			evt.stopPropagation();
			evt.preventDefault();
			
			var currentHREF = $(this).attr('href');

			window.setTimeout(function(href) {
				window.location = currentHREF;
			}, 400, currentHREF);
		}
	});
}

function setCookie(identifier, value, expiryDays) {
	let date = new Date();
	date.setTime(date.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
	const expires = "expires=" + date.toUTCString();
	document.cookie = identifier + "=" + value + "; " + expires + "; path=/";
}

function getCookie(identifier) {
	const name = identifier + "=";
	const cDecoded = decodeURIComponent(document.cookie); //to be careful
	const cArr = cDecoded .split('; ');
	let res;
	cArr.forEach(val => {
		if (val.indexOf(name) === 0) res = val.substring(name.length);
	})
	return res;
}

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

