// Search Page Controller - handles search-specific UI and orchestrates components
var minDate = new Date('2013-10-01');
var maxDate = new Date();
var suggestionsTextAjax,
	suggestionsEntitiesAjax,
	updateAjax;
var factionChart = null,
	timeRangeChart = null;
var selectedSuggestionIndex = null,
	selectedSuggestionColumn = 'suggestionContainerText';
var timelineViz = null;

// Component instances
var filterController = null;
var mediaResultsManager = null;

let resultsAttributes;

$(document).ready( function() {

	$('.loadingIndicator').hide();

	// Initialize components
	initializeComponents();

	// Search page specific UI behavior
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

	// Add window resize event listener for responsive chart
	$(window).resize(function() {
		if (typeof resultsAttributes === "object") {
			updateFactionChart();
		}
	});

	window.onpopstate = function(event) {
		updateContentsFromURL();
	}

	$('[name="factionID[]"], [name="numberOfTexts"], [name="aligned"], [name="public"]').change(function() {
		updateQuery();
	});

	$('[name="agendaItemTitle"]').keyup(function() {
		updateQuery();
	});

	$('main').on('change', '[name="sort"]', function() {
		updateQuery();
	});

	updateContentsFromURL();

	// Initialize search-specific UI
	initializeSearchUI();

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
		} else if ($('#edit-query').val() == '' && (evt.keyCode == 8 || evt.keyCode == 46)) {
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
				selectedSuggestionColumn = 'suggestionContainerEntities';
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
			if (selectedSuggestionColumn == 'suggestionContainerEntities' && 
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
				$('#suggestionContainerEntities .suggestionItem').length != 0) {
				
				selectedSuggestionColumn = 'suggestionContainerEntities';

				if (($('#suggestionContainerEntities .suggestionItem').length-1) < selectedSuggestionIndex) {
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
				} else if (selectedSuggestionColumn == 'suggestionContainerEntities') {
					var textValue = $('.searchSuggestionContainer .suggestionItem.selected').find('.suggestionItemLabel').text(),
						secondaryText = $('.searchSuggestionContainer .suggestionItem.selected').find('.partyIndicator').text(),
						factionID = $('.searchSuggestionContainer .suggestionItem.selected').find('.partyIndicator').attr('data-faction'),
						itemID = $('.searchSuggestionContainer .suggestionItem.selected').attr('data-item-id'),
						entityType = $('.searchSuggestionContainer .suggestionItem.selected').attr('data-type');
					
					// Handle different entity types
					if (entityType == 'person') {
						addQueryItem('person', textValue, secondaryText, itemID, factionID);
					} else {
						// For non-person entities, add as entity type
						addQueryItem(entityType, textValue, null, itemID);
					}
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

	// Update filter controller from URL
	if (filterController) {
		filterController.updateFromUrl();
	}

	//$('[name="person"]').val(getQueryVariable('person'));
	var entityTypes = ['person', 'organisation', 'document', 'term'];
	for (var t = 0; t < entityTypes.length; t++) {
		var entityType = entityTypes[t];
		var paramName = entityType + 'ID[]';
		
		$('#filterForm input[name="' + paramName + '"]').remove();
		var entityIDs = getQueryVariable(entityType + 'ID');
		if ($.isArray(entityIDs)) {
			for (var i = 0; i < entityIDs.length; i++) {
				$('#filterForm').append('<input type="hidden" name="' + paramName + '" value="'+ entityIDs[i] +'">');
			}
		} else if (entityIDs) {
			$('#filterForm').append('<input type="hidden" name="' + paramName + '" value="'+ entityIDs +'">');
		}
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

	$('[name="agendaItemTitle"]').val(getQueryVariable('agendaItemTitle'));

	updateFactionChart();
    $('#timelineVizWrapper').empty();
    updateTimelineViz();	

	/* DATE FUNCTIONS END */

	// Load initial results using the new media results manager
	if (mediaResultsManager) {
		const urlParams = new URLSearchParams(window.location.search);
		const initialQuery = urlParams.toString();
		mediaResultsManager.loadResults(initialQuery, false);
	}
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

		$('#suggestionContainerText, #suggestionContainerEntities').empty();
		selectedSuggestionIndex = null,
		selectedSuggestionColumn = 'suggestionContainerText';

		$('.searchSuggestionContainer').show();
		
		/*
		var firstSuggestionItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">'+ textValue +'</span><span class="ms-2" style="opacity: .68;">[Enter]</span></div>');
		$('#suggestionContainerText').append(firstSuggestionItem);
		*/

		if (textValue.indexOf(' ') != -1) {
			var exactSuggestionItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">"'+ textValue +'"</span><span class="ms-2" style="opacity: .68;">('+ localizedLabels.exactMatch +')</span></div>');
			
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
		    	var wildcardSuggestionBeginItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">'+ textValue +'*</span><span class="ms-2" style="opacity: .68;">('+ localizedLabels.wildcardSearchBegin +')</span></div>');
			
			wildcardSuggestionBeginItem.click(function(evt) {
				var textValue = $(this).children('.suggestionItemLabel').text();
				addQueryItem('text', textValue);
				updateQuery();
			});

			$('#suggestionContainerText').append(wildcardSuggestionBeginItem);

			var wildcardSuggestionEndItem = $('<div class="suggestionItem"><span class="suggestionItemLabel">*'+ textValue.toLowerCase() +'</span><span class="ms-2" style="opacity: .68;">('+ localizedLabels.wildcardSearchEnd +')</span></div>');
			
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

	    if(suggestionsEntitiesAjax && suggestionsEntitiesAjax.readyState != 4){
	        suggestionsEntitiesAjax.abort();
	    }

	    suggestionsEntitiesAjax = $.ajax({
		method: "POST",
		url: './api/v1/?action=autocomplete&itemType=entities&q='+ textValue
	}).done(function(data) {
		renderEntitiesSuggestions(textValue, data.data);
	}).fail(function(err) {
		//console.log(err);
	});

	} else {
		$('.searchSuggestionContainer').hide();
	}
}

function addQueryItem(queryType, queryText, secondaryText, itemID, factionID) {
	var queryItemIcon = queryType === 'text' ? '' : '<span class="icon-type-'+ queryType +' me-2"></span>';
	
	var queryItem = $('<span class="queryItem d-flex align-items-center" data-type="'+ queryType +'">'+ queryItemIcon +'<span class="queryText">'+ queryText +'</span></span>'),
		queryDeleteItem = $('<span class="queryDeleteItem icon-cancel ms-2"></span>');
	
	if (secondaryText) {
		queryItem.append('<span class="ms-2 partyIndicator" data-faction="'+ factionID +'">'+ secondaryText +'</span>');
	}
	
	// Add link icon for entity types (not text) - always before delete button
	if (queryType !== 'text' && itemID) {
		var queryLinkIcon = $('<a href="'+ config.dir.root +'/'+ queryType +'/'+ itemID +'" target="_blank" class="queryLinkIcon icon-link-ext ms-2" title="Go to '+ queryType +' details"></a>');
		queryItem.append(queryLinkIcon);
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
			suggestionItemFrequency = '<span class="badge rounded-pill">'+ data[i].freq +'</span>',
			suggestionItem = $('<div class="suggestionItem d-flex justify-content-between align-items-center" data-type="text">'+ suggestionItemText + suggestionItemFrequency +'</div>');

		suggestionItem.click(function(evt) {
			var textValue = $(this).children('.suggestionItemLabel').text();
			addQueryItem('text', textValue);
			updateQuery();
		});

		$('.searchSuggestionContainer #suggestionContainerText').append(suggestionItem);
	}
}

function renderEntitiesSuggestions(inputValue, data) {
	var maxSuggestions = 6;

	if (data.length == 0) {
		$('.searchSuggestionContainer #suggestionContainerEntities').append('<div class="my-3">'+ localizedLabels.noSuggestionsFound +'</div>');
	} else {
		for (var i = 0; i < data.length; i++) {
			var entityData = data[i];
			var suggestionItemIcon = '<div class="flex-shrink-0 me-2" style="width: 40px; height: 40px;"><div class="rounded-circle">';
			if (entityData.thumbnailURI) {
				suggestionItemIcon += '<img src="'+ entityData.thumbnailURI +'" alt="..." />';
			} else {
				suggestionItemIcon += '<span class="icon-type-'+ entityData.type +'" style="position: absolute;top: 48%;left: 50%;font-size: 18px;transform: translateX(-50%) translateY(-50%);"></span>';
			}
			suggestionItemIcon += '</div></div>';
			var suggestionItemLabel = '<span class="suggestionItemLabel">'+ entityData.label +'</span>';
			var suggestionItemSecondary = '';
			var suggestionItemClass = 'suggestionItem entityPreview d-flex py-1';
			var suggestionItemAttributes = 'data-type="'+ entityData.type +'" data-item-id="'+ entityData.id +'"';
			
			// Handle person-specific data (faction/party indicator)
			if (entityData.type === 'person' && entityData.faction) {
				suggestionItemSecondary = '<span class="ms-2 partyIndicator" data-faction="'+ entityData.faction.id +'">'+ entityData.faction.label +'</span>';
			}
			
			// Handle secondary label for all entity types
			if (entityData.labelAlternative) {
				suggestionItemSecondary += '<div class="small text-muted">'+ entityData.labelAlternative +'</div>';
			}
			
			// Add link icon (aligned to the right)
			var suggestionItemLinkIcon = '<a href="'+ config.dir.root +'/'+ entityData.type +'/'+ entityData.id +'" target="_blank" class="queryLinkIcon icon-link-ext ms-2 flex-shrink-0 d-flex align-items-center suggestion-link-icon" title="Go to '+ entityData.type +' details"></a>';
			
			var suggestionItemContent = '<div class="flex-grow-1">'+ suggestionItemLabel + suggestionItemSecondary +'</div>';
			var suggestionItem = $('<div class="'+ suggestionItemClass +'" '+ suggestionItemAttributes +'>'+ suggestionItemIcon + suggestionItemContent + suggestionItemLinkIcon +'</div>');

			suggestionItem.click(function(evt) {
				// Don't trigger suggestion selection if link icon was clicked
				if ($(evt.target).hasClass('queryLinkIcon') || $(evt.target).closest('.queryLinkIcon').length > 0) {
					return;
				}
				
				var textValue = $(this).find('.suggestionItemLabel').text(),
					secondaryText = $(this).find('.partyIndicator').text(),
					factionID = $(this).find('.partyIndicator').attr('data-faction'),
					itemID = $(this).attr('data-item-id'),
					entityType = $(this).attr('data-type');
				
				// Handle different entity types
				if (entityType == 'person') {
					addQueryItem('person', textValue, secondaryText, itemID, factionID);
				} else {
					// For non-person entities, add as entity type
					addQueryItem(entityType, textValue, null, itemID);
				}
				updateQuery();
			});

			$('.searchSuggestionContainer #suggestionContainerEntities').append(suggestionItem);
			
			if (i >= maxSuggestions-1) {
				break;
			}
		}
	}
}

function updateQuery() {
	$('input[name="q"]').val(getInteractiveQueryValues('text'));
	
	// Handle all entity types
	var entityTypes = ['person', 'organisation', 'document', 'term'];
	var hasEntityFilters = false;
	
	for (var t = 0; t < entityTypes.length; t++) {
		var entityType = entityTypes[t];
		var paramName = entityType + 'ID[]';
		
		// Remove existing inputs for this entity type
		$('#filterForm input[name="' + paramName + '"]').remove();
		
		// Add new inputs for this entity type
		var entityIDs = getInteractiveQueryValues(entityType);
		for (var i = 0; i < entityIDs.length; i++) {
			$('#filterForm').append('<input type="hidden" name="' + paramName + '" value="'+ entityIDs[i] +'">');
		}
		
		if (entityIDs.length > 0) {
			hasEntityFilters = true;
		}
	}
	
	// Use the new media results manager instead of the old updateResultList
	const formData = getSerializedForm();
	if (mediaResultsManager) {
		mediaResultsManager.loadResults(formData);
	}
	
	// Update document title
	if ($('input[name="q"]').val() != '' || hasEntityFilters) {
		if (hasEntityFilters) {
			var newDocumentTitle = '';
			document.title = '';
			$('.searchInputContainer .queryItem[data-item-id]').each(function() {
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

	var view = isMediaManagement ? 'table' : 'grid';
	var includeAllString = isMediaManagement ? '&includeAll=true' : '';

	updateAjax = $.ajax({
		method: "POST",
		url: config.dir.root + "/content/components/result."+view+".php?a=search"+includeAllString+"&queryOnly=1&" + langString + pageString + getSerializedForm()
	}).done(function(data) {
		// Use the same logic as result.grid.php to determine if we should show filters
		// Only these parameters count as "valid search criteria" that warrant showing the filter bar
		var requestQuery = getQueryVariable('q'),
			requestPersonID = getQueryVariable('personID'),
			requestOrganisationID = getQueryVariable('organisationID'),
			requestDocumentID = getQueryVariable('documentID'),
			requestTermID = getQueryVariable('termID');
		
		// Match the exact logic from result.grid.php line 28
		var hasValidSearchCriteria = (requestQuery && requestQuery.length >= 2) || 
									 requestPersonID || 
									 requestOrganisationID || 
									 requestDocumentID || 
									 requestTermID;
		
		if (hasValidSearchCriteria) {
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

		//TODO: fix this
		/*
		if (isMediaManagement) {
			$('#speechListContainer .resultWrapper > nav a').each(function() {
				var thisPage = getQueryVariableFromString('page', $(this).attr('href'));
				if (!thisPage) { thisPage = 1; }
				$(this).attr('href', '#page='+ thisPage);
			});
			//updateListeners('#speechListContainer');
		}
		*/	
	}).fail(function(err) {
		//console.log(err);
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
		/*
		currentQueries[targetSelector] = currentQueries[targetSelector].replace(/&page=[0-9]+/, '');
		currentQueries[targetSelector] += '&page='+ page;
		updateMediaList(currentQueries[targetSelector], targetSelector);
		*/
	});
}

function getInteractiveQueryValues(queryType) {
	var queryItems = $('.searchInputContainer .queryItem[data-type="'+ queryType +'"]');
	
	if (queryType == 'person' || queryType == 'organisation' || queryType == 'document' || queryType == 'term') {
		var queryValue = [];
		queryItems.each(function() {
			queryValue.push( $(this).attr('data-item-id') );
		});

		return queryValue;
	} else {
		var queryValue = '';
		queryItems.each(function() {
			queryValue += $(this).find('.queryText').text()+ ' ';
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

	// Handle person entities
	if ($('[name="personID[]"]').length != 0) {
		var personIDs = $.map($('[name="personID[]"]'), function(el) { return el.value; });
		if (personIDs) {
			if (typeof personDataFromRequest !== 'undefined') {
				// Use server-side data if available
				for (var i = personIDs.length - 1; i >= 0; i--) {
					var label = personDataFromRequest[personIDs[i]].attributes.label;
					if (personDataFromRequest[personIDs[i]].relationships.faction.data) {
						var secondaryLabel = personDataFromRequest[personIDs[i]].relationships.faction.data.attributes.label;
						var factionID = personDataFromRequest[personIDs[i]].relationships.faction.data.id;
						addQueryItem('person', label, secondaryLabel, personIDs[i], factionID);
					} else {
						addQueryItem('person', label, false, personIDs[i]);
					}
				}
			} else {
				// Fetch data via AJAX if server-side data not available
				loadEntityDataAndAddQueryItems('person', personIDs);
			}
		}
	}

	// Handle other entity types
	var entityTypes = ['organisation', 'document', 'term'];
	for (var t = 0; t < entityTypes.length; t++) {
		var entityType = entityTypes[t];
		var paramName = entityType + 'ID[]';
		var dataVarName = entityType + 'DataFromRequest';
		
		if ($('[name="' + paramName + '"]').length != 0) {
			var entityIDs = $.map($('[name="' + paramName + '"]'), function(el) { return el.value; });
			if (entityIDs) {
				if (typeof window[dataVarName] !== 'undefined') {
					// Use server-side data if available
					for (var i = entityIDs.length - 1; i >= 0; i--) {
						var label = window[dataVarName][entityIDs[i]].attributes.label;
						addQueryItem(entityType, label, null, entityIDs[i]);
					}
				} else {
					// Fetch data via AJAX if server-side data not available
					loadEntityDataAndAddQueryItems(entityType, entityIDs);
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

/**
 * Load entity data via AJAX and add query items when server-side data is not available
 */
function loadEntityDataAndAddQueryItems(entityType, entityIDs) {
	// Process each entity ID
	for (var i = entityIDs.length - 1; i >= 0; i--) {
		(function(currentEntityID, currentEntityType) {
			$.ajax({
				method: "POST",
				url: config.dir.root + "/api/v1/",
				data: {
					action: "getItem",
					itemType: currentEntityType,
					id: currentEntityID
				}
			}).done(function(response) {
				if (response && response.data) {
					var label = response.data.attributes.label;
					
					if (currentEntityType === 'person') {
						// Handle person with potential faction data
						if (response.data.relationships && response.data.relationships.faction && response.data.relationships.faction.data) {
							var secondaryLabel = response.data.relationships.faction.data.attributes.label;
							var factionID = response.data.relationships.faction.data.id;
							addQueryItem('person', label, secondaryLabel, currentEntityID, factionID);
						} else {
							addQueryItem('person', label, false, currentEntityID);
						}
					} else {
						// Handle other entity types
						addQueryItem(currentEntityType, label, null, currentEntityID);
					}
				}
			}).fail(function(err) {
				console.warn('Failed to load ' + currentEntityType + ' data for ID:', currentEntityID, err);
				// Add a fallback query item with just the ID as label
				addQueryItem(currentEntityType, currentEntityID, null, currentEntityID);
			});
		})(entityIDs[i], entityType);
	}
}

function updateTimelineViz() {
    if (typeof resultsAttributes !== "object") {
        return;
    }
    
    // Prepare data for the timeline
    var timelineData = [];
    for (let day in resultsAttributes["days"]) {
        timelineData.push({
            date: day,
            count: resultsAttributes["days"][day]["doc_count"],
            originalData: {
                date: day,
                count: resultsAttributes["days"][day]["doc_count"]
            }
        });
    }
    
    // Initialize or update the timeline visualization
    if (!timelineViz) {
        timelineViz = new TimelineViz({
            container: 'timelineVizWrapper',
            data: timelineData,
            minDate: minDate,
            maxDate: maxDate
        });
    } else {
        timelineViz.update(timelineData, minDate, maxDate);
    }
}

function updateFactionChart() {
    if (typeof resultsAttributes !== "object") {
        return;
    }

    var speechesPerFaction = resultsAttributes["resultsPerFaction"];
    
    // Check if the chart container exists
    var chartContainer = document.getElementById('factionChart');
    if (!chartContainer) {
        return;
    }
    
    // Clear any existing chart
    d3.select("#factionChart").selectAll("*").remove();
    
    // Store previous data for animation
    var previousData = window.factionChartData || [];
    window.factionChartData = []; // Will be populated below
    
    // Prepare data for D3
    var data = [];
    
    if (!speechesPerFaction || Object.keys(speechesPerFaction).length === 0) {
        data.push({ faction: ' ', value: 1, color: '#aaaaaa' });
    } else {
        for (var faction in speechesPerFaction) {
            if (speechesPerFaction.hasOwnProperty(faction)) {
                var factionColor = (factionIDColors[faction]) ? factionIDColors[faction] : "#aaaaaa";
                data.push({
                    faction: faction,
                    value: speechesPerFaction[faction],
                    color: factionColor
                });
            }
        }
    }
    
    // Store the new data for next update
    window.factionChartData = data;
    
    // Set up dimensions - use the parent container's dimensions
    var containerWidth = chartContainer.parentElement.clientWidth;
    var containerHeight = chartContainer.parentElement.clientHeight;
    var width = containerWidth;
    var height = containerHeight;
    
    // Create SVG container
    var svg = d3.select("#factionChart")
        .append("svg")
        .attr("width", width)
        .attr("height", height)
        .append("g")
        .attr("transform", "translate(" + width / 2 + "," + height / 2 + ")");
    
    // Calculate radius based on the smaller dimension
    var radius = Math.min(width, height) / 2;
    
    // Create color scale
    var color = d3.scaleOrdinal()
        .domain(data.map(d => d.faction))
        .range(data.map(d => d.color));
    
    // Create pie generator
    var pie = d3.pie()
        .value(d => d.value)
        .sort(null);
    
    // Create arc generator for the doughnut
    var arc = d3.arc()
        .outerRadius(radius)
        .innerRadius(radius * 0.75);
    
    // Add the arcs to the SVG
    var arcs = svg.selectAll(".arc")
        .data(pie(data))
        .enter()
        .append("g")
        .attr("class", "arc");
    
    // Add the paths
    arcs.append("path")
        .attr("d", arc)
        .style("fill", d => d.data.color)
        .style("stroke", "#fff")
        .style("stroke-width", "1px");
    
    // Add tooltips
    arcs.append("title")
        .text(d => d.data.faction + ": " + d.data.value);
        
    // Add animation
    if (previousData.length > 0) {
        // If we have previous data, animate from that state
        var previousPie = d3.pie()
            .value(d => d.value)
            .sort(null);
            
        var previousArcs = previousPie(previousData);
        
        // Match previous data to current data by faction
        var previousArcsMap = {};
        previousArcs.forEach(function(d) {
            previousArcsMap[d.data.faction] = d;
        });
        
        // Animate from previous state to current state
        arcs.selectAll("path")
            .attr("d", function(d) {
                // Find matching previous arc or use zero angles
                var prevArc = previousArcsMap[d.data.faction] || { startAngle: 0, endAngle: 0 };
                return arc(prevArc);
            })
            .transition()
            .duration(500)
            .ease(d3.easeQuadInOut)
            .attrTween("d", function(d) {
                var prevArc = previousArcsMap[d.data.faction] || { startAngle: 0, endAngle: 0 };
                var interpolate = d3.interpolate(
                    { startAngle: prevArc.startAngle, endAngle: prevArc.endAngle },
                    { startAngle: d.startAngle, endAngle: d.endAngle }
                );
                return function(t) {
                    return arc(interpolate(t));
                };
            });
    } else {
        // If no previous data, animate from zero
        arcs.selectAll("path")
            .attr("d", function(d) {
                // Create a copy of the data with zero angles
                var d0 = {
                    startAngle: 0,
                    endAngle: 0,
                    data: d.data
                };
                return arc(d0);
            })
            .transition()
            .duration(500)
            .ease(d3.easeQuadInOut)
            .attrTween("d", function(d) {
                var interpolate = d3.interpolate(
                    { startAngle: 0, endAngle: 0 },
                    { startAngle: d.startAngle, endAngle: d.endAngle }
                );
                return function(t) {
                    return arc(interpolate(t));
                };
            });
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

		// If we've reached our last frame, stop the animation
		if ( frame === totalFrames ) {
			clearInterval( counter );
		}
	}, frameDuration );
}

function runCounterAnimation() {
	const countupEls = document.querySelectorAll( '.countup' );
	countupEls.forEach( animateCountUp );
}

/**
 * Initialize the FilterController and MediaResults components
 */
function initializeComponents() {
	// Initialize filter controller
	filterController = new FilterController({
		mode: 'url-driven',
		baseUrl: '/search',
		onFilterChange: function(formData) {
			updateQuery();
		}
	});

	// Initialize media results manager
	mediaResultsManager = getMediaResultsManager('#speechListContainer', {
		mode: 'url-driven',
		view: 'grid',
		baseUrl: '/search'
	});

	// Set up callback for chart updates when results are loaded
	mediaResultsManager.onLoaded(function(data) {
		// Update filter bar visibility based on valid search criteria
		updateFilterBarVisibility();
		
		updateFactionChart();
		$('#timelineVizWrapper').empty();
		updateTimelineViz();
		runCounterAnimation();
	});
	
	// Load initial results from URL on page load
	const urlParams = new URLSearchParams(window.location.search);
	const initialQuery = urlParams.toString();
	if (initialQuery || window.location.pathname === '/search' || window.location.pathname.endsWith('/')) {
		mediaResultsManager.loadResults(initialQuery, false);
	}
}

/**
 * Initialize search-specific UI components (suggestions, query building)
 */
function initializeSearchUI() {
	// Search suggestion functionality remains here as it's search-page specific
	// Filter and result management is now handled by the components
}

/**
 * Update filter bar visibility based on valid search criteria
 * This matches the logic in result.grid.php for consistency
 */
function updateFilterBarVisibility() {
	// Use the same logic as result.grid.php to determine if we should show filters
	// Only these parameters count as "valid search criteria" that warrant showing the filter bar
	var requestQuery = getQueryVariable('q'),
		requestPersonID = getQueryVariable('personID'),
		requestOrganisationID = getQueryVariable('organisationID'),
		requestDocumentID = getQueryVariable('documentID'),
		requestTermID = getQueryVariable('termID');
	
	// Match the exact logic from result.grid.php line 28
	var hasValidSearchCriteria = (requestQuery && requestQuery.length >= 2) || 
								 requestPersonID || 
								 requestOrganisationID || 
								 requestDocumentID || 
								 requestTermID;
	
	if (hasValidSearchCriteria) {
		$('#filterbar').removeClass('nosearch');
		$('.filterContainer').removeClass('d-none').addClass('d-md-block');
		$('#toggleFilterContainer').removeClass('d-none').addClass('d-block');
	} else {
		$('#filterbar').addClass('nosearch');
		$('.filterContainer').removeClass('d-md-block').addClass('d-none');
		$('#toggleFilterContainer').removeClass('d-block').addClass('d-none');
	}
}