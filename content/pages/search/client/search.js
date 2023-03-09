var minDate = new Date('2017-10-01');
var maxDate = new Date();
var statsAjax,
	suggestionsTextAjax,
	suggestionsPeopleAjax;
var factionChart = null,
	timeRangeChart = null;
var selectedSuggestionIndex = null,
	selectedSuggestionColumn = 'suggestionContainerText';

let resultsAttributes;

$(document).ready( function() {

	$('.loadingIndicator').hide();

	$(window).scroll(function(){
		var scroll = $(window).scrollTop();

		if (scroll >= 10 && !$('body').hasClass('fixed') && !$('#filterbar').hasClass('nosearch')) {
			$('#speechListContainer').css('margin-top', $('.filterContainer').height() + 150);
			$('body').addClass('fixed');
			
		} else if (scroll < 10) {
			$('body').removeClass('fixed');
			$('#speechListContainer').css('margin-top', 0);
		}
	});

	$(window).scroll();

	window.onpopstate = function(event) {
		updateContentsFromURL();
	}

	$('body').on('click','#play-submit',function() {
		var firstResult = $('.resultList').find('.resultItem').first();

		if (firstResult.length != 0) {
			location.href = firstResult.children('.resultContent').children('a').eq(0).attr("href") + '&playresults=1';
		}
	});

	$('[name="factionID[]"]').change(function() {
		updateQuery();
	});

	$('main').on('change', '[name="sort"]', function() {
		updateQuery();
	});

	updateContentsFromURL();

	$('#edit-keys').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			return false;
		}
	});

	$('#edit-query').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem.selected').length == 0) {
				submitQueryText();
			}
			return false;
		} else if ($('#edit-query').val() == '' && evt.keyCode == 8 || evt.keyCode == 46) {
			// backspace or delete when query is empty
			evt.preventDefault();
			if ($('.searchInputContainer .queryItem.markedForDeletion').length != 0) {
				$('.searchInputContainer .queryItem.markedForDeletion .queryDeleteItem').click();
			} else {
				$('.searchInputContainer .queryItem').last().addClass('markedForDeletion')
			}
			return false;
		} else {
			$('.searchInputContainer .queryItem.markedForDeletion').removeClass('markedForDeletion');
		}
	});

	$('#edit-query').keyup(delay(function(evt) {
		if (evt.keyCode == 40 || evt.keyCode == 38 || evt.keyCode == 37 || evt.keyCode == 39) {return false;}
		updateSuggestions();
	}, 600));

	$('#edit-submit').click(function(evt) {
		submitQueryText();
	});
	
	$(document).keyup(function(evt) {
		if (evt.keyCode == 40) { 
			// down
			if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem').length == 0) {
				return false;
			} 
			if (selectedSuggestionColumn == 'suggestionContainerText' && 
				$('#suggestionContainerText .suggestionItem').length == 0) {
				selectedSuggestionColumn = 'suggestionContainerPeople';
			}
			if (selectedSuggestionIndex === null) {
				selectedSuggestionIndex = 0;
			} else if ((selectedSuggestionIndex+1) < $('#'+ selectedSuggestionColumn +' .suggestionItem').length) {
				selectedSuggestionIndex++; 
			}
			$('.searchSuggestionContainer .suggestionItem').removeClass('selected');
			$('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+')').addClass('selected');
			var result = $('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+') .suggestionItemLabel').text();
			$('#edit-query').val(result);  
			return false;
		} else if (evt.keyCode == 38) { 
			// up
			if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem').length == 0) {
				return false;
			} 
			$('.searchSuggestionContainer .suggestionItem').removeClass('selected');

			if (selectedSuggestionIndex === null) {
				return false;
			} else if (selectedSuggestionIndex > 0) {
				selectedSuggestionIndex--;            
			} else if (selectedSuggestionIndex == 0) {
				$('#edit-query').val($('.searchSuggestionContainer').data('current'));
				selectedSuggestionIndex = null;
				selectedSuggestionColumn = 'suggestionContainerText';
				return false;
			}

			$('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+')').addClass('selected');
			var result = $('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+') .suggestionItemLabel').text();
			$('#edit-query').val(result);  
			return false;
		} else if (evt.keyCode == 37) { 
			// left
			if (selectedSuggestionIndex === null) {
				return false;
			}
			if (selectedSuggestionColumn == 'suggestionContainerPeople' && 
				$('#suggestionContainerText .suggestionItem').length != 0) {
				
				selectedSuggestionColumn = 'suggestionContainerText';
				$('.searchSuggestionContainer .suggestionItem').removeClass('selected');
				$('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+')').addClass('selected');
				var result = $('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+') .suggestionItemLabel').text();
				$('#edit-query').val(result); 
			} 
			return false;
		} else if (evt.keyCode == 39) { 
			// right
			if (selectedSuggestionIndex === null) {
				return false;
			}
			if (selectedSuggestionColumn == 'suggestionContainerText' && 
				$('#suggestionContainerPeople .suggestionItem').length != 0) {
				
				selectedSuggestionColumn = 'suggestionContainerPeople';

				if (($('#suggestionContainerPeople .suggestionItem').length-1) < selectedSuggestionIndex) {
					selectedSuggestionIndex = $('#'+ selectedSuggestionColumn +' .suggestionItem').length-1;
				}

				$('.searchSuggestionContainer .suggestionItem').removeClass('selected');
				$('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+')').addClass('selected');
				var result = $('#'+ selectedSuggestionColumn +' .suggestionItem:eq('+selectedSuggestionIndex+') .suggestionItemLabel').text();
				$('#edit-query').val(result);  
			}
			return false;
		} else if (evt.keyCode == 13) { 
			// enter
			if ($('.searchSuggestionContainer').is(':visible') && 
				$('.searchSuggestionContainer .suggestionItem.selected').length != 0) {
				if (selectedSuggestionColumn == 'suggestionContainerText') {
					var textValue = $('.searchSuggestionContainer .suggestionItem.selected .suggestionItemLabel').text();
					addQueryItem('text', textValue);
					updateQuery();
				} else if (selectedSuggestionColumn == 'suggestionContainerPeople') {
					var textValue = $('.searchSuggestionContainer .suggestionItem.selected').children('.suggestionItemLabel').text(),
						secondaryText = $('.searchSuggestionContainer .suggestionItem.selected').children('.partyIndicator').text(),
						itemID = $('.searchSuggestionContainer .suggestionItem.selected').attr('data-item-id');
					addQueryItem('person', textValue, secondaryText, itemID);
					updateQuery();
				}
			}
		}
	});

	$(document).click(function(evt) {
		$('.searchInputContainer').removeClass('active');
		$('.searchSuggestionContainer').hide();
		$('.searchInputContainer .queryItem.markedForDeletion').removeClass('markedForDeletion');
	});

	$('#edit-query').click(function(evt) {
		$('.searchInputContainer').addClass('active');
		updateSuggestions();
		evt.stopPropagation();
	});

	$('.searchInputContainer').click(function(evt) {
		if ($(evt.target).hasClass('searchInputContainer')) {
			$(this).addClass('active');
			$('#edit-query').focus();
			evt.stopPropagation();
		}
	});

	$('.searchSuggestionContainer').click(function(evt) {
		evt.stopPropagation();
	});

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

	initParliamentSelectMenu();

});

function updateContentsFromURL() {

	//$('[name="person"]').val(getQueryVariable('person'));

	$('#filterForm input[name="personID[]"]').remove();
	var peopleIDs = getQueryVariable('personID');
	if ($.isArray(peopleIDs)) {
		for (var i = 0; i < peopleIDs.length; i++) {
			$('#filterForm').append('<input type="hidden" name="personID[]" value="'+ peopleIDs[i] +'">');
		}
	} else if (peopleIDs) {
		$('#filterForm').append('<input type="hidden" name="personID[]" value="'+ peopleIDs +'">');
	}

	$('[name="q"]').val(getQueryVariable('q'));
	$('[name="context"]').val(getQueryVariable('context'));
	$('[name="sort"]').val((getQueryVariable('sort')) ? getQueryVariable('sort') : 'relevance');

	$('[name="factionID[]"]').each(function() {
		$(this)[0].checked = false;
	});
	var factionQueries = getQueryVariable('factionID');
	if (factionQueries) {
		for (var p=0; p<factionQueries.length; p++) {
			var cleanValue = factionQueries[p].replace('+', ' ').toUpperCase();
			if ($('[name="factionID[]"][value="'+cleanValue+'"]').length != 0) {
				$('[name="factionID[]"][value="'+cleanValue+'"]')[0].checked = true;
			}
		}
	}
	
	initInteractiveQueryValues();

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
			
			var date2String = (date2.toISOString().slice(0,10) == maxDate.toISOString().slice(0,10)) ? localizedLabels.today : date2.toLocaleDateString('de-DE', options);

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

	var endDateString = (endDate == maxDate.toLocaleDateString('de-DE', options)) ? localizedLabels.today : endDate;

	$( "#timeRange" ).val( startDate + " - " + endDateString );
	
	$('#dateFrom').val((queryFrom) ? queryFrom : minDate.toISOString().slice(0,10));
	$('#dateTo').val((queryTo) ? queryTo : maxDate.toISOString().slice(0,10));

	updateFactionChart();
    $('#timelineVizWrapper').empty();
    updateTimelineViz();	

	/* DATE FUNCTIONS END */

	updateResultList();
}

function initParliamentSelectMenu() {
	$('.parliamentFilterContainer').on('change', 'select', function(evt) {
		var targetSelectMenu = $(evt.currentTarget);
		if (targetSelectMenu.attr('name') == 'parliament') {
			$('.parliamentFilterContainer #selectElectoralPeriod').remove();
			$('.parliamentFilterContainer #selectSession').remove();
		} else if (targetSelectMenu.attr('name') == 'electoralPeriod') {
			$('.parliamentFilterContainer #selectSession').remove();
		}
		updateQuery();
		$.ajax({
			method: "POST",
			url: "./content/pages/search/content.filter.parliaments.php?"+ getSerializedForm()
		}).done(function(data) {
			$('.parliamentFilterContainer').html($(data));
		}).fail(function(err) {
			//console.log(err);
		});
	});
}

function updateSuggestions() {
	var textValue = $('#edit-query').val(),
		suggestionText = $('.searchSuggestionContainer').data('current');

	if (textValue.length >= 3) {
		
		if (textValue == suggestionText) {
			$('.searchSuggestionContainer').show();
			return false;
		}

		$('.searchSuggestionContainer').data('current', textValue);

		$('#suggestionContainerText, #suggestionContainerPeople').empty();
		selectedSuggestionIndex = null,
		selectedSuggestionColumn = 'suggestionContainerText';

		$('.searchSuggestionContainer').show();
		
		/*
		var firstSuggestionItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">'+ textValue +'</span><span class="ml-2" style="opacity: .68;">[Enter]</span></div>');
		$('#suggestionContainerText').append(firstSuggestionItem);
		*/

		if (textValue.indexOf(' ') != -1) {
			var exactSuggestionItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">"'+ textValue +'"</span><span class="ml-2" style="opacity: .68;">('+ localizedLabels.exactMatch +')</span></div>');
			
			exactSuggestionItem.click(function(evt) {
				var textValue = $(this).children('.suggestionItemLabel').text();
				addQueryItem('text', textValue);
				updateQuery();
			});

			$('#suggestionContainerText').append(exactSuggestionItem);
		}

		if(suggestionsTextAjax && suggestionsTextAjax.readyState != 4){
	        suggestionsTextAjax.abort();
	    }

	    if (textValue.indexOf(' ') == -1) {
	    	
	    	if (textValue.indexOf('*') == -1) {
		    	var wildcardSuggestionBeginItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">'+ textValue +'*</span><span class="ml-2" style="opacity: .68;">('+ localizedLabels.wildcardSearchBegin +')</span></div>');
				
				wildcardSuggestionBeginItem.click(function(evt) {
					var textValue = $(this).children('.suggestionItemLabel').text();
					addQueryItem('text', textValue);
					updateQuery();
				});

				$('#suggestionContainerText').append(wildcardSuggestionBeginItem);

				var wildcardSuggestionEndItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">*'+ textValue.toLowerCase() +'</span><span class="ml-2" style="opacity: .68;">('+ localizedLabels.wildcardSearchEnd +')</span></div>');
				
				wildcardSuggestionEndItem.click(function(evt) {
					var textValue = $(this).children('.suggestionItemLabel').text();
					addQueryItem('text', textValue);
					updateQuery();
				});

				$('#suggestionContainerText').append(wildcardSuggestionEndItem);
			}
	    	suggestionsTextAjax = $.ajax({
				method: "POST",
				url: './api/v1/autocomplete/text?q='+ textValue
			}).done(function(data) {
				renderTextSuggestions(textValue, data.data);
			}).fail(function(err) {
				//console.log(err);
			});
	    }

	    if(suggestionsPeopleAjax && suggestionsPeopleAjax.readyState != 4){
	        suggestionsPeopleAjax.abort();
	    }

	    suggestionsPeopleAjax = $.ajax({
			method: "POST",
			url: './api/v1/search/people?name='+ textValue
		}).done(function(data) {
			renderPeopleSuggestions(textValue, data.data);
		}).fail(function(err) {
			//console.log(err);
		});

	} else {
		$('.searchSuggestionContainer').hide();
	}
}

function addQueryItem(queryType, queryText, secondaryText, itemID) {
	var queryItem = $('<span class="queryItem" data-type="'+ queryType +'"><span class="queryText">'+ queryText +'</span></span>'),
		queryDeleteItem = $('<span class="queryDeleteItem icon-cancel"></span>');
	if (secondaryText) {
		queryItem.append('<span class="ml-2 partyIndicator" data-faction="'+ secondaryText +'">'+ secondaryText +'</span>');
	}
	if (itemID) {
		queryItem.attr('data-item-id', itemID);
	}
	queryDeleteItem.click(function(evt) {
		evt.preventDefault();
		evt.stopPropagation();
		
		$(this).parents('.queryItem').remove();
		$('.searchInputContainer').addClass('active');
		$('#edit-query').focus();
		updateQuery();
	});

	queryItem.append(queryDeleteItem);

	$('.searchInputContainer input#edit-query').val('');
	updateSuggestions();
	if (queryType == 'person') {
		$('.searchInputContainer').prepend(queryItem);
	} else {
		$('.searchInputContainer input#edit-query').before(queryItem);
	}
	//$('.searchInputContainer input#edit-query').focus();
}

function renderTextSuggestions(inputValue, data) {
	for (var i = 0; i < data.length; i++) {
		var suggestionItemText = '<span class="suggestionItemLabel">'+ data[i].text +'</span>',
			suggestionItemFrequency = '<span class=" ml-2 badge badge-pill badge-primary">'+ data[i].freq +'</span>',
			suggestionItem = $('<div class="suggestionItem" data-type="text">'+ suggestionItemText /*+ suggestionItemFrequency*/ +'</div>');

		suggestionItem.click(function(evt) {
			var textValue = $(this).children('.suggestionItemLabel').text();
			addQueryItem('text', textValue);
			updateQuery();
		});

		$('.searchSuggestionContainer #suggestionContainerText').append(suggestionItem);
	}
}

function renderPeopleSuggestions(inputValue, data) {
	var maxSuggestions = 4;

	var textValue = $.trim(inputValue),
		isExactMatch = /\".+\"/.test(textValue),
		inputTermsArray = [];
	
	if (textValue.indexOf(' ') != -1 && !isExactMatch) {
		var textValueParts = textValue.split(' ');
		for (var i = 0; i < textValueParts.length; i++) {
			inputTermsArray.push(textValueParts[i]);
		}
	} else {
		inputTermsArray.push(textValue);
	}
	
	if (data.length == 0) {
		$('.searchSuggestionContainer #suggestionContainerPeople').append('<div class="my-3">'+ localizedLabels.noPeopleFound +'</div>');
	} else {
		for (var i = 0; i < data.length; i++) {
			var highlightedLabel = data[i].attributes.label;
			for (var h = inputTermsArray.length - 1; h >= 0; h--) {
				highlightedLabel = highlightedLabel.split(inputTermsArray[h]).join('<em>' + inputTermsArray[h] + '</em>');
			}

			var suggestionItemPerson = '<span class="suggestionItemLabel">'+ highlightedLabel +'</span>',
				suggestionItemFaction = (data[i].relationships.faction.data) ? '<span class="ml-2 partyIndicator" data-faction="'+ data[i].relationships.faction.data.attributes.label +'">'+ data[i].relationships.faction.data.attributes.label +'</span>' : '',
				suggestionItem = $('<div class="suggestionItem" data-type="person" data-item-id="'+ data[i].id +'">'+ suggestionItemPerson + suggestionItemFaction +'</div>');

			suggestionItem.click(function(evt) {
				var textValue = $(this).children('.suggestionItemLabel').text(),
					secondaryText = $(this).children('.partyIndicator').text(),
					itemID = $(this).attr('data-item-id');
				addQueryItem('person', textValue, secondaryText, itemID);
				updateQuery();
			});

			$('.searchSuggestionContainer #suggestionContainerPeople').append(suggestionItem);
			
			if (i >= maxSuggestions-1) {
				break;
			}
		}
	}
}

function updateQuery() {
	$('input[name="q"]').val(getInteractiveQueryValues('text'));
	
	$('#filterForm input[name="personID[]"]').remove();
	var peopleIDs = getInteractiveQueryValues('person');
	for (var i = 0; i < peopleIDs.length; i++) {
		$('#filterForm').append('<input type="hidden" name="personID[]" value="'+ peopleIDs[i] +'">');
	}

	history.pushState(null, "", "search?"+ getSerializedForm());
	updateResultList();
	if ($('input[name="q"]').val() != '' || peopleIDs.length != 0) {
		if (peopleIDs.length != 0) {
			var newDocumentTitle = '';
			document.title = '';
			$('.searchInputContainer .queryItem[data-type="person"]').each(function() {
				newDocumentTitle += $(this).find('.queryText').text() + ' ';
			});
			newDocumentTitle += $('input[name="q"]').val() +' - '+ localizedLabels.speeches +' | '+ localizedLabels.brand;
			document.title = newDocumentTitle;
		} else {
			document.title = $('input[name="q"]').val() +' - '+ localizedLabels.speeches +' | '+ localizedLabels.brand;
		}
	} else {
		document.title = localizedLabels.brand;
	}
}

function updateResultList() {
	$('.loadingIndicator').show();
	if(updateAjax && updateAjax.readyState != 4){
        updateAjax.abort();
    }
    
    var pageParam = getQueryVariable('page'),
    	pageString = '';
    if (pageParam) {
    	pageString = 'page=' + pageParam + '&';
    }

    var langParam = getQueryVariable('lang'),
    	langString = '';
    if (langParam) {
    	langString = 'lang=' + langParam + '&';
    }

	updateAjax = $.ajax({
		method: "POST",
		url: "./content/pages/search/content.result.php?a=search&queryOnly=1&" + langString + pageString + getSerializedForm()
	}).done(function(data) {
		var requestQuery = getQueryVariable('q'),
			requestPersonID = getQueryVariable('personID');
		if ((requestQuery && requestQuery.length >= 2) || requestPersonID) {
			$('#filterbar').removeClass('nosearch');
			$('.filterContainer').removeClass('d-none').addClass('d-md-block');
			$('#toggleFilterContainer').removeClass('d-none').addClass('d-block');
		} else {
			$('#filterbar').addClass('nosearch');
			$('.filterContainer').removeClass('d-md-block').addClass('d-none');
			$('#toggleFilterContainer').removeClass('d-block').addClass('d-none');
		}
		$('#speechListContainer .resultWrapper').html($(data));
		$('[name="sort"]').val((getQueryVariable('sort')) ? getQueryVariable('sort') : 'relevance');
		
		updateFactionChart();
        $('#timelineVizWrapper').empty();
        updateTimelineViz();

		$('.loadingIndicator').hide();
		runCounterAnimation();
	}).fail(function(err) {
		//console.log(err);
	});
}

function getInteractiveQueryValues(queryType) {
	var queryItems = $('.searchInputContainer .queryItem[data-type="'+ queryType +'"]');
	
	if (queryType == 'person') {
		var queryValue = [];
		queryItems.each(function() {
			queryValue.push( $(this).attr('data-item-id') );
		});

		return queryValue;
	} else {
		var queryValue = '';
		queryItems.each(function() {
			queryValue += $(this).children('.queryText').text()+ ' ';
		});

		return $.trim(queryValue);
	}
}

function submitQueryText() {
	$('.searchInputContainer .queryItem.markedForDeletion').removeClass('markedForDeletion');
	var textValue = $.trim($('#edit-query').val()),
		isExactMatch = /\".+\"/.test(textValue);
	if (textValue != '') {
		if (textValue.indexOf(' ') != -1 && !isExactMatch) {
			textValueParts = textValue.split(' ');
			for (var i = 0; i < textValueParts.length; i++) {
				addQueryItem('text', textValueParts[i]);
			}
		} else {
			addQueryItem('text', textValue);
		}

		updateQuery();
	}
}

function initInteractiveQueryValues() {
	
	$('.searchInputContainer .queryItem').remove();

	if ($('[name="personID[]"]').length != 0) {
		var personIDs = $.map($('[name="personID[]"]'), function(el) { return el.value; });
		if (personIDs && typeof personDataFromRequest !== 'undefined') {
			for (var i = personIDs.length - 1; i >= 0; i--) {
				var label = personDataFromRequest[personIDs[i]].attributes.label;
				if (personDataFromRequest[personIDs[i]].relationships.faction.data) {
					var secondaryLabel = personDataFromRequest[personIDs[i]].relationships.faction.data.attributes.label;
					addQueryItem('person', label, secondaryLabel, personIDs[i]);
				} else {
					addQueryItem('person', label, false, personIDs[i]);
				}
			}
		}
	}

	// init text string items
	var queryString = $.trim($('[name="q"]').val());

	if (queryString.indexOf(' ') != -1) {
		var queryStringParts = queryString.match(/(\"[^\"]+\"|[^\s]+)/g);

		for (var i = 0; i < queryStringParts.length; i++) {
			addQueryItem('text', queryStringParts[i]);
		}
	} else if (queryString != '') {
		addQueryItem('text', queryString);
	}
}

function updateTimelineViz() {
    if (typeof resultsAttributes !== "object") {
        return;
    }
    let highestSpeechCountPerDay = 0;

    for (let day in resultsAttributes["days"]) {
        if (resultsAttributes["days"][day]["doc_count"] > highestSpeechCountPerDay) {
            highestSpeechCountPerDay = resultsAttributes["days"][day]["doc_count"];
        }
    }
    for (let day in resultsAttributes["days"]) {
        let percentSpeechesPerDay = 100 * (resultsAttributes["days"][day]["doc_count"] / highestSpeechCountPerDay),
            oneDay = 24 * 60 * 60 * 1000,
            diffDays = Math.round(Math.abs((minDate - maxDate) / oneDay)),
            itemDate = new Date(day),
            daysSinceMinDate = Math.round(Math.abs((minDate - itemDate) / oneDay)),
            leftPercent = 100 * (daysSinceMinDate / diffDays);

        let timelineVizItem = $('<div class="timelineVizItem" data-speech-date="'+ day +'" data-speech-count="'+ resultsAttributes["days"][day]["doc_count"] +'" style="height:'+ percentSpeechesPerDay +'%; left:'+ leftPercent +'%"></div>');

        $('#timelineVizWrapper').append(timelineVizItem);
    }

}

function updateFactionChart() {

    if (typeof resultsAttributes !== "object") {
        return;
    }

    var speechesPerFaction = resultsAttributes["resultsPerFaction"];

    var factionData = {
        datasets: [{
            data: [],
            backgroundColor: [],
            borderWidth: [],
        }],
        labels: []
    };

    if (speechesPerFaction && speechesPerFaction.length == 0) {
        factionData.datasets[0].data.push(1);
        factionData.labels.push(' ');
        factionData.datasets[0].backgroundColor.push('#aaaaaa');
        factionData.datasets[0].borderWidth.push(1);
    } else {
        for (var faction in speechesPerFaction) {
            if (speechesPerFaction.hasOwnProperty(faction)) {
                var factionColor = (factionIDColors[faction]) ? factionIDColors[faction] : "#aaaaaa";

                factionData.datasets[0].data.push(speechesPerFaction[faction]);
                factionData.labels.push(faction);
                factionData.datasets[0].backgroundColor.push(factionColor);
                factionData.datasets[0].borderWidth.push(1);
            }
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

function getSerializedForm() {
	var formData = $('#filterForm :input, select[name="sort"]').filter(function(index, element) {
		if ($(element).attr('name') == 'dateFrom' && $(element).val() == minDate.toISOString().slice(0,10)) {
			return false;
		} else if ($(element).attr('name') == 'dateTo' && $(element).val() == maxDate.toISOString().slice(0,10)) {
			return false;
		} else if ($(element).attr('name') == 'sort' && $(element).val() == 'relevance') {
			return false;
		} else if ($(element).attr('name') == 'edit-query') {
			return false;
		} else if ($(element).attr('name') == 'parliament' && $(element).val() == 'all') {
			return false;
		} else if ($(element).attr('name') == 'electoralPeriod' && $(element).val() == 'all') {
			return false;
		} else if ($(element).attr('name') == 'sessionNumber' && $(element).val() == 'all') {
			return false;
		} else if ($(element).val() != '') {
			return true;
		} else {
			return false;
		}
    }).serialize();
	
    return formData;
}

function easeOutQuad(t) {
	return t === 1 ? 1 : 1 - Math.pow(2, -20 * t);
}

function animateCountUp(el) {
	let animationDuration = 4000;
	let frameDuration = 1000 / 60;
	let totalFrames = Math.round( animationDuration / frameDuration );
	let frame = 0;
	const countTo = parseInt( el.innerHTML, 10 );
	// Start the animation running 60 times per second
	const counter = setInterval( () => {
		frame++;
		// Calculate our progress as a value between 0 and 1
		// Pass that value to our easing function to get our
		// progress on a curve
		const progress = easeOutQuad( frame / totalFrames );
		// Use the progress value to calculate the current count
		const currentCount = Math.round( countTo * progress );

		// If the current count has changed, update the element
		if ( parseInt( el.innerHTML, 10 ) !== currentCount ) {
			el.innerHTML = currentCount;
		}

		// If we’ve reached our last frame, stop the animation
		if ( frame === totalFrames ) {
			clearInterval( counter );
		}
	}, frameDuration );
}

function runCounterAnimation() {
	const countupEls = document.querySelectorAll( '.countup' );
	countupEls.forEach( animateCountUp );
}