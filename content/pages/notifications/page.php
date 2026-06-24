<?php
include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"] ?? null, "requestPage", $pageType);

if (empty($_SESSION["login"]) || $auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"] ?? "";
    include_once (__DIR__."/../login/page.php");

} else {

    require_once(__DIR__ . '/../../../api/v1/modules/notification.php');
    $resp = notificationList(["limit" => 100]);
    $items = ($resp["meta"]["requestStatus"] === "success") ? $resp["data"] : [];

    $notificationRows = array_map(function ($n) {
        $a = $n["attributes"];
        return [
            "id" => $n["id"],
            "title" => $a["title"],
            "body" => $a["body"],
            "link" => $a["link"],
            "read" => $a["read"],
            "created" => $a["created"],
            "notificationType" => $a["notificationType"],
            "mediaID" => $a["mediaID"] ?? null,
            "parliament" => $a["parliament"] ?? null,
            "alertCriteria" => $a["alertCriteria"] ?? null,
        ];
    }, $items);

    include_once(__DIR__ . '/../../header.php');

    // Bulk action toolbar, repeated in each tab (scope = closest .tab-pane).
    $bulkToolbar = function () { ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-outline-primary bulk-mark-read" disabled><span class="icon-ok me-1"></span><?= L::markRead(); ?></button>
            <button type="button" class="btn btn-sm btn-outline-primary bulk-mark-unread" disabled><span class="icon-eye me-1"></span><?= L::markUnread(); ?></button>
            <button type="button" class="btn btn-sm btn-outline-primary bulk-delete" disabled><span class="icon-trash me-1"></span><?= L::delete(); ?></button>
            <a href="#" class="btn btn-sm btn-outline ms-auto bulk-mark-all-read"><?= L::notificationMarkAllRead(); ?></a>
        </div>
    <?php };
?>
<main class="container-fluid subpage">
    <div class="row" style="position: relative; z-index: 1">
        <div class="col-12">
            <h2 class="h4 mb-3"><?= L::notifications(); ?></h2>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#tab-alerts" role="tab" aria-controls="tab-alerts" aria-selected="true"><span class="icon-attention me-1"></span><?= L::notificationFilterAlerts(); ?></a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#tab-messages" role="tab" aria-controls="tab-messages" aria-selected="false"><span class="icon-megaphone me-1"></span><?= L::notificationFilterMessages(); ?></a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane bg-white fade show active" id="tab-alerts" role="tabpanel" aria-labelledby="alerts-tab">
                    <?php $bulkToolbar(); ?>
                    <table id="alertsNotifTable"></table>
                </div>
                <div class="tab-pane bg-white fade" id="tab-messages" role="tabpanel" aria-labelledby="messages-tab">
                    <?php $bulkToolbar(); ?>
                    <table id="messagesNotifTable"></table>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function () {
    var LANG = "<?= $lang ?>";
    var notificationData = <?= json_encode($notificationRows, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
    var api = (config.dir.root || "") + "/api/v1";
    var root = config.dir.root || "";
    function t(k, f) { return labels[k] || f || k; }

    var alertData = notificationData.filter(function (r) { return r.notificationType === 'alert'; });
    var messageData = notificationData.filter(function (r) { return r.notificationType !== 'alert'; });

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }
    function safeHref(url) {
        if (!url) { return null; }
        var s = String(url);
        if (s.charAt(0) === "/") { return s; }
        if (/^https?:\/\//i.test(s)) { return s; }
        return null;
    }
    function post(path) { return fetch(api + path, { method: "POST", credentials: "same-origin" }).then(function (r) { return r.json(); }); }

    function pad2(n) { return (n < 10 ? "0" : "") + n; }
    // Messages "Date" column: locale datetime (like the manage tables).
    function fmtDateTime(v) { return v ? new Date(v).toLocaleString(LANG) : "-"; }
    // Speech date in the media card: matches the result grid's date("d.m.Y").
    function fmtSpeechDate(v) {
        if (!v) { return ""; }
        var d = new Date(v);
        return pad2(d.getDate()) + "." + pad2(d.getMonth() + 1) + "." + d.getFullYear();
    }
    // Duration: matches the result grid's gmdate (i:s, or H:i:s for >= 1h).
    function fmtDuration(sec) {
        sec = parseInt(sec || 0, 10);
        if (!sec) { return ""; }
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        return h ? (h + ":" + pad2(m) + ":" + pad2(s)) : (pad2(m) + ":" + pad2(s));
    }

    // ---- Alerts: rich "Redebeitrag" cell resembling a search result item ----
    // Lazily fetched per row from the search API so we get the matched-sentence
    // snippet (_finds) with a direct timecoded link to the sentence.
    function buildMediaCard(it, row) {
        var mid = row.mediaID;
        var href = mid ? (root + "/media/" + encodeURIComponent(mid)) : safeHref(row.link);
        var speaker = escapeHtml(row.title || "");
        var agenda = "", date = row.created, duration = "", finds = [];
        if (it) {
            var ai = it.relationships && it.relationships.agendaItem && it.relationships.agendaItem.data;
            agenda = (ai && ai.attributes) ? (ai.attributes.title || ai.attributes.officialTitle || "") : "";
            date = (it.attributes && it.attributes.dateStart) ? it.attributes.dateStart : date;
            duration = it.attributes ? it.attributes.duration : "";
            finds = it._finds || [];
        }
        var meta = fmtSpeechDate(date);
        // Reuse the result-grid markup so .resultItem / .resultSnippets CSS applies.
        var html = '<div class="notifMediaCard resultItem' + (finds.length ? ' snippets' : '') + '">';
        html += '<a class="notifMediaHead" href="' + escapeHtml(href || "#") + '">';
        if (meta) { html += '<span class="notifMediaMeta me-2">' + escapeHtml(meta) + '</span>'; }
        if (speaker) { html += '<span class="notifMediaSpeaker">' + speaker + ': </span>'; }
        if (agenda) { html += '<span class="notifMediaAgenda">' + escapeHtml(agenda) + '</span>'; }
        html += '</a>';
        if (finds.length) {
            var q = (row.alertCriteria && row.alertCriteria.q) ? ("q=" + encodeURIComponent(row.alertCriteria.q) + "&") : "";
            html += '<div class="resultSnippets">';
            finds.slice(0, 3).forEach(function (f) {
                var start = f["data-start"] || "";
                var sl = root + "/media/" + encodeURIComponent(mid) + "?" + q + "t=" + encodeURIComponent(start);
                // context is server-side search highlight HTML (safe <em> markup).
                html += '<a class="resultSnippet" href="' + escapeHtml(sl) + '" title="▶">' + (f.context || "") + '</a>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    // Cache rendered media-card HTML per row so re-renders (e.g. after mark-read,
    // which re-runs the cell formatter) repopulate instantly instead of re-fetching
    // or being left blank.
    var mediaCellCache = {};
    var mediaCellLoading = {};
    function loadAlertCells() {
        // Criteria chips + lazily-loaded media cards for the currently rendered rows.
        if (window.CriteriaChips) {
            alertData.forEach(function (row) {
                if (row.alertCriteria) {
                    CriteriaChips.render(row.alertCriteria, '.alertCriteriaCell[data-id="' + row.id + '"]', { editable: false });
                }
            });
        }
        alertData.forEach(function (row) {
            var cell = document.querySelector('.alertMediaCell[data-id="' + row.id + '"]');
            if (!cell) { return; }
            if (mediaCellCache[row.id] != null) { cell.innerHTML = mediaCellCache[row.id]; return; }
            if (mediaCellLoading[row.id]) { return; }
            mediaCellLoading[row.id] = true;
            var fill = function (it) { mediaCellCache[row.id] = buildMediaCard(it, row); cell.innerHTML = mediaCellCache[row.id]; };
            if (!row.mediaID) { fill(null); return; }
            var q = (row.alertCriteria && row.alertCriteria.q) ? ("&q=" + encodeURIComponent(row.alertCriteria.q)) : "";
            fetch(api + "/search/media?id=" + encodeURIComponent(row.mediaID) + q + "&limit=1", { credentials: "same-origin" })
                .then(function (r) { return r.json(); })
                .then(function (res) { fill((res && res.data && res.data[0]) || null); })
                .catch(function () { fill(null); });
        });
    }

    var formatters = {
        titleFormatter: function (value, row) {
            var href = safeHref(row.link);
            var title = escapeHtml(row.title);
            return href
                ? '<a class="notif-link" href="' + escapeHtml(href) + '" data-id="' + escapeHtml(row.id) + '">' + title + '</a>'
                : title;
        },
        dateFormatter: function (value) { return fmtDateTime(value); },
        criteriaFormatter: function (value, row) {
            return '<div class="alertCriteriaCell" data-id="' + escapeHtml(row.id) + '"></div>';
        },
        mediaFormatter: function (value, row) {
            return '<div class="alertMediaCell" data-id="' + escapeHtml(row.id) + '"></div>';
        }
    };

    // Per-row action buttons (manage/alerts style). `withView` adds a link to the speech.
    function makeOperateFormatter(withView) {
        return function (value, row) {
            var html = '<div class="list-group list-group-horizontal">';
            if (withView) {
                var href = safeHref(row.link);
                if (href) {
                    html += '<a class="list-group-item list-group-item-action notif-view" title="' + t("view", "View") + '" href="' + escapeHtml(href) + '"><span class="icon-play-1"></span></a>';
                }
            }
            html += row.read
                ? '<a class="list-group-item list-group-item-action notif-toggle-read" title="' + t("markUnread", "Mark as unread") + '" href="javascript:void(0)"><span class="icon-eye"></span></a>'
                : '<a class="list-group-item list-group-item-action notif-toggle-read" title="' + t("markRead", "Mark as read") + '" href="javascript:void(0)"><span class="icon-ok"></span></a>';
            html += '<a class="list-group-item list-group-item-action notif-delete" title="' + t("delete", "Delete") + '" href="javascript:void(0)"><span class="icon-trash"></span></a>';
            html += '</div>';
            return html;
        };
    }

    var operateEvents = {
        'click .notif-toggle-read': function (e, value, row) {
            var $t = $(e.target).closest('table');
            var action = row.read ? 'markUnread' : 'markRead';
            post("/notification/" + action + "?id=" + encodeURIComponent(row.id)).then(function () {
                $t.bootstrapTable('updateByUniqueId', { id: row.id, row: { read: !row.read } });
            });
        },
        'click .notif-delete': function (e, value, row) {
            if (!confirm(t("alertConfirmDelete", "Delete?"))) { return; }
            var $t = $(e.target).closest('table');
            post("/notification/delete?id=" + encodeURIComponent(row.id)).then(function () {
                $t.bootstrapTable('removeByUniqueId', row.id);
            });
        }
    };

    var linkEvents = {
        'click .notif-link': function (e, value, row) {
            post("/notification/markRead?id=" + encodeURIComponent(row.id));
        }
    };

    // ---- Tables ----
    var $alerts = $('#alertsNotifTable');
    var $messages = $('#messagesNotifTable');
    var commonOpts = {
        classes: "table table-striped",
        locale: LANG,
        search: true,
        searchAlign: "left",
        pagination: true,
        pageSize: 25,
        pageList: [10, 25, 50, 100, 'all'],
        escape: true,
        uniqueId: 'id',
        sortName: 'created',
        sortOrder: 'desc',
        rowStyle: function (row) { return row.read ? {} : { classes: 'fw-bolder' }; }
    };

    $alerts.bootstrapTable($.extend({}, commonOpts, {
        data: alertData,
        onPostBody: loadAlertCells,
        columns: [
            {field: 'id', visible: false},
            {checkbox: true},
            {field: 'criteria', title: '<?= L::alertCriteria(); ?>', formatter: formatters.criteriaFormatter, width: 30, widthUnit: '%'},
            {field: 'media', title: '<?= L::speech(); ?>', formatter: formatters.mediaFormatter},
            {field: 'operate', title: '<?= L::actions(); ?>', formatter: makeOperateFormatter(true), events: operateEvents, class: 'minWidthColumn'}
        ]
    }));

    $messages.bootstrapTable($.extend({}, commonOpts, {
        data: messageData,
        columns: [
            {field: 'id', visible: false},
            {checkbox: true},
            {field: 'title', sortable: true, title: '<?= L::title(); ?>', formatter: formatters.titleFormatter, events: linkEvents},
            {field: 'body', sortable: true, title: '<?= L::notificationMessage(); ?>'},
            {field: 'created', sortable: true, title: '<?= L::date(); ?>', formatter: formatters.dateFormatter},
            {field: 'operate', title: '<?= L::actions(); ?>', formatter: makeOperateFormatter(false), events: operateEvents, class: 'minWidthColumn'}
        ]
    }));

    // ---- Bulk selection + actions (per tab pane) ----
    function paneTable(el) { return $(el).closest('.tab-pane').find('table'); }
    function selectionIds($t) { return $t.bootstrapTable('getSelections').map(function (r) { return r.id; }); }
    function refreshBulk($t) {
        var n = selectionIds($t).length;
        $t.closest('.tab-pane').find('.bulk-mark-read, .bulk-mark-unread, .bulk-delete').prop('disabled', n === 0);
    }
    function doBulk($t, action, mutate) {
        var ids = selectionIds($t);
        if (!ids.length) { return; }
        post("/notification/" + action + "?ids=" + encodeURIComponent(ids.join(","))).then(function () {
            mutate($t, ids);
            $t.bootstrapTable('uncheckAll');
            refreshBulk($t);
        });
    }
    [$alerts, $messages].forEach(function ($t) {
        $t.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table page-change.bs.table', function () { refreshBulk($t); });
    });

    $(document).on('click', '.bulk-mark-read', function () {
        doBulk(paneTable(this), 'markRead', function ($t, ids) { ids.forEach(function (id) { $t.bootstrapTable('updateByUniqueId', { id: id, row: { read: true } }); }); });
    });
    $(document).on('click', '.bulk-mark-unread', function () {
        doBulk(paneTable(this), 'markUnread', function ($t, ids) { ids.forEach(function (id) { $t.bootstrapTable('updateByUniqueId', { id: id, row: { read: false } }); }); });
    });
    $(document).on('click', '.bulk-delete', function () {
        if (!confirm(t("alertConfirmDelete", "Delete?"))) { return; }
        doBulk(paneTable(this), 'delete', function ($t, ids) { ids.forEach(function (id) { $t.bootstrapTable('removeByUniqueId', id); }); });
    });
    $(document).on('click', '.bulk-mark-all-read', function (e) {
        e.preventDefault();
        post("/notification/markAllRead").then(function () { location.reload(); });
    });

    // Bootstrap-table needs a re-layout when its tab becomes visible.
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $('.tab-pane.active table').bootstrapTable('resetView');
        loadAlertCells();
    });
});
</script>
    <?php

}

include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false));

?>
