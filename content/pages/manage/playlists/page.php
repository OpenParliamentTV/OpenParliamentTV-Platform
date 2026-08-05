<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/admin') ?>
<?php

    require_once(__DIR__ . '/../../../../api/v1/modules/playlist.php');
    $playlistsResp = playlistList([]);
    $playlists = ($playlistsResp["meta"]["requestStatus"] === "success") ? $playlistsResp["data"] : [];

    // Flatten into bootstrap-table rows (client-side data source, as in alerts).
    $playlistRows = array_map(function ($p) use ($config) {
        $at = $p["attributes"];
        return [
            "id" => $p["id"],
            "title" => $at["title"],
            "playlistType" => $at["playlistType"],
            "itemCount" => $at["itemCount"] ?? 0,
            "shared" => $at["shared"],
            "lastChanged" => $at["lastChanged"],
            "url" => $config["dir"]["root"] . "/playlist/" . $at["token"],
        ];
    }, $playlists);

?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="playlists-tab" data-bs-toggle="tab" data-bs-target="#playlists" role="tab" aria-controls="playlists" aria-selected="true"><span class="icon-list-numbered"></span> <?= L::playlistManageTitle(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="playlists" role="tabpanel" aria-labelledby="playlists-tab">
                            <table id="playlistsTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function () {
    var playlistData = <?= json_encode($playlistRows, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
    function t(k, f) { return labels[k] || f || k; }

    var formatters = {
        titleFormatter: function (value, row) {
            return '<a href="' + row.url + '">' + value + '</a>';
        },
        sharedFormatter: function (value) {
            return value
                ? '<span class="icon-link"></span> ' + t("playlistShare", "Share via link")
                : '&mdash;';
        },
        dateFormatter: function (value) {
            return value ? new Date(value).toLocaleString('<?= $lang ?>') : "-";
        },
        operateFormatter: function (value, row) {
            return '<div class="list-group list-group-horizontal">' +
                '<a class="list-group-item list-group-item-action" title="' + t("playlistPlayAll", "Play all") + '" href="' + row.url + '"><span class="icon-play-1"></span></a>' +
                '<a class="list-group-item list-group-item-action delete-playlist" title="' + t("delete", "Delete") + '" href="javascript:void(0)"><span class="icon-trash"></span></a>' +
                '</div>';
        }
    };

    var operateEvents = {
        'click .delete-playlist': function (e, value, row) {
            if (!confirm(t("playlistConfirmDelete", "Delete this playlist?"))) { return; }
            var body = new URLSearchParams();
            body.append("id", row.id);
            fetch('<?= $config["dir"]["root"] ?>/api/v1/playlist/delete', {
                method: "POST", credentials: "same-origin",
                headers: { "Accept": "application/json" }, body: body
            }).then(function () { window.location.reload(); });
        }
    };

    $('#playlistsTable').bootstrapTable({
        data: playlistData,
        classes: "table table-striped",
        locale: "<?= $lang; ?>",
        search: true,
        searchAlign: "left",
        pagination: true,
        pageSize: 25,
        pageList: [10, 25, 50, 'all'],
        escape: true,
        uniqueId: 'id',
        sortName: 'lastChanged',
        sortOrder: 'desc',
        columns: [
            { field: 'id', visible: false },
            { field: 'title', sortable: true, title: '<?= L::title(); ?>', formatter: formatters.titleFormatter, escape: false },
            { field: 'itemCount', sortable: true, title: '<?= L::speeches(); ?>' },
            { field: 'shared', sortable: true, title: '<?= L::share(); ?>', formatter: formatters.sharedFormatter, escape: false },
            { field: 'lastChanged', sortable: true, title: '<?= L::changeDate(); ?>', formatter: formatters.dateFormatter },
            { field: 'operate', title: '<?= L::actions(); ?>', formatter: formatters.operateFormatter, events: operateEvents, escape: false }
        ]
    });
});
</script>
