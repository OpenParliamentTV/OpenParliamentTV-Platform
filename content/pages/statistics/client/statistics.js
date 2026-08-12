/**
 * Statistics page controller.
 *
 * Server-provided values arrive in the OPTV_STATISTICS object that
 * content/pages/statistics/page.php prints: the parliament being shown, the date
 * its data starts (the beginning of its earliest electoral period), and the
 * pre-rendered overview figures. Nothing here hardcodes a parliament or a date.
 *
 * Localized strings come from `localizedLabels`, which content/base.php inlines
 * for the active language.
 */
// Set default end date to today
document.getElementById('wordTrendsEndDate').value = new Date().toISOString().split('T')[0];
document.getElementById('factionWordEndDate').value = new Date().toISOString().split('T')[0];

// General statistics data from server
var generalStatsData = OPTV_STATISTICS.general;

// Word trends interactive variables
var wordTrendsSuggestionsAjax;
var selectedWordSuggestionIndex = null;
var wordTrendsTimelineViz = null;
var wordTrendsSlider = null;

// Faction word trends variables
var factionWordSuggestionsAjax;
var selectedFactionWordSuggestionIndex = null;
var factionWordTimelineViz = null;
var factionWordSlider = null;
var currentFactionWord = null;

// Default timeline range
var minDate = new Date(OPTV_STATISTICS.dataStart);
var maxDate = new Date();

// Initialize word trends interactive interface and general statistics charts
$(document).ready(function() {
	initializeWordTrendsInterface();
	initializeFactionWordInterface();
	initializeGeneralStatisticsCharts();
});

// Initialize timeline after all scripts are loaded
$(window).on('load', function() {
	// Give timeline.js a moment to load if needed
	setTimeout(function() {
		if (typeof TimelineViz === 'undefined') {
			// TimelineViz not found - timeline.js may not be loaded
		} else {
			// TimelineViz available, timeline.js loaded successfully
			// Re-initialize timeline now that TimelineViz is available
			initializeWordTrendsTimeline();
			initializeFactionWordTimeline();
		}
	}, 100);
});

function initializeWordTrendsInterface() {
	// Initialize search interface components (timeline initialized separately)
	// Initialize slider immediately since Word Trends tab is active by default
	initializeWordTrendsSlider();
	
	// Also initialize slider when Word Trends tab is shown (for when switching tabs)
	$('#word-trends-tab').on('shown.bs.tab', function (e) {
		initializeWordTrendsSlider();
	});
	
	// Handle input events
	$('#wordTrendsQuery').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			// Only allow Enter if a suggestion is visible and selected
			if ($('.searchSuggestionContainer').is(':visible') && $('.searchSuggestionContainer .suggestionItem.selected').length > 0) {
				var textValue = $('.searchSuggestionContainer .suggestionItem.selected .suggestionItemLabel').text();
				addWordQueryItem(textValue);
				updateWordTrendsVisualization();
			}
			return false;
		} else if ($('#wordTrendsQuery').val() == '' && (evt.keyCode == 8 || evt.keyCode == 46)) {
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

	$('#wordTrendsQuery').keyup(delay(function(evt) {
		if (evt.keyCode == 40 || evt.keyCode == 38 || evt.keyCode == 37 || evt.keyCode == 39) {return false;}
		updateWordSuggestions();
	}, 600));
	
	// Handle suggestion navigation
	$(document).keyup(function(evt) {
		if ($('#word-trends').hasClass('active')) {
			if (evt.keyCode == 40) { 
				// down
				if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem').length == 0) {
					return false;
				} 
				if (selectedWordSuggestionIndex === null) {
					selectedWordSuggestionIndex = 0;
				} else if ((selectedWordSuggestionIndex+1) < $('#wordSuggestionContainer .suggestionItem').length) {
					selectedWordSuggestionIndex++; 
				}
				$('.searchSuggestionContainer .suggestionItem').removeClass('selected');
				$('#wordSuggestionContainer .suggestionItem:eq('+selectedWordSuggestionIndex+')').addClass('selected');
				var result = $('#wordSuggestionContainer .suggestionItem:eq('+selectedWordSuggestionIndex+') .suggestionItemLabel').text();
				$('#wordTrendsQuery').val(result);  
				return false;
			} else if (evt.keyCode == 38) { 
				// up
				if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem').length == 0) {
					return false;
				} 
				$('.searchSuggestionContainer .suggestionItem').removeClass('selected');

				if (selectedWordSuggestionIndex === null) {
					return false;
				} else if (selectedWordSuggestionIndex > 0) {
					selectedWordSuggestionIndex--;            
				} else if (selectedWordSuggestionIndex == 0) {
					$('#wordTrendsQuery').val($('.searchSuggestionContainer').data('current'));
					selectedWordSuggestionIndex = null;
					return false;
				}

				$('#wordSuggestionContainer .suggestionItem:eq('+selectedWordSuggestionIndex+')').addClass('selected');
				var result = $('#wordSuggestionContainer .suggestionItem:eq('+selectedWordSuggestionIndex+') .suggestionItemLabel').text();
				$('#wordTrendsQuery').val(result);  
				return false;
			} else if (evt.keyCode == 13) { 
				// enter
				if ($('.searchSuggestionContainer').is(':visible') && 
					$('.searchSuggestionContainer .suggestionItem.selected').length > 0) {
					var textValue = $('.searchSuggestionContainer .suggestionItem.selected .suggestionItemLabel').text();
					addWordQueryItem(textValue);
					updateWordTrendsVisualization();
				}
			}
		}
	});

	$(document).click(function(evt) {
		$('.searchInputContainer').removeClass('active');
		$('.searchSuggestionContainer').hide();
		$('.searchInputContainer .queryItem.markedForDeletion').removeClass('markedForDeletion');
	});

	$('#wordTrendsQuery').click(function(evt) {
		$('.searchInputContainer').addClass('active');
		updateWordSuggestions();
		evt.stopPropagation();
	});

	$('.searchInputContainer').click(function(evt) {
		if ($(evt.target).hasClass('searchInputContainer')) {
			$(this).addClass('active');
			$('#wordTrendsQuery').focus();
			evt.stopPropagation();
		}
	});

	$('.searchSuggestionContainer').click(function(evt) {
		evt.stopPropagation();
	});

	// Faction filter change handler
	$('.wordTrendsFactionCheckbox').change(function() {
		updateWordTrendsVisualization();
	});
	
	// Example link handler
	$('#wordTrendsExample').click(function(evt) {
		evt.preventDefault();
		
		// Clear existing words first
		$('.searchInputContainer .queryItem').remove();
		if (window.wordColorAssignments) {
			window.wordColorAssignments.clear();
		}
		
		// Add the example words
		const exampleWords = ['pandemie', 'flüchtlinge', 'ukraine'];
		exampleWords.forEach(word => {
			addWordQueryItem(word);
		});
		
		// Update visualization
		updateWordTrendsVisualization();
	});
}

// Initialize faction word interface
function initializeFactionWordInterface() {
	// Initialize slider for faction word trends
	initializeFactionWordSlider();
	
	// Handle input events for faction word query
	$('#factionWordQuery').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			// Only allow Enter if a suggestion is visible and selected
			if ($('#factionWordSuggestionContainer').is(':visible') && $('#factionWordSuggestionContainer .suggestionItem.selected').length > 0) {
				var textValue = $('#factionWordSuggestionContainer .suggestionItem.selected .suggestionItemLabel').text();
				setFactionWord(textValue);
			}
			return false;
		}
	});

	$('#factionWordQuery').keyup(delay(function(evt) {
		if (evt.keyCode == 40 || evt.keyCode == 38 || evt.keyCode == 37 || evt.keyCode == 39) {return false;}
		updateFactionWordSuggestions();
	}, 600));
	
	// Handle suggestion navigation for faction word
	$(document).keyup(function(evt) {
		if ($('#word-trends').hasClass('active') && $('#factionWordQuery').is(':focus')) {
			if (evt.keyCode == 40) { 
				// down
				if (!$('#factionWordSuggestionContainer').is(':visible') || $('#factionWordSuggestionContainer .suggestionItem').length == 0) {
					return false;
				} 
				if (selectedFactionWordSuggestionIndex === null) {
					selectedFactionWordSuggestionIndex = 0;
				} else if ((selectedFactionWordSuggestionIndex+1) < $('#factionWordSuggestionList .suggestionItem').length) {
					selectedFactionWordSuggestionIndex++; 
				}
				$('#factionWordSuggestionContainer .suggestionItem').removeClass('selected');
				$('#factionWordSuggestionList .suggestionItem:eq('+selectedFactionWordSuggestionIndex+')').addClass('selected');
				var result = $('#factionWordSuggestionList .suggestionItem:eq('+selectedFactionWordSuggestionIndex+') .suggestionItemLabel').text();
				$('#factionWordQuery').val(result);  
				return false;
			} else if (evt.keyCode == 38) { 
				// up
				if (!$('#factionWordSuggestionContainer').is(':visible') || $('#factionWordSuggestionContainer .suggestionItem').length == 0) {
					return false;
				} 
				$('#factionWordSuggestionContainer .suggestionItem').removeClass('selected');

				if (selectedFactionWordSuggestionIndex === null) {
					return false;
				} else if (selectedFactionWordSuggestionIndex > 0) {
					selectedFactionWordSuggestionIndex--;            
				} else if (selectedFactionWordSuggestionIndex == 0) {
					$('#factionWordQuery').val($('#factionWordSuggestionContainer').data('current'));
					selectedFactionWordSuggestionIndex = null;
					return false;
				}

				$('#factionWordSuggestionList .suggestionItem:eq('+selectedFactionWordSuggestionIndex+')').addClass('selected');
				var result = $('#factionWordSuggestionList .suggestionItem:eq('+selectedFactionWordSuggestionIndex+') .suggestionItemLabel').text();
				$('#factionWordQuery').val(result);  
				return false;
			} else if (evt.keyCode == 13) { 
				// enter
				if ($('#factionWordSuggestionContainer').is(':visible') && 
					$('#factionWordSuggestionContainer .suggestionItem.selected').length > 0) {
					var textValue = $('#factionWordSuggestionContainer .suggestionItem.selected .suggestionItemLabel').text();
					setFactionWord(textValue);
				}
			}
		}
	});

	$(document).click(function(evt) {
		$('#factionWordSuggestionContainer').hide();
	});

	$('#factionWordQuery').click(function(evt) {
		updateFactionWordSuggestions();
		evt.stopPropagation();
	});

	$('#factionWordSuggestionContainer').click(function(evt) {
		evt.stopPropagation();
	});
}

// Note: delay function is already available from generic.js

// Faction word suggestions functions
function updateFactionWordSuggestions() {
	var textValue = $('#factionWordQuery').val();

	if (textValue.length >= 2) {
		if (textValue == $('#factionWordSuggestionContainer').data('current')) {
			$('#factionWordSuggestionContainer').show();
			return false;
		}

		$('#factionWordSuggestionContainer').data('current', textValue);
		$('#factionWordSuggestionList').empty();
		selectedFactionWordSuggestionIndex = null;
		$('#factionWordSuggestionContainer').show();

		if(factionWordSuggestionsAjax && factionWordSuggestionsAjax.readyState != 4){
	        factionWordSuggestionsAjax.abort();
	    }

	    factionWordSuggestionsAjax = $.ajax({
			method: "POST",
			url: './api/v1/autocomplete/text?q='+ textValue
		}).done(function(data) {
			if (data && data.data) {
				renderFactionWordSuggestions(textValue, data.data);
			} else {
				$('#factionWordSuggestionContainer').hide();
			}
		}).fail(function() {
			$('#factionWordSuggestionContainer').hide();
		});
	} else {
		$('#factionWordSuggestionContainer').hide();
	}
}

function renderFactionWordSuggestions(inputValue, data) {
	if (!data || !Array.isArray(data)) {
		return;
	}
	
	for (var i = 0; i < data.length; i++) {
		var suggestionItemText = '<span class="suggestionItemLabel">'+ data[i].text +'</span>',
			suggestionItemFrequency = '<span class="badge rounded-pill">'+ data[i].freq +'</span>',
			suggestionItem = $('<div class="suggestionItem d-flex justify-content-between align-items-center" data-type="text">'+ suggestionItemText + suggestionItemFrequency +'</div>');

		suggestionItem.click(function(evt) {
			var textValue = $(this).children('.suggestionItemLabel').text();
			setFactionWord(textValue);
		});

		$('#factionWordSuggestionList').append(suggestionItem);
	}
}

function setFactionWord(word) {
	currentFactionWord = word;
	$('#factionWordQuery').val(word);
	$('#factionWordSuggestionContainer').hide();
	updateFactionWordVisualization();
}

// Word suggestions functions
function updateWordSuggestions() {
	var textValue = $('#wordTrendsQuery').val();

	if (textValue.length >= 2) {
		if (textValue == $('.searchSuggestionContainer').data('current')) {
			$('.searchSuggestionContainer').show();
			return false;
		}

		$('.searchSuggestionContainer').data('current', textValue);
		$('#wordSuggestionContainer').empty();
		selectedWordSuggestionIndex = null;
		$('.searchSuggestionContainer').show();

		if(wordTrendsSuggestionsAjax && wordTrendsSuggestionsAjax.readyState != 4){
	        wordTrendsSuggestionsAjax.abort();
	    }

	    wordTrendsSuggestionsAjax = $.ajax({
			method: "POST",
			url: './api/v1/autocomplete/text?q='+ textValue
		}).done(function(data) {
			if (data && data.data) {
				renderWordSuggestions(textValue, data.data);
			} else {
				$('.searchSuggestionContainer').hide();
			}
		}).fail(function() {
			$('.searchSuggestionContainer').hide();
		});
	} else {
		$('.searchSuggestionContainer').hide();
	}
}

function renderWordSuggestions(inputValue, data) {
	if (!data || !Array.isArray(data)) {
		return;
	}
	
	for (var i = 0; i < data.length; i++) {
		var suggestionItemText = '<span class="suggestionItemLabel">'+ data[i].text +'</span>',
			suggestionItemFrequency = '<span class="badge rounded-pill">'+ data[i].freq +'</span>',
			suggestionItem = $('<div class="suggestionItem d-flex justify-content-between align-items-center" data-type="text">'+ suggestionItemText + suggestionItemFrequency +'</div>');

		suggestionItem.click(function(evt) {
			var textValue = $(this).children('.suggestionItemLabel').text();
			addWordQueryItem(textValue);
			updateWordTrendsVisualization();
		});

		$('#wordSuggestionContainer').append(suggestionItem);
	}
}

// Query item management

function addWordQueryItem(wordText) {
	// Check if word already exists
	var existingWord = $('.searchInputContainer .queryItem[data-word="' + wordText + '"]');
	if (existingWord.length > 0) {
		return; // Don't add duplicates
	}

	var queryItem = $('<span class="queryItem d-flex align-items-center" data-type="text" data-word="'+ wordText +'"><span class="queryText">'+ wordText +'</span></span>'),
		queryDeleteItem = $('<span class="queryDeleteItem icon-cancel ms-2"></span>');
	
	queryDeleteItem.click(function(evt) {
		evt.preventDefault();
		evt.stopPropagation();
		
		const wordToRemove = $(this).parents('.queryItem').attr('data-word');
		$(this).parents('.queryItem').remove();
		
		// Remove color assignment to free up the color for reuse
		if (window.wordColorAssignments && wordToRemove) {
			window.wordColorAssignments.delete(wordToRemove);
		}
		
		$('.searchInputContainer').addClass('active');
		$('#wordTrendsQuery').focus();
		updateWordTrendsVisualization();
	});

	queryItem.append(queryDeleteItem);

	$('.searchInputContainer input#wordTrendsQuery').val('');
	updateWordSuggestions();
	$('.searchInputContainer input#wordTrendsQuery').before(queryItem);
}

function getSelectedWords() {
	var words = [];
	$('.searchInputContainer .queryItem[data-type="text"]').each(function() {
		words.push($(this).attr('data-word'));
	});
	return words;
}

// Electoral periods fetching function
async function fetchElectoralPeriods() {
	try {
		const response = await fetch('/api/v1/search/media?limit=1&parliament=' + encodeURIComponent(OPTV_STATISTICS.parliament));
		const data = await response.json();
		
		if (data && data.meta && data.meta.requestStatus === 'success' && data.meta.attributes && data.meta.attributes.electoralPeriods) {
			return data.meta.attributes.electoralPeriods || [];
		}
		return [];
	} catch (error) {
		console.warn('Error fetching electoral periods:', error);
		return [];
	}
}

// Slider initialization (separate from timeline)
function initializeWordTrendsSlider() {
	
	// Check if jQuery and slider are available
	if (typeof $ === 'undefined') {
		return;
	}
	
	if (!$.fn.slider) {
		return;
	}
	
	// Check if slider element exists
	const sliderElement = $("#wordTrendsSliderRange");
	if (sliderElement.length === 0) {
		return;
	}
	
	// Check if slider is already initialized
	if (sliderElement.hasClass('ui-slider')) {
		return;
	}
	
	const startTime = minDate.getTime();
	const endTime = maxDate.getTime();
	const defaultStart = minDate.getTime();
	const defaultEnd = maxDate.getTime();
	
	sliderElement.slider({
		range: true,
		min: startTime,
		max: endTime,
		values: [defaultStart, defaultEnd],
		slide: function(event, ui) {
			const startDate = new Date(ui.values[0]);
			const endDate = new Date(ui.values[1]);
			document.getElementById('wordTrendsTimeRange').value = formatDateRange(startDate, endDate);
		},
		stop: function(event, ui) {
			const startDate = new Date(ui.values[0]);
			const endDate = new Date(ui.values[1]);
			updateWordTrendsDateRange(startDate, endDate);
		}
	});
	
	// Set initial date range
	updateWordTrendsDateRange(new Date(defaultStart), new Date(defaultEnd));
}

// Timeline initialization
function initializeWordTrendsTimeline() {
	
	if (typeof TimelineViz === 'undefined') {
		return;
	}
	
	// Fetch electoral periods and initialize timeline (without data bars)
	fetchElectoralPeriods().then(function(electoralPeriods) {
		
		try {
			wordTrendsTimelineViz = new TimelineViz({
				container: 'wordTrendsTimelineWrapper',
				data: [], // No timeline bars, just electoral periods
				minDate: minDate,
				maxDate: maxDate,
				showElectoralPeriods: true,
				electoralPeriods: electoralPeriods || []
			});
		} catch (error) {
			console.warn('Timeline initialization failed:', error);
		}
	});
}

// Faction word slider initialization
function initializeFactionWordSlider() {
	
	// Check if jQuery and slider are available
	if (typeof $ === 'undefined') {
		return;
	}
	
	if (!$.fn.slider) {
		return;
	}
	
	// Check if slider element exists
	const sliderElement = $("#factionWordSliderRange");
	if (sliderElement.length === 0) {
		return;
	}
	
	// Check if slider is already initialized
	if (sliderElement.hasClass('ui-slider')) {
		return;
	}
	
	const startTime = minDate.getTime();
	const endTime = maxDate.getTime();
	const defaultStart = minDate.getTime();
	const defaultEnd = maxDate.getTime();
	
	sliderElement.slider({
		range: true,
		min: startTime,
		max: endTime,
		values: [defaultStart, defaultEnd],
		slide: function(event, ui) {
			const startDate = new Date(ui.values[0]);
			const endDate = new Date(ui.values[1]);
			document.getElementById('factionWordTimeRange').value = formatDateRange(startDate, endDate);
		},
		stop: function(event, ui) {
			const startDate = new Date(ui.values[0]);
			const endDate = new Date(ui.values[1]);
			updateFactionWordDateRange(startDate, endDate);
		}
	});
	
	// Set initial date range
	updateFactionWordDateRange(new Date(defaultStart), new Date(defaultEnd));
}

// Faction word timeline initialization
function initializeFactionWordTimeline() {
	
	if (typeof TimelineViz === 'undefined') {
		return;
	}
	
	// Fetch electoral periods and initialize timeline
	fetchElectoralPeriods().then(function(electoralPeriods) {
		
		try {
			factionWordTimelineViz = new TimelineViz({
				container: 'factionWordTimelineWrapper',
				data: [], // No timeline bars, just electoral periods
				minDate: minDate,
				maxDate: maxDate,
				showElectoralPeriods: true,
				electoralPeriods: electoralPeriods || []
			});
		} catch (error) {
			console.warn('Faction word timeline initialization failed:', error);
		}
	});
}

// Unified date formatting function
function formatDateRange(startDate, endDate) {
	const formatDate = (date) => {
		return date.toLocaleDateString('en-US', { 
			year: 'numeric', 
			month: 'short', 
			day: 'numeric' 
		});
	};
	return formatDate(startDate) + ' - ' + formatDate(endDate);
}

function updateFactionWordDateRange(startDate, endDate) {
	const startStr = startDate.toISOString().split('T')[0];
	const endStr = endDate.toISOString().split('T')[0];
	
	// Update hidden inputs and display
	document.getElementById('factionWordStartDate').value = startStr;
	document.getElementById('factionWordEndDate').value = endStr;
	document.getElementById('factionWordTimeRange').value = formatDateRange(startDate, endDate);
	
	// Update visualization if word is selected
	if (currentFactionWord) {
		updateFactionWordVisualization();
	}
}

function updateWordTrendsDateRange(startDate, endDate) {
	const startStr = startDate.toISOString().split('T')[0];
	const endStr = endDate.toISOString().split('T')[0];
	
	// Update hidden inputs and display
	document.getElementById('wordTrendsStartDate').value = startStr;
	document.getElementById('wordTrendsEndDate').value = endStr;
	document.getElementById('wordTrendsTimeRange').value = formatDateRange(startDate, endDate);
	
	// Update visualization if words are selected
	if (getSelectedWords().length > 0) {
		updateWordTrendsVisualization();
	}
}

// Auto-updating visualization
function updateWordTrendsVisualization() {
	var selectedWords = getSelectedWords();
	
	if (selectedWords.length === 0) {
		// Show empty state in chart area
		$('#wordTrendsChart').html(
			'<div class="text-center text-muted py-3">' +
			'<p class="small">' + statisticsLabel('statisticsNoWordsSelected') + '</p>' +
			'</div>'
		);
		return;
	}

	// Get filter values
	var startDate = $('#wordTrendsStartDate').val() || OPTV_STATISTICS.dataStart;
	var endDate = $('#wordTrendsEndDate').val() || new Date().toISOString().split('T')[0];
	
	// Get selected factions from checkboxes
	var selectedFactions = [];
	$('.wordTrendsFactionCheckbox:checked').each(function() {
		selectedFactions.push($(this).val());
	});
	
	// Prepare parameters. `match` decides whether inflected forms are counted
	// together; `normalize=relative` additionally returns the month's total word
	// count, which is what turns occurrences into a rate.
	var params = {
		words: selectedWords,
		startDate: startDate,
		endDate: endDate,
		parliament: OPTV_STATISTICS.parliament,
		match: $('#statisticsMatchMode').val() || 'stem',
		normalize: $('#statisticsNormalizeMode').val() || 'absolute'
	};

	if (selectedFactions.length > 0) {
		params.factions = selectedFactions;
	}

	// Make API call
	makeApiRequest('word-trends', params).then(function(result) {
		if (result.errors) {
			console.error('API returned errors:', result.errors);
			$('#wordTrendsChart').html(
				'<div class="alert alert-danger">' + (result.errors[0].detail || 'Unknown API error') + '</div>'
			);
			return;
		}
		
		displayWordTrends(result.data);
	}).catch(function(error) {
		console.error('Word trends error:', error);
		$('#wordTrendsChart').html(
			'<div class="alert alert-danger">Error loading word trends data: ' + (error.message || error) + '</div>'
		);
	});
}

// API request helper
async function makeApiRequest(endpoint, params = {}) {
	try {
		const url = new URL('/api/v1/statistics/' + endpoint, window.location.origin);

		Object.entries(params).forEach(([key, value]) => {
			if (value !== null && value !== undefined && value !== '') {
				if (Array.isArray(value)) {
					value.forEach(v => url.searchParams.append(key + '[]', v));
				} else {
					url.searchParams.append(key, value);
				}
			}
		});
		
		const response = await fetch(url);
		const data = await response.json();
		
		return data;
	} catch (error) {
		return { errors: [{ detail: error.message }] };
	}
}

// Utility function to create Bootstrap tables
function createTable(data, columns, tableId) {
	if (!data || data.length === 0) {
		return '<div class="text-muted text-center py-3">' + statisticsLabel('statisticsNoData') + '</div>';
	}
	
	let html = `<div class="table-responsive"><table class="table table-striped table-hover" id="${tableId}">`;
	html += '<thead><tr>';
	columns.forEach(col => {
		html += `<th>${col.title}</th>`;
	});
	html += '</tr></thead><tbody>';
	
	data.forEach(row => {
		html += '<tr>';
		columns.forEach(col => {
			let value = row[col.field];
			if (col.formatter) {
				value = col.formatter(value, row);
			}
			html += `<td>${value || '-'}</td>`;
		});
		html += '</tr>';
	});
	
	html += '</tbody></table></div>';
	return html;
}

// Format number with thousand separators
function formatNumber(num) {
	return new Intl.NumberFormat().format(num);
}

// Create entity link
function createEntityLink(entity, label = null) {
	const displayText = label || entity.label || entity.id;
	if (entity.links && entity.links.self) {
		return `<a href="${entity.links.self}" target="_blank">${displayText}</a>`;
	}
	return displayText;
}

// General Statistics Charts Initialization (for server-side rendered content)
function initializeGeneralStatisticsCharts() {
	// Only initialize charts when the General Statistics tab is shown
	$('#general-tab').on('shown.bs.tab', function (e) {
		// Small delay to ensure DOM is ready
		setTimeout(function() {
			initializeSpeechesByFactionChart();
			initializeSpeakingTimeByFactionChart();
			initializeAverageSpeakingTimeByFactionChart();
		}, 100);
	});
	
	// Initialize immediately if general tab is already active
	if ($('#general-tab').hasClass('active')) {
		setTimeout(function() {
			initializeSpeechesByFactionChart();
			initializeSpeakingTimeByFactionChart();
			initializeAverageSpeakingTimeByFactionChart();
		}, 100);
	}
}

// Initialize speeches by faction chart from server data

// The three overview donuts differ only in what they sum; statisticsFactionDonut()
// holds the shared configuration.
function initializeSpeechesByFactionChart() {
	window.speechesByFactionChart =
		statisticsFactionDonut('speechesByFactionChart', generalStatsData?.speeches?.byFaction, 'total');
}

function initializeSpeakingTimeByFactionChart() {
	window.speakingTimeByFactionChart =
		statisticsFactionDonut('speakingTimeByFactionChart', generalStatsData?.speakingTime?.byFaction, 'total');
}

function initializeAverageSpeakingTimeByFactionChart() {
	window.averageSpeakingTimeByFactionChart =
		statisticsFactionDonut('averageSpeakingTimeByFactionChart', generalStatsData?.speakingTime?.byFaction, 'average');
}
// Legacy chart functions removed - now using server-side rendered content with chart initialization
// Charts are initialized from initializeGeneralStatisticsCharts() function

// Entity Statistics Functions (remain unchanged for dynamic loading)
async function loadEntityStats() {
	const entityType = document.getElementById('entityType').value;
	const entityID = document.getElementById('entityID').value;
	
	if (!entityType) {
		alert(statisticsLabel('statisticsEntityType'));
		return;
	}
	
	if (!entityID) {
		alert(statisticsLabel('statisticsNoEntitySelected'));
		return;
	}
	
	const params = { entityType: entityType, entityID: entityID };
	
	const result = await makeApiRequest('entity', params);
	
	if (result.errors) {
		document.getElementById('entityStatsContent').innerHTML = 
			`<div class="alert alert-danger">${result.errors[0].detail}</div>`;
		return;
	}
	
	displayEntityStats(result.data);
}

function displayEntityStats(data) {
	const attrs = data.attributes;
	let html = '';
	
	
	// Speech counts
	if (attrs.speechCounts) {
		html += '<div class="row mb-4"><div class="col-md-6">';
		html += '<div class="card"><div class="card-body">';
		html += `<h6>How many speeches mention this entity?</h6>`;
		html += `<p><strong>Total Speeches:</strong> ${formatNumber(attrs.speechCounts.totalSpeeches)}</p>`;
		html += `<p><strong>Primary Context:</strong> ${formatNumber(attrs.speechCounts.speechesInPrimaryContext)}</p>`;
		html += '</div></div></div></div>';
	}
	
	// Entity associations
	if (attrs.entityAssociations) {
		html += '<div class="row mb-4">';
		if (attrs.entityAssociations.topDetectedEntities) {
			html += '<div class="col-md-6">';
			html += `<h6>Which entities are most often detected (NER) in the same speeches as this entity?</h6>`;
			html += createTable(attrs.entityAssociations.topDetectedEntities, [
				{ field: 'label', title: 'Entity', formatter: (val, row) => createEntityLink(row, val) },
				{ field: 'type', title: 'Type', formatter: (val) => val.charAt(0).toUpperCase() + val.slice(1) },
				{ field: 'coOccurrenceCount', title: 'Co-mentions', formatter: formatNumber }
			], 'detectedEntitiesTable');
			html += '</div>';
		}
		if (attrs.entityAssociations.topMentionedBy) {
			html += '<div class="col-md-6">';
			html += `<h6>In whose speeches is this entity most found?</h6>`;
			html += createTable(attrs.entityAssociations.topMentionedBy, [
				{ field: 'label', title: 'Speaker', formatter: (val, row) => createEntityLink(row, val) },
				{ field: 'coOccurrenceCount', title: 'Mentions', formatter: formatNumber }
			], 'mentionedByTable');
			html += '</div>';
		}
		html += '</div>';
	}
	
	// Speaker vocabulary (for person entities only)
	if (attrs.speakerVocabulary && attrs.entity.type === 'person') {
		html += '<div class="row mb-4"><div class="col-12">';
		html += `<h6>What are this person's most frequently used words?</h6>`;
		html += '<div class="row mb-3">';
		html += '<div class="col-md-6">';
		html += '<div class="card"><div class="card-body">';
		html += `<p><strong>Total Words:</strong> ${formatNumber(attrs.speakerVocabulary.totalWords)}</p>`;
		html += `<p><strong>Unique Words:</strong> ${formatNumber(attrs.speakerVocabulary.uniqueWords)}</p>`;
		html += '</div></div></div></div>';
		html += createTable(attrs.speakerVocabulary.topWords, [
			{ field: 'word', title: 'Word' },
			{ field: 'frequency', title: 'Frequency', formatter: formatNumber },
			{ field: 'speechCount', title: 'Speech Count', formatter: formatNumber }
		], 'vocabularyTable');
		html += '</div></div>';
	}
	
	// Trends section removed - will be implemented as timeline later
	
	document.getElementById('entityStatsContent').innerHTML = html;
}

function resetEntityStats() {
	document.getElementById('entityType').value = '';
	document.getElementById('entityID').value = '';
	document.getElementById('entityStatsContent').innerHTML = 
		'<div class="text-center text-muted py-5"><i class="bi bi-diagram-2 fs-1 mb-3"></i><p>No entity selected</p></div>';
}

function loadEntityExample(entityType, entityID) {
	document.getElementById('entityType').value = entityType;
	document.getElementById('entityID').value = entityID;
	loadEntityStats();
}

// Legacy function removed - functionality moved to updateWordTrendsVisualization()

function displayWordTrends(data) {
	const attrs = data.attributes;

	window.statisticsLastTrends = attrs;

	if (attrs.trends && attrs.trends.length > 0) {
		createWordTrendsChart(attrs.trends, attrs.startDate, attrs.endDate);
		renderWordTrendsFootnotes(attrs);
	} else {
		document.getElementById('wordTrendsChart').innerHTML =
			'<div class="text-center text-muted py-3"><p class="small">' +
			statisticsLabel('statisticsNoTrendData') + '</p></div>';
		$('#wordTrendsFootnotes').empty();
	}
}

/**
 * The two things a trend line cannot show on its own.
 *
 * Which surface forms a grouped line actually covers — otherwise "flüchtlinge"
 * silently includes three other spellings and the number looks wrong to anyone
 * who checks it — and when each word was first recorded, with the caveat that
 * this is the first use on the platform rather than in parliament.
 */
function renderWordTrendsFootnotes(attrs) {
	let container = $('#wordTrendsFootnotes');
	if (!container.length) {
		container = $('<div id="wordTrendsFootnotes" class="small text-muted mt-2"></div>');
		$('#wordTrendsChart').after(container);
	}
	container.empty();

	attrs.trends.forEach(function (trend) {
		if (!trend.total) return;

		const row = $('<div class="mb-1"></div>');
		const label = $('<strong></strong>').text(trend.word);
		row.append(label);

		// Only worth saying when the group is more than the word itself.
		if (attrs.match === 'stem' && trend.forms && trend.forms.length > 1) {
			const others = trend.forms.map(f => f.word).filter(w => w !== trend.word);
			if (others.length) {
				row.append(document.createTextNode(
					' — ' + statisticsLabel('statisticsIncludesForms') + ': ' + others.join(', ')));
			}
		}

		if (trend.firstUse && trend.firstUse.date) {
			const link = $('<a class="ms-2"></a>')
				.attr('href', statisticsSearchUrl({
					q: trend.word,
					dateFrom: trend.firstUse.date,
					dateTo: trend.firstUse.date
				}))
				.attr('title', statisticsLabel('statisticsFirstUseNote'))
				.text(statisticsLabel('statisticsFirstUse') + ': ' + trend.firstUse.date);
			row.append(link);
		}

		container.append(row);
	});
}


/**
 * Word frequency over time, one line per word.
 *
 * Points are clickable: each one stands for a set of speeches in a given month,
 * and clicking opens them in search. In stem mode the search uses the word as
 * the user typed it — the search index stems at query time, so the results match
 * the same group the line covers.
 */
function createWordTrendsChart(trendsData, startDate, endDate) {
	const margin = { top: 5, right: 5, bottom: 25, left: 35 };
	const canvas = statisticsChartCanvas('wordTrendsChart', margin);
	if (!canvas) return;

	const { svg, width, height } = canvas;
	const startParsed = statisticsParseDay(startDate);
	const endParsed = statisticsParseDay(endDate);

	if (!window.wordColorAssignments) window.wordColorAssignments = new Map();
	const colors = (word) => statisticsSeriesColor(word, window.wordColorAssignments, STATISTICS_WORD_COLORS);
	trendsData.forEach(w => colors(w.word));

	const relative = ($('#statisticsNormalizeMode').val() === 'relative');

	// Every word gets a point at every date any word has, so a gap reads as a
	// zero rather than as a line jumping across the missing months.
	const allDates = new Set();
	trendsData.forEach(w => w.timeline.forEach(d => allDates.add(d.date)));
	const sortedDates = Array.from(allDates).sort();

	const allData = [];
	trendsData.forEach(wordData => {
		const byDate = new Map(wordData.timeline.map(d => [d.date, d]));
		sortedDates.forEach(dateStr => {
			const bucket = byDate.get(dateStr);
			const rawCount = bucket ? bucket.totalCount : 0;
			const tokenCount = bucket ? (bucket.tokenCount || 0) : 0;
			allData.push({
				word: wordData.word,
				date: statisticsParseMonth(dateStr),
				// In relative mode the plotted value is occurrences per million words
				// spoken that month, so a month with more sitting time does not read
				// as a rise in the word itself. The API returns the denominator, not
				// the rate, which is what lets this switch without refetching.
				count: (relative && tokenCount > 0) ? (rawCount / tokenCount * 1000000) : rawCount,
				rawCount: rawCount,
				tokenCount: tokenCount,
				speechCount: bucket ? bucket.speechCount : 0
			});
		});
	});

	const filteredData = allData.filter(d => d.date >= startParsed && d.date <= endParsed);
	if (!filteredData.length) return;

	const { xScale, yScale } = statisticsChartScales(filteredData, startParsed, endParsed, width, height);
	statisticsChartAxes(svg, xScale, yScale, height, statisticsChartTicks(startParsed, endParsed));

	const line = statisticsChartLine(xScale, yScale);
	const area = statisticsChartArea(xScale, yScale, height);

	d3.group(filteredData, d => d.word).forEach((values, word) => {
		statisticsChartSeries(svg, values, {
			color: colors(word),
			line: line,
			area: area,
			xScale: xScale,
			yScale: yScale,
			pointTitle: d => d.word + ' — ' + d.rawCount + ' × ('
				+ d3.timeFormat('%m/%Y')(d.date) + ')\n'
				+ statisticsLabel('statisticsShowSpeeches'),
			onPointClick: d => window.open(statisticsSearchUrl({
				q: d.word,
				dateFrom: d3.timeFormat('%Y-%m-01')(d.date),
				dateTo: d3.timeFormat('%Y-%m-%d')(d3.timeMonth.offset(d.date, 1) - 1),
				parliament: OPTV_STATISTICS.parliament
			}), '_blank')
		});
	});

	updateQueryItemColors(trendsData, colors);

	window.currentTrendsData = trendsData;
	window.currentStartDate = startDate;
	window.currentEndDate = endDate;
}


function displayFactionWordTrends(data) {
	if (!data || !data.attributes) {
		console.error('Invalid data structure:', data);
		document.getElementById('factionWordChart').innerHTML = 
			'<div class="alert alert-danger">' + statisticsLabel('statisticsUnavailable') + '</div>';
		return;
	}
	
	const attrs = data.attributes;
	
	if (attrs.trends && attrs.trends.length > 0) {
		createFactionWordChart(attrs.trends, attrs.startDate, attrs.endDate);
		// Kept for the shared resize handler to redraw from.
		window.currentFactionTrendsData = attrs.trends;
		window.currentFactionStartDate = attrs.startDate;
		window.currentFactionEndDate = attrs.endDate;
	} else {
		document.getElementById('factionWordChart').innerHTML =
			'<div class="text-center text-muted py-3"><p class="small">' +
			statisticsLabel('statisticsNoTrendData') + '</p></div>';
	}
}

/**
 * The same word over time, split by faction.
 *
 * Colours come from the platform's faction palette rather than the word palette,
 * so a faction keeps its identity here and everywhere else on the site.
 */
function createFactionWordChart(trendsData, startDate, endDate) {
	const margin = { top: 20, right: 120, bottom: 25, left: 35 };
	const canvas = statisticsChartCanvas('factionWordChart', margin);
	if (!canvas) return;

	const { svg, width, height } = canvas;
	const startParsed = statisticsParseDay(startDate);
	const endParsed = statisticsParseDay(endDate);

	const factionColors = Object.assign({}, typeof factionIDColors !== 'undefined' ? factionIDColors : {});

	const allData = [];
	const factionLabels = {};
	const word = trendsData.length ? trendsData[0].word : '';

	trendsData.forEach(wordData => {
		(wordData.factionBreakdown || []).forEach(factionData => {
			factionLabels[factionData.id] = factionData.label;
			factionData.timeline.forEach(bucket => {
				allData.push({
					faction: factionData.id,
					factionLabel: factionData.label,
					word: wordData.word,
					date: statisticsParseMonth(bucket.date),
					count: bucket.totalCount || 0,
					rawCount: bucket.totalCount || 0,
					speechCount: bucket.speechCount || 0
				});
			});
		});
	});

	const filteredData = allData.filter(d => d.date >= startParsed && d.date <= endParsed);
	if (!filteredData.length) return;

	const { xScale, yScale } = statisticsChartScales(filteredData, startParsed, endParsed, width, height);
	statisticsChartAxes(svg, xScale, yScale, height, statisticsChartTicks(startParsed, endParsed));

	const line = statisticsChartLine(xScale, yScale);

	const legend = svg.append("g")
		.attr("class", "legend")
		.attr("transform", `translate(${width + 10}, 20)`);
	let legendY = 0;

	d3.group(filteredData, d => d.faction).forEach((values, factionID) => {
		const color = factionColors[factionID] || '#999999';

		statisticsChartSeries(svg, values, {
			color: color,
			line: line,
			xScale: xScale,
			yScale: yScale,
			pointTitle: d => (factionLabels[factionID] || factionID) + ' — ' + d.rawCount + ' × ('
				+ d3.timeFormat('%m/%Y')(d.date) + ')\n' + statisticsLabel('statisticsShowSpeeches'),
			onPointClick: d => window.open(statisticsSearchUrl({
				q: word,
				factionID: factionID,
				dateFrom: d3.timeFormat('%Y-%m-01')(d.date),
				dateTo: d3.timeFormat('%Y-%m-%d')(d3.timeMonth.offset(d.date, 1) - 1),
				parliament: OPTV_STATISTICS.parliament
			}), '_blank')
		});

		const item = legend.append("g").attr("transform", `translate(0, ${legendY})`);
		item.append("line")
			.attr("x1", 0).attr("x2", 15).attr("y1", 0).attr("y2", 0)
			.attr("stroke", color).attr("stroke-width", 2);
		item.append("text")
			.attr("x", 20).attr("y", 0).attr("dy", "0.35em")
			.style("font-size", "12px").style("font-family", "inherit")
			.text(factionLabels[factionID] || factionID);
		legendY += 20;
	});
}

function updateQueryItemColors(trendsData, colors) {
	// Clear any existing colors first
	$('.searchInputContainer .queryItem').css('background-color', '').css('border-color', '').css('color', '');
	
	// Apply colors based on chart color scheme
	trendsData.forEach(wordData => {
		const word = wordData.word;
		const color = colors(word);
		const queryItem = $(`.searchInputContainer .queryItem[data-word="${word}"]`);
		
		if (queryItem.length > 0) {
			// Apply background color, border, and white text
			queryItem.css({
				'background-color': color,
				'border-color': color,
				'border-width': '2px',
				'color': '#fff'
			});
		}
	});
}

function resetWordTrends() {
	// Clear all selected words
	$('.searchInputContainer .queryItem').remove();
	document.getElementById('wordTrendsQuery').value = '';
	$('.wordTrendsFactionCheckbox').prop('checked', false);
	$('.searchSuggestionContainer').hide();
	
	// Clear color assignments
	if (window.wordColorAssignments) {
		window.wordColorAssignments.clear();
	}
	
	// Reset timeline range slider
	if ($("#wordTrendsSliderRange").slider) {
		const defaultStart = minDate.getTime();
		const defaultEnd = maxDate.getTime();
		$("#wordTrendsSliderRange").slider("values", [defaultStart, defaultEnd]);
		updateWordTrendsDateRange(new Date(defaultStart), new Date(defaultEnd));
	}
	
	document.getElementById('wordTrendsChart').innerHTML = 
		'<div class="text-center text-muted py-3"><p class="small">' + statisticsLabel('statisticsNoWordsSelected') + '</p></div>';
}



// Statistics are loaded on-demand to optimize initial page load time


// ---------------------------------------------------------------------------
// Localization
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// URL state
// ---------------------------------------------------------------------------

/**
 * Keeps the visible state of the page in the URL.
 *
 * The point is shareability: a chart someone has built up — the words, the date
 * range, the factions, the display mode — has to survive being copied into a
 * message or a footnote. Everything a viewer can change is therefore a URL
 * parameter, and the page reads them back on load and on history navigation.
 *
 * Defaults are omitted from the URL, so an untouched page stays at /statistics
 * rather than accumulating a query string that says nothing.
 */
const StatisticsUrlState = {
	defaults: {
		section: 'general',
		match: 'stem',
		normalize: 'absolute'
	},

	/** Current state, read from the URL. */
	read: function () {
		const p = new URLSearchParams(window.location.search);
		return {
			section: p.get('section') || this.defaults.section,
			words: (p.get('words') || '').split(',').map(w => w.trim()).filter(Boolean),
			dateFrom: p.get('dateFrom') || '',
			dateTo: p.get('dateTo') || '',
			factions: p.getAll('factionID[]').concat(p.getAll('factionID')).filter(Boolean),
			match: p.get('match') || this.defaults.match,
			normalize: p.get('normalize') || this.defaults.normalize,
			entityType: p.get('entityType') || '',
			entityID: p.get('entityID') || '',
			parliament: p.get('parliament') || ''
		};
	},

	/** Current state, read from the DOM. */
	collect: function () {
		const active = $('#statisticsTab button.nav-link.active').attr('data-bs-target') || '#general';
		return {
			section: active.replace('#', ''),
			words: getSelectedWords(),
			dateFrom: $('#wordTrendsStartDate').val() || '',
			dateTo: $('#wordTrendsEndDate').val() || '',
			factions: $('.wordTrendsFactionCheckbox:checked').map((i, el) => $(el).val()).get(),
			match: $('#statisticsMatchMode').val() || this.defaults.match,
			normalize: $('#statisticsNormalizeMode').val() || this.defaults.normalize,
			entityType: $('#entityType').val() || '',
			entityID: $('#entityID').val() || '',
			parliament: OPTV_STATISTICS.parliament
		};
	},

	/** Write state to the address bar without reloading. */
	write: function (state, replace) {
		const p = new URLSearchParams();

		if (state.section && state.section !== this.defaults.section) p.set('section', state.section);
		if (state.words && state.words.length) p.set('words', state.words.join(','));
		if (state.dateFrom) p.set('dateFrom', state.dateFrom);
		if (state.dateTo) p.set('dateTo', state.dateTo);
		(state.factions || []).forEach(f => p.append('factionID[]', f));
		if (state.match && state.match !== this.defaults.match) p.set('match', state.match);
		if (state.normalize && state.normalize !== this.defaults.normalize) p.set('normalize', state.normalize);
		if (state.entityType) p.set('entityType', state.entityType);
		if (state.entityID) p.set('entityID', state.entityID);
		// Only worth carrying when the instance actually has more than one.
		if (state.parliament && $('#statisticsParliamentSwitcher').length) p.set('parliament', state.parliament);

		const query = p.toString();
		const url = window.location.pathname + (query ? '?' + query : '');

		if (replace) {
			history.replaceState(null, '', url);
		} else {
			history.pushState(null, '', url);
		}
	},

	/** Push the DOM's current state into the URL. */
	sync: function () {
		if (this.hydrating) return;
		this.write(this.collect(), false);
	},

	/**
	 * Put the page into the given state.
	 *
	 * Guarded by `hydrating` so the change handlers this fires do not each write
	 * the URL again while it is being applied.
	 */
	apply: function (state) {
		this.hydrating = true;

		const tab = $('#statisticsTab button[data-bs-target="#' + state.section + '"]');
		if (tab.length && !tab.hasClass('active')) {
			bootstrap.Tab.getOrCreateInstance(tab[0]).show();
		}

		$('.searchInputContainer .queryItem').remove();
		if (window.wordColorAssignments) window.wordColorAssignments.clear();
		state.words.forEach(word => addWordQueryItem(word));

		if (state.dateFrom) $('#wordTrendsStartDate').val(state.dateFrom);
		if (state.dateTo) $('#wordTrendsEndDate').val(state.dateTo);

		$('.wordTrendsFactionCheckbox').each(function () {
			$(this).prop('checked', state.factions.indexOf($(this).val()) !== -1);
		});

		if ($('#statisticsMatchMode').length) $('#statisticsMatchMode').val(state.match);
		if ($('#statisticsNormalizeMode').length) $('#statisticsNormalizeMode').val(state.normalize);

		if (state.entityType) $('#entityType').val(state.entityType);
		if (state.entityID) $('#entityID').val(state.entityID);

		this.hydrating = false;
	},

	/** Re-render whatever the current state asks for. */
	refresh: function (state) {
		if (state.words.length) {
			updateWordTrendsVisualization();
		}
		if (state.entityType && state.entityID && typeof loadEntityStats === 'function') {
			loadEntityStats();
		}
	},

	init: function () {
		const state = this.read();
		this.apply(state);
		this.refresh(state);

		// Every control that changes what is on screen also changes the URL.
		$('#statisticsTab button').on('shown.bs.tab', () => this.sync());
		$('#wordTrendsStartDate, #wordTrendsEndDate').on('change', () => this.sync());
		$(document).on('change', '.wordTrendsFactionCheckbox', () => this.sync());
		$(document).on('change', '#statisticsMatchMode, #statisticsNormalizeMode', () => this.sync());
		$(document).on('change', '#entityType, #entityID', () => this.sync());

		window.addEventListener('popstate', () => {
			const popped = this.read();
			this.apply(popped);
			this.refresh(popped);
		});
	}
};

$(document).ready(function () {
	StatisticsUrlState.init();
});


/**
 * Download the current trend data.
 *
 * Journalists and researchers habitually want the numbers behind a chart, and
 * the alternative is transcribing them off the axis. Both the raw count and the
 * denominator are included so the rate can be recomputed and checked.
 */
function statisticsDownloadTrendsCsv() {
	const attrs = window.statisticsLastTrends;
	if (!attrs || !attrs.trends || !attrs.trends.length) return;

	const rows = [['word', 'date', 'occurrences', 'speeches', 'wordsSpokenInPeriod']];

	attrs.trends.forEach(function (trend) {
		(trend.timeline || []).forEach(function (bucket) {
			rows.push([trend.word, bucket.date, bucket.totalCount, bucket.speechCount,
				bucket.tokenCount === undefined ? '' : bucket.tokenCount]);
		});
		(trend.factionBreakdown || []).forEach(function (faction) {
			(faction.timeline || []).forEach(function (bucket) {
				rows.push([trend.word + ' (' + (faction.label || faction.id) + ')', bucket.date,
					bucket.totalCount, bucket.speechCount,
					bucket.tokenCount === undefined ? '' : bucket.tokenCount]);
			});
		});
	});

	const csv = rows.map(r => r.map(function (cell) {
		const s = String(cell === null || cell === undefined ? '' : cell);
		return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
	}).join(',')).join('\n');

	const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	const link = document.createElement('a');
	link.href = URL.createObjectURL(blob);
	link.download = 'word-trends-' + (attrs.words || []).join('-') + '.csv';
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	URL.revokeObjectURL(link.href);
}

$(document).ready(function () {
	$('#statisticsDownloadCsv').on('click', statisticsDownloadTrendsCsv);

	// Switching display mode re-renders from data already held, except for the
	// first switch into relative mode, which needs the denominator fetched.
	$(document).on('change', '#statisticsMatchMode, #statisticsNormalizeMode', function () {
		updateWordTrendsVisualization();
	});
});

/**
 * Redraw the charts when the window resizes.
 *
 * Registered once. The word-trends chart used to add a fresh resize listener on
 * every render, so after a few edits the same redraw ran several times per
 * resize event.
 */
$(document).ready(function () {
	let resizeTimer = null;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			if (document.getElementById('wordTrendsChart') && window.currentTrendsData) {
				createWordTrendsChart(window.currentTrendsData, window.currentStartDate, window.currentEndDate);
			}
			if (document.getElementById('factionWordChart') && window.currentFactionTrendsData) {
				createFactionWordChart(window.currentFactionTrendsData,
					window.currentFactionStartDate, window.currentFactionEndDate);
			}
		}, 250);
	});
});


// ---------------------------------------------------------------------------
// Distinctive words
// ---------------------------------------------------------------------------

/**
 * Words that characterise a scope, as a clickable cloud.
 *
 * This sits above the word input as the zero-input entry point: a page that
 * opens on an empty chart and a text box asks the visitor to already know what
 * they are looking for. A cloud of what is distinctive about the current
 * electoral period gives them somewhere to start, and every word in it is a
 * click away from being a trend line.
 *
 * Sized by score rather than frequency — the point is distinctiveness, not that
 * "menschen" is common everywhere.
 */
async function loadDistinctiveWords() {
	const container = $('#statisticsDistinctiveWords');
	if (!container.length) return;

	const scope = $('#statisticsDistinctiveScope').val();
	if (!scope) return;

	const [key, value] = scope.split('=');
	container.html('<span class="text-muted small">…</span>');

	const result = await makeApiRequest('significant-words',
		Object.assign({ parliament: OPTV_STATISTICS.parliament, size: 24 }, { [key]: value }));

	if (result.errors || !result.data) {
		container.html('<span class="text-muted small">' + statisticsLabel('statisticsNoData') + '</span>');
		return;
	}

	const words = result.data.attributes.words || [];
	if (!words.length) {
		container.html('<span class="text-muted small">' + statisticsLabel('statisticsNoData') + '</span>');
		return;
	}

	const maxScore = d3.max(words, w => w.score) || 1;
	const minScore = d3.min(words, w => w.score) || 0;
	const size = d3.scaleLinear().domain([minScore, maxScore]).range([0.85, 1.9]);

	container.empty();
	words.forEach(function (entry) {
		$('<a class="me-2" href="#"></a>')
			.text(entry.word)
			.attr('title', entry.speechCount + ' ' + statisticsLabel('speeches'))
			.css({ 'font-size': size(entry.score).toFixed(2) + 'rem', 'line-height': 1.4 })
			.on('click', function (evt) {
				evt.preventDefault();
				// Adding it to the comparison is the useful next step, not
				// navigating away from the page.
				addWordQueryItem(entry.word);
				updateWordTrendsVisualization();
				StatisticsUrlState.sync();
			})
			.appendTo(container);
	});
}

/**
 * Fill the scope picker with this parliament's electoral periods.
 *
 * Periods rather than dates, because "what characterised the 20th Bundestag" is
 * a question people actually have, and the boundaries are meaningful.
 */
async function initializeDistinctiveWords() {
	const select = $('#statisticsDistinctiveScope');
	if (!select.length) return;

	const periods = await fetchElectoralPeriods();

	if (periods && periods.length) {
		periods
			.slice()
			.sort((a, b) => ((b.attributes || {}).number || 0) - ((a.attributes || {}).number || 0))
			.forEach(function (period) {
				if (!period.id) return;
				const number = (period.attributes || {}).number;
				$('<option></option>')
					.attr('value', 'electoralPeriodID=' + period.id)
					.text((number ? number + '. ' : '') + statisticsLabel('electoralPeriod'))
					.appendTo(select);
			});
	}

	if (!select.children().length) {
		// No period metadata available: fall back to the whole recorded span,
		// which the endpoint accepts as a date scope.
		$('<option></option>')
			.attr('value', 'dateFrom=' + OPTV_STATISTICS.dataStart)
			.text(statisticsLabel('statisticsOverview'))
			.appendTo(select);
	}

	select.on('change', function () {
		loadDistinctiveWords();
		loadMostUsedWords();
	});
	loadDistinctiveWords();
	loadMostUsedWords();
}

$(document).ready(function () {
	initializeDistinctiveWords();
});


/**
 * The most-used words of the same scope, beside the distinctive ones.
 *
 * Deliberately shown together. Eleven of the top twenty words are shared by all
 * five Bundestag factions, so this list mostly describes parliamentary German;
 * putting it next to what is distinctive is what makes that legible rather than
 * leaving someone to wonder why every scope looks alike.
 *
 * Sized by frequency, as the neighbouring list is sized by distinctiveness.
 */
async function loadMostUsedWords() {
	const container = $('#statisticsMostUsedWords');
	if (!container.length) return;

	const scope = $('#statisticsDistinctiveScope').val();
	if (!scope) return;

	const [key, value] = scope.split('=');
	container.html('<span class="text-muted small">…</span>');

	const result = await makeApiRequest('general',
		Object.assign({ parliament: OPTV_STATISTICS.parliament }, { [key]: value }));

	const words = (((result || {}).data || {}).attributes || {}).vocabulary;
	const top = (words || {}).topWords || [];

	if (!top.length) {
		container.html('<span class="text-muted small">' + statisticsLabel('statisticsNoData') + '</span>');
		return;
	}

	const max = d3.max(top, w => w.frequency) || 1;
	const min = d3.min(top, w => w.frequency) || 0;
	const size = d3.scaleLinear().domain([min, max]).range([0.85, 1.7]);

	container.empty();
	top.slice(0, 24).forEach(function (entry) {
		$('<a class="me-2" href="#"></a>')
			.text(entry.word)
			.attr('title', entry.frequency.toLocaleString() + ' ×')
			.css({ 'font-size': size(entry.frequency).toFixed(2) + 'rem', 'line-height': 1.4 })
			.on('click', function (evt) {
				evt.preventDefault();
				addWordQueryItem(entry.word);
				updateWordTrendsVisualization();
				StatisticsUrlState.sync();
			})
			.appendTo(container);
	});
}