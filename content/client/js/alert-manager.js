/**
 * Alert manager (Plan B): drives the "Save as alert" button + modal, and exposes
 * helpers reused by the /manage/alerts page. Relies on global `config`,
 * `localizedLabels`, and Bootstrap's Modal. Loaded for logged-in users only.
 */
(function () {
	"use strict";

	var apiBase = (window.config && config.dir && config.dir.root ? config.dir.root : "") + "/api/v1";
	var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
	function t(key, fallback) { return labels[key] || fallback || key; }

	function post(path, params) {
		var body = new URLSearchParams();
		Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
		return fetch(apiBase + path, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Accept": "application/json" },
			body: body
		}).then(function (r) { return r.json(); });
	}

	function toast(msg) {
		var el = document.createElement("div");
		el.className = "alert alert-success shadow-sm";
		el.style.cssText = "position:fixed;bottom:1rem;right:1rem;z-index:2000;max-width:320px;";
		el.textContent = msg;
		document.body.appendChild(el);
		setTimeout(function () { el.remove(); }, 3000);
	}

	// ---- Modal ----------------------------------------------------------------
	var modalEl = document.getElementById("alertModal");
	var bsModal = (modalEl && window.bootstrap) ? new bootstrap.Modal(modalEl) : null;

	function openModal(opts) {
		if (!modalEl) { return; }
		opts = opts || {};
		var criteria = opts.criteria || {};
		document.getElementById("alertModalId").value = opts.id || "";
		document.getElementById("alertModalCriteria").value = JSON.stringify(criteria);
		// Render the criteria as editable chips (delete + per-entity role) via the
		// shared CriteriaChips component, the same visual used in the filterbar/lists.
		if (window.CriteriaChips) {
			CriteriaChips.render(criteria, "#alertModalCriteriaChips", { editable: true });
		}
		document.getElementById("alertModalFrequency").value = opts.frequency || "realtime";
		document.getElementById("alertModalChannelInApp").checked = opts.channelInApp !== false;
		document.getElementById("alertModalChannelEmail").checked = opts.channelEmail !== false;
		document.getElementById("alertModalTitle").textContent = opts.id ? t("alertEditTitle", "Edit alert") : t("alertCreateTitle", "Create alert");
		if (bsModal) { bsModal.show(); }
	}

	if (modalEl) {
		var saveBtn = document.getElementById("alertModalSave");
		saveBtn.addEventListener("click", function () {
			var id = document.getElementById("alertModalId").value;
			// Collect the (possibly edited) chips back into criteria, then re-apply the
			// scalar filters that aren't represented as editable chips.
			var baseCriteria = {};
			try { baseCriteria = JSON.parse(document.getElementById("alertModalCriteria").value || "{}"); } catch (e) {}
			var criteria = window.CriteriaChips ? CriteriaChips.collect("#alertModalCriteriaChips") : baseCriteria;
			["parliament", "electoralPeriodID", "context"].forEach(function (k) {
				if (baseCriteria[k]) { criteria[k] = baseCriteria[k]; }
			});
			var params = {
				criteria: JSON.stringify(criteria),
				frequency: document.getElementById("alertModalFrequency").value,
				channelInApp: document.getElementById("alertModalChannelInApp").checked,
				channelEmail: document.getElementById("alertModalChannelEmail").checked
			};
			var path = id ? "/alert/update?id=" + encodeURIComponent(id) : "/alert/create";
			post(path, params).then(function (res) {
				if (res && res.meta && res.meta.requestStatus === "success") {
					if (bsModal) { bsModal.hide(); }
					toast(id ? t("alertUpdatedToast", "Alert updated") : t("alertSavedToast", "Alert created"));
					decorateSaveButtons(true);
					document.dispatchEvent(new CustomEvent("alertsChanged"));
				} else {
					var detail = (res && res.errors && res.errors[0]) ? res.errors[0].detail : "Error";
					toast(detail);
				}
			});
		});
	}

	// ---- "Save as alert" button (search + entity result grids) ---------------
	function parseCriteria(btn) {
		try { return JSON.parse(btn.getAttribute("data-criteria") || "{}"); }
		catch (e) { return {}; }
	}

	function setSubscribedState(btn, subscribed) {
		var label = btn.querySelector(".saveAsAlertLabel");
		if (label) { label.textContent = subscribed ? t("alertSubscribed", "Subscribed") : t("alertSaveAsAlert", "Save as alert"); }
		btn.classList.toggle("btn-primary", subscribed);
		btn.classList.toggle("btn-outline-primary", !subscribed);
		btn.setAttribute("data-subscribed", subscribed ? "1" : "0");
	}

	function decorateSaveButtons(forceRecheck) {
		var btn = document.getElementById("saveAsAlertButton");
		if (!btn) { return; }
		if (btn.getAttribute("data-decorated") === "1" && !forceRecheck) { return; }
		btn.setAttribute("data-decorated", "1");
		post("/alert/status", { criteria: JSON.stringify(parseCriteria(btn)) }).then(function (res) {
			if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
				setSubscribedState(btn, !!res.data.subscribed);
			}
		});
	}

	// Delegated click — works even though the grid is injected via AJAX later.
	document.addEventListener("click", function (e) {
		var btn = e.target.closest && e.target.closest("#saveAsAlertButton");
		if (!btn) { return; }
		e.preventDefault();
		if (btn.getAttribute("data-subscribed") === "1") {
			window.location.href = (config.dir.root || "") + "/manage/alerts";
			return;
		}
		openModal({ criteria: parseCriteria(btn) });
	});

	// Re-decorate when the result grid is (re)injected.
	var observer = new MutationObserver(function () { decorateSaveButtons(false); });
	observer.observe(document.body, { childList: true, subtree: true });
	decorateSaveButtons(false);

	// ---- Public helpers for the manage page ----------------------------------
	window.AlertManager = {
		openCreate: function (criteria) { openModal({ criteria: criteria }); },
		openEdit: function (alert) {
			openModal({
				id: alert.id,
				criteria: alert.attributes.criteria,
				frequency: alert.attributes.frequency,
				channelInApp: alert.attributes.channelInApp,
				channelEmail: alert.attributes.channelEmail
			});
		},
		remove: function (id) {
			return post("/alert/delete?id=" + encodeURIComponent(id), {}).then(function (res) {
				if (res && res.meta && res.meta.requestStatus === "success") {
					toast(t("alertDeletedToast", "Alert deleted"));
					document.dispatchEvent(new CustomEvent("alertsChanged"));
				}
				return res;
			});
		},
		toast: toast
	};
})();
