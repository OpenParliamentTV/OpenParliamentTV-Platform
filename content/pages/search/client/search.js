// Search Page Controller - handles search-specific UI and orchestrates components
var minDate = new Date("2013-10-01"); // Fallback date - actual minDate calculated dynamically from electoral periods
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

	window.onpopstate = function(event) {
		updateContentsFromURL();
	}


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




});

function updateContentsFromURL() {

	// Update filter controller from URL
	if (filterController) {
		filterController.updateFromUrl();
	}

	// Query text field (not handled by FilterController)
	$('[name="q"]').val(getQueryVariable('q'));
	
	initInteractiveQueryValues();


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
	if (mediaResultsManager && filterController) {
		const formData = filterController.getSerializedForm();
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
    
    // Get electoral periods from search results if available
    var electoralPeriodsData = [];
    if (resultsAttributes && resultsAttributes.electoralPeriods) {
        electoralPeriodsData = resultsAttributes.electoralPeriods;
    }
    
    // Calculate dynamic minDate from electoral periods data or use fallback
    var dynamicMinDate = calculateTimelineMinDate(electoralPeriodsData, minDate.toISOString().slice(0,10));
    
    // Function to initialize timeline with electoral periods
    function initTimeline() {
        if (!timelineViz) {
            timelineViz = new TimelineViz({
                container: 'timelineVizWrapper',
                data: timelineData,
                minDate: dynamicMinDate,
                maxDate: maxDate,
                showElectoralPeriods: true,
                electoralPeriods: electoralPeriodsData || []
            });
        } else {
            timelineViz.update(timelineData, dynamicMinDate, maxDate);
        }
    }
    
    initTimeline();
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
    
    // Prepare data for the generic chart function
    var data = [];
    
    if (!speechesPerFaction || Object.keys(speechesPerFaction).length === 0) {
        data.push({ 
            id: 'empty', 
            label: 'No data', 
            value: 1, 
            color: '#aaaaaa' 
        });
    } else {
        for (var faction in speechesPerFaction) {
            if (speechesPerFaction.hasOwnProperty(faction)) {
                data.push({
                    id: faction,
                    label: faction,
                    value: speechesPerFaction[faction]
                });
            }
        }
    }
    
    // Initialize or update the chart using the generic function
    if (!window.factionChart) {
        window.factionChart = renderDonutChart({
            container: '#factionChart',
            data: data,
            type: 'donut',
            colorType: 'factions',
            valueField: 'value',
            labelField: 'label',
            idField: 'id',
            animate: true,
            animationDuration: 500,
            innerRadius: 0.75,
            margin: 1,
            showTooltips: false,
            enableAutoResize: false
        });
    } else {
        window.factionChart.update(data);
    }
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
	
	// Simple resize handler for faction chart
	$(window).resize(function() {
		if (typeof resultsAttributes === "object" && window.factionChart) {
			updateFactionChart();
		}
	});
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