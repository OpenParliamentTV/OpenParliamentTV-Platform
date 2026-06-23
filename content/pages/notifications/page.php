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
        ];
    }, $items);

    include_once(__DIR__ . '/../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row" style="position: relative; z-index: 1">
        <div class="col-12 d-flex justify-content-between align-items-center mb-2">
            <h2 class="m-0"><?= L::notifications(); ?></h2>
            <a href="#" id="inboxMarkAllRead" class="btn btn-sm btn-outline"><?= L::notificationMarkAllRead(); ?></a>
        </div>
        <div class="col-12">
            <div class="bg-white">
                <table id="notificationsTable"></table>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function () {
    var notificationData = <?= json_encode($notificationRows, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var labels = window.localizedLabels || {};
    var api = (config.dir.root || "") + "/api/v1";
    function t(k, f) { return labels[k] || f || k; }

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

    var formatters = {
        titleFormatter: function (value, row) {
            var href = safeHref(row.link);
            var title = escapeHtml(row.title);
            return href
                ? '<a class="notif-link" href="' + escapeHtml(href) + '" data-id="' + escapeHtml(row.id) + '">' + title + '</a>'
                : title;
        },
        dateFormatter: function (value) {
            return value ? new Date(value).toLocaleString('<?= $lang ?>') : "-";
        },
        operateFormatter: function (value, row) {
            if (row.read) { return ''; }
            return '<a class="list-group-item list-group-item-action mark-read" title="' + t("markRead", "Mark as read") + '" href="javascript:void(0)"><span class="icon-ok"></span></a>';
        }
    };

    var operateEvents = {
        'click .mark-read': function (e, value, row) {
            post("/notification/markRead?id=" + encodeURIComponent(row.id)).then(function () {
                $('#notificationsTable').bootstrapTable('updateByUniqueId', { id: row.id, row: { read: true } });
            });
        }
    };
    var linkEvents = {
        'click .notif-link': function (e, value, row) {
            // Mark read in the background; navigation proceeds via the link.
            post("/notification/markRead?id=" + encodeURIComponent(row.id));
        }
    };

    $('#notificationsTable').bootstrapTable({
        data: notificationData,
        classes: "table table-striped",
        locale: "<?= $lang; ?>",
        search: true,
        searchAlign: "left",
        pagination: true,
        pageSize: 25,
        pageList: [10, 25, 50, 100, 'all'],
        escape: true,
        uniqueId: 'id',
        sortName: 'created',
        sortOrder: 'desc',
        rowStyle: function (row) { return row.read ? {} : { classes: 'fw-semibold' }; },
        columns: [
            {field: 'id', visible: false},
            {field: 'title', sortable: true, title: '<?= L::title(); ?>', formatter: formatters.titleFormatter, events: linkEvents},
            {field: 'body', sortable: true, title: '<?= L::notificationMessage(); ?>'},
            {field: 'created', sortable: true, title: '<?= L::date(); ?>', formatter: formatters.dateFormatter},
            {field: 'operate', title: '<?= L::actions(); ?>', formatter: formatters.operateFormatter, events: operateEvents, class: 'minWidthColumn'}
        ]
    });

    $('#inboxMarkAllRead').on('click', function (e) {
        e.preventDefault();
        post("/notification/markAllRead").then(function () { location.reload(); });
    });
});
</script>
    <?php

}

include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false));

?>
