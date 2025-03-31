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
                    <h2><?php echo L::platformSettings; ?></h2>
                    <div class="card mb-3">
						<div class="card-body">
							<a class="btn btn-outline-success btn-sm me-1"><span class="icon-plus"></span> Test Animated Button</a>
						</div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="people" aria-selected="true"><span class="icon-cog"></span> <?php echo L::settings; ?></a>
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