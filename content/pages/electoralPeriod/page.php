<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {


include_once(__DIR__ . '/../../header.php');
require_once(__DIR__."/../../../modules/utilities/functions.entities.php");
$flatDataArray = flattenEntityJSON($apiResult["data"]);
?>
<main class="container-fluid subpage">
	<div class="detailsHeader">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col flex-grow-0 d-none d-sm-block detailsThumbnailContainer">
						<div class="rounded-circle">
							<span class="icon-check" style="position: absolute;top: 50%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
						</div>
					</div>
					<div class="col">
						<div><?= $apiResult["data"]["attributes"]["parliamentLabel"] ?></div>
						<h2><?= $apiResult["data"]["attributes"]["number"] ?>. <?= L::electoralPeriod() ?></h2>
						<div><?php 
							if ($apiResult["data"]["attributes"]["dateStart"]) {
								$formattedDateStart = date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateStart"]));
								$formattedDateEnd = ($apiResult["data"]["attributes"]["dateEnd"]) ? date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateEnd"])) : "";
								echo $formattedDateStart." – ".$formattedDateEnd; 
							}
						?></div>
					</div>
				</div>
			</div>
			<div class="col-12">
				<hr>
				<div class="resultTimeline" data-filter-key="electoralPeriodID" data-filter-value="<?= $apiResult["data"]["id"] ?>"></div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?= L::relatedMedia() ?></span></a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" role="tab" aria-controls="sessions" aria-selected="false"><span class="icon-group"></span><span class="nav-item-label"><?= L::sessions() ?></span></a>
				</li>
				<li class="nav-item ms-auto">
					<a class="nav-link" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span><span class="nav-item-label d-none d-sm-inline"><?= L::data() ?></span></a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
					<div id="speechListContainer">
						<div class="resultWrapper"></div>
						<div class="loadingIndicator">
							<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
					<div class="relationshipsList row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6">
					<?php 
					foreach ($apiResult["data"]["relationships"]["sessions"]["data"] as $relationshipItem) {
						$formattedDateStart = date("d.m.Y", strtotime($relationshipItem["data"]["attributes"]["dateStart"]));
					?>
						<div class="entityPreview col" data-type="<?= $relationshipItem["type"] ?>">
							<div class="entityContainer">
								<a href="<?= $config["dir"]["root"]."/".$relationshipItem["data"]["type"]."/".$relationshipItem["data"]["id"] ?>">
									<div><?= $formattedDateStart ?></div>
									<div class="entityTitle"><?= L::session() ?> <?= $relationshipItem["data"]["attributes"]["number"] ?></div>
								</a>
							</div>
						</div>
					<?php 
					} 
					?>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					<?php include_once(__DIR__ . '/../../components/entity.data.php'); ?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript">
	$(document).ready( function() {
		renderFilteredResultTimeline('.resultTimeline');
		updateMediaList("electoralPeriodID=<?= $apiResult["data"]["id"] ?>&sort=date-asc");
		$('#dataTable').bootstrapTable({
			classes: 'table-striped table-bordered',
			showToggle: false,
			multiToggleDefaults: [],
			search: true,
			searchAlign: 'left',
			buttonsAlign: 'right',
			showExport: true,
			exportDataType: 'basic',
			exportTypes: ['csv', 'excel', 'txt', 'json'],
			exportOptions: {
				htmlContent: true,
				excelstyles: ['mso-data-placement', 'color', 'background-color'],
				fileName: 'Export',
				onCellHtmlData: function(cell, rowIndex, colIndex, htmlData) {
					var cleanedString = cell.html().replace(/<br\s*[\/]?>/gi, "\r\n");
					//htmlData = cleanedString;
					return htmlData;
				}
			},
			sortName: false,
			cardView: false,
			locale: '<?= $lang; ?>'
		});
	});
</script>
<?php
}
?>