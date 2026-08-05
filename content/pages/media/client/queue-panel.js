/**
 * Queue panel for the media player.
 *
 * Renders the queue player.js assembled (the current search's results today, a
 * stored playlist later) as a list below the player, and keeps the "{n} of {m}"
 * position indicator in the header in sync.
 *
 * The queue arrives as an ordered id list — up to ten thousand of them — so rows
 * are created empty and their display metadata is fetched a page at a time, only
 * for rows that actually scroll into view. Relies on the globals player.js
 * exposes (resultList, currentMediaID, currentQueueIndex, updateQuery) plus
 * `config` and `localizedLabels`.
 */
(function () {
	"use strict";

	var CHUNK_SIZE = 50,   // metadata fetched per API page
		ROW_BATCH = 100;   // rows added to the DOM per batch

	var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
	function t(key, fallback) { return labels[key] || fallback || key; }

	var panel = document.getElementById("queuePanel");
	if (!panel) { return; }

	var listEl = null,
		observer = null,
		renderedCount = 0,
		requestedChunks = {},
		loadedChunks = {},
		rowsById = {};

	// ---- helpers --------------------------------------------------------------

	/** Seconds → "m:ss", matching how durations read in the result grid. */
	function formatDuration(seconds) {
		if (seconds === null || seconds === undefined || isNaN(seconds)) { return ""; }
		var total = Math.round(seconds),
			minutes = Math.floor(total / 60),
			rest = total % 60;
		return minutes + ":" + (rest < 10 ? "0" : "") + rest;
	}

	/** ISO date → the locale's short date, falling back to the raw string. */
	function formatDate(iso) {
		if (!iso) { return ""; }
		var d = new Date(iso);
		if (isNaN(d.getTime())) { return iso; }
		return d.toLocaleDateString(document.documentElement.lang || undefined, {
			year: "numeric", month: "2-digit", day: "2-digit"
		});
	}

	/** The current search params, which the queue rows must keep carrying. */
	function searchParams() {
		return (typeof removeQuoteParamsFromURL === "function")
			? removeQuoteParamsFromURL(window.location.search.toString())
			: window.location.search.toString();
	}

	// ---- rendering ------------------------------------------------------------

	function buildShell() {
		panel.innerHTML = "";
		panel.hidden = false;

		var header = document.createElement("div");
		header.className = "queuePanelHeader";

		var title = document.createElement("span");
		title.className = "queuePanelTitle";
		title.textContent = t("queuePanelTitle", "Queue");
		header.appendChild(title);

		var count = document.createElement("span");
		count.className = "queuePanelCount";
		count.textContent = String(queueTotal);
		header.appendChild(count);

		panel.appendChild(header);

		listEl = document.createElement("ol");
		listEl.className = "queueList";
		panel.appendChild(listEl);
	}

	function buildRow(item, index) {
		var li = document.createElement("li");
		li.className = "queueItem";
		li.setAttribute("data-speech-id", item.id);
		li.setAttribute("data-index", String(index));

		var link = document.createElement("a");
		link.className = "queueItemLink";
		link.href = config.dir.root + "/media/" + item.id + searchParams();
		link.addEventListener("click", function (event) {
			// Left-click navigates in place through the player's own AJAX path;
			// modified clicks keep working as ordinary links.
			if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) { return; }
			event.preventDefault();
			if (typeof updateQuery === "function") { updateQuery(item.id); }
		});

		var bar = document.createElement("span");
		bar.className = "queueItemFactionBar partyIndicator";
		link.appendChild(bar);

		var pos = document.createElement("span");
		pos.className = "queueItemPosition";
		pos.textContent = String(index + 1);
		link.appendChild(pos);

		var body = document.createElement("span");
		body.className = "queueItemBody";
		link.appendChild(body);

		li.appendChild(link);
		rowsById[item.id] = li;
		return li;
	}

	/** Fill a row once its metadata has arrived. */
	function fillRow(li, node) {
		var attributes = node.attributes || {},
			body = li.querySelector(".queueItemBody"),
			bar = li.querySelector(".queueItemFactionBar");

		if (!body) { return; }
		body.innerHTML = "";

		if (bar && attributes.factionID) {
			bar.setAttribute("data-faction", attributes.factionID);
		}

		var meta = document.createElement("span");
		meta.className = "queueItemMeta";

		var speaker = document.createElement("span");
		speaker.className = "queueItemSpeaker";
		speaker.textContent = attributes.speaker || "";
		meta.appendChild(speaker);

		if (attributes.faction) {
			var faction = document.createElement("span");
			faction.className = "partyIndicator partyIndicatorInline queueItemFaction";
			faction.setAttribute("data-faction", attributes.factionID || "");
			faction.textContent = attributes.faction;
			meta.appendChild(faction);
		}

		body.appendChild(meta);

		var title = document.createElement("span");
		title.className = "queueItemTitle";
		title.textContent = attributes.title || "";
		body.appendChild(title);

		var footer = document.createElement("span");
		footer.className = "queueItemFooter";

		var date = document.createElement("span");
		date.className = "queueItemDate";
		date.textContent = formatDate(attributes.dateStart);
		footer.appendChild(date);

		var duration = document.createElement("span");
		duration.className = "queueItemDuration";
		duration.textContent = formatDuration(attributes.duration);
		footer.appendChild(duration);

		if (attributes.findCount) {
			var hits = document.createElement("span");
			hits.className = "queueItemHits";
			hits.appendChild(document.createElement("span")).className = "icon-search";
			hits.appendChild(document.createTextNode(attributes.findCount));
			footer.appendChild(hits);
		}

		body.appendChild(footer);
		li.classList.add("loaded");
	}

	// ---- chunked metadata loading ---------------------------------------------

	/**
	 * Fetch one page of display metadata. Page N of the same query in the same
	 * sort order lines up with rows N*CHUNK_SIZE.. of the id list, so no id
	 * lookup is needed — it is literally the same search, paged.
	 */
	function loadChunk(chunk) {
		if (requestedChunks[chunk]) { return; }
		requestedChunks[chunk] = true;

		var params = window.location.search.split("?")[1],
			url = config.dir.root + "/api/v1/search/media?fields=queue"
				+ "&limit=" + CHUNK_SIZE + "&page=" + (chunk + 1)
				+ (params ? "&" + params : "");

		fetch(url, { credentials: "same-origin", headers: { "Accept": "application/json" } })
			.then(function (r) { return r.json(); })
			.then(function (response) {
				if (!response || !response.data) { return; }
				loadedChunks[chunk] = true;
				response.data.forEach(function (node) {
					var li = rowsById[node.id];
					if (li) { fillRow(li, node); }
				});
			})
			.catch(function (err) {
				// Let it be retried on the next scroll rather than leaving the
				// rows permanently blank.
				requestedChunks[chunk] = false;
				console.error("Failed to load queue metadata:", err);
			});
	}

	// ---- windowed rows ----------------------------------------------------------

	/**
	 * A search can return thousands of results, and building a row for every one
	 * of them up front is what makes the panel expensive. Rows are appended in
	 * batches instead — as the list is scrolled, or jumped ahead when playback
	 * lands somewhere further down the queue.
	 */
	function appendRows(target) {
		target = Math.min(target, resultList.length);
		if (!listEl || target <= renderedCount) { return; }

		var fragment = document.createDocumentFragment();
		for (var i = renderedCount; i < target; i++) {
			fragment.appendChild(buildRow(resultList[i], i));
		}
		listEl.appendChild(fragment);
		renderedCount = target;

		observeRows();
	}

	/** One more batch, for scrolling to the end of the rendered window. */
	function extendWindow() {
		appendRows(renderedCount + ROW_BATCH);
	}

	/**
	 * Make sure row `upto` exists, without growing the window when it already
	 * does — otherwise every navigation would add a batch whether or not it
	 * needed one.
	 */
	function ensureRendered(upto) {
		if (upto < renderedCount) { return; }
		appendRows(Math.max(upto + 1, renderedCount + ROW_BATCH));
	}

	function observeRows() {
		if (observer) { observer.disconnect(); }
		if (!("IntersectionObserver" in window)) {
			loadChunk(0);
			return;
		}

		// The list is the scroll container (max-height + overflow-y), so it —
		// not the panel — is what rows are measured against.
		observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }
				var index = parseInt(entry.target.getAttribute("data-index"), 10);
				loadChunk(Math.floor(index / CHUNK_SIZE));
				// Reaching the tail of the rendered window extends it.
				if (index >= renderedCount - 1) { extendWindow(); }
			});
		}, { root: listEl, rootMargin: "200px" });

		Array.prototype.forEach.call(listEl.children, function (li) {
			observer.observe(li);
		});
	}

	// ---- position + active row -------------------------------------------------

	function updatePosition() {
		var indicator = document.getElementById("queuePosition");
		if (!indicator) { return; }

		if (currentQueueIndex === null || currentQueueIndex === undefined || !queueTotal) {
			indicator.textContent = "";
			indicator.hidden = true;
			return;
		}

		indicator.hidden = false;
		indicator.textContent = t("queuePosition", "{current} of {total}")
			.replace("{current}", currentQueueIndex + 1)
			.replace("{total}", queueTotal);
	}

	function updateActiveRow() {
		if (!listEl) { return; }

		// Autoplay can walk past the end of the rendered window.
		if (currentQueueIndex !== null && currentQueueIndex !== undefined) {
			ensureRendered(currentQueueIndex);
		}

		var previous = listEl.querySelector(".queueItem.active");
		if (previous) {
			previous.classList.remove("active");
			previous.removeAttribute("aria-current");
		}

		var active = rowsById[currentMediaID];
		if (!active) { return; }

		active.classList.add("active");
		active.setAttribute("aria-current", "true");

		if (!panel.classList.contains("collapsed")) {
			active.scrollIntoView({ block: "nearest" });
		}
	}

	/** Reflect the panel's open/closed state on the header toggle. */
	function syncToggle() {
		var toggle = document.getElementById("toggleQueuePanel");
		if (!toggle) { return; }

		var collapsed = panel.classList.contains("collapsed");
		toggle.hidden = panel.hidden;
		toggle.classList.toggle("active", !collapsed);
		toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
		toggle.title = collapsed ? t("queueShow", "Show queue") : t("queueHide", "Hide queue");
	}

	function render() {
		if (!resultList || !resultList.length) {
			panel.hidden = true;
			syncToggle();
			return;
		}

		requestedChunks = {};
		loadedChunks = {};
		rowsById = {};
		renderedCount = 0;

		buildShell();

		// Enough rows to cover the first screenful and to reach whatever is
		// currently playing, however far down the queue that is.
		ensureRendered(currentQueueIndex || 0);

		// Open by default where there is room for it; on phones the player
		// already fills the screen, so the queue starts out of the way.
		if (window.innerWidth >= 768) {
			panel.classList.remove("collapsed");
		}

		syncToggle();
		updateActiveRow();
		updatePosition();
	}

	// ---- wiring ----------------------------------------------------------------

	document.addEventListener("optvQueueReady", render);

	// Fires on every result switch: the rows are still valid, only the highlight
	// and the position move.
	document.addEventListener("optvQueueItemChanged", function () {
		updateActiveRow();
		updatePosition();
	});

	document.addEventListener("click", function (event) {
		var toggle = event.target.closest ? event.target.closest("#toggleQueuePanel") : null;
		if (!toggle || panel.hidden) { return; }
		event.preventDefault();

		var collapsed = panel.classList.toggle("collapsed");
		syncToggle();

		if (!collapsed) {
			// The list was display:none while collapsed, so nothing intersected
			// and no metadata was ever requested for it.
			observeRows();
			updateActiveRow();
		}
	});

}());
