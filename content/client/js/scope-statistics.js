/**
 * Loads the distinctive words and entities for a scoped statistics dashboard.
 *
 * Everything else in the dashboard is rendered server-side from cached figures;
 * these two blocks are fetched separately because significance is the one query
 * worth not blocking first paint on.
 *
 * Words are sized by how distinctive they are, entities are drawn as cards with a
 * proportional bar, and both link to the speeches in this scope behind them.
 */

$(document).ready(function () {
	const module = document.getElementById('scopeStatisticsModule');
	if (!module) return;

	const param = module.getAttribute('data-scope-param');
	const value = module.getAttribute('data-scope-value');
	if (!param || !value) return;

	scopeStatisticsLoadWords(param, value);
	scopeStatisticsLoadEntities(param, value);
});

function scopeStatisticsLoadWords(param, value) {
	const container = $('#scopeStatisticsWords');
	if (!container.length) return;

	const params = new URLSearchParams({ size: 20 });
	params.set(param, value);

	fetch(config.dir.root + '/api/v1/statistics/significant-words?' + params.toString())
		.then(response => response.json())
		.then(function (result) {
			const words = (((result || {}).data || {}).attributes || {}).words || [];

			// A short sitting genuinely may have nothing that stands out against
			// the rest of the corpus. Drop the card rather than show an empty one.
			if (!words.length) {
				container.closest('.col-lg-6').remove();
				return;
			}

			const max = d3.max(words, w => w.score) || 1;
			const min = d3.min(words, w => w.score) || 0;
			const size = d3.scaleLinear().domain([min, max]).range([0.85, 1.7]);

			container.empty();
			words.forEach(function (entry) {
				const filters = { q: entry.word };
				// Scope parameter names are shared with media search, so the same
				// key that scoped the statistics query scopes the search.
				filters[param] = value;

				$('<a class="me-2"></a>')
					.attr('href', statisticsSearchUrl(filters))
					.attr('title', entry.speechCount + ' ' + statisticsLabel('speeches'))
					.css({ 'font-size': size(entry.score).toFixed(2) + 'rem', 'line-height': 1.4 })
					.text(entry.word)
					.appendTo(container);
			});
		})
		.catch(function () {
			container.closest('.col-lg-6').remove();
		});
}

/**
 * The entities that characterise this scope, beside the ones it names most.
 *
 * All three types go into one card here rather than behind a selector: a sitting
 * has few enough distinctive entities that showing them together reads better
 * than making the reader hunt through three tabs for the two that have any.
 */
function scopeStatisticsLoadEntities(param, value) {
	const container = $('#scopeStatisticsEntities');
	if (!container.length) return;

	const params = new URLSearchParams({ size: 8 });
	params.set(param, value);

	fetch(config.dir.root + '/api/v1/statistics/significant-entities?' + params.toString())
		.then(response => response.json())
		.then(function (result) {
			const byType = ((((result || {}).data || {}).attributes || {}).entities || {}).byType || {};

			const rows = ['person', 'organisation', 'term']
				.reduce((all, type) => all.concat(byType[type] || []), []);

			// A short sitting genuinely may name nothing that stands out against
			// the rest of the corpus. Drop the card rather than show an empty one.
			if (!rows.length) {
				container.closest('.col-lg-6').remove();
				return;
			}

			const maxScore = d3.max(rows, r => r.score) || 1;

			const list = document.createElement('div');
			list.className = 'entityList';

			rows.sort((a, b) => b.score - a.score).forEach(function (row) {
				const node = OptvEntityItem.fromStatsRow(row);
				if (!node) return;

				const filters = {};
				filters[scopeStatisticsFilterName(row.type)] = row.id + '~NER';
				// Scope parameter names are shared with media search, so the same
				// key that scoped the statistics query scopes the search.
				filters[param] = value;

				list.appendChild(OptvEntityItem.build(node, {
					href: statisticsSearchUrl(filters),
					score: row.score,
					scoreMax: maxScore,
					countLabel: row.speechCount + ' ' + statisticsLabel('speeches')
				}));
			});

			container.empty().append(list);
		})
		.catch(function () {
			container.closest('.col-lg-6').remove();
		});
}

/** The media-search filter parameter for an entity type. */
function scopeStatisticsFilterName(entityType) {
	return {
		person: 'personID',
		organisation: 'organisationID',
		document: 'documentID',
		term: 'termID'
	}[entityType] || 'personID';
}
