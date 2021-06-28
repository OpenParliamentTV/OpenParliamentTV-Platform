function updateMediaList(query) {
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
	}).fail(function(err) {
		console.log(err);
	});
}