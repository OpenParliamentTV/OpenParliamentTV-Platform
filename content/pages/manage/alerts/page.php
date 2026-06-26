<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/admin') ?>
<?php 

    require_once(__DIR__ . '/../../../../api/v1/modules/alert.php');
    $alertsResp = alertList([]);
    $alerts = ($alertsResp["meta"]["requestStatus"] === "success") ? $alertsResp["data"] : [];

    // Build a /search URL from criteria (same param names as the search API). Drop
    // the presentation-only `_labels` snapshot so it doesn't leak into the URL.
    $searchUrlFromCriteria = function ($criteria) use ($config) {
        unset($criteria["_labels"]);
        if (empty($criteria)) { return $config["dir"]["root"] . "/search"; }
        $qs = preg_replace('/%5B\d+%5D=/i', '%5B%5D=', http_build_query($criteria));
        return $config["dir"]["root"] . "/search?" . $qs;
    };

    // Flatten alerts into bootstrap-table rows (client-side data source).
    $alertRows = array_map(function ($a) use ($searchUrlFromCriteria) {
        $at = $a["attributes"];
        return [
            "id" => $a["id"],
            "criteria" => $at["criteriaSummary"],
            "frequency" => $at["frequency"],
            "channelEmail" => $at["channelEmail"],
            "channelInApp" => $at["channelInApp"],
            "active" => $at["active"],
            "created" => $at["created"],
            "searchUrl" => $searchUrlFromCriteria($at["criteria"]),
            "alert" => $a, // full object, used by the edit modal
        ];
    }, $alerts);

?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" role="tab" aria-controls="alerts" aria-selected="true"><span class="icon-bell-fill"></span> <?= L::alertManageTitle(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="alerts" role="tabpanel" aria-labelledby="alerts-tab">
                            <table id="alertsTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function () {
    var alertData = <?= json_encode($alertRows, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var labels = (typeof localizedLabels !== "undefined" && localizedLabels) ? localizedLabels : {};
    function t(k, f) { return labels[k] || f || k; }

    var freqLabels = {
        realtime: t("alertFrequencyRealtime", "Real-time"),
        daily: t("alertFrequencyDaily", "Daily digest"),
        weekly: t("alertFrequencyWeekly", "Weekly digest")
    };

    var formatters = {
        criteriaFormatter: function (value, row) {
            // Placeholder; populated with shared chips in onPostBody.
            return '<div class="alertCriteriaCell" data-id="' + row.id + '"></div>';
        },
        frequencyFormatter: function (value) {
            return '<span class="badge bg-light text-dark">' + (freqLabels[value] || value) + '</span>';
        },
        channelsFormatter: function (value, row) {
            var out = [];
            if (row.channelInApp) { out.push(t("alertChannelInApp", "In-app")); }
            if (row.channelEmail) { out.push(t("alertChannelEmail", "Email")); }
            return out.join(", ") || "&mdash;";
        },
        activeFormatter: function (value, row) {
            return '<div class="form-check form-switch">' +
                '<input class="form-check-input alert-active-switch" type="checkbox" data-alertid="' + row.id + '" ' +
                (value ? 'checked' : '') + '></div>';
        },
        dateFormatter: function (value) {
            return value ? new Date(value).toLocaleString('<?= $lang ?>') : "-";
        },
        operateFormatter: function (value, row) {
            return '<div class="list-group list-group-horizontal">' +
                '<a class="list-group-item list-group-item-action view-alert" title="' + t("alertViewMatching", "View matching speeches") + '" href="' + row.searchUrl + '"><span class="icon-search"></span></a>' +
                '<a class="list-group-item list-group-item-action edit-alert" title="' + t("edit", "Edit") + '" href="javascript:void(0)"><span class="icon-pencil"></span></a>' +
                '<a class="list-group-item list-group-item-action delete-alert" title="' + t("delete", "Delete") + '" href="javascript:void(0)"><span class="icon-trash"></span></a>' +
                '</div>';
        }
    };

    var operateEvents = {
        'click .edit-alert': function (e, value, row) { if (window.AlertManager) { AlertManager.openEdit(row.alert); } },
        'click .delete-alert': function (e, value, row) {
            if (!confirm(t("alertConfirmDelete", "Delete this alert?"))) { return; }
            if (window.AlertManager) { AlertManager.remove(row.id); }
        }
    };

    // Render each alert's criteria as shared chips after every table (re)paint.
    function renderCriteriaChips() {
        if (!window.CriteriaChips) { return; }
        alertData.forEach(function (row) {
            var criteria = row.alert && row.alert.attributes ? row.alert.attributes.criteria : null;
            CriteriaChips.render(criteria || {}, '.alertCriteriaCell[data-id="' + row.id + '"]', { editable: false });
        });
    }

    $('#alertsTable').bootstrapTable({
        data: alertData,
        classes: "table table-striped",
        locale: "<?= $lang; ?>",
        search: true,
        searchAlign: "left",
        pagination: true,
        pageSize: 25,
        pageList: [10, 25, 50, 'all'],
        escape: true,
        uniqueId: 'id',
        sortName: 'created',
        sortOrder: 'desc',
        onPostBody: renderCriteriaChips,
        columns: [
            {field: 'id', visible: false},
            {field: 'criteria', sortable: true, title: '<?= L::alertCriteria(); ?>', formatter: formatters.criteriaFormatter},
            {field: 'frequency', sortable: true, title: '<?= L::alertFrequency(); ?>', formatter: formatters.frequencyFormatter},
            {field: 'channels', title: '<?= L::alertChannels(); ?>', formatter: formatters.channelsFormatter},
            {field: 'active', sortable: true, title: '<?= L::active(); ?>', formatter: formatters.activeFormatter},
            {field: 'created', sortable: true, title: '<?= L::date(); ?>', formatter: formatters.dateFormatter},
            {field: 'operate', title: '<?= L::actions(); ?>', formatter: formatters.operateFormatter, events: operateEvents, class: 'minWidthColumn'}
        ]
    });

    // Active/paused toggle.
    $(document).on('change', '.alert-active-switch', function () {
        var id = $(this).data('alertid');
        var active = $(this).prop('checked');
        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/alert/update?id=' + id,
            method: 'POST',
            data: { active: active ? 1 : 0 }
        });
    });

    // Reload after create/edit/delete from the modal/AlertManager.
    document.addEventListener("alertsChanged", function () { setTimeout(function () { location.reload(); }, 600); });
});
</script>
