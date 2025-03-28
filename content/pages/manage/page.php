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
            <h2><?php echo L::personalSettings; ?></h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/notifications"><?php echo L::notifications; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/users/MYUSERID">My User Settings</a>
                </div>
            </div>
            <hr>
            <h2>Administration</h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/conflicts"><?php echo L::manageConflicts; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/data"><?php echo L::manageData; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/users"><?php echo L::manageUsers; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/import"><?php echo L::data; ?>-Import</a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/config"><?php echo L::platformSettings; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/entities"><?php echo L::manageEntities; ?></a>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="./manage/opensearch">Search Index</a>
                </div>
            </div>
            <hr>
            <h2>DEBUG</h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <a class="d-block p-4 bg-white text-center" href="<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMedia&v=1&p=bt&conflicts=true" target="_self">Show Media Data (v can be hash or id, p is just required if v is an ID)</a>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
}
include_once(__DIR__ . '/../../footer.php');
?>