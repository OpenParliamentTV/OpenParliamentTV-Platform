<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../../header.php');
    include_once(__DIR__ . '/../../../../../api/v1/api.php');

?>
<main class="container-fluid subpage">
    <div class="d-flex">
        <div class="sidebarContainer">
            <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        </div>
        <div class="col flex-grow-1 mt-1">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail Media</h2>

                    <?php
                    if ($_REQUEST["id"] == "noText") {


                        $items = apiV1(array("action"=>"mediaIrregularity","itemType"=>"noText","parliament"=>"DE"));
                        echo '<h2>Media no text (proceeding) - ('.count($items["items"]).' items)</h2>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Media ID</th>
                                            <th>Aligned</th>
                                            <th>Public</th>
                                            <th>Last Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        foreach ($items["items"] as $item) {
                            echo '
                            <tr>
                                <td><a href="./'.$item["MediaID"].'" target="_self">'.$item["MediaID"].'</a></td>
                                <td>'.$item["MediaAligned"].'</td>
                                <td>'.$item["MediaPublic"].'</td>
                                <td>'.$item["MediaLastChanged"].'</td>
                            </tr>';
                        }

                        echo '</tbody>
                                </table>';


                    } elseif ($_REQUEST["id"] == "moreTexts") {

                        $items = apiV1(array("action"=>"mediaIrregularity","itemType"=>"moreTexts","parliament"=>"DE"));
                        echo '<h2>Media with more than one text (proceeding) - ('.count($items["items"]).' items)</h2>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Media ID</th>
                                            <th>Texts</th>
                                            <th>Aligned</th>
                                            <th>Public</th>
                                            <th>Last Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        foreach ($items["items"] as $item) {
                            echo '
                            <tr>
                                <td><a href="./'.$item["MediaID"].'" target="_self">'.$item["MediaID"].'</a></td>
                                <td>'.$item["TextEntries"].'</td>
                                <td>'.$item["MediaAligned"].'</td>
                                <td>'.$item["MediaPublic"].'</td>
                                <td>'.$item["MediaLastChanged"].'</td>
                            </tr>';
                        }
                        
                                echo '</tbody>
                                </table>';


                    } elseif ($_REQUEST["id"] == "notPublic") {
                        $items = apiV1(array("action"=>"mediaIrregularity","itemType"=>"notPublic","parliament"=>"DE"));
                        echo '<h2>Media with more than one text (proceeding) - ('.count($items["items"]).' items)</h2>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Media ID</th>
                                            <th>Aligned</th>
                                            <th>Public</th>
                                            <th>Last Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        foreach ($items["items"] as $item) {
                            echo '
                            <tr>
                                <td><a href="./'.$item["MediaID"].'" target="_self">'.$item["MediaID"].'</a></td>
                                <td>'.$item["MediaAligned"].'</td>
                                <td>'.$item["MediaPublic"].'</td>
                                <td>'.$item["MediaLastChanged"].'</td>
                            </tr>';
                        }

                        echo '</tbody>
                                </table>';
                    } elseif ($_REQUEST["id"] == "notAligned") {
                        $items = apiV1(array("action"=>"mediaIrregularity","itemType"=>"notAligned","parliament"=>"DE"));
                        echo '<h2>Media with more than one text (proceeding) - ('.count($items["items"]).' items)</h2>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Media ID</th>
                                            <th>Aligned</th>
                                            <th>Public</th>
                                            <th>Last Change</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        foreach ($items["items"] as $item) {
                            echo '
                            <tr>
                                <td><a href="./'.$item["MediaID"].'" target="_self">'.$item["MediaID"].'</a></td>
                                <td>'.$item["MediaAligned"].'</td>
                                <td>'.$item["MediaPublic"].'</td>
                                <td>'.$item["MediaLastChanged"].'</td>
                            </tr>';
                        }

                        echo '</tbody>
                                </table>';
                    } elseif (preg_match("~[a-zA-Z]*-[0-9]{10}~", $_REQUEST["id"])){

                        $item = apiV1(array("action"=>"getItem", "itemType"=>"media", "id"=>$_REQUEST["id"]));

                        ?>
                         <div id="accordion">
                            <div class="card">
                                <div class="card-header" id="headingOne">
                                    <h5 class="mb-0">
                                        <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                            Show Item JSON
                                        </button>
                                    </h5>
                                </div>

                                <div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#accordion">
                                    <div class="card-body" id='json-view'>
                                    </div>
                                </div>
                            </div>
                         </div>

                        <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css?v=<?= $config["version"] ?>" media="all">
                        <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/atom-one-light.min.css?v=<?= $config["version"] ?>" media="all">
                        <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js?v=<?= $config["version"] ?>"></script>
                        <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/highlight.min.js?v=<?= $config["version"] ?>"></script>
                        <script>$(function() {
                                $("#json-view").jsonView(<?=json_encode($item)?>);
                            })</script>
                        <?php

                    } elseif ($_REQUEST["id"] == "irregularities") {

                    ?>
                    <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                        <a class="d-block p-4 bg-white text-center" href="../media/noText">Media without Text</a>
                    </div>
                    <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                        <a class="d-block p-4 bg-white text-center" href="../media/moreTexts">Media with > 1 Text</a>
                    </div>
                    <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                        <a class="d-block p-4 bg-white text-center" href="../media/notPublic">Not Public</a>
                    </div>
                    <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                        <a class="d-block p-4 bg-white text-center" href="../media/notAligned">Not Aligned</a>
                    </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>