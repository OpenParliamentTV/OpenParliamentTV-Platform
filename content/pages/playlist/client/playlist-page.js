/**
 * Playlist page: owner-only editing.
 *
 * Everything here is a no-op for a guest viewing a shared playlist — the server
 * only renders the controls for the owner, and the API rejects the calls anyway.
 * Reordering renumbers client-side and sends the full order, which is what lets
 * the server renumber authoritatively without trusting per-item positions.
 */
(function () {
	"use strict";

	var data = window.playlistPageData;
	if (!data || !data.owner || !data.id) { return; }

	var apiBase = (window.config && config.dir && config.dir.root ? config.dir.root : "") + "/api/v1";
	var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
	function t(key, fallback) { return labels[key] || fallback || key; }

	function post(path, params) {
		var body = new URLSearchParams();
		Object.keys(params || {}).forEach(function (key) {
			if (Array.isArray(params[key])) {
				params[key].forEach(function (v) { body.append(key + "[]", v); });
			} else {
				body.append(key, params[key]);
			}
		});
		return fetch(apiBase + path, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Accept": "application/json" },
			body: body
		}).then(function (r) { return r.json(); });
	}

	function toast(message) {
		var el = document.createElement("div");
		el.className = "alert alert-success shadow-sm";
		el.style.cssText = "position:fixed;bottom:1rem;right:1rem;z-index:2000;max-width:320px;";
		el.textContent = message;
		document.body.appendChild(el);
		setTimeout(function () { el.remove(); }, 3000);
	}

	var list = document.getElementById("playlistItems");

	/** Renumber the visible positions after a move or a removal. */
	function renumber() {
		if (!list) { return; }
		Array.prototype.forEach.call(list.children, function (li, index) {
			var pos = li.querySelector(".playlistItemPosition");
			if (pos) { pos.textContent = String(index + 1); }
		});
	}

	function persistOrder() {
		if (!list) { return; }
		var order = Array.prototype.map.call(list.children, function (li) {
			return li.getAttribute("data-item-id");
		}).filter(Boolean);
		post("/playlist/itemsReorder", { id: data.id, order: order });
	}

	// ---- share toggle ---------------------------------------------------------

	var sharedSwitch = document.getElementById("playlistSharedSwitch");
	if (sharedSwitch) {
		sharedSwitch.addEventListener("change", function () {
			var on = sharedSwitch.checked;
			post("/playlist/update", { id: data.id, shared: on ? 1 : 0 }).then(function () {
				var link = document.getElementById("playlistShareLink");
				if (link) { link.classList.toggle("d-none", !on); }
			});
		});
	}

	var copyButton = document.getElementById("playlistCopyButton");
	if (copyButton) {
		copyButton.addEventListener("click", function () {
			var field = document.getElementById("playlistShareUrl");
			if (!field) { return; }
			field.select();
			navigator.clipboard.writeText(field.value).then(function () {
				copyButton.textContent = t("apiKeyCopied", "Copied");
			});
		});
	}

	// ---- rename / delete ------------------------------------------------------

	var renameButton = document.getElementById("playlistRenameButton");
	if (renameButton) {
		renameButton.addEventListener("click", function () {
			var heading = document.getElementById("playlistTitle");
			var current = heading ? heading.textContent.trim() : "";
			var next = window.prompt(t("title", "Title"), current);
			if (next === null || next.trim() === "" || next === current) { return; }
			post("/playlist/update", { id: data.id, title: next.trim() }).then(function (response) {
				if (response && response.data) {
					heading.textContent = response.data.attributes.title;
					document.title = response.data.attributes.title;
				}
			});
		});
	}

	var deleteButton = document.getElementById("playlistDeleteButton");
	if (deleteButton) {
		deleteButton.addEventListener("click", function () {
			if (!window.confirm(t("playlistConfirmDelete", "Delete this playlist?"))) { return; }
			post("/playlist/delete", { id: data.id }).then(function () {
				window.location.href = config.dir.root + "/manage/playlists";
			});
		});
	}

	// ---- item actions ---------------------------------------------------------

	if (list) {
		list.addEventListener("click", function (event) {
			var button = event.target.closest ? event.target.closest("button") : null;
			if (!button) { return; }
			var li = button.closest(".playlistItem");
			if (!li) { return; }

			if (button.classList.contains("playlistItemUp")) {
				if (li.previousElementSibling) {
					list.insertBefore(li, li.previousElementSibling);
					renumber();
					persistOrder();
				}
				return;
			}

			if (button.classList.contains("playlistItemDown")) {
				if (li.nextElementSibling) {
					list.insertBefore(li.nextElementSibling, li);
					renumber();
					persistOrder();
				}
				return;
			}

			if (button.classList.contains("playlistItemRemove")) {
				var itemId = li.getAttribute("data-item-id");
				if (!itemId) { return; }
				post("/playlist/itemRemove", { itemID: itemId }).then(function (response) {
					if (response && response.meta && response.meta.requestStatus === "success") {
						li.remove();
						renumber();
						toast(t("delete", "Delete"));
					}
				});
			}
		});
	}

}());
