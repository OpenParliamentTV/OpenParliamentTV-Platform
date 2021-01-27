var minDate = new Date('2017-10-01');
var maxDate = new Date();
var statsAjax;
var partyChart = null,
	timeRangeChart = null;

$(document).ready( function() {

	$('.loadingIndicator').hide();

	$(window).scroll(function(){
		var scroll = $(window).scrollTop();

		if (scroll >= 40 && !$('body').hasClass('fixed')) {
			$('#speechListContainer').css('margin-top', $('.filterContainer').height() + 115);
			$('body').addClass('fixed');
			
		} else if (scroll < 40) {
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

	$('[name="party[]"], [name="sessionNumber"]').change(function() {
		updateQuery();
	});

	$('main').on('change', '[name="sort"]', function() {
		updateQuery();
	});

	$('[name="name"]').val(getQueryVariable('name'));
	$('[name="q"]').val(getQueryVariable('q'));
	$('[name="sessionNumber"]').val(getQueryVariable('sessionNumber'));
	$('[name="sort"]').val((getQueryVariable('sort')) ? getQueryVariable('sort') : 'relevance');

	var partyQueries = getQueryVariable('party');

	if (partyQueries) {
		for (var p=0; p<partyQueries.length; p++) {
			var cleanValue = partyQueries[p].replace('+', ' ').toUpperCase();
			if ($('[name="party[]"][value="'+cleanValue+'"]').length != 0) {
				$('[name="party[]"][value="'+cleanValue+'"]')[0].checked = true;
			}
		}
	}

	/* DATE FUNCTIONS START */

	var options = { year: 'numeric', month: '2-digit', day: '2-digit' };

	var queryFrom = getQueryVariable('timefrom');
	var queryTo = getQueryVariable('timeto');

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

			$('#timefrom').val(date1.toISOString().slice(0,10));
			$('#timeto').val(date2.toISOString().slice(0,10));

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
	
	$('#timefrom').val((queryFrom) ? queryFrom : minDate.toISOString().slice(0,10));
	$('#timeto').val((queryTo) ? queryTo : maxDate.toISOString().slice(0,10));

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
		$('.resultItem[data-party="'+ $(this).children('input').val() +'"]').removeClass('inactive');
		$(this).removeClass('inactive');
	}, function() {
		$('.resultItem, #filterForm .formCheckbox').removeClass('inactive');
	});

});

function updateStatsViz() {
	getResultStats(function(data) {
		updatePartyChart(data.info.speechesPerParty);
		updateTimeRangeChart(data.results);
	});
}

function updatePartyChart(speechesPerParty) {

	var partyData = {
	    datasets: [{
	        data: [],
	        backgroundColor: [],
	        borderWidth: [],
	    }],
	    labels: []
	};

	for (var party in speechesPerParty) {
		if (speechesPerParty.hasOwnProperty(party)) {           
			var partyColor = (partyColors[party]) ? partyColors[party] : "#aaaaaa";

			partyData.datasets[0].data.push(speechesPerParty[party]);
			partyData.labels.push(party);
			partyData.datasets[0].backgroundColor.push(partyColor);
			partyData.datasets[0].borderWidth.push(1);
		}
	}

	if (!partyChart) {
		var partyCtx = document.getElementById('partyChart').getContext('2d');
		partyChart = new Chart(partyCtx, {
			type: 'doughnut',
			data: partyData,
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
		partyChart.data.labels = [];
		partyChart.data.datasets[0].data = [];
		partyChart.data.datasets[0].backgroundColor = [];
		// update chart
		for (var i = 0; i <= partyData.datasets[0].data.length; i++) {
			partyChart.data.labels.push(partyData.labels[i]);
			partyChart.data.datasets[0].data.push(partyData.datasets[0].data[i]);
			partyChart.data.datasets[0].backgroundColor.push(partyData.datasets[0].backgroundColor[i]);
		}
		partyChart.update();
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
		timeRangeData.labels.push(party);
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
		console.log(err);
	});
}

function getSerializedForm() {
	var formData = $('#filterForm :input, select[name="sort"]').filter(function(index, element) {
		if ($(element).attr('name') == 'timefrom' && $(element).val() == minDate.toISOString().slice(0,10)) {
			return false;
		} else if ($(element).attr('name') == 'timeto' && $(element).val() == maxDate.toISOString().slice(0,10)) {
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