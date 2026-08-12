/**
 * Fills the statistics module on an entity page.
 *
 * Reads the entity from the module's data attributes rather than from a global,
 * so the partial stays self-contained and the page needs no extra plumbing.
 *
 * Everything renders from one API call. If it fails, the module removes itself
 * instead of leaving four empty cards: a page that is missing a section reads as
 * a page without that section, while a page of empty boxes reads as broken.
 */

$(document).ready(function () {
	const module = document.getElementById('entityStatisticsModule');
	if (!module) return;

	const entityType = module.getAttribute('data-entity-type');
	const entityID = module.getAttribute('data-entity-id');
	const speaks = module.getAttribute('data-entity-speaks') === '1';
	if (!entityType || !entityID) return;

	entityStatisticsFetch(entityType, entityID).then(function (attrs) {
		if (!attrs) {
			$(module).remove();
			return;
		}

		const context = {
			entityID: entityID,
			entityType: entityType,
			filterName: entityStatisticsFilterName(entityType)
		};

		entityStatisticsRenderMentions('entityStatisticsMentions', attrs.mentionsOverTime, context);

		entityStatisticsRenderNetwork('entityStatisticsNetwork',
			attrs.entity || { id: entityID, type: entityType, label: entityID },
			(attrs.entityAssociations || {}).topDetectedEntities);

		entityStatisticsRenderMentionedBy('entityStatisticsMentionedBy',
			(attrs.entityAssociations || {}).topMentionedBy, context);

		if (speaks) {
			entityStatisticsRenderWords('entityStatisticsWords', attrs.speakerVocabulary, context);
			entityStatisticsLoadDistinctive('entityStatisticsDistinctive', entityType, entityID, context);
		}
	});
});

/**
 * Who talks about this entity most.
 *
 * Each name links to that person's speeches mentioning this entity, which is
 * the question the list raises and would otherwise leave unanswered.
 */
function entityStatisticsRenderMentionedBy(containerId, mentionedBy, context) {
	const container = $('#' + containerId);
	if (!container.length) return;

	const rows = (mentionedBy || []).slice(0, 10);
	if (!rows.length) {
		container.html('<div class="text-muted small py-2">' + statisticsLabel('statisticsNoData') + '</div>');
		return;
	}

	const list = $('<ul class="list-unstyled mb-0"></ul>');
	rows.forEach(function (row) {
		const filters = { personID: row.id };
		filters[context.filterName] = context.entityID + '~NER';

		$('<li class="mb-1"></li>')
			.append($('<a></a>').attr('href', statisticsSearchUrl(filters)).text(row.label || row.id))
			.append($('<span class="text-muted small ms-2"></span>').text(row.coOccurrenceCount))
			.appendTo(list);
	});
	container.empty().append(list);
}

/**
 * This speaker's most-used words.
 *
 * Each word links to their speeches containing it. Only rendered for entities
 * that actually speak — see the partial.
 */
function entityStatisticsRenderWords(containerId, vocabulary, context) {
	const container = $('#' + containerId);
	if (!container.length) return;

	const words = ((vocabulary || {}).topWords || []).slice(0, 20);
	if (!words.length) {
		container.html('<div class="text-muted small py-2">' + statisticsLabel('statisticsNoData') + '</div>');
		return;
	}

	const max = d3.max(words, w => w.frequency) || 1;
	const min = d3.min(words, w => w.frequency) || 0;
	const size = d3.scaleLinear().domain([min, max]).range([0.85, 1.6]);

	const cloud = $('<div class="d-flex flex-wrap gap-2 align-items-baseline"></div>');
	words.forEach(function (entry) {
		$('<a class="me-1"></a>')
			.attr('href', statisticsSearchUrl({
				q: entry.word,
				[context.filterName]: context.entityID
			}))
			.attr('title', entry.frequency + ' × / ' + entry.speechCount + ' ' + statisticsLabel('speeches'))
			.css({ 'font-size': size(entry.frequency).toFixed(2) + 'rem', 'line-height': 1.4 })
			.text(entry.word)
			.appendTo(cloud);
	});

	container.empty().append(cloud);

	if (vocabulary.uniqueWords) {
		$('<div class="small text-muted mt-2"></div>')
			.text(statisticsLabel('statisticsUniqueWords') + ': ' + vocabulary.uniqueWords.toLocaleString())
			.appendTo(container);
	}
}


/**
 * What this speaker says more than others do.
 *
 * The companion to the most-used list beside it. Most-used is nearly the same
 * for everyone -- eleven of the top twenty words are shared by all five
 * Bundestag factions -- so it describes parliamentary German rather than this
 * speaker. This answers the question the other list raises.
 */
function entityStatisticsLoadDistinctive(containerId, entityType, entityID, context) {
	const container = $('#' + containerId);
	if (!container.length) return;

	// Only the two scopes the endpoint understands as "who was speaking".
	const scopeKey = entityType === 'person' ? 'personID'
		: (entityType === 'organisation' ? 'factionID' : null);
	if (!scopeKey) {
		container.closest('.col-lg-6').remove();
		return;
	}

	const params = new URLSearchParams({ size: 20 });
	params.set(scopeKey, entityID);

	fetch('/api/v1/statistics/significant-words?' + params.toString())
		.then(r => r.json())
		.then(function (result) {
			const words = (((result || {}).data || {}).attributes || {}).words || [];
			if (!words.length) {
				container.closest('.col-lg-6').remove();
				return;
			}

			const max = d3.max(words, w => w.score) || 1;
			const min = d3.min(words, w => w.score) || 0;
			const size = d3.scaleLinear().domain([min, max]).range([0.85, 1.6]);

			const cloud = $('<div class="d-flex flex-wrap gap-2 align-items-baseline"></div>');
			words.forEach(function (entry) {
				const filters = { q: entry.word };
				filters[context.filterName] = context.entityID;
				$('<a class="me-1"></a>')
					.attr('href', statisticsSearchUrl(filters))
					.attr('title', entry.speechCount + ' ' + statisticsLabel('speeches'))
					.css({ 'font-size': size(entry.score).toFixed(2) + 'rem', 'line-height': 1.4 })
					.text(entry.word)
					.appendTo(cloud);
			});
			container.empty().append(cloud);
		})
		.catch(function () {
			container.closest('.col-lg-6').remove();
		});
}