<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

include_once(__DIR__ . '/../../header.php'); 
?>
<main class="container-fluid subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Statistics</h2>
			<p class="text-muted">Browse and analyze parliamentary word usage statistics, speaker vocabulary data, and network relationships from the database.</p>
		</div>
	</div>

	<!-- Statistics Navigation -->
	<ul class="nav nav-tabs modern-tabs" id="statisticsTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
				Word Frequency
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="entity-tab" data-bs-toggle="tab" data-bs-target="#entity" type="button" role="tab">
				Speaker Vocabulary
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="word-trends-tab" data-bs-toggle="tab" data-bs-target="#word-trends" type="button" role="tab">
				Word Trends
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network" type="button" role="tab">
				Network
			</button>
		</li>
	</ul>

	<!-- Tab Content -->
	<div class="tab-content modern-tabs" id="statisticsTabContent">
		
		<!-- Word Frequency Statistics Tab -->
		<div class="tab-pane fade" id="general" role="tabpanel">
			<div class="row mb-3">
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Word Frequency Filters</h5>
						</div>
						<div class="card-body">
							<form id="generalStatsForm">
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="generalFactionFilter" class="form-label">Faction Filter</label>
										<input type="text" class="form-control" id="generalFactionFilter" placeholder="Enter faction ID (optional)">
									</div>
									<div class="col-md-6 mb-3">
										<label for="generalLimit" class="form-label">Results Limit</label>
										<input type="number" class="form-control" id="generalLimit" value="20" min="5" max="100" step="5">
									</div>
								</div>
								<button type="button" class="btn btn-primary" onclick="loadGeneralStats()">Load Data</button>
								<button type="button" class="btn btn-secondary" onclick="resetGeneralStats()">Reset</button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Data Info</h5>
						</div>
						<div class="card-body">
							<div id="generalStatsInfo">
								<div class="text-muted">Click "Load Data" to start</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Word Frequency Results</h5>
						</div>
						<div class="card-body">
							<div id="generalStatsContent">
								<div class="text-center text-muted py-5">
									<i class="bi bi-bar-chart fs-1 mb-3"></i>
									<p>No data loaded yet</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Speaker Vocabulary Tab -->
		<div class="tab-pane fade" id="entity" role="tabpanel">
			<div class="row mb-3">
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Speaker Filters</h5>
						</div>
						<div class="card-body">
							<form id="entityStatsForm">
								<div class="row">
									<div class="col-md-12 mb-3">
										<label for="speakerID" class="form-label">Speaker ID *</label>
										<input type="text" class="form-control" id="speakerID" placeholder="e.g. Q1234567" required>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12 mb-3">
										<label for="speakerLimit" class="form-label">Results Limit</label>
										<input type="number" class="form-control" id="speakerLimit" value="50" min="10" max="500" step="10">
									</div>
								</div>
								<button type="button" class="btn btn-primary" onclick="loadEntityStats()">Load Data</button>
								<button type="button" class="btn btn-secondary" onclick="resetEntityStats()">Reset</button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Speaker Info</h5>
						</div>
						<div class="card-body">
							<div id="entityStatsInfo">
								<div class="text-muted">Please enter required fields</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Speaker Vocabulary Results</h5>
						</div>
						<div class="card-body">
							<div id="entityStatsContent">
								<div class="text-center text-muted py-5">
									<i class="bi bi-person-circle fs-1 mb-3"></i>
									<p>No speaker selected</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Word Trends Tab -->
		<div class="tab-pane fade show active" id="word-trends" role="tabpanel">
			<div class="row mb-3">
				<div class="col-12">
					<div class="card">
						<div class="card-body">
							<div class="mb-2">
								<span class="text-muted small">Example: <a href="#" id="wordTrendsExample">pandemie, flüchtlinge, ukraine</a></span>
							</div>
							<div class="searchContainer p-0">
								<div class="position-relative">
									<div>
										<div class="searchInputContainer clearfix">
											<input id="wordTrendsQuery" class="border-0 p-1" style="outline:none;width:300px;" placeholder="Enter words to analyze trends..." name="wordTrendsQuery" value="" type="text">
										</div>
									</div>
									<div class="searchSuggestionContainer">
										<div class="row">
											<div class="col col-12">
												<div style="font-weight: bolder;">Word Suggestions</div>
												<hr class="my-1">
												<div id="wordSuggestionContainer"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
							
							<div class="row mt-3">
								<div class="col-12 mb-3">
									<div class="rangeContainer">
										<label for="wordTrendsTimeRange"><b>Time Period:</b></label>
										<input type="text" id="wordTrendsTimeRange" readonly style="border:0; background: transparent;"/>
										<div id="wordTrendsChart" class="chartVisualization mt-2">
											<div class="text-center text-muted py-3">
												<p class="small">No words analyzed yet</p>
											</div>
										</div>
										<div class="position-relative">
											<div id="wordTrendsTimelineWrapper" class="resultTimeline"></div>
											<div id="wordTrendsSliderRange" class="sliderRange"></div>
										</div>
										<input type="hidden" id="wordTrendsStartDate" value="2013-10-01"/>
										<input type="hidden" id="wordTrendsEndDate"/>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<label class="form-label"><b>Filter by Factions</b></label>
									<div class="d-flex flex-wrap gap-3">
										<div class="form-check">
											<input id="wordtrends-party-Q2207512" name="wordTrendsFactionID[]" value="Q2207512" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q2207512">SPD</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q1023134" name="wordTrendsFactionID[]" value="Q1023134" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q1023134">CDU/CSU</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q1007353" name="wordTrendsFactionID[]" value="Q1007353" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q1007353">DIE GRÜNEN</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q1387991" name="wordTrendsFactionID[]" value="Q1387991" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q1387991">FDP</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q42575708" name="wordTrendsFactionID[]" value="Q42575708" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q42575708">AfD</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q1826856" name="wordTrendsFactionID[]" value="Q1826856" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q1826856">DIE LINKE</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q127785176" name="wordTrendsFactionID[]" value="Q127785176" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q127785176">BSW</label>
										</div>
										<div class="form-check">
											<input id="wordtrends-party-Q4316268" name="wordTrendsFactionID[]" value="Q4316268" type="checkbox" class="form-check-input wordTrendsFactionCheckbox"> 
											<label class="form-check-label" for="wordtrends-party-Q4316268">fraktionslos</label>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Network Analysis Tab -->
		<div class="tab-pane fade" id="network" role="tabpanel">
			<div class="row mb-3">
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Network Filters</h5>
						</div>
						<div class="card-body">
							<form id="networkStatsForm">
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="networkEntityType" class="form-label">Entity Type</label>
										<select class="form-select" id="networkEntityType">
											<option value="">All entities</option>
											<option value="person">Person</option>
											<option value="organisation">Organisation</option>
											<option value="document">Document</option>
											<option value="term">Term</option>
										</select>
									</div>
									<div class="col-md-6 mb-3">
										<label for="networkEntityID" class="form-label">Entity ID</label>
										<input type="text" class="form-control" id="networkEntityID" placeholder="e.g. Q1234567">
									</div>
								</div>
								<button type="button" class="btn btn-primary" onclick="loadNetworkStats()">Load Data</button>
								<button type="button" class="btn btn-secondary" onclick="resetNetworkStats()">Reset</button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Network Info</h5>
						</div>
						<div class="card-body">
							<div id="networkStatsInfo">
								<div class="text-muted">Analyze entity relationships and connections</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Network Results</h5>
						</div>
						<div class="card-body">
							<div id="networkStatsContent">
								<div class="text-center text-muted py-5">
									<i class="bi bi-diagram-3 fs-1 mb-3"></i>
									<p>No network data loaded</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>


	</div>


</main>

<script>
// Set default end date to today
document.getElementById('wordTrendsEndDate').value = new Date().toISOString().split('T')[0];

// Word trends interactive variables
var wordTrendsSuggestionsAjax;
var selectedWordSuggestionIndex = null;
var wordTrendsTimelineViz = null;
var wordTrendsSlider = null;

// Default timeline range
var minDate = new Date("2013-10-01");
var maxDate = new Date();

// Initialize word trends interactive interface
$(document).ready(function() {
	initializeWordTrendsInterface();
});

// Initialize timeline after all scripts are loaded
$(window).on('load', function() {
	// Give timeline.js a moment to load if needed
	setTimeout(function() {
		if (typeof TimelineViz === 'undefined') {
			console.error('TimelineViz not found - timeline.js may not be loaded');
		} else {
			console.log('TimelineViz available, timeline.js loaded successfully');
			// Re-initialize timeline now that TimelineViz is available
			initializeWordTrendsTimeline();
		}
	}, 100);
});

function initializeWordTrendsInterface() {
	// Initialize search interface components (timeline initialized separately)
	// Initialize slider immediately since Word Trends tab is active by default
	initializeWordTrendsSlider();
	
	// Also initialize slider when Word Trends tab is shown (for when switching tabs)
	$('#word-trends-tab').on('shown.bs.tab', function (e) {
		console.log('Word Trends tab shown, initializing slider...');
		initializeWordTrendsSlider();
	});
	
	// Handle input events
	$('#wordTrendsQuery').keydown(function(evt) {
		if (evt.keyCode == 13) {
			evt.preventDefault();
			if (!$('.searchSuggestionContainer').is(':visible') || $('.searchSuggestionContainer .suggestionItem.selected').length == 0) {
				addWordFromInput();
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
					$('.searchSuggestionContainer .suggestionItem.selected').length != 0) {
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

// Delay function for input debouncing
function delay(callback, ms) {
	var timer = 0;
	return function() {
		var context = this, args = arguments;
		clearTimeout(timer);
		timer = setTimeout(function () {
			callback.apply(context, args);
		}, ms || 0);
	};
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
			renderWordSuggestions(textValue, data.data);
		}).fail(function(err) {
			console.log('Word suggestions error:', err);
		});
	} else {
		$('.searchSuggestionContainer').hide();
	}
}

function renderWordSuggestions(inputValue, data) {
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
function addWordFromInput() {
	var textValue = $.trim($('#wordTrendsQuery').val());
	if (textValue != '') {
		addWordQueryItem(textValue);
		updateWordTrendsVisualization();
	}
}

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
		const response = await fetch('/api/v1/?action=search&itemType=media&limit=1');
		const data = await response.json();
		
		if (data && data.meta && data.meta.requestStatus === 'success' && data.meta.attributes && data.meta.attributes.electoralPeriods) {
			return data.meta.attributes.electoralPeriods || [];
		}
		return [];
	} catch (error) {
		console.log('Error fetching electoral periods:', error);
		return [];
	}
}

// Slider initialization (separate from timeline)
function initializeWordTrendsSlider() {
	console.log('initializeWordTrendsSlider called');
	
	// Check if jQuery and slider are available
	if (typeof $ === 'undefined') {
		console.error('jQuery not available');
		return;
	}
	
	if (!$.fn.slider) {
		console.error('jQuery UI slider not available');
		return;
	}
	
	// Check if slider element exists
	const sliderElement = $("#wordTrendsSliderRange");
	if (sliderElement.length === 0) {
		console.error('Slider element #wordTrendsSliderRange not found');
		return;
	}
	
	// Check if slider is already initialized
	if (sliderElement.hasClass('ui-slider')) {
		console.log('Slider already initialized, skipping...');
		return;
	}
	
	console.log('Initializing range slider...', 'Element found:', sliderElement.length);
	
	const startTime = minDate.getTime();
	const endTime = maxDate.getTime();
	const defaultStart = minDate.getTime();
	const defaultEnd = maxDate.getTime();
	
	console.log('Slider range:', new Date(startTime), 'to', new Date(endTime));
	
	try {
		sliderElement.slider({
			range: true,
			min: startTime,
			max: endTime,
			values: [defaultStart, defaultEnd],
			slide: function(event, ui) {
				const startDate = new Date(ui.values[0]);
				const endDate = new Date(ui.values[1]);
				updateWordTrendsDateRangeDisplay(startDate, endDate);
			},
			stop: function(event, ui) {
				const startDate = new Date(ui.values[0]);
				const endDate = new Date(ui.values[1]);
				updateWordTrendsDateRange(startDate, endDate);
			}
		});
		
		console.log('Range slider initialized successfully');
		
		// Set initial date range
		updateWordTrendsDateRange(new Date(defaultStart), new Date(defaultEnd));
	} catch (error) {
		console.error('Error initializing slider:', error);
	}
}

// Timeline initialization
function initializeWordTrendsTimeline() {
	console.log('initializeWordTrendsTimeline called');
	
	if (typeof TimelineViz === 'undefined') {
		console.error('TimelineViz not available, cannot initialize timeline');
		return;
	}
	
	console.log('Fetching electoral periods...');
	
	// Fetch electoral periods and initialize timeline (without data bars)
	fetchElectoralPeriods().then(function(electoralPeriods) {
		console.log('Electoral periods fetched:', electoralPeriods.length, 'periods');
		
		try {
			console.log('Initializing TimelineViz with', electoralPeriods.length, 'electoral periods');
			
			wordTrendsTimelineViz = new TimelineViz({
				container: 'wordTrendsTimelineWrapper',
				data: [], // No timeline bars, just electoral periods
				minDate: minDate,
				maxDate: maxDate,
				showElectoralPeriods: true,
				electoralPeriods: electoralPeriods || []
			});
			
			console.log('Timeline initialization complete');
		} catch (error) {
			console.error('Timeline initialization error:', error);
		}
	}).catch(function(error) {
		console.error('Error fetching electoral periods:', error);
	});
}

function updateWordTrendsDateRangeDisplay(startDate, endDate) {
	// Format dates for display only (no chart update)
	const formatDate = (date) => {
		return date.toLocaleDateString('en-US', { 
			year: 'numeric', 
			month: 'short', 
			day: 'numeric' 
		});
	};
	
	document.getElementById('wordTrendsTimeRange').value = 
		formatDate(startDate) + ' - ' + formatDate(endDate);
}

function updateWordTrendsDateRange(startDate, endDate) {
	// Format dates
	const startStr = startDate.toISOString().split('T')[0];
	const endStr = endDate.toISOString().split('T')[0];
	
	// Update hidden inputs
	document.getElementById('wordTrendsStartDate').value = startStr;
	document.getElementById('wordTrendsEndDate').value = endStr;
	
	// Update display
	updateWordTrendsDateRangeDisplay(startDate, endDate);
	
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
			'<p class="small">No words selected for analysis</p>' +
			'</div>'
		);
		return;
	}

	// Get filter values
	var startDate = $('#wordTrendsStartDate').val() || '2013-01-01';
	var endDate = $('#wordTrendsEndDate').val() || new Date().toISOString().split('T')[0];
	
	// Get selected factions from checkboxes
	var selectedFactions = [];
	$('.wordTrendsFactionCheckbox:checked').each(function() {
		selectedFactions.push($(this).val());
	});
	
	// Prepare parameters
	var params = {
		words: selectedWords,
		startDate: startDate,
		endDate: endDate,
		parliament: 'de'
	};
	
	if (selectedFactions.length > 0) {
		params.factions = selectedFactions;
	}

	// Make API call
	makeApiRequest('word-trends', params).then(function(result) {
		if (result.errors) {
			$('#wordTrendsChart').html(
				'<div class="alert alert-danger">' + result.errors[0].detail + '</div>'
			);
			return;
		}
		
		displayWordTrends(result.data);
	}).catch(function(error) {
		console.error('Word trends error:', error);
		$('#wordTrendsChart').html(
			'<div class="alert alert-danger">Error loading word trends data</div>'
		);
	});
}

// API request helper
async function makeApiRequest(endpoint, params = {}) {
	try {
		const url = new URL('/api/v1/', window.location.origin);
		url.searchParams.append('action', 'statistics');
		url.searchParams.append('itemType', endpoint);
		
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
		console.error('API Error:', error);
		return { errors: [{ detail: error.message }] };
	}
}

// Utility function to create Bootstrap tables
function createTable(data, columns, tableId) {
	if (!data || data.length === 0) {
		return '<div class="text-muted text-center py-3">No data available</div>';
	}
	
	let html = `<div class="table-responsive"><table class="table table-striped table-hover" id="${tableId}">`;
	html += '<thead class="table-dark"><tr>';
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

// General Statistics Functions
async function loadGeneralStats() {
	const factionID = document.getElementById('generalFactionFilter').value;
	const limit = document.getElementById('generalLimit').value;
	
	const params = {};
	if (factionID) params.factionID = factionID;
	if (limit) params.limit = limit;
	
	const result = await makeApiRequest('general', params);
	
	if (result.errors) {
		document.getElementById('generalStatsContent').innerHTML = 
			`<div class="alert alert-danger">${result.errors[0].detail}</div>`;
		return;
	}
	
	displayGeneralStats(result.data);
}

function displayGeneralStats(data) {
	const attrs = data.attributes;
	let html = '';
	
	// Summary info
	document.getElementById('generalStatsInfo').innerHTML = `
		<p><strong>Context:</strong> ${attrs.context || 'main-speaker'}</p>
		${attrs.factionID ? `<p><strong>Faction:</strong> ${attrs.factionID}</p>` : ''}
	`;
	
	// Speakers section
	if (attrs.speakers) {
		html += '<div class="row mb-4"><div class="col-12">';
		html += `<h6>Who are the speakers that give the most speeches? (${formatNumber(attrs.speakers.total)} total speakers)</h6>`;
		html += createTable(attrs.speakers.topSpeakers, [
			{ field: 'label', title: 'Speaker', formatter: (val, row) => createEntityLink(row, val) },
			{ field: 'speechCount', title: 'Speech Count', formatter: formatNumber }
		], 'speakersTable');
		html += '</div></div>';
	}
	
	// Speaking time section
	if (attrs.speakingTime) {
		html += '<div class="row mb-4"><div class="col-md-6">';
		html += '<div class="card"><div class="card-body">';
		html += `<h6>How much total speaking time is recorded?</h6>`;
		html += `<p><strong>Total:</strong> ${formatNumber(Math.round(attrs.speakingTime.total))} seconds</p>`;
		html += `<p><strong>Average:</strong> ${formatNumber(Math.round(attrs.speakingTime.average))} seconds</p>`;
		html += '</div></div></div></div>';
	}
	
	// Word frequency section
	if (attrs.wordFrequency) {
		html += '<div class="row mb-4"><div class="col-12">';
		html += `<h6>What are the most frequently spoken words in parliamentary speeches?</h6>`;
		html += createTable(attrs.wordFrequency.topWords, [
			{ field: 'word', title: 'Word' },
			{ field: 'speechCount', title: 'Usage Count', formatter: formatNumber }
		], 'wordsTable');
		html += '</div></div>';
	}
	
	// Share of voice section
	if (attrs.shareOfVoice) {
		html += '<div class="row mb-4">';
		if (attrs.shareOfVoice.factions) {
			html += '<div class="col-12">';
			html += `<h6>Which factions/groups give the most speeches?</h6>`;
			html += createTable(attrs.shareOfVoice.factions, [
				{ field: 'label', title: 'Faction/Group', formatter: (val, row) => createEntityLink(row, val) },
				{ field: 'speechCount', title: 'Speech Count', formatter: formatNumber }
			], 'factionsTable');
			html += '</div>';
		}
		html += '</div>';
	}
	
	document.getElementById('generalStatsContent').innerHTML = html;
}

function resetGeneralStats() {
	document.getElementById('generalFactionFilter').value = '';
	document.getElementById('generalLimit').value = '20';
	document.getElementById('generalStatsInfo').innerHTML = 
		'<div class="text-muted">Click "Load Data" to start</div>';
	document.getElementById('generalStatsContent').innerHTML = 
		'<div class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-1 mb-3"></i><p>No data loaded yet</p></div>';
}

// Speaker Statistics Functions
async function loadEntityStats() {
	const speakerID = document.getElementById('speakerID').value;
	const limit = document.getElementById('speakerLimit').value;
	
	if (!speakerID) {
		alert('Please enter a speaker ID');
		return;
	}
	
	const params = { entityType: 'person', entityID: speakerID };
	if (limit) params.limit = limit;
	
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
	
	// Entity info
	document.getElementById('entityStatsInfo').innerHTML = `
		<p><strong>Entity:</strong> ${createEntityLink(attrs.entity)} (${attrs.entity.type})</p>
	`;
	
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
		if (attrs.entityAssociations.topCoOccurringPersons) {
			html += '<div class="col-md-6">';
			html += `<h6>Which other people are most often mentioned in the same speeches as this person?</h6>`;
			html += createTable(attrs.entityAssociations.topCoOccurringPersons, [
				{ field: 'label', title: 'Person', formatter: (val, row) => createEntityLink(row, val) },
				{ field: 'coOccurrenceCount', title: 'Co-mentions', formatter: formatNumber }
			], 'coOccurringTable');
			html += '</div>';
		}
		if (attrs.entityAssociations.topMainSpeakers) {
			html += '<div class="col-md-6">';
			html += `<h6>Who are the main speakers that most often talk about this entity?</h6>`;
			html += createTable(attrs.entityAssociations.topMainSpeakers, [
				{ field: 'label', title: 'Speaker', formatter: (val, row) => createEntityLink(row, val) },
				{ field: 'coOccurrenceCount', title: 'Co-mentions', formatter: formatNumber }
			], 'mainSpeakersTable');
			html += '</div>';
		}
		html += '</div>';
	}
	
	// Speaker vocabulary (for person entities)
	if (attrs.speakerVocabulary) {
		html += '<div class="row mb-4"><div class="col-12">';
		html += `<h6>What are this speaker's most frequently used words?</h6>`;
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
	document.getElementById('speakerID').value = '';
	document.getElementById('speakerLimit').value = '50';
	document.getElementById('entityStatsInfo').innerHTML = 
		'<div class="text-muted">Please enter a speaker ID</div>';
	document.getElementById('entityStatsContent').innerHTML = 
		'<div class="text-center text-muted py-5"><i class="bi bi-person-circle fs-1 mb-3"></i><p>No speaker selected</p></div>';
}

// Legacy word trends function (kept for compatibility)
async function loadWordTrends() {
	// This function is now handled by the interactive interface
	updateWordTrendsVisualization();
}

function displayWordTrends(data) {
	const attrs = data.attributes;
	
	// Create chart directly in the wordTrendsChart div (now positioned between time period and timeline)
	if (attrs.trends && attrs.trends.length > 0) {
		// Create D3.js line chart with date range
		createWordTrendsChart(attrs.trends, attrs.startDate, attrs.endDate);
	} else {
		// Show empty state in the chart area
		document.getElementById('wordTrendsChart').innerHTML = 
			'<div class="text-center text-muted py-3"><p class="small">No trend data available for selected words</p></div>';
	}
}

function createWordTrendsChart(trendsData, startDate, endDate) {
	// Clear any existing chart
	d3.select("#wordTrendsChart").selectAll("*").remove();
	
	// Get container dimensions for responsive design with fallbacks
	const container = document.getElementById('wordTrendsChart');
	let containerWidth = container.offsetWidth;
	let containerHeight = container.offsetHeight;
	
	// Fallback if container dimensions are 0 or too small (common during redraws)
	if (containerWidth < 200) {
		containerWidth = container.parentElement.offsetWidth || 800;
	}
	if (containerHeight < 200) {
		containerHeight = 400; // Fallback height
	}
	
	// Set dimensions and margins - minimal margins for full width/height
	const margin = {top: 5, right: 5, bottom: 25, left: 35};
	const width = containerWidth - margin.left - margin.right;
	const height = containerHeight - margin.top - margin.bottom;
	
	// Create SVG with responsive width
	const svg = d3.select("#wordTrendsChart")
		.append("svg")
		.attr("width", "100%")
		.attr("height", height + margin.top + margin.bottom)
		.attr("viewBox", `0 0 ${width + margin.left + margin.right} ${height + margin.top + margin.bottom}`)
		.append("g")
		.attr("transform", `translate(${margin.left},${margin.top})`);
	
	// Parse data and prepare for D3
	const parseDate = d3.timeParse("%Y-%m");
	const parseInputDate = d3.timeParse("%Y-%m-%d");
	
	// Custom color palette for word trends
	const customColors = [
		"#597081",
		"#339966", 
		"#16a09c",
		"#0073a6",
		"#8b5180",
		"#999933",
		"#CC3399",
		"#ae764d",
		"#cf910d",
		"#b85e02"
	];
	
	// Create stable color assignment that persists across chart updates
	if (!window.wordColorAssignments) {
		window.wordColorAssignments = new Map();
	}
	
	// Assign colors to words that don't have them yet using smart random selection
	trendsData.forEach(wordData => {
		if (!window.wordColorAssignments.has(wordData.word)) {
			const usedColors = Array.from(window.wordColorAssignments.values());
			const availableColors = customColors.filter(color => !usedColors.includes(color));
			
			let colorToAssign;
			if (availableColors.length > 0) {
				if (usedColors.length === 0) {
					// First color: pick randomly from all available
					colorToAssign = availableColors[Math.floor(Math.random() * availableColors.length)];
				} else {
					// Subsequent colors: try to pick colors that are more distant from used ones
					const usedIndices = usedColors.map(color => customColors.indexOf(color));
					
					// Score each available color by its distance from used colors
					const colorScores = availableColors.map(color => {
						const colorIndex = customColors.indexOf(color);
						// Calculate minimum distance to any used color (circular array)
						const minDistance = Math.min(...usedIndices.map(usedIndex => {
							const distance = Math.abs(colorIndex - usedIndex);
							return Math.min(distance, customColors.length - distance); // Circular distance
						}));
						return { color, score: minDistance };
					});
					
					// Sort by distance score (higher is better)
					colorScores.sort((a, b) => b.score - a.score);
					
					// Pick randomly from the top 3 most distant colors (or fewer if not available)
					const topColors = colorScores.slice(0, Math.min(3, colorScores.length));
					const randomTopColor = topColors[Math.floor(Math.random() * topColors.length)];
					colorToAssign = randomTopColor.color;
				}
			} else {
				// All colors used, pick randomly
				colorToAssign = customColors[Math.floor(Math.random() * customColors.length)];
			}
			
			window.wordColorAssignments.set(wordData.word, colorToAssign);
		}
	});
	
	// Create color function that uses stable assignments
	const colors = (word) => window.wordColorAssignments.get(word);
	
	// Get all unique dates across all words
	const allDates = new Set();
	trendsData.forEach(wordData => {
		wordData.timeline.forEach(d => {
			allDates.add(d.date);
		});
	});
	
	// Sort dates
	const sortedDates = Array.from(allDates).sort();
	
	// Create complete dataset with zero-filled missing values
	const allData = [];
	trendsData.forEach(wordData => {
		const wordDataMap = new Map();
		wordData.timeline.forEach(d => {
			wordDataMap.set(d.date, d);
		});
		
		// Fill in all dates for this word
		sortedDates.forEach(dateStr => {
			const existingData = wordDataMap.get(dateStr);
			allData.push({
				word: wordData.word,
				date: parseDate(dateStr),
				count: existingData ? existingData.totalCount : 0,
				speechCount: existingData ? existingData.speechCount : 0
			});
		});
	});
	
	// Set scales using user-specified date range
	const startDateParsed = parseInputDate(startDate);
	const endDateParsed = parseInputDate(endDate);
	
	// Filter data to only include points within the selected date range
	let filteredData = allData.filter(d => d.date >= startDateParsed && d.date <= endDateParsed);
	
	// Ensure each word has data points at the exact start and end dates (with value 0 if needed)
	const words = [...new Set(filteredData.map(d => d.word))];
	const boundaryData = [];
	
	words.forEach(word => {
		const wordData = filteredData.filter(d => d.word === word);
		
		// Check if we have data exactly at start date
		const hasStartDate = wordData.some(d => d.date.getTime() === startDateParsed.getTime());
		if (!hasStartDate) {
			boundaryData.push({
				word: word,
				date: startDateParsed,
				count: 0,
				speechCount: 0
			});
		}
		
		// Check if we have data exactly at end date
		const hasEndDate = wordData.some(d => d.date.getTime() === endDateParsed.getTime());
		if (!hasEndDate) {
			boundaryData.push({
				word: word,
				date: endDateParsed,
				count: 0,
				speechCount: 0
			});
		}
	});
	
	// Add boundary data points
	filteredData = filteredData.concat(boundaryData);
	
	const xScale = d3.scaleTime()
		.domain([startDateParsed, endDateParsed])
		.range([0, width]);
	
	const yScale = d3.scaleLinear()
		.domain([0, d3.max(filteredData, d => d.count)])
		.range([height, 0]);
	
	// Create line generator
	const line = d3.line()
		.x(d => xScale(d.date))
		.y(d => yScale(d.count))
		.curve(d3.curveMonotoneX);
	
	// Create area generator for fills
	const area = d3.area()
		.x(d => xScale(d.date))
		.y0(height) // Bottom of the area (baseline)
		.y1(d => yScale(d.count)) // Top of the area (data line)
		.curve(d3.curveMonotoneX);
	
	// Determine time range and appropriate formatting
	const timeRangeInDays = (endDateParsed - startDateParsed) / (1000 * 60 * 60 * 24);
	const timeRangeInMonths = timeRangeInDays / 30;
	const timeRangeInYears = timeRangeInDays / 365;
	
	let xAxisFormat, tickInterval;
	
	if (timeRangeInDays <= 90) {
		// Less than 3 months: show weeks or days
		xAxisFormat = d3.timeFormat("%d.%m");
		tickInterval = d3.timeWeek.every(1);
	} else if (timeRangeInMonths <= 24) {
		// Less than 2 years: show months
		xAxisFormat = d3.timeFormat("%m/%Y");
		tickInterval = d3.timeMonth.every(Math.ceil(timeRangeInMonths / 12));
	} else if (timeRangeInYears <= 10) {
		// Less than 10 years: show years
		xAxisFormat = d3.timeFormat("%Y");
		tickInterval = d3.timeYear.every(Math.max(1, Math.ceil(timeRangeInYears / 8)));
	} else {
		// More than 10 years: show years with larger intervals
		xAxisFormat = d3.timeFormat("%Y");
		tickInterval = d3.timeYear.every(Math.max(2, Math.ceil(timeRangeInYears / 6)));
	}
	
	// Add x-axis with adaptive formatting
	svg.append("g")
		.attr("transform", `translate(0,${height})`)
		.call(d3.axisBottom(xScale)
			.ticks(tickInterval)
			.tickFormat(xAxisFormat))
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");
	
	svg.append("g")
		.call(d3.axisLeft(yScale))
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");
	
	// Group filtered data by word
	const wordGroups = d3.group(filteredData, d => d.word);
	
	// Draw areas and lines for each word
	wordGroups.forEach((values, word) => {
		// Sort by date
		values.sort((a, b) => a.date - b.date);
		
		// Draw area fill first (so it appears behind the line)
		svg.append("path")
			.datum(values)
			.attr("fill", colors(word))
			.attr("fill-opacity", 0.1) // Low opacity area fill
			.attr("stroke", "none")
			.attr("d", area);
		
		// Draw line on top
		svg.append("path")
			.datum(values)
			.attr("fill", "none")
			.attr("stroke", colors(word))
			.attr("stroke-width", 2)
			.attr("d", line);
	});
	
	// Update query item colors to match chart colors (legend functionality)
	updateQueryItemColors(trendsData, colors);
	
	// Add resize event handler for responsive behavior
	window.addEventListener('resize', function() {
		// Debounce resize events
		clearTimeout(window.chartResizeTimeout);
		window.chartResizeTimeout = setTimeout(function() {
			// Check if we still have the chart container and trend data
			if (document.getElementById('wordTrendsChart') && window.currentTrendsData) {
				// Add small delay to ensure container has resized properly
				setTimeout(function() {
					createWordTrendsChart(window.currentTrendsData, window.currentStartDate, window.currentEndDate);
				}, 50);
			}
		}, 250);
	});
	
	// Store current data for resize handler
	window.currentTrendsData = trendsData;
	window.currentStartDate = startDate;
	window.currentEndDate = endDate;
}

// Update query item colors to match chart legend
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
		'<div class="text-center text-muted py-3"><p class="small">No words selected for analysis</p></div>';
}

// Network Statistics Functions
async function loadNetworkStats() {
	const entityType = document.getElementById('networkEntityType').value;
	const entityID = document.getElementById('networkEntityID').value;
	
	const params = {};
	if (entityType) params.entityType = entityType;
	if (entityID) params.entityID = entityID;
	
	const result = await makeApiRequest('network', params);
	
	if (result.errors) {
		document.getElementById('networkStatsContent').innerHTML = 
			`<div class="alert alert-danger">${result.errors[0].detail}</div>`;
		return;
	}
	
	displayNetworkStats(result.data);
}

function displayNetworkStats(data) {
	let html = '';
	
	// Network info
	let infoHtml = '<div class="text-muted">Network analysis complete</div>';
	if (data.attributes && data.attributes.entity) {
		infoHtml = `<p><strong>Focused Entity:</strong> ${createEntityLink(data.attributes.entity)}</p>`;
	}
	document.getElementById('networkStatsInfo').innerHTML = infoHtml;
	
	// Display network data based on structure
	if (data.attributes) {
		html += '<div class="row"><div class="col-12">';
		html += '<div class="alert alert-info">Network data available</div>';
		html += '<pre>' + JSON.stringify(data.attributes, null, 2) + '</pre>';
		html += '</div></div>';
	} else {
		html += '<div class="text-center text-muted py-5">';
		html += '<i class="bi bi-diagram-3 fs-1 mb-3"></i>';
		html += '<p>Network analysis complete</p>';
		html += '</div>';
	}
	
	document.getElementById('networkStatsContent').innerHTML = html;
}

function resetNetworkStats() {
	document.getElementById('networkEntityType').value = '';
	document.getElementById('networkEntityID').value = '';
	document.getElementById('networkStatsInfo').innerHTML = 
		'<div class="text-muted">Analyze entity relationships and connections</div>';
	document.getElementById('networkStatsContent').innerHTML = 
		'<div class="text-center text-muted py-5"><i class="bi bi-diagram-3 fs-1 mb-3"></i><p>No network data loaded</p></div>';
}


// Don't auto-load stats to avoid slow initial page load
// Users can click "Load Data" to fetch statistics when needed
</script>

<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<?php
}
?>