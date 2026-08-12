/**
 * Loads the distinctive words for a scoped statistics dashboard.
 *
 * Everything else in the dashboard is rendered server-side from cached figures;
 * this one block is fetched separately because significant_text re-analyses text
 * at query time and is the only part worth not blocking first paint on.
 *
 * Words are sized by how distinctive they are, and each links to the speeches in
 * this scope that contain it.
 */

$(document).ready(function () {
	const module = document.getElementById('scopeStatisticsModule');
	if (!module) return;

	const container = $('#scopeStatisticsWords');
	if (!container.length) return;

	const param = module.getAttribute('data-scope-param');
	const value = module.getAttribute('data-scope-value');
	if (!param || !value) return;

	const params = new URLSearchParams({ size: 20 });
	params.set(param, value);

	fetch('/api/v1/statistics/significant-words?' + params.toString())
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
});
