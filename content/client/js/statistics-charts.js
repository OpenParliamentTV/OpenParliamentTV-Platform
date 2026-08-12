/**
 * Shared chart building blocks for statistics visualizations.
 *
 * Used by the statistics page and by the statistics modules on entity and
 * structure pages. The charts differ in what they plot and how they colour it,
 * not in how they are built: each measures a container, makes an SVG with the
 * same viewBox arrangement, scales time against a linear count, and styles its
 * axes identically. Those steps live here once, so a new chart is a data mapping
 * and a colour choice rather than another ninety lines of D3.
 *
 * Depends on d3 (loaded globally in content/head.php) and, for the donut helper,
 * renderDonutChart() from content/client/js/generic.js.
 */
/**
 * A localized string, by key.
 *
 * content/base.php inlines the whole active-language file as `localizedLabels`,
 * so there is nothing to fetch. Falls back to the key itself, which makes a
 * missing translation visible rather than rendering an empty element.
 */
function statisticsLabel(key) {
	return (typeof localizedLabels !== 'undefined' && localizedLabels[key]) || key;
}

/**
 * A search URL for the given filters.
 *
 * Every figure these charts show describes speeches that exist and can be
 * watched; linking back to them is the point of collecting the figures at all.
 */
function statisticsSearchUrl(filters) {
	const p = new URLSearchParams();
	Object.entries(filters).forEach(([key, value]) => {
		if (value === null || value === undefined || value === '') return;
		if (Array.isArray(value)) {
			value.forEach(v => p.append(key + '[]', v));
		} else {
			p.set(key, value);
		}
	});
	return '/search?' + p.toString();
}
// ---------------------------------------------------------------------------
// Chart primitives
// ---------------------------------------------------------------------------
//
// The line charts on this page differ in what they plot and how they colour it,
// not in how they are built: each measures a container, makes an SVG with the
// same viewBox arrangement, scales time against a linear count, and styles its
// axes identically. Those steps live here once so a new chart is a data mapping
// and a colour choice rather than another ninety lines of D3 boilerplate.

/** Timeline buckets arrive as "2024-03"; date inputs as "2024-03-15". */
const statisticsParseMonth = d3.timeParse("%Y-%m");
const statisticsParseDay = d3.timeParse("%Y-%m-%d");

/** Palette for word series. Faction series use the platform's faction colours. */
const STATISTICS_WORD_COLORS = [
	"#597081", "#339966", "#16a09c", "#0073a6", "#8b5180",
	"#999933", "#CC3399", "#ae764d", "#cf910d", "#b85e02"
];

/**
 * Prepare an SVG canvas inside a container.
 *
 * Containers routinely measure 0 during a redraw or while a tab is hidden, which
 * is why the fallbacks exist: without them a chart drawn on an inactive tab gets
 * a negative width and silently renders nothing.
 *
 * @return {{svg, width, height}|null} null when the container is not present.
 */
function statisticsChartCanvas(containerId, margin) {
	const container = document.getElementById(containerId);
	if (!container) return null;

	d3.select('#' + containerId).selectAll('*').remove();

	let containerWidth = container.offsetWidth;
	let containerHeight = container.offsetHeight;
	if (containerWidth < 200) containerWidth = (container.parentElement && container.parentElement.offsetWidth) || 800;
	if (containerHeight < 200) containerHeight = 400;

	const width = containerWidth - margin.left - margin.right;
	const height = containerHeight - margin.top - margin.bottom;

	const svg = d3.select('#' + containerId)
		.append("svg")
		.attr("width", "100%")
		.attr("height", height + margin.top + margin.bottom)
		.attr("viewBox", `0 0 ${width + margin.left + margin.right} ${height + margin.top + margin.bottom}`)
		.append("g")
		.attr("transform", `translate(${margin.left},${margin.top})`);

	return { svg: svg, width: width, height: height };
}

/**
 * Time-vs-count scales over an explicit date range.
 *
 * The x domain comes from the requested range rather than from the data, so an
 * empty stretch reads as an empty stretch instead of the chart quietly zooming
 * to whatever months happen to have values.
 */
function statisticsChartScales(data, startDate, endDate, width, height) {
	return {
		xScale: d3.scaleTime().domain([startDate, endDate]).range([0, width]),
		yScale: d3.scaleLinear().domain([0, d3.max(data, d => d.count) || 1]).nice().range([height, 0])
	};
}

/** Tick spacing and label format appropriate to the span being shown. */
function statisticsChartTicks(startDate, endDate) {
	const days = (endDate - startDate) / (1000 * 60 * 60 * 24);
	const months = days / 30;
	const years = days / 365;

	if (days <= 90) {
		return { format: d3.timeFormat("%d.%m"), interval: d3.timeWeek.every(1) };
	}
	if (months <= 24) {
		return { format: d3.timeFormat("%m/%Y"), interval: d3.timeMonth.every(Math.ceil(months / 12)) };
	}
	if (years <= 10) {
		return { format: d3.timeFormat("%Y"), interval: d3.timeYear.every(Math.max(1, Math.ceil(years / 8))) };
	}
	return { format: d3.timeFormat("%Y"), interval: d3.timeYear.every(Math.max(2, Math.ceil(years / 6))) };
}

/** Draw both axes with the page's shared styling. */
function statisticsChartAxes(svg, xScale, yScale, height, ticks) {
	const xAxis = d3.axisBottom(xScale);
	if (ticks) xAxis.ticks(ticks.interval).tickFormat(ticks.format);

	svg.append("g")
		.attr("transform", `translate(0,${height})`)
		.call(xAxis)
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");

	svg.append("g")
		.call(d3.axisLeft(yScale))
		.selectAll("text")
		.style("font-size", "12px")
		.style("font-family", "inherit");
}

/** Line generator over {date, count}. */
function statisticsChartLine(xScale, yScale) {
	return d3.line().x(d => xScale(d.date)).y(d => yScale(d.count)).curve(d3.curveMonotoneX);
}

/** Area generator over {date, count}, for the fill under a line. */
function statisticsChartArea(xScale, yScale, height) {
	return d3.area().x(d => xScale(d.date)).y0(height).y1(d => yScale(d.count)).curve(d3.curveMonotoneX);
}

/**
 * Draw one series, plus the invisible hit targets that make its points
 * clickable. Every figure here describes speeches that can be watched, so a
 * point that does not lead back to them is a dead end.
 */
function statisticsChartSeries(svg, values, options) {
	values.sort((a, b) => a.date - b.date);

	if (options.area) {
		svg.append("path")
			.datum(values)
			.attr("fill", options.color)
			.attr("fill-opacity", 0.1)
			.attr("stroke", "none")
			.attr("d", options.area);
	}

	svg.append("path")
		.datum(values)
		.attr("fill", "none")
		.attr("stroke", options.color)
		.attr("stroke-width", 2)
		.attr("d", options.line);

	if (!options.onPointClick) return;

	svg.selectAll(null)
		.data(values.filter(d => d.count > 0))
		.enter()
		.append("circle")
		.attr("cx", d => options.xScale(d.date))
		.attr("cy", d => options.yScale(d.count))
		.attr("r", 6)
		.attr("fill", options.color)
		.attr("fill-opacity", 0)
		.style("cursor", "pointer")
		.on("mouseover", function () { d3.select(this).attr("fill-opacity", 0.6); })
		.on("mouseout", function () { d3.select(this).attr("fill-opacity", 0); })
		.append("title")
		.text(d => options.pointTitle ? options.pointTitle(d) : '');

	svg.selectAll("circle")
		.on("click", (event, d) => options.onPointClick(d));
}

/**
 * Stable colour per series key.
 *
 * Assignments persist across redraws in `store`, so removing one word does not
 * recolour the others — a chart whose colours move on every edit is unreadable.
 */
function statisticsSeriesColor(key, store, palette) {
	if (!store.has(key)) {
		const used = Array.from(store.values());
		const free = palette.filter(c => used.indexOf(c) === -1);
		store.set(key, free.length ? free[0] : palette[used.length % palette.length]);
	}
	return store.get(key);
}

/**
 * Render one of the overview donuts.
 *
 * Replaces three near-identical initializers that differed only in which
 * container they targeted and which field they summed.
 */
function statisticsFactionDonut(containerId, factions, valueField) {
	if (!document.getElementById(containerId) || !factions) return null;

	const data = factions.map(faction => ({
		id: faction.id,
		label: faction.label,
		value: faction[valueField]
	}));
	if (!data.length) return null;

	return renderDonutChart({
		container: '#' + containerId,
		data: data,
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


