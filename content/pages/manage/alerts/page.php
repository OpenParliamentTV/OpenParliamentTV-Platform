<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"] ?? null, "requestPage", $pageType);

if (empty($_SESSION["login"]) || $auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"] ?? "";
    include_once (__DIR__."/../../login/page.php");

} else {

    require_once(__DIR__ . '/../../../../api/v1/modules/alert.php');
    $alertsResp = alertList([]);
    $alerts = ($alertsResp["meta"]["requestStatus"] === "success") ? $alertsResp["data"] : [];

    // Build a /search URL from criteria (same param names as the search API).
    $searchUrlFromCriteria = function ($criteria) use ($config) {
        if (empty($criteria)) { return $config["dir"]["root"] . "/search"; }
        $qs = preg_replace('/%5B\d+%5D=/i', '%5B%5D=', http_build_query($criteria));
        return $config["dir"]["root"] . "/search?" . $qs;
    };

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12 col-lg-9">
                    <h2 class="mb-4"><?= L::alertManageTitle(); ?></h2>
                    <div id="alertList">
                        <?php if (empty($alerts)): ?>
                            <div class="text-muted py-4"><?= L::alertNone(); ?></div>
                        <?php else: foreach ($alerts as $alert):
                            $a = $alert["attributes"]; ?>
                            <div class="bg-white border rounded p-3 mb-2 d-flex justify-content-between align-items-start" data-alert-id="<?= (int)$alert["id"] ?>">
                                <div class="me-2">
                                    <div class="fw-bold"><?= h($a["label"]) ?> <?php if (!$a["active"]): ?><span class="badge bg-secondary"><?= L::alertPaused() ?></span><?php endif; ?></div>
                                    <div class="small text-muted"><?= h($a["criteriaSummary"]) ?></div>
                                    <div class="small text-muted"><?= h($a["frequency"]) ?><?= $a["channelEmail"] ? " · " . h(L::alertChannelEmail()) : "" ?></div>
                                    <a class="small" href="<?= hAttr($searchUrlFromCriteria($a["criteria"])) ?>"><?= L::alertViewMatching() ?></a>
                                </div>
                                <div class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary alertEditBtn" data-alert='<?= hAttr(json_encode($alert, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE)) ?>'><?= L::edit() ?></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger alertDeleteBtn" data-alert-id="<?= (int)$alert["id"] ?>"><?= L::delete() ?></button>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    document.querySelectorAll(".alertEditBtn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            try { AlertManager.openEdit(JSON.parse(btn.getAttribute("data-alert"))); } catch (e) {}
        });
    });
    document.querySelectorAll(".alertDeleteBtn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            if (!confirm(localizedLabels.alertConfirmDelete || "Delete this alert?")) { return; }
            AlertManager.remove(btn.getAttribute("data-alert-id"));
        });
    });
    document.addEventListener("alertsChanged", function () { setTimeout(function () { location.reload(); }, 600); });
})();
</script>
<?php
    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>
