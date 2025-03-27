<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../../header.php'); ?>

<main class="container-fluid">
    <div class="d-flex">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="flex-grow-1" style="padding-top: 30px; padding-bottom: 30px;">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail User</h2>
                    <div class="card mb-3">
                        <div class="card-body">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
    include_once(__DIR__ . '/../../../../footer.php');

}
?>