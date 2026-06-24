/**
 * CriteriaChips — shared visualizer for search/filter criteria.
 *
 * Renders any saved search criteria (the same param shape used by the search API,
 * RSS feeds and alerts) as chips that match the #filterbar's queryItem + faction
 * indicator look. One source of truth for three surfaces:
 *   - the live #filterbar chips (search.js delegates chip + role-control building here)
 *   - the #alertModal (editable chips)
 *   - the /manage/alerts list and /notifications messages (read-only chips)
 *
 * Criteria shape (flat, search-API compatible). Entity tokens may carry a
 * per-entity role as "<id>~<role>". A presentation-only `_labels` sidecar maps
 * ids to {label, faction, color} so names/colors render without live lookups:
 *
 *   { q, personID:[], organisationID:[], factionID:[], termID:[], documentID:[],
 *     parliament, electoralPeriodID, _labels:{ "Q1": {label, faction, color} } }
 *
 * Depends on globals: jQuery (`$`), `config`, `localizedLabels`, `factionIDColors`.
 */
window.CriteriaChips = (function ($) {
	"use strict";

	var L = function (key, fallback) {
		// localizedLabels is a top-level `const` (lexical global), so it is NOT a
		// property of window — reference it directly, mirroring generic.js.
		var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
		return labels[key] || fallback || key;
	};

	// Only person chips carry a role ("context") selector. The "*" sentinel means
	// "any context" — translated to an empty context server-side, so the backend's
	// per-type default (main-speaker) is not applied. Members of parliament get the
	// full presiding/speaking context list; all other people get a reduced set.
	var PERSON_ROLES_MP = ["*", "main-speaker", "speaker", "president", "vice-president", "interim-president", "NER"];
	var PERSON_ROLES_OTHER = ["*", "main-speaker", "NER"];

	function rolesFor(type, subtype) {
		if (type !== "person") { return []; }
		return (subtype === "memberOfParliament") ? PERSON_ROLES_MP : PERSON_ROLES_OTHER;
	}

	// The role that a bare (role-less) token represents — mirrors the search
	// backend's determineDefaultContext(). Only person carries a non-default ("*")
	// context; for it a bare token means main-speaker.
	var DEFAULT_ROLE = {
		person: "main-speaker"
	};

	var ROLE_ICONS = {
		"*":                    "icon-asterisk",
		"main-speaker":         "icon-comment",
		"speaker":              "icon-chat-empty",
		"president":            "icon-group",
		"vice-president":       "icon-group",
		"interim-president":    "icon-group",
		"NER":                  "icon-magic",
		"main-speaker-faction": "icon-group",
		"main-speaker-party":   "icon-bank",
		"proceedingsReference": "icon-doc-text"
	};

	var ENTITY_TYPES = ["person", "organisation", "term", "document"];

	function isEntityType(type) {
		return ENTITY_TYPES.indexOf(type) !== -1;
	}

	// The role a *bare* (role-less) token resolves to — must mirror the search
	// backend's determineDefaultContext(), which is keyed by entity type only
	// (always main-speaker for person). Used to decide when a token needs an
	// explicit "~role" suffix.
	function defaultRole(type) {
		return DEFAULT_ROLE.hasOwnProperty(type) ? DEFAULT_ROLE[type] : "";
	}

	// The role a *freshly added* chip starts on (UI default), which DOES depend on
	// the person subtype: members of parliament default to main-speaker; other
	// people default to "*" (any context) — emitted as an explicit "~*" token since
	// the backend would otherwise treat a bare person token as main-speaker.
	function initialRole(type, subtype) {
		if (type === "person") {
			return (subtype === "memberOfParliament") ? "main-speaker" : "*";
		}
		return defaultRole(type);
	}

	// Map a role/context string to the existing `context<CamelCase>` lang key, e.g.
	// "vice-president" -> "contextvicePresident", "main-speaker" -> "contextmainSpeaker",
	// "NER" -> "contextnER" (first segment lower-cased, the rest upper-cased).
	function contextLabelKey(role) {
		return "context" + role.split("-").map(function (seg, i) {
			return i === 0
				? seg.charAt(0).toLowerCase() + seg.slice(1)
				: seg.charAt(0).toUpperCase() + seg.slice(1);
		}).join("");
	}

	function roleLabel(role) {
		if (role === "*" || role === "all" || role === "any") { return L("all", "All"); }
		if (role === "NER") { return L("automaticallyDetectedInSpeeches", "Automatically detected"); }
		return L(contextLabelKey(role), role);
	}

	// Tooltip / readable form prefixed with the generic "Context" label,
	// e.g. "Context: Main Speaker".
	function roleTitle(role) {
		return L("context", "Context") + ": " + roleLabel(role);
	}

	// Split "<id>~<role>" into [id, role|null].
	function splitRole(token) {
		token = String(token == null ? "" : token);
		var pos = token.indexOf("~");
		if (pos === -1) { return [token, null]; }
		return [token.slice(0, pos), token.slice(pos + 1)];
	}

	// Icon for the non-entity / scalar chip types.
	function scalarIcon(type) {
		switch (type) {
			case "q":               return "icon-search";
			case "faction":         return "icon-group";
			case "parliament":      return "icon-bank";
			case "electoralPeriod": return "icon-calendar";
			default:                return ""; // free-text term: no icon (matches filterbar)
		}
	}

	// ---- Role control (context) ----------------------------------------------
	// Only person chips get this. Read-only: a single role icon with a tooltip.
	// Editable: a small custom dropdown (icon-only when closed; icon + label open).
	// The available roles depend on the person subtype (memberOfParliament vs other).
	function buildRoleControl(type, role, opts, subtype) {
		opts = opts || {};
		var roles = rolesFor(type, subtype);
		role = role || defaultRole(type);
		if (!roles.length) { return null; }

		if (!opts.editable) {
			return $('<span class="queryRole ms-2"></span>')
				.addClass(ROLE_ICONS[role] || "")
				.attr("title", roleTitle(role));
		}

		var wrap = $('<span class="queryRole queryRoleEditable ms-2"></span>');
		var toggle = $('<span class="queryRoleToggle"></span>')
			.addClass(ROLE_ICONS[role] || "")
			.attr("title", roleTitle(role));
		var menu = $('<span class="queryRoleMenu"></span>');
		// Generic "Context" header so the bare role nouns read in context.
		menu.append($('<span class="queryRoleMenuHeader"></span>').text(L("context", "Context")));

		roles.forEach(function (r) {
			var opt = $('<span class="queryRoleOption"></span>')
				.append('<span class="' + (ROLE_ICONS[r] || "") + ' me-2"></span>')
				.append($("<span></span>").text(roleLabel(r)));
			if (r === role) { opt.addClass("active"); }
			opt.on("click", function (e) {
				e.preventDefault();
				e.stopPropagation();
				var chip = wrap.closest(".queryItem");
				chip.attr("data-role", r);
				toggle.attr("class", "queryRoleToggle").addClass(ROLE_ICONS[r] || "").attr("title", roleTitle(r));
				menu.find(".queryRoleOption").removeClass("active");
				opt.addClass("active");
				menu.removeClass("show");
				if (typeof opts.onRoleChange === "function") { opts.onRoleChange(chip, r); }
			});
			menu.append(opt);
		});

		toggle.on("click", function (e) {
			e.preventDefault();
			e.stopPropagation();
			$(".queryRoleMenu.show").not(menu).removeClass("show");
			menu.toggleClass("show");
		});

		wrap.append(toggle).append(menu);
		return wrap;
	}

	// ---- Chip factory ---------------------------------------------------------
	// spec: { type, id, label, faction, factionLabel, role, subtype }
	// opts: { editable, link, onDelete, onRoleChange }
	function buildChip(spec, opts) {
		opts = opts || {};
		var type = spec.type;
		var entity = isEntityType(type);
		var chip = $('<span class="queryItem d-flex align-items-center"></span>').attr("data-type", type);
		if (spec.id) { chip.attr("data-item-id", spec.id); }
		if (type === "person" && spec.subtype) { chip.attr("data-subtype", spec.subtype); }

		var iconClass = entity ? ("icon-type-" + type) : scalarIcon(type);
		if (iconClass) { chip.append('<span class="' + iconClass + ' me-2"></span>'); }

		var labelText = spec.label != null && spec.label !== "" ? spec.label : (spec.id || "");
		if (type === "faction") {
			// The label itself is the party-coloured badge (no separate affiliation).
			chip.append($('<span class="queryText partyIndicator"></span>')
				.attr("data-faction", spec.id || labelText)
				.text(labelText));
		} else {
			chip.append($('<span class="queryText"></span>').text(labelText));
			// Faction colour badge for a person's affiliation.
			if (spec.faction) {
				chip.append($('<span class="ms-2 partyIndicator"></span>')
					.attr("data-faction", spec.faction)
					.text(spec.factionLabel || spec.faction));
			}
		}

		// Per-entity role control (person only).
		if (entity) {
			var role = spec.role || initialRole(type, spec.subtype);
			chip.attr("data-role", role);
			var roleCtrl = buildRoleControl(type, role, opts, spec.subtype);
			if (roleCtrl) { chip.append(roleCtrl); }
		}

		// Link to entity detail page.
		if (opts.link !== false && entity && spec.id) {
			chip.append($('<a target="_blank" class="queryLinkIcon icon-link-ext ms-2"></a>')
				.attr("href", config.dir.root + "/" + type + "/" + encodeURIComponent(spec.id))
				.attr("title", L("goToDetails", "Open details")));
		}

		// Delete control (editable only).
		if (opts.editable) {
			var del = $('<span class="queryDeleteItem icon-cancel ms-2"></span>');
			del.on("click", function (e) {
				e.preventDefault();
				e.stopPropagation();
				chip.remove();
				if (typeof opts.onDelete === "function") { opts.onDelete(chip); }
			});
			chip.append(del);
		}

		return chip;
	}

	// ---- Render a whole criteria object into a container ----------------------
	function render(criteria, el, opts) {
		opts = opts || {};
		var $el = $(el);
		if (!$el.length) { return; }
		$el.empty().addClass("criteriaChips");

		criteria = criteria || {};
		var labels = (criteria._labels && typeof criteria._labels === "object") ? criteria._labels : {};
		var chips = [];

		function asArray(v) {
			if (v == null) { return []; }
			return Array.isArray(v) ? v : [v];
		}

		function entityChips(key, type) {
			asArray(criteria[key]).forEach(function (token) {
				var parts = splitRole(token);
				var id = parts[0], role = parts[1];
				if (!id) { return; }
				var meta = labels[id] || {};
				chips.push(buildChip({
					type: type,
					id: id,
					label: meta.label || id,
					faction: meta.faction || null,
					factionLabel: meta.factionLabel || meta.faction || null,
					role: role || defaultRole(type),
					subtype: meta.type || null
				}, opts));
			});
		}

		entityChips("personID", "person");
		entityChips("organisationID", "organisation");
		entityChips("termID", "term");
		entityChips("documentID", "document");

		// Faction chips (party-coloured, no role/link).
		asArray(criteria.factionID).forEach(function (token) {
			var parts = splitRole(token);
			var id = parts[0];
			if (!id) { return; }
			var meta = labels[id] || {};
			chips.push(buildChip({ type: "faction", id: id, label: meta.label || id }, opts));
		});

		// Free-text query → one chip per token (mirrors the filterbar).
		if (criteria.q) {
			String(criteria.q).match(/(\"[^\"]+\"|[^\s]+)/g)?.forEach(function (word) {
				chips.push(buildChip({ type: "q", label: word }, opts));
			});
		}

		// Scalar filter chips — informational, not editable (re-applied on save).
		var staticOpts = $.extend({}, opts, { editable: false });
		if (criteria.parliament) {
			var pl = (window.config && config.parliament && config.parliament[criteria.parliament] && config.parliament[criteria.parliament].label) || criteria.parliament;
			chips.push(buildChip({ type: "parliament", label: pl }, staticOpts));
		}
		if (criteria.electoralPeriodID) {
			var ep = (labels[criteria.electoralPeriodID] && labels[criteria.electoralPeriodID].label) || criteria.electoralPeriodID;
			chips.push(buildChip({ type: "electoralPeriod", label: ep }, staticOpts));
		}

		if (!chips.length) {
			$el.append('<span class="text-muted">&mdash;</span>');
			return;
		}
		chips.forEach(function (c) { $el.append(c); });
	}

	// ---- Read editable chips back into a criteria object ----------------------
	// Used by the alert modal on save. Produces the same flat shape (+ _labels).
	function collect(el) {
		var $el = $(el);
		var out = { _labels: {} };
		var keyByType = {
			person: "personID", organisation: "organisationID",
			term: "termID", document: "documentID", faction: "factionID"
		};

		$el.find(".queryItem").each(function () {
			var $chip = $(this);
			var type = $chip.attr("data-type");
			if (type === "q" || type === "text") {
				out.q = (out.q ? out.q + " " : "") + $chip.find(".queryText").first().text();
				return;
			}
			var key = keyByType[type];
			if (!key) { return; } // parliament / electoralPeriod chips are not editable here
			var id = $chip.attr("data-item-id");
			if (!id) { return; }
			var role = $chip.attr("data-role");
			var token = (role && role !== defaultRole(type)) ? (id + "~" + role) : id;
			if (!out[key]) { out[key] = []; }
			out[key].push(token);

			var label = $chip.find(".queryText").first().text();
			var $faction = $chip.find(".partyIndicator").first();
			var meta = {};
			if (label) { meta.label = label; }
			var subtype = $chip.attr("data-subtype");
			if (subtype) { meta.type = subtype; }
			if ($faction.length) {
				if (type === "faction") {
					meta.label = label;
				} else {
					meta.faction = $faction.attr("data-faction");
					meta.factionLabel = $faction.text();
				}
			}
			if (Object.keys(meta).length) { out._labels[id] = meta; }
		});

		if (!Object.keys(out._labels).length) { delete out._labels; }
		return out;
	}

	// Close any open role menu when clicking elsewhere.
	$(document).on("click.criteriaChips", function () {
		$(".queryRoleMenu.show").removeClass("show");
	});

	return {
		render: render,
		buildChip: buildChip,
		buildRoleControl: buildRoleControl,
		collect: collect,
		defaultRole: defaultRole,
		splitRole: splitRole,
		roleLabel: roleLabel
	};
})(jQuery);
