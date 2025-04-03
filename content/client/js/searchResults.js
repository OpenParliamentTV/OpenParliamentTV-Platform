var currentQueries = {};

function updateMediaList(query, targetSelector) {
	if (!targetSelector) {
		var targetSelector = '#speechListContainer';
	}

	if ($(targetSelector).length == 0) {
		return;
	}

	currentQueries[targetSelector] = query;

	$(targetSelector +' .loadingIndicator').show();
	
	$.ajax({
		method: "POST",
		url: config.dir.root+"/content/components/result.grid.php?a=search&"+query
	}).done(function(data) {
		$(targetSelector +' .resultWrapper').html($(data));
		$(targetSelector +' .loadingIndicator').hide();
		//console.log(targetSelector);
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