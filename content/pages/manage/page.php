<?php
include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {
    include_once(__DIR__ . '/../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/sidebar.php'); ?>
        <div class="sidebar-content">
            <h2><?= L::personalSettings; ?></h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/notifications"><?= L::notifications; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/users/<?= $_SESSION["userdata"]["id"]; ?>"><?= L::userSettings; ?></a>
                </div>
            </div>
            <hr>
            <h2><?= L::administration; ?></h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/conflicts"><?= L::manageConflicts; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/users"><?= L::manageUsers; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/import"><?= L::manageImport; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/config"><?= L::platformSettings; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/entities"><?= L::manageEntities; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/opensearch"><?= L::manageSearchIndex; ?></a>
                </div>
            </div>
            <hr>
            <h2>DEBUG</h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="<?= $config["dir"]["root"]; ?>/server/ajaxServer.php?a=getMedia&v=1&p=bt&conflicts=true" target="_self">Show Media Data (v can be hash or id, p is just required if v is an ID)</a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
}
include_once(__DIR__ . '/../../footer.php');
?>