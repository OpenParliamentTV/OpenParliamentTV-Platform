/**
 * One entity, rendered as the platform's `.entityItem` card.
 *
 * The client-side mirror of content/components/entity-item.php. The two files
 * produce the same DOM and must be changed together — a surface that renders
 * entities server-side and one that renders them from an API response are the
 * same card, and the CSS is written once.
 *
 * Input is the flat preview node modules/entities/preview.php produces.
 * `fromStatsRow()` is the adapter for the statistics endpoints, which return the
 * same fields under the same names plus whichever measure the panel is showing.
 *
 * Everything is built with textContent and setAttribute rather than string
 * concatenation: entity labels are user-editable platform data, and the one
 * renderer this replaces built its markup by concatenation.
 */

(function (window, document) {
	'use strict';

	/** Root path prefix for entity page links. */
	function rootPath() {
		return (window.config && window.config.dir && window.config.dir.root) || '';
	}

	/**
	 * A preview node from a statistics API row.
	 *
	 * Mirrors entityBuildPreviewNode()'s output, built from what
	 * statisticsEnrichEntityRows() puts on the wire.
	 */
	function fromStatsRow(row) {
		if (!row || !row.type || !row.id) return null;

		return {
			type: row.type,
			id: row.id,
			subtype: row.subtype || null,
			label: row.label || String(row.id),
			// The API sends no secondary label of its own; a person's faction is
			// what the card shows there, exactly as the PHP builder decides.
			secondaryLabel: (row.type === 'person' && row.faction) ? row.faction.label : '',
			thumbnailURI: row.thumbnailURI || null,
			faction: row.faction || null,
			href: rootPath() + '/' + row.type + '/' + encodeURIComponent(row.id)
		};
	}

	/**
	 * Build the card element.
	 *
	 * @param {object} node    A preview node.
	 * @param {object} options Same keys as the partial's $entityItemOptions,
	 *                         plus `onLinkClick` which has no server equivalent.
	 * @returns {HTMLElement}
	 */
	function build(node, options) {
		const wrapper = document.createElement('div');
		fill(wrapper, node, options);
		return wrapper;
	}

	/**
	 * Fill (or refill) a wrapper element with the card.
	 *
	 * Separate from build() because callers keep references to the element —
	 * a list that re-sorts in place must not replace the nodes it tracks.
	 */
	function fill(wrapper, node, options) {
		node = node || {};
		options = options || {};

		const type = node.type || '';
		const factionID = (node.faction && node.faction.id) || '';

		wrapper.className = 'entityItem ' + (options.classes !== undefined ? options.classes : 'col');
		wrapper.setAttribute('data-type', type);
		wrapper.setAttribute('data-entity-id', node.id || '');
		Object.entries(options.dataAttrs || {}).forEach(function (entry) {
			wrapper.setAttribute('data-' + entry[0], entry[1]);
		});

		wrapper.textContent = '';

		const container = document.createElement('div');
		container.className = 'entityContainer' + (factionID ? ' partyIndicator' : '');
		container.setAttribute('data-faction', factionID);

		const link = document.createElement('a');
		link.setAttribute('href', options.href || node.href || '');
		if (typeof options.onLinkClick === 'function') {
			link.addEventListener('click', options.onLinkClick);
		}

		if (options.showThumbnail !== false) {
			const thumbnail = document.createElement('div');
			thumbnail.className = 'thumbnailContainer';
			const circle = document.createElement('div');
			circle.className = 'rounded-circle';
			if (node.thumbnailURI) {
				const img = document.createElement('img');
				img.setAttribute('src', node.thumbnailURI);
				img.setAttribute('alt', '');
				circle.appendChild(img);
			} else {
				const icon = document.createElement('span');
				icon.className = 'icon-type-' + type;
				circle.appendChild(icon);
			}
			thumbnail.appendChild(circle);
			link.appendChild(thumbnail);
		}

		const body = document.createElement('div');
		body.className = 'entityBody';

		if (options.typeLabel) {
			const typeLine = document.createElement('div');
			typeLine.className = 'entityType';
			typeLine.textContent = options.typeLabel;
			body.appendChild(typeLine);
		}

		const title = document.createElement('div');
		title.className = 'entityTitle';
		if (options.labelHtml) {
			// Markup the caller already escaped — the search suggestions pass an
			// <em>-highlighted label through here, as the partial does.
			title.innerHTML = options.labelHtml;
		} else {
			title.textContent = node.label || node.id || '';
		}

		// A person's faction is shown as the same inline indicator the speech card
		// and the filter chips use, rather than as plain text: it is the
		// platform's standard way of naming a faction and it carries the colour.
		// It trails the name and wraps with it. Organisations that ARE a faction
		// skip it — the card is already tinted.
		const factionLabel = (node.faction && node.faction.label) || '';
		const showFactionBadge = (type === 'person' && factionID && factionLabel);
		if (showFactionBadge) {
			const badge = document.createElement('span');
			badge.className = 'partyIndicator partyIndicatorInline';
			badge.setAttribute('data-faction', factionID);
			badge.textContent = factionLabel;
			title.appendChild(document.createTextNode(' '));
			title.appendChild(badge);
		}
		body.appendChild(title);

		// A person's secondary label IS their faction, so once the badge carries
		// it there is nothing left for its own line. Mirrors the partial.
		let secondaryLabel = node.secondaryLabel || '';
		if (showFactionBadge && secondaryLabel === factionLabel) secondaryLabel = '';
		if (secondaryLabel) {
			const secondary = document.createElement('div');
			secondary.className = 'entitySecondary break-lines truncate-lines';
			secondary.textContent = secondaryLabel;
			body.appendChild(secondary);
		}

		if (options.contextLabel) {
			const context = document.createElement('div');
			context.className = 'entityContext';
			const icon = document.createElement('span');
			icon.className = 'icon-megaphone';
			context.appendChild(icon);
			context.appendChild(document.createTextNode(options.contextLabel));
			body.appendChild(context);
		}

		if (options.count > 0) {
			const count = document.createElement('div');
			count.className = 'entityCount';
			if (options.countLabel) count.setAttribute('title', options.countLabel);
			const badge = document.createElement('span');
			badge.className = 'badge rounded-pill';
			// Mirrors the partial's number_format().
			badge.textContent = Number(options.count).toLocaleString();
			count.appendChild(badge);
			body.appendChild(count);
		}

		if (options.score !== undefined && options.score !== null) {
			const max = (options.scoreMax !== undefined && options.scoreMax !== null)
				? options.scoreMax : options.score;
			if (max > 0) {
				const bar = document.createElement('div');
				bar.className = 'entityBar';
				const fillSpan = document.createElement('span');
				const percent = Math.max(0, Math.min(100, 100 * (options.score / max)));
				fillSpan.style.width = percent.toFixed(2) + '%';
				bar.appendChild(fillSpan);
				body.appendChild(bar);
			}
		}

		link.appendChild(body);
		container.appendChild(link);

		if (options.buttonHtml) {
			const holder = document.createElement('div');
			holder.innerHTML = options.buttonHtml;
			while (holder.firstChild) container.appendChild(holder.firstChild);
		}

		wrapper.appendChild(container);
	}

	window.OptvEntityItem = {
		fromStatsRow: fromStatsRow,
		build: build,
		fill: fill
	};
})(window, document);
