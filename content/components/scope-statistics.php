<?php defined('OPTV') or die(); ?>
<?php
/**
 * Statistics dashboard for a stretch of parliamentary time.
 *
 * Used by the session, agendaItem and electoralPeriod pages. All three ask the
 * same questions of a different slice — how much was said, by whom, and what
 * made this slice different — so they share one partial scoped by a single
 * parameter.
 *
 * The figures come from a scoped `statistics/general`, fetched here rather than
 * client-side: they are cached per scope and the page can render complete on
 * first paint. Distinctive words load client-side, because significant_text is
 * the one query that can be slow enough to be worth not blocking on.
 *
 * Expects from the page, before including:
 *   $scopeStatsParam  'sessionID' | 'agendaItemID' | 'electoralPeriodID'
 *   $scopeStatsValue  the id to scope to
 */

if (empty($scopeStatsParam) || empty($scopeStatsValue)) {
    return;
}

$scopeStatsResult = apiV1([
    'action' => 'statistics',
    'itemType' => 'general',
    $scopeStatsParam => $scopeStatsValue,
]);
$scopeStats = $scopeStatsResult['data']['attributes'] ?? null;

// An empty scope is a normal state (an agenda item with no speeches yet), not an
// error, and an empty dashboard says less than no dashboard.
if (!$scopeStats || empty($scopeStats['speeches']['total'])) {
    return;
}

$scopeStatsCompact = ($scopeStatsParam === 'agendaItemID');
?>

<div class="row" id="scopeStatisticsModule"
     data-scope-param="<?= hAttr($scopeStatsParam) ?>"
     data-scope-value="<?= hAttr($scopeStatsValue) ?>">

	<div class="col-12 mb-3">
		<div class="row g-3">
			<div class="col-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<h6 class="card-title"><?= h(L::statisticsTotalSpeeches()) ?></h6>
						<p class="card-text mb-0"><strong><?= h(number_format($scopeStats['speeches']['total'])) ?></strong></p>
					</div>
				</div>
			</div>
			<div class="col-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<h6 class="card-title"><?= h(L::statisticsTotalSpeakers()) ?></h6>
						<p class="card-text mb-0"><strong><?= h(number_format($scopeStats['speakers']['total'] ?? 0)) ?></strong></p>
					</div>
				</div>
			</div>
			<?php if (!empty($scopeStats['speakingTime']['total'])): ?>
			<div class="col-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<h6 class="card-title"><?= h(L::statisticsTotalSpeakingTime()) ?></h6>
						<p class="card-text mb-0"><strong><?= h(getTimeDistanceString(['input' => $scopeStats['speakingTime']['total'], 'mode' => 'duration', 'short' => true])) ?></strong></p>
						<small class="text-muted"><?= h(L::statisticsSpeakingTimeNote()) ?></small>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<?php if (!empty($scopeStats['vocabulary']['totalUniqueWords'])): ?>
			<div class="col-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<h6 class="card-title"><?= h(L::statisticsUniqueWords()) ?></h6>
						<p class="card-text mb-0"><strong><?= h(number_format($scopeStats['vocabulary']['totalUniqueWords'])) ?></strong></p>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if (!empty($scopeStats['speakingTime']['byFaction'])): ?>
	<div class="col-lg-<?= $scopeStatsCompact ? '12' : '6' ?> mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsSpeakingTimePerFaction()) ?></h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm table-striped mb-0">
						<thead>
							<tr>
								<th><?= h(L::faction()) ?></th>
								<th><?= h(L::total()) ?></th>
								<th><?= h(L::statisticsAverage()) ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($scopeStats['speakingTime']['byFaction'] as $scopeFaction): ?>
							<tr>
								<td><span class="partyIndicatorInline partyIndicator" data-faction="<?= hAttr($scopeFaction['id']) ?>"><?= h($scopeFaction['label']) ?></span></td>
								<td><?= h(getTimeDistanceString(['input' => $scopeFaction['total'], 'mode' => 'duration', 'short' => true])) ?></td>
								<td><?= h(getTimeDistanceString(['input' => $scopeFaction['average'], 'mode' => 'duration', 'short' => true])) ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if (!$scopeStatsCompact && !empty($scopeStats['speakers']['topSpeakers'])): ?>
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsTopSpeakers()) ?></h5>
			</div>
			<div class="card-body">
				<ul class="list-unstyled mb-0">
					<?php foreach (array_slice($scopeStats['speakers']['topSpeakers'], 0, 10) as $scopeSpeaker): ?>
					<li class="mb-1">
						<a href="<?= hAttr($config['dir']['root'] . '/person/' . $scopeSpeaker['id']) ?>"><?= h($scopeSpeaker['label']) ?></a>
						<span class="text-muted small ms-2"><?= h(number_format($scopeSpeaker['speechCount'])) ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php
	/*
	 * The two lists belong together. On its own the most-used list describes
	 * parliamentary German rather than this sitting -- the same content nouns come
	 * top whatever the scope -- and the distinctive list is what actually answers
	 * "what was this one about". Shown side by side, the first explains why the
	 * second is needed.
	 */
	?>
	<?php if (!empty($scopeStats['vocabulary']['topWords'])): ?>
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsMostUsedWords()) ?></h5>
			</div>
			<div class="card-body">
				<p class="small text-muted"><?= h(L::statisticsWordsNote()) ?></p>
				<div class="d-flex flex-wrap gap-2 align-items-baseline">
					<?php foreach (array_slice($scopeStats['vocabulary']['topWords'], 0, 20) as $scopeWord): ?>
					<a href="<?= hAttr($config['dir']['root'] . '/search?' . http_build_query(['q' => $scopeWord['word'], $scopeStatsParam => $scopeStatsValue])) ?>"
					   title="<?= hAttr($scopeWord['frequency'] . ' ×') ?>"><?= h($scopeWord['word']) ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsDistinctiveWords()) ?></h5>
			</div>
			<div class="card-body">
				<p class="small text-muted"><?= h(L::statisticsDistinctiveWordsNote()) ?></p>
				<div id="scopeStatisticsWords" class="d-flex flex-wrap gap-2 align-items-baseline"></div>
			</div>
		</div>
	</div>
</div>

<!-- statistics-charts.js supplies statisticsSearchUrl() and statisticsLabel(). -->
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/statistics-charts.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/scope-statistics.js?v=<?= $config["version"] ?>"></script>
