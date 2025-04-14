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
                    <h2><?= L::manageStructure; ?></h2>
                    <div class="card mb-3">
						<div class="card-body">
							
						</div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="electoralPeriods-tab" data-bs-toggle="tab" data-bs-target="#electoralPeriods" role="tab" aria-controls="electoralPeriods" aria-selected="true"><span class="icon-check me-2"></span><?= L::electoralPeriods; ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" role="tab" aria-controls="sessions" aria-selected="false"><span class="icon-group me-2"></span><?= L::sessions; ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="agendaItems-tab" data-bs-toggle="tab" data-bs-target="#agendaItems" role="tab" aria-controls="agendaItems" aria-selected="false"><span class="icon-list-numbered me-2"></span><?= L::agendaItems; ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="electoralPeriods" role="tabpanel" aria-labelledby="electoralPeriods-tab">
							[Electoral Periods]
                        </div>
                        <div class="tab-pane bg-white fade" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
							[Sessions]
                        </div>
                        <div class="tab-pane bg-white fade" id="agendaItems" role="tabpanel" aria-labelledby="agendaItems-tab">
							[Agenda Items]
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