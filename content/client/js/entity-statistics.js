/**
 * Statistics for a single entity: when it was talked about, and what it was
 * talked about alongside.
 *
 * Shared by the statistics page's entity section and by the statistics module on
 * person, organisation, document and term pages, so the same entity reads the
 * same way wherever you meet it.
 *
 * Depends on content/client/js/statistics-charts.js for the chart primitives.
 */

/** Node ceiling for the co-occurrence graph; beyond this it stops being legible. */
const ENTITY_NETWORK_MAX_NODES = 25;

/** Below this viewport width the graph is replaced by the plain list. */
const ENTITY_NETWORK_MIN_WIDTH = 576;

/**
 * Fetch one entity's statistics.
 *
 * @return {Promise<object|null>} The `attributes` object, or null.
 */
async function entityStatisticsFetch(entityType, entityID, options = {}) {
	const params = new URLSearchParams({ entityType: entityType, entityID: entityID });
	if (options.parliament) params.set('parliament', options.parliament);
	if (options.dateFrom) params.set('dateFrom', options.dateFrom);
	if (options.dateTo) params.set('dateTo', options.dateTo);

	try {
		const response = await fetch('/api/v1/statistics/entity?' + params.toString());
		const data = await response.json();
		return (data && data.data && data.data.attributes) ? data.data.attributes : null;
	} catch (error) {
		return null;
	}
}

/**
 * Mentions over time.
 *
 * Documents get two lines rather than one: being formally on the agenda and
 * being named mid-speech are different events, and averaging them into a single
 * "references" figure would hide which is which.
 *
 * Each point links to the speeches behind it.
 */
function entityStatisticsRenderMentions(containerId, mentions, context) {
	const container = document.getElementById(containerId);
	if (!container) return;

	const series = Object.keys(mentions || {}).filter(k => (mentions[k] || []).length);
	if (!series.length) {
		container.innerHTML = '<div class="text-muted small py-2">'
			+ statisticsLabel('statisticsNoData') + '</div>';
		return;
	}

	const seriesLabels = {
		mentioned: statisticsLabel('statisticsMentionedInDebate'),
		linked: statisticsLabel('statisticsOnTheAgenda')
	};
	const seriesColors = { mentioned: STATISTICS_WORD_COLORS[0], linked: STATISTICS_WORD_COLORS[2] };

	const allPoints = [];
	series.forEach(function (key) {
		mentions[key].forEach(function (bucket) {
			allPoints.push({
				series: key,
				date: statisticsParseMonth(bucket.date),
				count: bucket.speechCount
			});
		});
	});

	const dates = allPoints.map(p => p.date).filter(Boolean);
	if (!dates.length) return;

	const margin = { top: 5, right: 5, bottom: 25, left: 35 };
	const canvas = statisticsChartCanvas(containerId, margin);
	if (!canvas) return;

	const start = d3.min(dates);
	const end = d3.max(dates);
	const { svg, width, height } = canvas;
	const { xScale, yScale } = statisticsChartScales(allPoints, start, end, width, height);
	statisticsChartAxes(svg, xScale, yScale, height, statisticsChartTicks(start, end));

	const line = statisticsChartLine(xScale, yScale);
	const area = statisticsChartArea(xScale, yScale, height);

	series.forEach(function (key) {
		const values = allPoints.filter(p => p.series === key);
		statisticsChartSeries(svg, values, {
			color: seriesColors[key] || STATISTICS_WORD_COLORS[0],
			line: line,
			area: series.length === 1 ? area : null,
			xScale: xScale,
			yScale: yScale,
			pointTitle: d => (seriesLabels[key] || key) + ' — ' + d.count + ' '
				+ statisticsLabel('speeches') + ' (' + d3.timeFormat('%m/%Y')(d.date) + ')\n'
				+ statisticsLabel('statisticsShowSpeeches'),
			onPointClick: function (d) {
				const filters = {
					dateFrom: d3.timeFormat('%Y-%m-01')(d.date),
					dateTo: d3.timeFormat('%Y-%m-%d')(d3.timeMonth.offset(d.date, 1) - 1)
				};
				// The filter name search uses for this entity type, with the
				// context that matches the series being clicked.
				filters[context.filterName] = context.entityID
					+ (key === 'linked' ? '~proceedingsReference' : '~NER');
				window.open(statisticsSearchUrl(filters), '_blank');
			}
		});
	});

	// A legend is only worth the space when there is more than one line.
	if (series.length > 1) {
		const legend = d3.select('#' + containerId + ' svg g').append('g')
			.attr('transform', `translate(0, ${-margin.top + 2})`);
		let x = 0;
		series.forEach(function (key) {
			const item = legend.append('g').attr('transform', `translate(${x}, 0)`);
			item.append('line').attr('x1', 0).attr('x2', 12).attr('y1', 0).attr('y2', 0)
				.attr('stroke', seriesColors[key]).attr('stroke-width', 2);
			item.append('text').attr('x', 16).attr('dy', '0.35em')
				.style('font-size', '11px').style('font-family', 'inherit')
				.text(seriesLabels[key] || key);
			x += (seriesLabels[key] || key).length * 6.5 + 30;
		});
	}
}

/**
 * Entities most often mentioned in the same speeches, as a force-directed graph.
 *
 * The graph answers a question a table cannot: which of these belong together.
 * On narrow viewports it degrades to the table, because a 25-node graph on a
 * phone is decoration.
 */
function entityStatisticsRenderNetwork(containerId, entity, associations) {
	const container = document.getElementById(containerId);
	if (!container) return;

	const related = (associations || []).slice(0, ENTITY_NETWORK_MAX_NODES);
	if (!related.length) {
		container.innerHTML = '<div class="text-muted small py-2">'
			+ statisticsLabel('statisticsNoData') + '</div>';
		return;
	}

	if (container.offsetWidth && container.offsetWidth < ENTITY_NETWORK_MIN_WIDTH) {
		entityStatisticsRenderList(containerId, related);
		return;
	}

	const canvas = statisticsChartCanvas(containerId, { top: 5, right: 5, bottom: 5, left: 5 });
	if (!canvas) return;
	const { svg, width, height } = canvas;

	const nodes = [{ id: entity.id, label: entity.label, type: entity.type, center: true }]
		.concat(related.map(r => ({ id: r.id, label: r.label, type: r.type, count: r.coOccurrenceCount })));
	const links = related.map(r => ({ source: entity.id, target: r.id, value: r.coOccurrenceCount }));

	const maxCount = d3.max(related, r => r.coOccurrenceCount) || 1;
	const strokeWidth = d3.scaleLinear().domain([0, maxCount]).range([0.5, 4]);
	const radius = d3.scaleSqrt().domain([0, maxCount]).range([3, 12]);

	// Entity-type colours, matching the type indicators used elsewhere.
	const typeColor = {
		person: STATISTICS_WORD_COLORS[3],
		organisation: STATISTICS_WORD_COLORS[1],
		term: STATISTICS_WORD_COLORS[4],
		document: STATISTICS_WORD_COLORS[8]
	};

	const simulation = d3.forceSimulation(nodes)
		.force('link', d3.forceLink(links).id(d => d.id).distance(80))
		.force('charge', d3.forceManyBody().strength(-180))
		.force('center', d3.forceCenter(width / 2, height / 2))
		.force('collide', d3.forceCollide().radius(d => (d.center ? 18 : radius(d.count || 0) + 10)));

	const link = svg.append('g').selectAll('line').data(links).enter().append('line')
		.attr('stroke', '#cccccc')
		.attr('stroke-width', d => strokeWidth(d.value));

	const node = svg.append('g').selectAll('g').data(nodes).enter().append('g')
		.style('cursor', d => d.center ? 'default' : 'pointer')
		.on('click', function (event, d) {
			if (!d.center) window.open('/' + d.type + '/' + d.id, '_blank');
		});

	node.append('circle')
		.attr('r', d => d.center ? 10 : radius(d.count || 0))
		.attr('fill', d => d.center ? '#535263' : (typeColor[d.type] || '#999999'));

	node.append('title').text(d => d.label + (d.count ? ' — ' + d.count + ' ' + statisticsLabel('speeches') : ''));

	node.append('text')
		.text(d => d.label)
		.attr('x', d => (d.center ? 14 : radius(d.count || 0) + 4))
		.attr('dy', '0.35em')
		.style('font-size', d => d.center ? '13px' : '11px')
		.style('font-weight', d => d.center ? 'bold' : 'normal')
		.style('font-family', 'inherit')
		.style('pointer-events', 'none');

	simulation.on('tick', function () {
		link.attr('x1', d => d.source.x).attr('y1', d => d.source.y)
			.attr('x2', d => d.target.x).attr('y2', d => d.target.y);
		node.attr('transform', d => `translate(${d.x},${d.y})`);
	});

	// Force layouts settle over time; stopping it keeps a background tab from
	// running the simulation indefinitely.
	setTimeout(() => simulation.stop(), 4000);
}

/** Plain list fallback for the co-occurrence data. */
function entityStatisticsRenderList(containerId, related) {
	const rows = related.map(function (r) {
		const link = '<a href="/' + r.type + '/' + r.id + '">' + $('<div>').text(r.label).html() + '</a>';
		return '<li class="mb-1">' + link + ' <span class="text-muted small">' + r.coOccurrenceCount + '</span></li>';
	}).join('');
	document.getElementById(containerId).innerHTML = '<ul class="list-unstyled mb-0">' + rows + '</ul>';
}

/** The media-search filter parameter for an entity type. */
function entityStatisticsFilterName(entityType) {
	return {
		person: 'personID',
		organisation: 'organisationID',
		document: 'documentID',
		term: 'termID'
	}[entityType] || 'personID';
}
