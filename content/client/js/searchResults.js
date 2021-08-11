var currentQueries = {};

function updateMediaList(query, targetSelector) {
	if (!targetSelector) {
		var targetSelector = '#speechListContainer';
	}

	currentQueries[targetSelector] = query;

	$(targetSelector +' .loadingIndicator').show();
	
	$.ajax({
		method: "POST",
		url: config.dir.root+"/content/pages/search/content.result.php?a=search&"+query
	}).done(function(data) {
		$(targetSelector +' .resultWrapper').html($(data));
		$(targetSelector +' .loadingIndicator').hide();
		console.log(targetSelector);
		$(targetSelector +' [name="sort"]').val((getQueryVariableFromString('sort', currentQueries[targetSelector])) ? getQueryVariableFromString('sort', currentQueries[targetSelector]) : 'relevance');
		$(targetSelector +' .resultWrapper > nav a').each(function() {
			var thisPage = getQueryVariableFromString('page', $(this).attr('href'));
			if (!thisPage) { thisPage = 1; }
			$(this).attr('href', '#page='+ thisPage);
		});
		updateListeners(targetSelector);
	}).fail(function(err) {
		console.log(err);
	});
}

function updateListeners(targetSelector) {
	$(targetSelector +' .resultWrapper > nav a').click(function(evt) {
		evt.stopPropagation();
		evt.preventDefault();
		var page = 1;
		var pageParts = $(this).attr('href').split('#page=');
		if (pageParts.length > 1) {
			page = pageParts[1];
		}
		currentQueries[targetSelector] = currentQueries[targetSelector].replace(/&page=[0-9]+/, '');
		currentQueries[targetSelector] += '&page='+ page;
		updateMediaList(currentQueries[targetSelector], targetSelector);
	});

	$(targetSelector +' [name="sort"]').on('change', function() {
		currentQueries[targetSelector] = currentQueries[targetSelector].replace(/&sort=[a-zA-Z|-]+/, '');
		if ($(this).val() != 'relevance') {
			currentQueries[targetSelector] += '&sort=' + $(this).val();
		}
		updateMediaList(currentQueries[targetSelector], targetSelector);
	});
	$(targetSelector +' #play-submit').on('click',function() {
		var firstResult = $(targetSelector +' .resultList').find('.resultItem').first();

		if (firstResult.length != 0) {
			location.href = firstResult.children('.resultContent').children('a').eq(0).attr("href") + '&playresults=1';
		}
	});
}

function getQueryVariableFromString(variable, queryString) {
	var query = queryString.replace('?', ''),
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