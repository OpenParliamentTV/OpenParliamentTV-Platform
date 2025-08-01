<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="people" aria-selected="true"><span class="icon-cog"></span> <?= L::settings(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
							[CONTENT]
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>