<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<?php
// The overview figures are fetched by pageStatistics() in modules/routing/handlers.php,
// which resolves the parliament rather than assuming one.
$generalStats = $generalStats["data"]["attributes"] ?? null;
$statisticsParliaments = $config["parliament"] ?? [];
?>
<main class="container-fluid subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12 mb-3">
			<h2><?= h(L::statistics()) ?></h2>
			<p class="text-muted"><?= h(L::statisticsClaim()) ?></p>
		</div>
	</div>

	<?php if (count($statisticsParliaments) > 1): ?>
	<!-- Only rendered when the instance actually serves more than one parliament. -->
	<div class="row mb-3">
		<div class="col-12">
			<div class="btn-group btn-group-sm" role="group" id="statisticsParliamentSwitcher">
				<?php foreach ($statisticsParliaments as $parliamentKey => $parliamentConfig): ?>
				<a class="btn btn-outline-secondary<?= $parliamentKey === $statisticsParliament ? ' active' : '' ?>"
				   href="<?= hAttr($config["dir"]["root"] . '/statistics?parliament=' . $parliamentKey) ?>"><?= h($parliamentConfig["label"] ?? $parliamentKey) ?></a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Statistics Navigation -->
	<ul class="nav nav-tabs modern-tabs" id="statisticsTab" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
				<?= h(L::statisticsOverview()) ?>
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="entity-tab" data-bs-toggle="tab" data-bs-target="#entity" type="button" role="tab">
				<?= h(L::statisticsEntities()) ?>
			</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="word-trends-tab" data-bs-toggle="tab" data-bs-target="#word-trends" type="button" role="tab">
				<?= h(L::statisticsWordTrends()) ?>
			</button>
		</li>
	</ul>

	<!-- Tab Content -->
	<div class="tab-content modern-tabs" id="statisticsTabContent">

		<!-- General Statistics Tab -->
		<div class="tab-pane fade show active" id="general" role="tabpanel">
			<?php
			if (!$generalStats) {
				echo '<div class="alert alert-warning">' . h(L::statisticsUnavailable()) . '</div>';
			} else {
			?>

			<!-- Row 1: Speeches + Speaking Time -->
			<div class="row mb-4 align-items-stretch">
				<!-- Speeches Section -->
				<div class="col-xl-6 mb-4 mb-xl-0 d-flex">
					<div class="card flex-fill">
						<div class="card-header">
							<h5 class="card-title mb-0"><?= h(L::statisticsSpeechesPerFaction()) ?></h5>
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
													<h6 class="card-title"><?= h(L::statisticsTotalSpeeches()) ?></h6>
													<p class="card-text"><strong><?= h(number_format($generalStats["speeches"]["total"])) ?></strong></p>
												</div>
											</div>
										</div>
									</div>
									<div class="table-responsive">
										<table class="table table-striped table-sm">
											<thead>
												<tr>
													<th><?= h(L::faction()) ?></th>
													<th><?= h(L::statisticsSpeechCount()) ?></th>
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
							<h5 class="card-title mb-0"><?= h(L::statisticsSpeakingTimePerFaction()) ?></h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-sm-4 col-md-3 col-lg-2">
									<h6 class="mb-2 text-center"><?= h(L::total()) ?></h6>
									<div class="chart-container mb-3" style="height: 100px;">
										<div id="speakingTimeByFactionChart" style="height: 100%; width: 100%;"></div>
									</div>
									
									<h6 class="mb-2 mt-4 text-center"><?= h(L::statisticsAverage()) ?></h6>
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
														<h6 class="card-title"><?= h(L::statisticsTotalSpeakingTime()) ?></h6>
														<p class="card-text"><strong><?= h(getTimeDistanceString(['input' => $generalStats["speakingTime"]["total"], 'mode' => 'duration', 'short' => true])) ?></strong></p>
													</div>
												</div>
											</div>
											<div class="col-md-6">
												<div class="card flex-fill">
													<div class="card-body">
														<h6 class="card-title"><?= h(L::statisticsAverageSpeakingTime()) ?></h6>
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
														<th><?= h(L::faction()) ?></th>
														<th><?= h(L::total()) ?></th>
														<th><?= h(L::statisticsAverage()) ?></th>
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
							<h5 class="card-title mb-0"><?= h(L::statisticsTopSpeakers()) ?></h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-12 mb-3">
									<div class="card flex-fill">
										<div class="card-body">
											<h6 class="card-title"><?= h(L::statisticsTotalSpeakers()) ?></h6>
											<p class="card-text"><strong><?= h(number_format($generalStats["speakers"]["total"])) ?></strong></p>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Tabbed content for speakers -->
							<ul class="nav nav-tabs modern-tabs mb-3" id="speakersTab" role="tablist">
								<li class="nav-item" role="presentation">
									<button class="nav-link active" id="speakers-total-tab" data-bs-toggle="pill" data-bs-target="#speakers-total" type="button" role="tab"><?= h(L::total()) ?></button>
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
													<th><?= h(L::personSingular()) ?></th>
													<th><?= h(L::statisticsSpeechCount()) ?></th>
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
													<th><?= h(L::personSingular()) ?></th>
													<th><?= h(L::statisticsSpeechCount()) ?></th>
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
							<h5 class="card-title mb-0"><?= h(L::statisticsMostUsedWords()) ?></h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-12 mb-3">
									<div class="card flex-fill">
										<div class="card-body">
											<h6 class="card-title"><?= h(L::statisticsUniqueWords()) ?></h6>
											<p class="card-text"><strong><?= h(number_format($generalStats["vocabulary"]["totalUniqueWords"])) ?></strong></p>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Tabbed content for vocabulary -->
							<ul class="nav nav-tabs modern-tabs mb-3" id="vocabularyTab" role="tablist">
								<li class="nav-item" role="presentation">
									<button class="nav-link active" id="vocabulary-total-tab" data-bs-toggle="pill" data-bs-target="#vocabulary-total" type="button" role="tab"><?= h(L::total()) ?></button>
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
													<th><?= h(L::statisticsWord()) ?></th>
													<th><?= h(L::statisticsSpeechCount()) ?></th>
													<th><?= h(L::statisticsFrequency()) ?></th>
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
														<th><?= h(L::statisticsWord()) ?></th>
														<th><?= h(L::statisticsSpeechCount()) ?></th>
													<th><?= h(L::statisticsFrequency()) ?></th>
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
							<h5 class="card-title mb-0"><?= h(L::statisticsEntities()) ?></h5>
						</div>
						<div class="card-body">
							<form id="entityStatsForm">
								<div class="row">
									<div class="col-md-6 mb-3">
										<label for="entityType" class="form-label">Entity Type *</label>
										<select class="form-select" id="entityType" required>
											<option value=""><?= h(L::statisticsEntityType()) ?></option>
											<option value="person"><?= h(L::personSingular()) ?></option>
											<option value="organisation"><?= h(L::organisation()) ?></option>
											<option value="document"><?= h(L::document()) ?></option>
											<option value="term"><?= h(L::term()) ?></option>
										</select>
									</div>
									<div class="col-md-6 mb-3">
										<label for="entityID" class="form-label">Entity ID *</label>
										<input type="text" class="form-control" id="entityID" placeholder="e.g. Q1234567" required>
									</div>
								</div>
								<button type="button" class="btn btn-primary" onclick="loadEntityStats()"><?= h(L::showResult()) ?></button>
								<button type="button" class="btn btn-secondary" onclick="resetEntityStats()"><?= h(L::statisticsReset()) ?></button>
								
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
							<h5 class="card-title mb-0"><?= h(L::statisticsMentions()) ?></h5>
						</div>
						<div class="card-body">
							<div id="entityStatsContent">
								<div class="text-center text-muted py-5">
									<i class="bi bi-diagram-2 fs-1 mb-3"></i>
									<p><?= h(L::statisticsNoEntitySelected()) ?></p>
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
						<div class="card-header d-flex flex-wrap gap-2 align-items-center">
							<h5 class="card-title mb-0 me-auto"><?= h(L::statisticsWord()) ?></h5>
							<select class="form-select form-select-sm w-auto" id="statisticsDistinctiveScope"></select>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-lg-6 mb-3 mb-lg-0">
									<h6><?= h(L::statisticsMostUsedWords()) ?></h6>
									<p class="small text-muted"><?= h(L::statisticsWordsNote()) ?></p>
									<div id="statisticsMostUsedWords" class="d-flex flex-wrap gap-2 align-items-baseline"></div>
								</div>
								<div class="col-lg-6">
									<h6><?= h(L::statisticsDistinctiveWords()) ?></h6>
									<p class="small text-muted"><?= h(L::statisticsDistinctiveWordsNote()) ?></p>
									<div id="statisticsDistinctiveWords" class="d-flex flex-wrap gap-2 align-items-baseline"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row mb-3">
				<div class="col-12">
					<div class="card flex-fill">
						<div class="card-body">
							<div class="mb-2">
								<span class="text-muted small"><?= h(L::example()) ?>: <a href="#" id="wordTrendsExample">pandemie, flüchtlinge, ukraine</a></span>
							</div>
							<div class="searchContainer p-0">
								<div class="position-relative">
									<div>
										<div class="searchInputContainer clearfix">
											<input id="wordTrendsQuery" class="border-0 p-1" style="outline:none;width:300px;" placeholder="<?= hAttr(L::statisticsAddWord()) ?>" name="wordTrendsQuery" value="" type="text">
										</div>
									</div>
									<div class="searchSuggestionContainer">
										<div class="row">
											<div class="col col-12">
												<div style="font-weight: bolder;"><?= h(L::statisticsWordSuggestions()) ?></div>
												<hr class="my-1">
												<div id="wordSuggestionContainer"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
							
							<div class="row mt-3">
								<div class="col-12">
									<div class="d-flex flex-wrap gap-3 align-items-end">
										<div>
											<label class="form-label mb-1" for="statisticsMatchMode"><b><?= h(L::statisticsWord()) ?></b></label>
											<select class="form-select form-select-sm" id="statisticsMatchMode">
												<option value="stem"><?= h(L::statisticsMatchStem()) ?></option>
												<option value="exact"><?= h(L::statisticsMatchExact()) ?></option>
											</select>
										</div>
										<div>
											<label class="form-label mb-1" for="statisticsNormalizeMode"><b><?= h(L::statisticsFrequency()) ?></b></label>
											<select class="form-select form-select-sm" id="statisticsNormalizeMode">
												<option value="absolute"><?= h(L::statisticsAbsolute()) ?></option>
												<option value="relative"><?= h(L::statisticsRelative()) ?></option>
											</select>
										</div>
										<div class="flex-grow-1">
											<small class="text-muted" id="statisticsModeNote"><?= h(L::statisticsRelativeNote()) ?></small>
										</div>
										<div>
											<button type="button" class="btn btn-sm btn-outline-secondary" id="statisticsDownloadCsv"><?= h(L::statisticsDownloadCsv()) ?></button>
										</div>
									</div>
								</div>
							</div>

							<div class="row mt-3">
								<div class="col-12 mb-3">
									<div class="rangeContainer">
										<label for="wordTrendsTimeRange"><b><?= h(L::timePeriod()) ?></b></label>
										<input type="text" id="wordTrendsTimeRange" readonly style="border:0; background: transparent;"/>
										<div id="wordTrendsChart" class="chartVisualization mt-2">
											<div class="text-center text-muted py-3">
												<p class="small"><?= h(L::statisticsNoWordsSelected()) ?></p>
											</div>
										</div>
										<div class="position-relative">
											<div id="wordTrendsTimelineWrapper" class="resultTimeline"></div>
											<div id="wordTrendsSliderRange" class="sliderRange"></div>
										</div>
										<input type="hidden" id="wordTrendsStartDate" value="<?= hAttr($statisticsDataStart) ?>"/>
										<input type="hidden" id="wordTrendsEndDate"/>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<label class="form-label"><b><?= h(L::statisticsFilterByFactions()) ?></b></label>
									<div class="d-flex flex-wrap gap-3 checkboxList">
										<?php
										// The same list the search filter bar renders, so both stay in step and
										// both pick up the per-faction colours head.php generates from the same
										// query. This used to be eight German Wikidata ids written into the
										// markup — wrong on any other parliament, and quietly stale on this one
										// whenever a new faction enters parliament.
										$factionCheckboxName = 'wordTrendsFactionID[]';
										$factionCheckboxPrefix = 'wordtrends-faction-';
										$factionCheckboxClass = 'wordTrendsFactionCheckbox';
										include(__DIR__ . '/../../components/faction-checkboxes.php');
										?>
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
							<h5 class="card-title mb-0"><?= h(L::statisticsWordTrends()) ?></h5>
						</div>
						<div class="card-body">
							<div class="mb-3">
								<span class="text-muted small">Analyze how a single word's usage varies across different political factions over time</span>
							</div>
							<div class="searchContainer p-0 mb-3">
								<div class="position-relative">
									<div>
										<div class="searchInputContainer clearfix">
											<input id="factionWordQuery" class="border-0 p-1" style="outline:none;width:300px;" placeholder="<?= hAttr(L::statisticsAddWord()) ?>" name="factionWordQuery" value="" type="text">
										</div>
									</div>
									<div class="searchSuggestionContainer" id="factionWordSuggestionContainer" style="display: none;">
										<div class="row">
											<div class="col col-12">
												<div style="font-weight: bolder;"><?= h(L::statisticsWordSuggestions()) ?></div>
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
										<label for="factionWordTimeRange"><b><?= h(L::timePeriod()) ?></b></label>
										<input type="text" id="factionWordTimeRange" readonly style="border:0; background: transparent;"/>
										<div id="factionWordChart" class="chartVisualization mt-2" style="height: 400px;">
											<div class="text-center text-muted py-3">
												<p class="small"><?= h(L::statisticsNoWordsSelected()) ?></p>
											</div>
										</div>
										<div class="position-relative">
											<div id="factionWordTimelineWrapper" class="resultTimeline"></div>
											<div id="factionWordSliderRange" class="sliderRange"></div>
										</div>
										<input type="hidden" id="factionWordStartDate" value="<?= hAttr($statisticsDataStart) ?>"/>
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
// Everything the controller needs from the server. The page must not hardcode a
// parliament or a corpus start date: both differ per instance.
const OPTV_STATISTICS = {
	parliament: <?= json_encode($statisticsParliament, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>,
	dataStart: <?= json_encode($statisticsDataStart, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>,
	general: <?= json_encode($generalStats, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/statistics-charts.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/statistics/client/statistics.js?v=<?= $config["version"] ?>"></script>
