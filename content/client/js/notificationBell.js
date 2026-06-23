/**
 * Notification bell (Plan B): polls the unread count and renders the dropdown.
 * Relies on the global `config` (config.dir.root) and `localizedLabels` defined
 * in index.php. Only runs when the bell element is present (logged-in users).
 */
(function () {
	"use strict";

	var bell = document.getElementById("notificationBell");
	if (!bell) { return; }

	var apiBase = (window.config && config.dir && config.dir.root ? config.dir.root : "") + "/api/v1";
	var labels = window.localizedLabels || {};
	var badge = document.getElementById("notificationBellBadge");
	var list = document.getElementById("notificationBellList");
	var markAllBtn = document.getElementById("notificationMarkAllRead");
	var POLL_MS = 30000;

	function t(key, fallback) { return labels[key] || fallback || key; }

	function apiGet(path) {
		return fetch(apiBase + path, { credentials: "same-origin", headers: { "Accept": "application/json" } })
			.then(function (r) { return r.json(); });
	}
	function apiPost(path) {
		return fetch(apiBase + path, { method: "POST", credentials: "same-origin", headers: { "Accept": "application/json" } })
			.then(function (r) { return r.json(); });
	}

	function setBadge(count) {
		if (!badge) { return; }
		if (count > 0) {
			badge.textContent = count > 99 ? "99+" : String(count);
			badge.classList.remove("d-none");
		} else {
			badge.classList.add("d-none");
		}
	}

	function refreshCount() {
		apiGet("/notification/unreadCount")
			.then(function (res) {
				if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
					setBadge(res.data.unreadCount || 0);
				}
			})
			.catch(function () { /* ignore transient errors */ });
	}

	// Only allow http(s) or same-origin relative paths as notification links;
	// anything else (e.g. javascript:) falls back to "#".
	function safeHref(url) {
		if (!url) { return "#"; }
		var s = String(url);
		if (s.charAt(0) === "/") { return s; }
		if (/^https?:\/\//i.test(s)) { return s; }
		return "#";
	}

	function emptyState(msg) {
		var div = document.createElement("div");
		div.className = "text-center text-muted py-4 small";
		div.textContent = msg;
		return div;
	}

	function buildItem(n) {
		var a = n.attributes || {};
		var el = document.createElement("a");
		el.className = "notificationItem" + (a.read ? "" : " unread");
		el.href = safeHref(a.link);

		var titleEl = document.createElement("div");
		titleEl.className = "nTitle";
		titleEl.textContent = a.title || "";
		el.appendChild(titleEl);

		if (a.body) {
			var bodyEl = document.createElement("div");
			bodyEl.className = "nBody";
			bodyEl.textContent = a.body;
			el.appendChild(bodyEl);
		}

		var timeEl = document.createElement("div");
		timeEl.className = "nTime";
		timeEl.textContent = a.created ? String(a.created).replace("T", " ").substring(0, 16) : "";
		el.appendChild(timeEl);

		el.addEventListener("click", function () {
			if (n.id != null) { apiPost("/notification/markRead?id=" + encodeURIComponent(n.id)); }
			// Let the navigation proceed via the href.
		});
		return el;
	}

	function renderList(items) {
		if (!list) { return; }
		list.textContent = "";
		if (!items || !items.length) {
			list.appendChild(emptyState(t("notificationNone", "No notifications yet")));
			return;
		}
		items.forEach(function (n) { list.appendChild(buildItem(n)); });
	}

	function loadList() {
		if (list) { list.textContent = ""; list.appendChild(emptyState("…")); }
		apiGet("/notification/list?limit=10")
			.then(function (res) {
				if (res && res.meta && res.meta.requestStatus === "success") {
					renderList(res.data || []);
				}
			})
			.catch(function () { /* ignore */ });
	}

	bell.addEventListener("show.bs.dropdown", loadList);

	if (markAllBtn) {
		markAllBtn.addEventListener("click", function (e) {
			e.preventDefault();
			apiPost("/notification/markAllRead").then(function () {
				setBadge(0);
				loadList();
			});
		});
	}

	refreshCount();
	setInterval(refreshCount, POLL_MS);
})();
