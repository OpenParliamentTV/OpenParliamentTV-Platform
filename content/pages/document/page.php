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
					<div class="col d-none d-md-block flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<span class="icon-doc-text" style="position: absolute;top: 50%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
						</div>
					</div>
					<div class="col">
						<?php 
						if (isset($apiResult["data"]["attributes"]["additionalInformation"]["subType"])) {
						?> 
						<div><?= $apiResult["data"]["attributes"]["additionalInformation"]["subType"] ?> <?php echo L::by ?>: <?=$apiResult["data"]["attributes"]["additionalInformation"]["creator"][0] ?></div>
						<?php
						}
						?>
						<h2><?= $apiResult["data"]["attributes"]["label"] ?></h2>
						<div><?= $apiResult["data"]["attributes"]["labelAlternative"][0] ?></div>
						<div><?= $apiResult["data"]["attributes"]["abstract"] ?></div>
						<div class="mt-1"><?php echo L::source ?>: <a target="_blank" href="<?= $apiResult["data"]["attributes"]["sourceURI"] ?>"><?= $apiResult["data"]["attributes"]["sourceURI"] ?></a></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?php echo L::relatedMedia ?></span></a>
				</li>
				<?php 
				if ($config["display"]["ner"]) {
				?>
				<li class="nav-item">
					<a class="nav-link" id="ner-tab" data-toggle="tab" href="#ner" role="tab" aria-controls="ner" aria-selected="true"><span class="icon-annotations"></span><span class="nav-item-label"><?php echo L::automaticallyDetectedInSpeeches ?> (beta)</span></a>
				</li>
				<?php 
				}
				?>
				<li class="nav-item ml-auto">
					<a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span><span class="nav-item-label d-none d-sm-inline"><?php echo L::data ?></span></a>
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
				<?php 
				if ($config["display"]["ner"]) {
				?>
				<div class="tab-pane fade" id="ner" role="tabpanel" aria-labelledby="ner-tab">
					<div id="nerListContainer">
						<div class="resultWrapper"></div>
						<div class="loadingIndicator">
							<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
						</div>
					</div>
				</div>
				<?php 
				}
				?>
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					<?php include_once(__DIR__ . '/../../components/entity.data.php'); ?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("documentID=<?= $apiResult["data"]["id"] ?>&context=proceedingsReference&sort=date-desc");
		updateMediaList("documentID=<?= $apiResult["data"]["id"] ?>&context=NER&sort=date-desc", "#nerListContainer");
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