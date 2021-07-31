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
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<span class="icon-check" style="position: absolute;top: 50%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
						</div>
					</div>
					<div class="col">
						<div><?= $apiResult["data"]["attributes"]["parliamentLabel"] ?></div>
						<h2><?= $apiResult["data"]["attributes"]["number"] ?>. <?php echo L::electoralPeriod ?></h2>
						<div><?php 
							$formattedDateStart = date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateStart"]));
							$formattedDateEnd = ($apiResult["data"]["attributes"]["dateEnd"]) ? date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateEnd"])) : "";
							echo $formattedDateStart." – ".$formattedDateEnd; 
						?></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span> <?php echo L::relatedMedia ?></a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="sessions-tab" data-toggle="tab" href="#sessions" role="tab" aria-controls="sessions" aria-selected="false"><span class="icon-group"></span> <?php echo L::sessions ?></a>
				</li>
				<li class="nav-item ml-auto">
					<a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span> <?php echo L::data ?></a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
					<div id="speechListContainer">
						<div class="resultWrapper">
							<?php include_once('content.result.php'); ?>
						</div>
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
						<div class="entityPreview col" data-type="<?= $relationshipItem["type"] ?>"><a href="<?= $config["dir"]["root"]."/".$relationshipItem["data"]["type"]."/".$relationshipItem["data"]["id"] ?>"><?= $formattedDateStart ?><br><?php echo L::session ?> <?= $relationshipItem["data"]["attributes"]["number"] ?></a></div>
					<?php 
					} 
					?>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					<table id="dataTable" class="table table-striped table-bordered">
						<thead>
							<tr>
								<th>Key</th>
								<th>Value</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							foreach ($flatDataArray as $key => $value) {
								echo '<tr><td>'.$key.'</td><td>'.$value.'</td><tr>';
							}
							?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js"></script>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("electoralPeriodID=<?= $apiResult["data"]["id"] ?>&sort=date-asc");
		$('#dataTable').bootstrapTable({
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
			locale: 'de-DE'
		});
	});
</script>
<?php
}
?>