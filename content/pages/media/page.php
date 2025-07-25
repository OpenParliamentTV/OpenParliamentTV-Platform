<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth(isset($_SESSION["userdata"]["id"]) ? $_SESSION["userdata"]["id"] : null, "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {


include_once(__DIR__ . '/../../header.php'); 
require_once(__DIR__."/../../../modules/media/include.media.php");

if ($emptyResult == 1) {
    include_once(__DIR__ . '/../404/page.php');
} else {
    $flatDataArray = flattenEntityJSON($apiResult["data"][0]);
?>
    <main id="content">
        <?php include_once('content.player.php'); ?>
    </main>
    <?php include_once(__DIR__ . '/../../footer.php'); ?>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/FrameTrail.min.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/share-this.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/shareQuote.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript">
        var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;
        var currentMediaID = <?= json_encode($speech['id'], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    </script>

<?php
    }
}
?>