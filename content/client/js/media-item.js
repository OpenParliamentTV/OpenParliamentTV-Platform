/**
 * One speech, rendered as the platform's `.resultItem` card — client side.
 *
 * The mirror of content/components/media-item.php: same markup, same class
 * names, same date and duration formatting, so a card built here is
 * indistinguishable from one the server rendered. Change the two together.
 *
 * Used by the player's queue panel and the notifications list, which both
 * assemble their cards after the page has loaded. Input is the flat queue node
 * mediaQueueNode() produces (modules/entities/media.php) — a node without
 * `attributes` renders as a placeholder shell, which is what lets the queue put
 * rows on screen before their metadata has been paged in.
 */
(function (window, document) {
	"use strict";

	var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};

	function t(key, fallback) { return labels[key] || fallback || key; }

	function pad2(n) { return (n < 10 ? "0" : "") + n; }

	/** Mirrors the partial's gmdate("i:s") / gmdate("H:i:s"). */
	function formatDuration(seconds) {
		var total = Math.floor(Number(seconds) || 0),
			h = Math.floor(total / 3600),
			m = Math.floor((total % 3600) / 60),
			s = total % 60;
		return (total >= 3600 ? pad2(h) + ":" : "") + pad2(m) + ":" + pad2(s);
	}

	/** Mirrors the partial's date("d.m.Y"). */
	function formatDate(iso) {
		if (!iso) { return ""; }
		var d = new Date(iso);
		if (isNaN(d.getTime())) { return iso; }
		return pad2(d.getDate()) + "." + pad2(d.getMonth() + 1) + "." + d.getFullYear();
	}

	function el(tag, className) {
		var node = document.createElement(tag);
		if (className) { node.className = className; }
		return node;
	}

	/**
	 * The card's inner `.resultContent`. Split out from build() so a row that
	 * arrived without metadata can be refilled later without being replaced —
	 * the queue keys its lookup map and its IntersectionObserver on the
	 * <article> element itself.
	 */
	function buildContent(node, options) {
		var attributes = node.attributes || {},
			hasMetadata = !!node.attributes,
			faction = attributes.factionID || "",
			segments = (node.segments && node.segments.length) ? node.segments : null,
			snippets = (options.snippets && options.snippets.length) ? options.snippets : null,
			duration = Number(attributes.duration) || 0,
			hitCount = parseInt(attributes.findCount, 10) || 0;

		var content = el("div", "resultContent partyIndicator");
		content.setAttribute("data-faction", faction);

		var link = el("a", "resultLink");
		link.href = options.href || "#";
		if (typeof options.onLinkClick === "function") {
			link.addEventListener("click", options.onLinkClick);
		}
		link.appendChild(el("div", "icon-play-1"));

		if (options.position !== null && options.position !== undefined) {
			var position = el("div", "resultPosition");
			position.textContent = String(options.position);
			link.appendChild(position);
		}

		content.appendChild(link);

		// Nothing but the shell until the metadata arrives — a zeroed duration
		// and a 1970 date would be worse than blank.
		if (!hasMetadata) { return content; }

		var durationEl = el("div", "resultDuration");
		durationEl.textContent = formatDuration(duration);
		link.appendChild(durationEl);

		var date = el("div", "resultDate");
		date.textContent = formatDate(attributes.dateStart);
		// The hit count stands in for the passages on cards that carry the
		// segments but not the text to quote them with.
		if (hitCount > 0 && !snippets) {
			var hits = el("span", "resultHitCount");
			hits.title = t("playlistSegment", "Selected segment");
			hits.appendChild(el("span", "icon-search"));
			hits.appendChild(document.createTextNode(String(hitCount)));
			date.appendChild(hits);
		}
		link.appendChild(date);

		var meta = el("div", "resultMeta");
		if (options.speakerHtml) {
			meta.innerHTML = options.speakerHtml;
		} else {
			meta.textContent = attributes.speaker || "";
		}
		if (attributes.faction) {
			var factionLabel = el("span", "partyIndicator partyIndicatorInline");
			factionLabel.setAttribute("data-faction", faction);
			factionLabel.textContent = attributes.faction;
			meta.appendChild(document.createTextNode(" "));
			meta.appendChild(factionLabel);
		}
		link.appendChild(meta);

		link.appendChild(el("hr"));

		if (options.showTitle !== false) {
			var title = el("div", "resultTitle");
			title.textContent = attributes.title || "";
			link.appendChild(title);
		}

		if (snippets) {
			var snippetList = el("div", "resultSnippets");
			var base = options.snippetHrefBase || options.href || "";
			snippets.forEach(function (snippet) {
				var snippetLink = el("a", "resultSnippet");
				snippetLink.href = base + "&t=" + encodeURIComponent(snippet["data-start"] || "");
				snippetLink.title = t("playlistSegment", "Selected segment");
				// context is the search highlight HTML OpenSearch produced (safe
				// <em> markup), as everywhere else search results are quoted.
				snippetLink.innerHTML = snippet.context || "";
				snippetList.appendChild(snippetLink);
			});
			content.appendChild(snippetList);
		}

		if (options.actionsHtml) {
			var actions = el("div", "resultActions");
			actions.innerHTML = options.actionsHtml;
			content.appendChild(actions);
		}

		if (segments && duration > 0) {
			var timeline = el("div", "hitTimeline");
			segments.forEach(function (segment) {
				// Clamp into the [0,100]% track: alignment timecodes can exceed
				// the media duration, which would otherwise push the .hit outside
				// .hitTimeline/.resultItem and break the layout.
				var start = Number(segment.start) || 0,
					end = Number(segment.end) || 0,
					left = Math.max(0, Math.min(100, 100 * (start / duration))),
					width = Math.max(0, Math.min(100 - left, 100 * ((end - start) / duration))),
					hit = el("div", "hit");
				hit.style.left = left + "%";
				hit.style.width = width + "%";
				timeline.appendChild(hit);
			});
			content.appendChild(timeline);
		}

		return content;
	}

	/** The modifier classes the card's state is expressed through. */
	function applyState(article, node, options) {
		var hasMetadata = !!node.attributes;
		article.classList.toggle("hits", !!(node.segments && node.segments.length));
		article.classList.toggle("numbered", options.position !== null && options.position !== undefined);
		article.classList.toggle("placeholder", !hasMetadata);
	}

	/** The entity an annotation of this context points at, out of `pool`. */
	function annotated(annotations, pool, context) {
		var match = null;
		(annotations || []).forEach(function (annotation) {
			if (match || !annotation.attributes || annotation.attributes.context !== context) { return; }
			(pool || []).forEach(function (candidate) {
				if (!match && candidate.id === annotation.id) { match = candidate; }
			});
		});
		return match;
	}

	window.OptvMediaItem = {

		/**
		 * A full search hit → the flat node build() wants. The mirror of
		 * mediaQueueNode() (modules/entities/media.php), for the surfaces that
		 * hold a search result rather than a queue projection — they need the
		 * `_finds` a `fields=queue` response drops.
		 *
		 * speakerRole is deliberately absent: the card never shows it, and
		 * translating it is the server's job.
		 */
		fromSearchHit: function (hit) {
			var attributes = hit.attributes || {},
				relationships = hit.relationships || {},
				annotations = (hit.annotations || {}).data || [],
				speaker = annotated(annotations, (relationships.people || {}).data, "main-speaker"),
				faction = annotated(annotations, (relationships.organisations || {}).data, "main-speaker-faction"),
				agendaItem = (relationships.agendaItem || {}).data,
				segments = [];

			(hit._finds || []).forEach(function (find) {
				if (find["data-start"] === null || find["data-start"] === undefined) { return; }
				// The 0.3s off the end is what the player has always trimmed, so
				// a segment stored from a search hit plays back like the hit did.
				segments.push({ start: parseFloat(find["data-start"]), end: parseFloat(find["data-end"]) - 0.3 });
			});
			segments.sort(function (a, b) { return a.start - b.start; });

			return {
				type: "media",
				id: hit.id || null,
				attributes: {
					parliament: attributes.parliament || null,
					dateStart: attributes.dateStart || null,
					duration: attributes.duration === undefined ? null : parseFloat(attributes.duration),
					title: (agendaItem && agendaItem.attributes) ? (agendaItem.attributes.title || null) : null,
					speaker: (speaker && speaker.attributes) ? speaker.attributes.label : null,
					speakerID: speaker ? speaker.id : null,
					faction: (faction && faction.attributes) ? faction.attributes.label : null,
					factionID: faction ? faction.id : null,
					findCount: segments.length
				},
				segments: segments.length ? segments : null
			};
		},

		/**
		 * @param {object} node    A mediaQueueNode; without `attributes` it
		 *                         renders as a placeholder to be filled later.
		 * @param {object} options href, position, speakerHtml, showTitle,
		 *                         snippets, snippetHrefBase, actionsHtml,
		 *                         classes, dataAttrs, onLinkClick.
		 * @return {HTMLElement} The <article class="resultItem">
		 */
		build: function (node, options) {
			options = options || {};

			var article = el("article", ("resultItem " + (options.classes === undefined ? "col" : options.classes)).trim());
			article.setAttribute("data-speech-id", node.id || "");
			article.setAttribute("data-faction", (node.attributes && node.attributes.factionID) || "");
			Object.keys(options.dataAttrs || {}).forEach(function (name) {
				article.setAttribute("data-" + name, options.dataAttrs[name]);
			});

			applyState(article, node, options);
			article.appendChild(buildContent(node, options));

			return article;
		},

		/**
		 * Refill a card that was built without metadata, keeping the <article>
		 * element itself — callers hold references to it.
		 */
		fill: function (article, node, options) {
			options = options || {};

			var previous = article.querySelector(".resultContent"),
				content = buildContent(node, options);

			article.setAttribute("data-faction", (node.attributes && node.attributes.factionID) || "");
			applyState(article, node, options);

			if (previous) {
				article.replaceChild(content, previous);
			} else {
				article.appendChild(content);
			}
		}
	};

}(window, document));
