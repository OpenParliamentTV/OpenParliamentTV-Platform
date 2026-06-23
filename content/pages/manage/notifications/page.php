<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"] ?? null, "requestPage", $pageType);

if (empty($_SESSION["login"]) || $auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"] ?? "";
    include_once (__DIR__."/../../login/page.php");

} else {

    require_once(__DIR__ . '/../../../../api/v1/modules/notification.php');
    $prefResp = notificationPreferences([]);
    $pref = ($prefResp["meta"]["requestStatus"] === "success") ? $prefResp["data"] : ["emailEnabled" => true];

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12 col-lg-8">
                    <h2 class="mb-4"><?= L::notificationSettingsTitle(); ?></h2>
                    <div class="bg-white border rounded p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="prefEmailEnabled" <?= !empty($pref["emailEnabled"]) ? "checked" : "" ?>>
                            <label class="form-check-label" for="prefEmailEnabled"><?= L::notificationEmailEnabled(); ?></label>
                        </div>
                        <div class="mt-3">
                            <a href="<?= $config["dir"]["root"] ?>/manage/alerts" class="btn btn-sm btn-outline-primary"><?= L::alertManageTitle(); ?></a>
                            <a href="<?= $config["dir"]["root"] ?>/notifications" class="btn btn-sm btn-outline"><?= L::notificationViewAll(); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var api = (config.dir.root || "") + "/api/v1";
    var cb = document.getElementById("prefEmailEnabled");
    if (cb) {
        cb.addEventListener("change", function () {
            var body = new URLSearchParams();
            body.append("emailEnabled", cb.checked);
            fetch(api + "/notification/preferences", { method: "POST", credentials: "same-origin", body: body })
                .then(function (r) { return r.json(); })
                .then(function () { if (window.AlertManager) { AlertManager.toast((localizedLabels.save || "Saved")); } });
        });
    }
})();
</script>
<?php
    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>
