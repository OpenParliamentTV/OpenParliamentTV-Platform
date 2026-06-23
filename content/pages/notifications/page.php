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

    include_once(__DIR__ . '/../../header.php');
?>
    <main class="container subpage">
        <div class="row" style="position: relative; z-index: 1">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h2><?= L::notificationInboxTitle(); ?></h2>
                <a href="#" id="inboxMarkAllRead" class="btn btn-sm btn-outline-secondary"><?= L::notificationMarkAllRead(); ?></a>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-lg-9">
                <div id="inboxList" class="list-group">
                    <?php if (empty($items)): ?>
                        <div class="text-muted py-4"><?= L::notificationNone(); ?></div>
                    <?php else: foreach ($items as $n): $a = $n["attributes"]; ?>
                        <a class="list-group-item list-group-item-action <?= empty($a["read"]) ? "fw-semibold" : "" ?>"
                           href="<?= hAttr($a["link"] ?: "#") ?>"
                           data-id="<?= (int)$n["id"] ?>">
                            <div class="d-flex justify-content-between">
                                <span><?= h($a["title"]) ?></span>
                                <small class="text-muted"><?= h(substr((string)$a["created"], 0, 16)) ?></small>
                            </div>
                            <?php if (!empty($a["body"])): ?><div class="small text-muted"><?= h($a["body"]) ?></div><?php endif; ?>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </main>
<script>
(function () {
    var api = (config.dir.root || "") + "/api/v1";
    function post(path) { return fetch(api + path, { method: "POST", credentials: "same-origin" }).then(function (r) { return r.json(); }); }
    document.querySelectorAll("#inboxList .list-group-item").forEach(function (el) {
        el.addEventListener("click", function () {
            var id = el.getAttribute("data-id");
            if (id) { post("/notification/markRead?id=" + encodeURIComponent(id)); }
        });
    });
    var markAll = document.getElementById("inboxMarkAllRead");
    if (markAll) {
        markAll.addEventListener("click", function (e) {
            e.preventDefault();
            post("/notification/markAllRead").then(function () { location.reload(); });
        });
    }
})();
</script>
    <?php

}

include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false));

?>
