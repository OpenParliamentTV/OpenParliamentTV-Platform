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
		$('#speechListContainer [name="sort"]').val((getSortFromQuery(currentQuery)) ? getSortFromQuery(currentQuery) : 'relevance');
		updateListeners();
	}).fail(function(err) {
		console.log(err);
	});
}

function updateListeners() {
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

function getSortFromQuery(queryString) {
	var query = queryString,
		vars = query.split("&"),
		pair,
		returnValue = null;
	for (var i = 0; i < vars.length; i++) {
		pair = vars[i].split("=");
		if (pair[0] == 'sort') {
			returnValue = pair[1];
		}
	}
	return returnValue;
}