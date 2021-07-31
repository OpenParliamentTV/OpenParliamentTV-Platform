var currentQuery = null;

function updateMediaList(query) {
	currentQuery = query;
	$('.loadingIndicator').show();
	if(updateAjax && updateAjax.readyState != 4){
        updateAjax.abort();
    }
	updateAjax = $.ajax({
		method: "POST",
		url: config.dir.root+"/content/pages/search/content.result.php?a=search&"+query
	}).done(function(data) {
		$('#speechListContainer .resultWrapper').html($(data));
		$('.loadingIndicator').hide();
		$('#speechListContainer [name="sort"]').val((getQueryVariableFromString('sort', currentQuery)) ? getQueryVariableFromString('sort', currentQuery) : 'relevance');
		$('.resultWrapper > nav a').each(function() {
			var thisPage = getQueryVariableFromString('page', $(this).attr('href'));
			if (!thisPage) { thisPage = 1; }
			$(this).attr('href', '#page='+ thisPage);
		});
		updateListeners();
	}).fail(function(err) {
		console.log(err);
	});
}

function updateListeners() {
	$('.resultWrapper > nav a').click(function(evt) {
		evt.stopPropagation();
		evt.preventDefault();
		var page = 1;
		var pageParts = $(this).attr('href').split('#page=');
		if (pageParts.length > 1) {
			page = pageParts[1];
		}
		currentQuery = currentQuery.replace(/&page=[0-9]+/, '');
		currentQuery += '&page='+ page;
		updateMediaList(currentQuery);
	});

	$('#speechListContainer [name="sort"]').on('change', function() {
		currentQuery = currentQuery.replace(/&sort=[a-zA-Z|-]+/, '');
		if ($(this).val() != 'relevance') {
			currentQuery += '&sort=' + $(this).val();
		}
		updateMediaList(currentQuery);
	});
	$('#speechListContainer #play-submit').on('click',function() {
		var firstResult = $('.resultList').find('.resultItem').first();

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