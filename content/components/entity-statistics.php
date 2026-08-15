<?php defined('OPTV') or die(); ?>
<?php
/**
 * Statistics module for an entity page.
 *
 * Two blocks, and which of them appear depends on the entity:
 *
 *   Activity  when and how much this entity SPOKE. Only people and the
 *             faction-like organisations ever do, so it is omitted entirely for
 *             documents, terms, ministries and companies rather than rendered
 *             empty — a block reading "0" invites the reader to conclude the
 *             data is missing.
 *   Mentions  when this entity was TALKED ABOUT. Applies to everything, and is
 *             the only block a document or a term gets.
 *
 * The blocks are shells: the figures load client-side from
 * content/client/js/entity-statistics.js, so an entity page is never held up by
 * a statistics query.
 *
 * Expects from the page: $data (the entity node) and $page (its type).
 */

$entityStatsType = $page ?? '';
$entityStatsID = $data['id'] ?? '';

if ($entityStatsType === '' || $entityStatsID === '') {
    return;
}

// Whether this entity can appear as a speaker at all. Person pages always can;
// organisations only when they are the kind that holds seats.
$entityStatsSubType = $data['attributes']['type'] ?? '';
$entityStatsSpeaks = ($entityStatsType === 'person')
    || ($entityStatsType === 'organisation' && in_array($entityStatsSubType, ['faction', 'party'], true));
?>

<div class="row" id="entityStatisticsModule"
     data-entity-type="<?= hAttr($entityStatsType) ?>"
     data-entity-id="<?= hAttr($entityStatsID) ?>"
     data-entity-speaks="<?= $entityStatsSpeaks ? '1' : '0' ?>">

	<?php if ($entityStatsSpeaks): ?>
	<?php
	/*
	 * Most-used and distinctive side by side, because the pair is the point. The
	 * most-used list is very nearly the same for everyone -- eleven of the top
	 * twenty words are shared by all five Bundestag factions -- so on its own it
	 * says little. Seen next to what is distinctive, it shows why.
	 */
	?>
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsMostUsedWords()) ?></h5>
			</div>
			<div class="card-body">
				<p class="small text-muted"><?= h(L::statisticsWordsNote()) ?></p>
				<div id="entityStatisticsWords"></div>
			</div>
		</div>
	</div>
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsDistinctiveWords()) ?></h5>
			</div>
			<div class="card-body">
				<p class="small text-muted"><?= h(L::statisticsDistinctiveWordsNote()) ?></p>
				<div id="entityStatisticsDistinctive"></div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($entityStatsSpeaks): ?>
	<?php
	/*
	 * The same pair again, asked of who and what was NAMED rather than of the
	 * words used. Counts cover the speaker's own words only -- the interjections
	 * the protocol records ("(Beifall bei der SPD)", "(Zuruf des Abg. X)") carry
	 * 71% of all recognised entity mentions, and crediting those to the speaker
	 * would make this a list of who heckles them.
	 *
	 * One type selector for both panels: the reader is asking one question and
	 * choosing which kind of answer to see.
	 */
	?>
	<div class="col-12" id="entityStatisticsEntitiesModule">
		<div class="row">
			<div class="col-12 mb-2 d-flex justify-content-end">
				<label class="form-label mb-0 me-2 align-self-center small text-muted" for="entityStatisticsTypeSelect"><?= h(L::statisticsEntityType()) ?></label>
				<select class="form-select form-select-sm w-auto entityStatisticsTypeSelect" id="entityStatisticsTypeSelect">
					<option value="person"><?= h(L::personPlural()) ?></option>
					<option value="organisation"><?= h(L::organisations()) ?></option>
					<option value="term"><?= h(L::terms()) ?></option>
				</select>
			</div>
			<div class="col-lg-6 mb-4">
				<div class="card h-100">
					<div class="card-header">
						<h5 class="card-title mb-0"><?= h(L::statisticsMostMentioned()) ?></h5>
					</div>
					<div class="card-body">
						<p class="small text-muted"><?= h(L::statisticsWordsNote()) ?></p>
						<div id="entityStatisticsMostMentioned"></div>
					</div>
				</div>
			</div>
			<div class="col-lg-6 mb-4">
				<div class="card h-100">
					<div class="card-header">
						<h5 class="card-title mb-0"><?= h(L::statisticsDistinctiveEntities()) ?></h5>
					</div>
					<div class="card-body">
						<p class="small text-muted"><?= h(L::statisticsDistinctiveEntitiesNote()) ?></p>
						<div id="entityStatisticsDistinctiveEntities"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($entityStatsSpeaks): ?>
	<?php
	/*
	 * Only for entities that speak. For everything else the timeline in the page
	 * header already IS the mention timeline -- `/search/media?termID=…` carries no
	 * default context, so it counts exactly the NER mentions this chart would, and
	 * measurement confirms it: 82 vs 84 for a term, 613 vs 622 for a document.
	 *
	 * A person or a faction is the one case where the two differ, because their
	 * header timeline defaults to the main-speaker context and therefore shows when
	 * they SPOKE, not when they were talked about.
	 */
	?>
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsMentions()) ?></h5>
			</div>
			<div class="card-body">
				<p class="small text-muted"><?= h(L::statisticsMentionsNote()) ?></p>
				<div id="entityStatisticsMentions" style="height: 180px;"></div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsCoOccurring()) ?></h5>
			</div>
			<div class="card-body">
				<div id="entityStatisticsNetwork" style="height: 320px;"></div>
			</div>
		</div>
	</div>

	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="card-title mb-0"><?= h(L::statisticsMentionedBy()) ?></h5>
			</div>
			<div class="card-body">
				<div id="entityStatisticsMentionedBy"></div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/statistics-charts.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/entity-statistics.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/entity-statistics-init.js?v=<?= $config["version"] ?>"></script>
