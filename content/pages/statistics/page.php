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
		<div class="col-12 mb-3">
			<h2>Statistics</h2>
		</div>
	</div>

	<!-- Statistics Navigation -->
	<ul class="nav nav-tabs modern-tabs" id="statisticsTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
				General Statistics
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="entity-tab" data-bs-toggle="tab" data-bs-target="#entity" type="button" role="tab">
				Entity Statistics
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="word-trends-tab" data-bs-toggle="tab" data-bs-target="#word-trends" type="button" role="tab">
				Word Trends
			</button>
		</li>
	</ul>

	<!-- Tab Content -->
	<div class="tab-content modern-tabs" id="statisticsTabContent">
		
		<!-- General Statistics Tab -->
		<div class="tab-pane fade show active" id="general" role="tabpanel">
			<?php
			// Fetch general statistics data server-side
			$generalStatsParams = [
				"action" => "statistics",
				"itemType" => "general",
				"parliament" => "DE"
			];
			$generalStatsResult = apiV1($generalStatsParams);
			$generalStats = $generalStatsResult["data"]["attributes"] ?? null;
			
			if (!$generalStats) {
				echo '<div class="alert alert-warning">Unable to load general statistics data.</div>';
			} else {
			?>
			
			<!-- Row 1: Speeches + Speaking Time -->
			<div class="row mb-4 align-items-stretch">
				<!-- Speeches Section -->
				<div class="col-xl-6 mb-4 mb-xl-0 d-flex">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Speeches per Faction/Group</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-sm-4 col-md-3 col-lg-2">
									<div class="chart-container mb-3" style="height: 100px;">
										<div id="speechesByFactionChart" style="height: 100%; width: 100%;"></div>
									</div>
								</div>
								<div class="col-sm-8 col-md-9 col-lg-10">
									<div class="row">
										<div class="col-12 mb-3">
											<div class="card flex-fill">
												<div class="card-body">
													<h6 class="card-title">Total Speeches</h6>
													<p class="card-text"><strong><?= h(number_format($generalStats["speeches"]["total"])) ?></strong></p>
												</div>
											</div>
										</div>
									</div>
									<div class="table-responsive">
										<table class="table table-striped table-sm">
											<thead>
												<tr>
													<th>Faction/Group</th>
													<th>Speech Count</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($generalStats["speeches"]["byFaction"] as $faction): ?>
												<tr>
													<td>
														<?php if (isset($faction["links"]["self"])): ?>
															<a href="<?= h($faction['links']['self']) ?>" target="_blank"><?= h($faction["label"]) ?></a>
														<?php else: ?>
															<?= h($faction["label"]) ?>
														<?php endif; ?>
													</td>
													<td><?= number_format($faction["total"]) ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Speaking Time Section -->
				<div class="col-xl-6 d-flex">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Speaking Time per Faction/Group</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-sm-4 col-md-3 col-lg-2">
									<h6 class="mb-2 text-center">Total</h6>
									<div class="chart-container mb-3" style="height: 100px;">
										<div id="speakingTimeByFactionChart" style="height: 100%; width: 100%;"></div>
									</div>
									
									<h6 class="mb-2 mt-4 text-center">Average</h6>
									<div class="chart-container mb-3" style="height: 100px;">
										<div id="averageSpeakingTimeByFactionChart" style="height: 100%; width: 100%;"></div>
									</div>
								</div>
								<div class="col-sm-8 col-md-9 col-lg-10">
									<?php if (isset($generalStats["speakingTime"])): ?>
										<div class="row">
											<div class="col-md-6">
												<div class="card flex-fill">
													<div class="card-body">
														<h6 class="card-title">Total Speaking Time</h6>
														<p class="card-text"><strong><?= h(getTimeDistanceString(['input' => $generalStats["speakingTime"]["total"], 'mode' => 'duration', 'short' => true])) ?></strong></p>
													</div>
												</div>
											</div>
											<div class="col-md-6">
												<div class="card flex-fill">
													<div class="card-body">
														<h6 class="card-title">Average Speaking Time</h6>
														<p class="card-text"><strong><?= h(getTimeDistanceString(['input' => $generalStats["speakingTime"]["average"], 'mode' => 'duration', 'short' => true])) ?></strong></p>
													</div>
												</div>
											</div>
										</div>
										
										<!-- Show breakdown by faction if available -->
										<?php if (isset($generalStats["speakingTime"]["byFaction"])): ?>
										<div class="table-responsive mt-3">
											<table class="table table-striped table-sm">
												<thead>
													<tr>
														<th>Faction/Group</th>
														<th>Total Time</th>
														<th>Average Time</th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($generalStats["speakingTime"]["byFaction"] as $faction): ?>
													<tr>
														<td><?= h($faction["label"]) ?></td>
														<td><?= h(getTimeDistanceString(['input' => $faction["total"], 'mode' => 'duration', 'short' => true])) ?></td>
														<td><?= h(getTimeDistanceString(['input' => $faction["average"], 'mode' => 'duration', 'short' => true])) ?></td>
													</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<!-- Row 2: Speakers + Vocabulary -->
			<div class="row mb-4 align-items-stretch">
				<!-- Speakers Section -->
				<div class="col-xl-6 mb-4 mb-xl-0 d-flex">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Top 20 Speakers</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-12 mb-3">
									<div class="card flex-fill">
										<div class="card-body">
											<h6 class="card-title">Total Speakers</h6>
											<p class="card-text"><strong><?= h(number_format($generalStats["speakers"]["total"])) ?></strong></p>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Tabbed content for speakers -->
							<ul class="nav nav-tabs modern-tabs mb-3" id="speakersTab" role="tablist">
								<li class="nav-item" role="presentation">
									<button class="nav-link active" id="speakers-total-tab" data-bs-toggle="pill" data-bs-target="#speakers-total" type="button" role="tab">Total</button>
								</li>
								<?php foreach ($generalStats["speakers"]["byFaction"] as $faction): ?>
								<li class="nav-item" role="presentation">
									<button class="nav-link" id="speakers-<?= hAttr($faction['id']) ?>-tab" data-bs-toggle="pill" data-bs-target="#speakers-<?= hAttr($faction['id']) ?>" type="button" role="tab"><?= h($faction["label"]) ?></button>
								</li>
								<?php endforeach; ?>
							</ul>
							
							<div class="tab-content modern-tabs" id="speakersTabContent">
								<!-- Total speakers tab -->
								<div class="tab-pane fade show active" id="speakers-total" role="tabpanel">
									<div class="table-responsive">
										<table class="table table-striped table-sm">
											<thead>
												<tr>
													<th>Speaker</th>
													<th>Speech Count</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($generalStats["speakers"]["topSpeakers"] as $speaker): ?>
												<tr>
													<td>
														<?php if (isset($speaker["links"]["self"])): ?>
															<a href="<?= h($speaker['links']['self']) ?>" target="_blank"><?= h($speaker["label"]) ?></a>
														<?php else: ?>
															<?= h($speaker["label"]) ?>
														<?php endif; ?>
													</td>
													<td><?= number_format($speaker["speechCount"]) ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
								
								<!-- Faction-specific speaker tabs -->
								<?php foreach ($generalStats["speakers"]["byFaction"] as $faction): ?>
								<div class="tab-pane fade" id="speakers-<?= hAttr($faction['id']) ?>" role="tabpanel">
									<div class="table-responsive">
										<table class="table table-striped table-sm">
											<thead>
												<tr>
													<th>Speaker</th>
													<th>Speech Count</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($faction["topSpeakers"] as $speaker): ?>
												<tr>
													<td>
														<?php if (isset($speaker["links"]["self"])): ?>
															<a href="<?= h($speaker['links']['self']) ?>" target="_blank"><?= h($speaker["label"]) ?></a>
														<?php else: ?>
															<?= h($speaker["label"]) ?>
														<?php endif; ?>
													</td>
													<td><?= number_format($speaker["speechCount"]) ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Vocabulary Section -->
				<div class="col-xl-6 d-flex">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Most used words</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-12 mb-3">
									<div class="card flex-fill">
										<div class="card-body">
											<h6 class="card-title">Total Unique Words</h6>
											<p class="card-text"><strong><?= h(number_format($generalStats["vocabulary"]["totalUniqueWords"])) ?></strong></p>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Tabbed content for vocabulary -->
							<ul class="nav nav-tabs modern-tabs mb-3" id="vocabularyTab" role="tablist">
								<li class="nav-item" role="presentation">
									<button class="nav-link active" id="vocabulary-total-tab" data-bs-toggle="pill" data-bs-target="#vocabulary-total" type="button" role="tab">Total</button>
								</li>
								<?php if (isset($generalStats["vocabulary"]["byFaction"])): ?>
									<?php foreach ($generalStats["vocabulary"]["byFaction"] as $faction): ?>
									<li class="nav-item" role="presentation">
										<button class="nav-link" id="vocabulary-<?= hAttr($faction['id']) ?>-tab" data-bs-toggle="pill" data-bs-target="#vocabulary-<?= hAttr($faction['id']) ?>" type="button" role="tab"><?= h($faction["label"]) ?></button>
									</li>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
							
							<div class="tab-content modern-tabs" id="vocabularyTabContent">
								<!-- Total vocabulary tab -->
								<div class="tab-pane fade show active" id="vocabulary-total" role="tabpanel">
									<div class="table-responsive">
										<table class="table table-striped table-sm">
											<thead>
												<tr>
													<th>Word</th>
													<th>Speech Count</th>
													<th>Frequency</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($generalStats["vocabulary"]["topWords"] as $word): ?>
												<tr>
													<td><?= h($word["word"]) ?></td>
													<td><?= number_format($word["speechCount"]) ?></td>
													<td><?= number_format($word["frequency"]) ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
								
								<!-- Faction-specific vocabulary tabs -->
								<?php if (isset($generalStats["vocabulary"]["byFaction"])): ?>
									<?php foreach ($generalStats["vocabulary"]["byFaction"] as $faction): ?>
									<div class="tab-pane fade" id="vocabulary-<?= hAttr($faction['id']) ?>" role="tabpanel">
										<div class="table-responsive">
											<table class="table table-striped table-sm">
												<thead>
													<tr>
														<th>Word</th>
														<th>Speech Count</th>
													<th>Frequency</th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ($faction["topWords"] as $word): ?>
													<tr>
														<td><?= h($word["word"]) ?></td>
														<td><?= number_format($word["speechCount"]) ?></td>
														<td><?= number_format($word["frequency"]) ?></td>
													</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</div>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<?php
			} // End if generalStats
			?>
		</div>

		<!-- Entity Statistics Tab -->
		<div class="tab-pane fade" id="entity" role="tabpanel">
			<div class="row mb-3">
				<div class="col-md-6">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Entity Filters</h5>
						</div>
						<div class="card-body">
							<form id="entityStatsForm">
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="entityType" class="form-label">Entity Type *</label>
										<select class="form-select" id="entityType" required>
											<option value="">Select entity type</option>
											<option value="person">Person</option>
											<option value="organisation">Organisation</option>
											<option value="document">Document</option>
											<option value="term">Term</option>
										</select>
									</div>
									<div class="col-md-6 mb-3">
										<label for="entityID" class="form-label">Entity ID *</label>
										<input type="text" class="form-control" id="entityID" placeholder="e.g. Q1234567" required>
									</div>
								</div>
								<button type="button" class="btn btn-primary" onclick="loadEntityStats()">Load Data</button>
								<button type="button" class="btn btn-secondary" onclick="resetEntityStats()">Reset</button>
								
								<div class="mt-3">
									<small class="text-muted">Examples: 
										<a href="#" onclick="loadEntityExample('person', 'Q567'); return false;">Q567 (Angela Merkel, person)</a> | 
										<a href="#" onclick="loadEntityExample('organisation', 'Q56010'); return false;">Q56010 (Bundeswehr, organisation)</a>
									</small>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-12">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Entity Statistics Results</h5>
						</div>
						<div class="card-body">
							<div id="entityStatsContent">
								<div class="text-center text-muted py-5">
									<i class="bi bi-diagram-2 fs-1 mb-3"></i>
									<p>No entity selected</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Word Trends Tab -->
		<div class="tab-pane fade" id="word-trends" role="tabpanel">
			<div class="row mb-3">
				<div class="col-12">
					<div class="card flex-fill">
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
			
			<!-- Single Word Faction Trends Section -->
			<div class="row mt-4">
				<div class="col-12">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0">Word by Faction Analysis</h5>
						</div>
						<div class="card-body">
							<div class="mb-3">
								<span class="text-muted small">Analyze how a single word's usage varies across different political factions over time</span>
							</div>
							<div class="searchContainer p-0 mb-3">
								<div class="position-relative">
									<div>
										<div class="searchInputContainer clearfix">
											<input id="factionWordQuery" class="border-0 p-1" style="outline:none;width:300px;" placeholder="Enter a word to analyze by faction..." name="factionWordQuery" value="" type="text">
										</div>
									</div>
									<div class="searchSuggestionContainer" id="factionWordSuggestionContainer" style="display: none;">
										<div class="row">
											<div class="col col-12">
												<div style="font-weight: bolder;">Word Suggestions</div>
												<hr class="my-1">
												<div id="factionWordSuggestionList"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
							
							<div class="row">
								<div class="col-12 mb-3">
									<div class="rangeContainer">
										<label for="factionWordTimeRange"><b>Time Period:</b></label>
										<input type="text" id="factionWordTimeRange" readonly style="border:0; background: transparent;"/>
										<div id="factionWordChart" class="chartVisualization mt-2" style="height: 400px;">
											<div class="text-center text-muted py-3">
												<p class="small">Select a word to see faction trends</p>
											</div>
										</div>
										<div class="position-relative">
											<div id="factionWordTimelineWrapper" class="resultTimeline"></div>
											<div id="factionWordSliderRange" class="sliderRange"></div>
										</div>
										<input type="hidden" id="factionWordStartDate" value="2013-10-01"/>
										<input type="hidden" id="factionWordEndDate"/>
									</div>
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
document.getElementById('factionWordEndDate').value = new Date().toISOString().split('T')[0];

// General statistics data from server
var generalStatsData = <?= json_encode($generalStats, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;

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
var minDate = new Date("2013-10-01");
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
		const response = await fetch('/api/v1/?action=search&itemType=media&limit=1');
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
		return { errors: [{ detail: error.message }] };
	}
}

// Utility function to create Bootstrap tables
function createTable(data, columns, tableId) {
	if (!data || data.length === 0) {
		return '<div class="text-muted text-center py-3">No data available</div>';
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
function initializeSpeechesByFactionChart() {
	const chartContainer = document.getElementById('speechesByFactionChart');
	if (!chartContainer || !generalStatsData?.speeches?.byFaction) return;
	
	// Use the raw data from server
	const speechesData = generalStatsData.speeches.byFaction.map(faction => ({
		id: faction.id,
		label: faction.label,
		value: faction.total
	}));
	
	// Render donut chart
	if (speechesData.length > 0) {
		window.speechesByFactionChart = renderDonutChart({
			container: '#speechesByFactionChart',
			data: speechesData,
			type: 'donut',
			colorType: 'factions',
			valueField: 'value',
			labelField: 'label',
			idField: 'id',
			animate: true,
			animationDuration: 750,
			innerRadius: 0.8
		});
	}
}

// Initialize speaking time by faction chart from server data
function initializeSpeakingTimeByFactionChart() {
	const chartContainer = document.getElementById('speakingTimeByFactionChart');
	if (!chartContainer || !generalStatsData?.speakingTime?.byFaction) return;
	
	// Use the raw data from server
	const speakingTimeData = generalStatsData.speakingTime.byFaction.map(faction => ({
		id: faction.id,
		label: faction.label,
		value: faction.total
	}));
	
	// Render donut chart
	if (speakingTimeData.length > 0) {
		window.speakingTimeByFactionChart = renderDonutChart({
			container: '#speakingTimeByFactionChart',
			data: speakingTimeData,
			type: 'donut',
			colorType: 'factions',
			valueField: 'value',
			labelField: 'label',
			idField: 'id',
			animate: true,
			animationDuration: 750,
			innerRadius: 0.8
		});
	}
}

// Initialize average speaking time by faction chart from server data
function initializeAverageSpeakingTimeByFactionChart() {
	const chartContainer = document.getElementById('averageSpeakingTimeByFactionChart');
	if (!chartContainer || !generalStatsData?.speakingTime?.byFaction) return;
	
	// Use the raw data from server
	const averageSpeakingTimeData = generalStatsData.speakingTime.byFaction.map(faction => ({
		id: faction.id,
		label: faction.label,
		value: faction.average
	}));
	
	// Render donut chart
	if (averageSpeakingTimeData.length > 0) {
		window.averageSpeakingTimeByFactionChart = renderDonutChart({
			container: '#averageSpeakingTimeByFactionChart',
			data: averageSpeakingTimeData,
			type: 'donut',
			colorType: 'factions',
			valueField: 'value',
			labelField: 'label',
			idField: 'id',
			animate: true,
			animationDuration: 750,
			innerRadius: 0.8
		});
	}
}

// Legacy chart functions removed - now using server-side rendered content with chart initialization
// Charts are initialized from initializeGeneralStatisticsCharts() function

// Entity Statistics Functions (remain unchanged for dynamic loading)
async function loadEntityStats() {
	const entityType = document.getElementById('entityType').value;
	const entityID = document.getElementById('entityID').value;
	
	if (!entityType) {
		alert('Please select an entity type');
		return;
	}
	
	if (!entityID) {
		alert('Please enter an entity ID');
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
	
	// Assign colors to words that don't have them yet
	trendsData.forEach(wordData => {
		if (!window.wordColorAssignments.has(wordData.word)) {
			const usedColors = Array.from(window.wordColorAssignments.values());
			const availableColors = customColors.filter(color => !usedColors.includes(color));
			
			// Pick the first available color, or cycle through if all are used
			const colorToAssign = availableColors.length > 0 
				? availableColors[0] 
				: customColors[usedColors.length % customColors.length];
			
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
	
	// Add axes with consistent styling
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

// Faction word visualization
function updateFactionWordVisualization() {
	if (!currentFactionWord) {
		// Show empty state
		$('#factionWordChart').html(
			'<div class="text-center text-muted py-3">' +
			'<p class="small">Select a word to see faction trends</p>' +
			'</div>'
		);
		return;
	}

	// Get filter values
	var startDate = $('#factionWordStartDate').val() || '2013-01-01';
	var endDate = $('#factionWordEndDate').val() || new Date().toISOString().split('T')[0];
	
	// Show loading state
	$('#factionWordChart').html(
		'<div class="text-center text-muted py-3">' +
		'<div class="spinner-border spinner-border-sm me-2" role="status"></div>' +
		'<span>Loading faction data...</span>' +
		'</div>'
	);
	
	// Prepare parameters with faction separation
	var params = {
		words: [currentFactionWord],
		startDate: startDate,
		endDate: endDate,
		parliament: 'de',
		separateByFaction: true
	};

	// Make single API call with faction separation
	makeApiRequest('word-trends', params).then(function(result) {
		if (result.errors) {
			console.error('API returned errors:', result.errors);
			$('#factionWordChart').html(
				'<div class="alert alert-danger">' + (result.errors[0].detail || 'Unknown API error') + '</div>'
			);
			return;
		}
		
		displayFactionWordTrends(result.data);
	}).catch(function(error) {
		console.error('Faction word trends error:', error);
		$('#factionWordChart').html(
			'<div class="alert alert-danger">Error loading faction word trends data: ' + (error.message || error) + '</div>'
		);
	});
}

function displayFactionWordTrends(data) {
	if (!data || !data.attributes) {
		console.error('Invalid data structure:', data);
		document.getElementById('factionWordChart').innerHTML = 
			'<div class="alert alert-danger">Invalid response data structure</div>';
		return;
	}
	
	const attrs = data.attributes;
	
	// Create chart for faction-separated trends
	if (attrs.trends && attrs.trends.length > 0) {
		// Create D3.js line chart with faction separation
		createFactionWordChart(attrs.trends, attrs.startDate, attrs.endDate);
	} else {
		// Show empty state in the chart area
		document.getElementById('factionWordChart').innerHTML = 
			'<div class="text-center text-muted py-3"><p class="small">No trend data available for "' + currentFactionWord + '"</p></div>';
	}
}

function createFactionWordChart(trendsData, startDate, endDate) {
	// Clear any existing chart
	d3.select("#factionWordChart").selectAll("*").remove();
	
	// Get container dimensions
	const container = document.getElementById('factionWordChart');
	let containerWidth = container.offsetWidth;
	let containerHeight = container.offsetHeight;
	
	// Fallback if container dimensions are 0 or too small
	if (containerWidth < 200) {
		containerWidth = container.parentElement.offsetWidth || 800;
	}
	if (containerHeight < 200) {
		containerHeight = 400; // Fallback height
	}
	
	// Set dimensions and margins
	const margin = {top: 20, right: 120, bottom: 25, left: 35};
	const width = containerWidth - margin.left - margin.right;
	const height = containerHeight - margin.top - margin.bottom;
	
	// Create SVG
	const svg = d3.select("#factionWordChart")
		.append("svg")
		.attr("width", "100%")
		.attr("height", height + margin.top + margin.bottom)
		.attr("viewBox", `0 0 ${width + margin.left + margin.right} ${height + margin.top + margin.bottom}`)
		.append("g")
		.attr("transform", `translate(${margin.left},${margin.top})`);
	
	// Parse data
	const parseDate = d3.timeParse("%Y-%m");
	const parseInputDate = d3.timeParse("%Y-%m-%d");
	
	// Use faction colors from generic.js
	const factionColors = {
		...factionIDColors, // Import from generic.js
		"Q4316268": "#808080"     // fraktionslos - gray (not in generic.js)
	};
	
	// Prepare data by faction
	const allData = [];
	const factionLabels = {};
	
	trendsData.forEach(wordData => {
		if (wordData.factionBreakdown) {
			wordData.factionBreakdown.forEach(factionData => {
				factionLabels[factionData.factionID] = factionData.factionLabel;
				factionData.timeline.forEach(timePoint => {
					allData.push({
						faction: factionData.factionID,
						factionLabel: factionData.factionLabel,
						date: parseDate(timePoint.date),
						count: timePoint.totalCount || 0
					});
				});
			});
		}
	});
	
	// Filter data to date range
	const startDateParsed = parseInputDate(startDate);
	const endDateParsed = parseInputDate(endDate);
	let filteredData = allData.filter(d => d.date >= startDateParsed && d.date <= endDateParsed);
	
	// Set scales
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
	
	// Add axes with consistent styling
	svg.append("g")
		.attr("transform", `translate(0,${height})`)
		.call(d3.axisBottom(xScale))
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");
	
	svg.append("g")
		.call(d3.axisLeft(yScale))
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");
	
	// Group data by faction
	const factionGroups = d3.group(filteredData, d => d.faction);
	
	// Create legend
	const legend = svg.append("g")
		.attr("class", "legend")
		.attr("transform", `translate(${width + 10}, 20)`);
	
	let legendY = 0;
	
	// Draw lines for each faction
	factionGroups.forEach((values, factionID) => {
		// Sort by date
		values.sort((a, b) => a.date - b.date);
		
		const color = factionColors[factionID] || '#999999';
		
		// Draw line
		svg.append("path")
			.datum(values)
			.attr("fill", "none")
			.attr("stroke", color)
			.attr("stroke-width", 2)
			.attr("d", line);
		
		// Add legend item
		const legendItem = legend.append("g")
			.attr("transform", `translate(0, ${legendY})`);
		
		legendItem.append("line")
			.attr("x1", 0)
			.attr("x2", 15)
			.attr("y1", 0)
			.attr("y2", 0)
			.attr("stroke", color)
			.attr("stroke-width", 2);
		
		legendItem.append("text")
			.attr("x", 20)
			.attr("y", 0)
			.attr("dy", "0.35em")
			.style("font-size", "12px")
			.style("font-family", "inherit")
			.text(factionLabels[factionID] || factionID);
		
		legendY += 20;
	});
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



// Statistics are loaded on-demand to optimize initial page load time
</script>

<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<?php
}
?>