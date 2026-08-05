/**
 * Playlist manager: drives the shared "add to playlist" modal.
 *
 * Every add surface funnels through PlaylistManager.openAdd() with the same
 * payload — a media id, its parliament, and either a segment list or nothing at
 * all for the whole speech. The surfaces differ only in where those segments
 * come from: a search result's hits, a transcript selection, or none.
 *
 * Buttons are bound by delegation on document, because two of the surfaces
 * (result grids, the player title bar) are replaced by AJAX at any time.
 * Relies on globals `config`, `localizedLabels` and Bootstrap's Modal.
 */
(function () {
	"use strict";

	var apiBase = (window.config && config.dir && config.dir.root ? config.dir.root : "") + "/api/v1";
	var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
	function t(key, fallback) { return labels[key] || fallback || key; }

	var modalEl = document.getElementById("playlistModal");
	var bsModal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;

	var pending = null;   // what the open dialog will add
	var playlists = [];

	function request(path, params, method) {
		var body = new URLSearchParams();
		Object.keys(params || {}).forEach(function (key) { body.append(key, params[key]); });

		if (method === "GET") {
			return fetch(apiBase + path + "?" + body.toString(), {
				credentials: "same-origin", headers: { "Accept": "application/json" }
			}).then(function (r) { return r.json(); });
		}
		return fetch(apiBase + path, {
			method: "POST", credentials: "same-origin",
			headers: { "Accept": "application/json" }, body: body
		}).then(function (r) { return r.json(); });
	}

	function toast(message, isError) {
		var el = document.createElement("div");
		el.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm";
		el.style.cssText = "position:fixed;bottom:1rem;right:1rem;z-index:2000;max-width:320px;";
		el.textContent = message;
		document.body.appendChild(el);
		setTimeout(function () { el.remove(); }, 3000);
	}

	/** One row per existing playlist; clicking it adds and closes. */
	function renderList() {
		var list = document.getElementById("playlistModalList");
		if (!list) { return; }
		list.innerHTML = "";

		playlists.forEach(function (playlist) {
			var button = document.createElement("button");
			button.type = "button";
			button.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
			button.textContent = playlist.attributes.title;

			var count = document.createElement("span");
			count.className = "badge bg-light text-dark";
			count.textContent = playlist.attributes.itemCount || 0;
			button.appendChild(count);

			button.addEventListener("click", function () { addTo(playlist.id); });
			list.appendChild(button);
		});
	}

	function addTo(playlistId) {
		if (!pending) { return; }

		var params = {
			id: playlistId,
			mediaID: pending.mediaID,
			parliament: pending.parliament || ""
		};
		if (pending.segments && pending.segments.length) {
			params.segments = JSON.stringify(pending.segments);
		}

		request("/playlist/itemAdd", params).then(function (response) {
			if (response && response.meta && response.meta.requestStatus === "success") {
				toast(t("playlistAdded", "Added to playlist"));
				if (bsModal) { bsModal.hide(); }
				document.dispatchEvent(new CustomEvent("playlistsChanged"));
			} else {
				var error = (response && response.errors && response.errors[0]) ? response.errors[0].detail : null;
				toast(error || t("playlistAddTo", "Add to playlist"), true);
			}
		});
	}

	/**
	 * Open the dialog.
	 *
	 * @param {{mediaID: string, parliament: string, segments: ?Array}} payload
	 */
	function openAdd(payload) {
		if (!modalEl || !payload || !payload.mediaID) { return; }
		pending = payload;

		var scope = document.getElementById("playlistModalScope");
		if (scope) {
			scope.textContent = (payload.segments && payload.segments.length)
				? t("playlistSegment", "Selected segment")
				: t("playlistWholeSpeech", "Whole speech");
		}

		var list = document.getElementById("playlistModalList");
		if (list) { list.innerHTML = ""; }

		request("/playlist/list", {}, "GET").then(function (response) {
			playlists = (response && response.data) ? response.data : [];
			renderList();
		});

		if (bsModal) { bsModal.show(); }
	}

	/** Create a playlist and put the pending item straight into it. */
	function createAndAdd() {
		var field = document.getElementById("playlistModalNewTitle");
		if (!field) { return; }
		var title = field.value.trim();
		if (title === "") { field.focus(); return; }

		request("/playlist/create", { title: title }).then(function (response) {
			if (response && response.data) {
				field.value = "";
				addTo(response.data.id);
			} else {
				var error = (response && response.errors && response.errors[0]) ? response.errors[0].detail : null;
				toast(error || t("playlistNew", "New playlist"), true);
			}
		});
	}

	/**
	 * Resolve a set of search criteria into playlist items and save them.
	 *
	 * The result set is fetched through the same compact projection the queue
	 * panel uses, so each speech arrives with its hits already in segment shape.
	 * The server caps that response, and playlistCreate caps again on write —
	 * the response reports what was actually stored.
	 *
	 * @param {Object} criteria Search params, as the RSS/alert links use them
	 * @param {string} title
	 */
	function saveSearchAsPlaylist(criteria, title) {
		var query = new URLSearchParams();
		Object.keys(criteria || {}).forEach(function (key) {
			var value = criteria[key];
			if (Array.isArray(value)) {
				value.forEach(function (v) { query.append(key + "[]", v); });
			} else if (value !== null && value !== "") {
				query.append(key, value);
			}
		});
		query.append("fields", "queue");
		query.append("getAllResults", "1");

		fetch(apiBase + "/search/media?" + query.toString(), {
			credentials: "same-origin", headers: { "Accept": "application/json" }
		}).then(function (r) { return r.json(); }).then(function (response) {
			if (!response || !response.data || !response.data.length) {
				toast(t("playlistEmpty", "This playlist is empty."), true);
				return;
			}
			var items = response.data.map(function (node) {
				return {
					mediaID: node.id,
					parliament: node.attributes.parliament || "",
					segments: node.segments || null
				};
			});
			saveResults(items, title);
		});
	}

	/**
	 * Save a whole result set as a new playlist.
	 *
	 * @param {Array} items [{mediaID, parliament, segments}, …]
	 * @param {string} title
	 */
	function saveResults(items, title) {
		request("/playlist/create", { title: title, items: JSON.stringify(items) }).then(function (response) {
			if (!response || !response.data) {
				var error = (response && response.errors && response.errors[0]) ? response.errors[0].detail : null;
				toast(error || t("playlistSaveResults", "Save results as playlist"), true);
				return;
			}
			var truncated = response.meta && response.meta.truncated;
			toast(truncated
				? t("playlistTruncated", "Only the first {count} speeches were saved.").replace("{count}", truncated.stored)
				: t("playlistAdded", "Added to playlist"));
			document.dispatchEvent(new CustomEvent("playlistsChanged"));
		});
	}

	// ---- wiring ---------------------------------------------------------------

	if (modalEl) {
		var createButton = document.getElementById("playlistModalCreate");
		if (createButton) { createButton.addEventListener("click", createAndAdd); }

		var newTitle = document.getElementById("playlistModalNewTitle");
		if (newTitle) {
			newTitle.addEventListener("keydown", function (event) {
				if (event.key === "Enter") { event.preventDefault(); createAndAdd(); }
			});
		}
	}

	// Delegated: result grids and the player fragment are swapped by AJAX, so a
	// handler bound to the elements themselves would not survive navigation.
	document.addEventListener("click", function (event) {
		var saveButton = event.target.closest ? event.target.closest("#saveAsPlaylistButton") : null;
		if (saveButton) {
			event.preventDefault();
			var criteria = {};
			try { criteria = JSON.parse(saveButton.getAttribute("data-criteria")) || {}; } catch (e) { criteria = {}; }
			var title = window.prompt(t("playlistSaveResults", "Save results as playlist"), criteria.q || t("playlists", "Playlists"));
			if (title === null || title.trim() === "") { return; }
			saveSearchAsPlaylist(criteria, title.trim());
			return;
		}

		var button = event.target.closest ? event.target.closest(".addToPlaylistButton") : null;
		if (!button) { return; }
		event.preventDefault();
		event.stopPropagation();

		var segments = null;
		var raw = button.getAttribute("data-segments");
		if (raw) {
			try { segments = JSON.parse(raw); } catch (e) { segments = null; }
		}

		openAdd({
			mediaID: button.getAttribute("data-media-id"),
			parliament: button.getAttribute("data-parliament") || "",
			segments: segments
		});
	});

	window.PlaylistManager = {
		openAdd: openAdd,
		saveResults: saveResults
	};

}());
