var minDate = new Date('2017-10-01');
var maxDate = new Date();
var statsAjax;
var factionChart = null,
	timeRangeChart = null;

$(document).ready( function() {

	$('.loadingIndicator').hide();

	$(window).scroll(function(){
		var scroll = $(window).scrollTop();

		if (scroll >= 10 && !$('body').hasClass('fixed')) {
			$('#speechListContainer').css('margin-top', $('.filterContainer').height() + 115);
			$('body').addClass('fixed');
			
		} else if (scroll < 10) {
			$('body').removeClass('fixed');
			$('#speechListContainer').css('margin-top', 0);
		}
	});

	$(window).scroll();

	window.onpopstate = function(event) {
		updateResultList();
	}

	$('body').on('click','#play-submit',function() {
		var firstResult = $('.resultList').find('.resultItem').first();

		if (firstResult.length != 0) {
			location.href = firstResult.children('.resultContent').children('a').eq(0).attr("href") + '&playresults=1';
		}
	});

	$('[name="factionID[]"], [name="sessionNumber"]').change(function() {
		updateQuery();
	});

	$('main').on('change', '[name="sort"]', function() {
		updateQuery();
	});

	$('[name="person"]').val(getQueryVariable('person'));
	$('[name="q"]').val(getQueryVariable('q'));
	$('[name="sessionNumber"]').val(getQueryVariable('sessionNumber'));
	$('[name="sort"]').val((getQueryVariable('sort')) ? getQueryVariable('sort') : 'relevance');

	var factionQueries = getQueryVariable('factionID');

	if (factionQueries) {
		for (var p=0; p<factionQueries.length; p++) {
			var cleanValue = factionQueries[p].replace('+', ' ').toUpperCase();
			if ($('[name="factionID[]"][value="'+cleanValue+'"]').length != 0) {
				$('[name="factionID[]"][value="'+cleanValue+'"]')[0].checked = true;
			}
		}
	}

	/* DATE FUNCTIONS START */

	var options = { year: 'numeric', month: '2-digit', day: '2-digit' };

	var queryFrom = getQueryVariable('dateFrom');
	var queryTo = getQueryVariable('dateTo');

	var queryFromDate = new Date(queryFrom);
	var queryToDate = new Date(queryTo);

	$('#sliderRange').slider({
		range: true,
		min: minDate.getTime(),
		max: maxDate.getTime(),
		slide: function (event, ui) {
			
			var date1 = new Date(ui.values[0]);
			var date2 = new Date(ui.values[1]);
			
			var date2String = (date2.toISOString().slice(0,10) == maxDate.toISOString().slice(0,10)) ? 'heute' : date2.toLocaleDateString('de-DE', options);

			$("#timeRange").val( date1.toLocaleDateString('de-DE', options) + " - " + date2String );

			$('#dateFrom').val(date1.toISOString().slice(0,10));
			$('#dateTo').val(date2.toISOString().slice(0,10));

		},
		stop: function (event, ui) {
			updateQuery();
		},
		values: [(queryFrom) ? queryFromDate.getTime() : 0, (queryTo) ? queryToDate.getTime() : maxDate.getTime()]
	});

	var startDate = (queryFrom) ? queryFromDate.toLocaleDateString('de-DE', options) : minDate.toLocaleDateString('de-DE', options);
	var endDate = (queryTo) ? queryToDate.toLocaleDateString('de-DE', options) : maxDate.toLocaleDateString('de-DE', options);

	var endDateString = (endDate == maxDate.toLocaleDateString('de-DE', options)) ? 'heute' : endDate;

	$( "#timeRange" ).val( startDate + " - " + endDateString );
	
	$('#dateFrom').val((queryFrom) ? queryFrom : minDate.toISOString().slice(0,10));
	$('#dateTo').val((queryTo) ? queryTo : maxDate.toISOString().slice(0,10));

	updateStatsViz();

	/* DATE FUNCTIONS END */

	$('#edit-query, #edit-keys').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			return false;
		}
	});

	$('#edit-query').keyup(delay(function(evt) {
		updateQuery();
	}, 600));

	$('#edit-keys').keyup(delay(function(evt) {
		updateQuery();
	}, 1000));

	$('#filterForm .formCheckbox').hover(function() {
		$('.resultItem, #filterForm .formCheckbox').addClass('inactive');
		$('.resultItem[data-faction="'+ $(this).children('input').val() +'"]').removeClass('inactive');
		$(this).removeClass('inactive');
	}, function() {
		$('.resultItem, #filterForm .formCheckbox').removeClass('inactive');
	});

});

function updateStatsViz() {
	getResultStats(function(data) {
		updateFactionChart(data.info.speechesPerFaction);
		updateTimeRangeChart(data.results);
	});
}

function updateFactionChart(speechesPerFaction) {

	var factionData = {
	    datasets: [{
	        data: [],
	        backgroundColor: [],
	        borderWidth: [],
	    }],
	    labels: []
	};

	for (var faction in speechesPerFaction) {
		if (speechesPerFaction.hasOwnProperty(faction)) {           
			var factionColor = (factionColors[faction]) ? factionColors[faction] : "#aaaaaa";

			factionData.datasets[0].data.push(speechesPerFaction[faction]);
			factionData.labels.push(faction);
			factionData.datasets[0].backgroundColor.push(factionColor);
			factionData.datasets[0].borderWidth.push(1);
		}
	}

	if (!factionChart) {
		var factionCtx = document.getElementById('factionChart').getContext('2d');
		factionChart = new Chart(factionCtx, {
			type: 'doughnut',
			data: factionData,
			options: {
				responsive: true,
				aspectRatio: 1,
				legend: {
					display: false
				},
				tooltips: {
					enabled: false
				},
				cutoutPercentage: 75,
				animation: {
					animateRotate: true
				}
			}
		});
	} else {
		//empty chart
		factionChart.data.labels = [];
		factionChart.data.datasets[0].data = [];
		factionChart.data.datasets[0].backgroundColor = [];
		// update chart
		for (var i = 0; i <= factionData.datasets[0].data.length; i++) {
			factionChart.data.labels.push(factionData.labels[i]);
			factionChart.data.datasets[0].data.push(factionData.datasets[0].data[i]);
			factionChart.data.datasets[0].backgroundColor.push(factionData.datasets[0].backgroundColor[i]);
		}
		factionChart.update();
	}
	
}

function updateTimeRangeChart(results) {
	
	var resultDates = [],
		highestSpeechesPerDay = 0;

	$('#timelineVizWrapper').empty();

	for (var i = 0; i < results.length; i++) {
		
		var currentDate = results[i]['date'];

		if (resultDates[currentDate]) {
			resultDates[currentDate]['count']++;
			resultDates[currentDate]['speechIDs'].push(results[i]['id']);
		} else {
			resultDates[currentDate] = [];
			resultDates[currentDate]['count'] = 1;
			resultDates[currentDate]['speechIDs'] = [results[i]['id']];
		}

		if (resultDates[currentDate]['count'] > highestSpeechesPerDay) {
			highestSpeechesPerDay = resultDates[currentDate]['count'];
		}
	}

	for (var speechDate in resultDates) {
		var percentSpeechesPerDay = 100 * (resultDates[speechDate]['count'] / highestSpeechesPerDay),
			oneDay = 24 * 60 * 60 * 1000,
			diffDays = Math.round(Math.abs((minDate - maxDate) / oneDay)),
			itemDate = new Date(speechDate),
			daysSinceMinDate = Math.round(Math.abs((minDate - itemDate) / oneDay)),
			leftPercent = 100 * (daysSinceMinDate / diffDays);

		var timelineVizItem = $('<div class="timelineVizItem" data-speech-date="'+ speechDate +'" data-speech-count="'+ resultDates[speechDate]['count'] +'" style="height:'+ percentSpeechesPerDay +'%; left:'+ leftPercent +'%"></div>');

		$('#timelineVizWrapper').append(timelineVizItem);
	}

	/*
	var timeRangeData = {
	    datasets: [{
	        data: [30, 10, 3]
	    }],
	    labels: []
	};

	for (var i=0; i < results; i++) {
		timeRangeData.datasets[0].data.push(results[i]);
		timeRangeData.labels.push(faction);
	}

	if (!timeRangeChart) {
		var timeRangeCtx = document.getElementById('timeRangeChart').getContext('2d');
		timeRangeChart = new Chart(timeRangeCtx, {
			type: 'line',
			data: timeRangeData,
			options: {
				responsive: true,
				aspectRatio: 1,
				legend: {
					display: false
				},
				tooltips: {
					enabled: true
				},
				animation: {
					animateRotate: true
				}
			}
		});
	} else {
		//empty chart
		timeRangeChart.data.labels = [];
		timeRangeChart.data.datasets[0].data = [];
		// update chart
		for (var i = 0; i <= timeRangeData.datasets[0].data.length; i++) {
			timeRangeChart.data.labels.push(timeRangeData.labels[i]);
			timeRangeChart.data.datasets[0].data.push(timeRangeData.datasets[0].data[i]);
		}
		timeRangeChart.update();
	}
	*/
}

function updateQuery() {
	history.pushState(null, "", "search?"+ getSerializedForm());
	updateResultList();
}

function updateResultList() {
	$('.loadingIndicator').show();
	if(updateAjax && updateAjax.readyState != 4){
        updateAjax.abort();
    }
	updateAjax = $.ajax({
		method: "POST",
		url: "./content/pages/search/content.result.php?a=search&"+ getSerializedForm()
	}).done(function(data) {
		$('#speechListContainer .resultWrapper').html($(data));
		$('[name="sort"]').val((getQueryVariable('sort')) ? getQueryVariable('sort') : 'relevance');
		updateStatsViz(minDate, maxDate);
		$('.loadingIndicator').hide();
	}).fail(function(err) {
		console.log(err);
	});
}

function getResultStats(statsCallback) {
	if(statsAjax && statsAjax.readyState != 4){
        statsAjax.abort();
    }
	statsAjax = $.ajax({
		method: "GET",
		url: "./server/ajaxServer.php?a=stats&"+ getSerializedForm()
	}).done(function(data) {
		statsCallback(data.return);
	}).fail(function(err) {
		//console.log(err);
	});
}

function getSerializedForm() {
	var formData = $('#filterForm :input, select[name="sort"]').filter(function(index, element) {
		if ($(element).attr('name') == 'dateFrom' && $(element).val() == minDate.toISOString().slice(0,10)) {
			return false;
		} else if ($(element).attr('name') == 'dateTo' && $(element).val() == maxDate.toISOString().slice(0,10)) {
			return false;
		} else if ($(element).attr('name') == 'sort' && $(element).val() == 'relevance') {
			return false;
		} else if ($(element).val() != '') {
			return true;
		} else {
			return false;
		}
    }).serialize();
	
    return formData;
}