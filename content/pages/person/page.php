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
			<div class="col-12 col-md-8 col-lg-9">
				<div class="row align-items-center">
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<?php if ($apiResult["data"]["attributes"]["thumbnailURI"]) { ?>
								<img src="<?= hAttr($apiResult["data"]["attributes"]["thumbnailURI"]) ?>" alt="..." style="position: absolute;">
							<?php } else { ?>
								<span class="icon-type-person" style="position: absolute;top: 48%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } ?>
						</div>
						<?php if ($apiResult["data"]["attributes"]["thumbnailURI"]) { ?>
						<div class="copyrightInfo"><span class="icon-info-circled"></span><span class="copyrightText"><?= L::source(); ?>: <?= safeHtml($apiResult["data"]["attributes"]["thumbnailCreator"]); ?>, <?= safeHtml($apiResult["data"]["attributes"]["thumbnailLicense"]) ?></span></div>
						<?php } ?>
					</div>
					<div class="col">
						<?php 
						if (isset($apiResult["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<h2><?= h($apiResult["data"]["attributes"]["label"]) ?><a href="../organisation/<?= hAttr($apiResult["data"]["relationships"]["faction"]["data"]["id"]) ?>" class="partyIndicator ms-2" data-faction="<?= hAttr($apiResult["data"]["relationships"]["faction"]["data"]["id"]) ?>" style="font-size: 14px;"><?= h($apiResult["data"]["relationships"]["faction"]["data"]["attributes"]["label"]) ?></a></h2>
							
						<?php 
						} else {
						?>
							<h2><?= h($apiResult["data"]["attributes"]["label"]) ?></h2>
						<?php 
						}
						if ($apiResult["data"]["attributes"]["abstract"] && $apiResult["data"]["attributes"]["abstract"] != "undefined") { ?>
							<div class="mt-2"><?= h($apiResult["data"]["attributes"]["abstract"]) ?></div>
							<a class="btn btn-sm me-2 mt-2" href="<?= hAttr($apiResult["data"]["attributes"]["additionalInformation"]["wikipedia"]["url"]) ?>" target="_blank">
								<span><?= L::moreAt(); ?> Wikipedia</span><img class="ms-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikipedia.svg">
							</a>
						<?php } ?>
						<!--
						<div class="mt-1"><a target="_blank" href="<?= $apiResult["data"]["attributes"]["websiteURI"] ?>"><?= $apiResult["data"]["attributes"]["websiteURI"] ?></a></div>
						-->
					</div>
				</div>
			</div>
			<div class="col-12 col-md-4 col-lg-3">
				<?php include_once(__DIR__ . '/../../components/entity.links.php'); ?>
			</div>
			<div class="col-12">
				<hr>
				<div class="resultTimeline" data-filter-key="personID" data-filter-value="<?= hAttr($apiResult["data"]["id"]) ?>"></div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?= L::speeches() ?></span></a>
				</li>
				<?php if ($apiResult["data"]["attributes"]["type"] == "memberOfParliament") { ?>
					<li class="nav-item">
						<a class="nav-link" id="interventions-tab" data-bs-toggle="tab" data-bs-target="#interventions" role="tab" aria-controls="interventions" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?= L::interjections() ?></span></a>
					</li>
				<?php } ?>
				<?php if ($config["display"]["ner"]) { ?>
					<li class="nav-item">
						<a class="nav-link" id="ner-tab" data-bs-toggle="tab" data-bs-target="#ner" role="tab" aria-controls="ner" aria-selected="true"><span class="icon-annotations"></span><span class="nav-item-label"><?= L::automaticallyDetectedInSpeeches() ?><span class="alert ms-1 px-1 py-0 alert-warning" data-bs-toggle="modal" data-bs-target="#nerModal"><span class="icon-attention me-1"></span><u>beta</u></span></span></a>
					</li>
				<?php } ?>
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
				<div class="tab-pane fade" id="interventions" role="tabpanel" aria-labelledby="interventions-tab">
					<div id="interventionListContainer">
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
<div class="modal fade" id="nerModal" tabindex="-1" role="dialog" aria-labelledby="nerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nerModalTitle"><?= L::automaticallyDetected(); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><?= L::messageAutomaticallyDetected(); ?></div>
        </div>
    </div>
</div>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/mediaResults.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript">
	$(document).ready( function() {
		renderFilteredResultTimeline('.resultTimeline');
		updateMediaList(<?= json_encode("personID=" . $apiResult["data"]["id"] . "&context=main-speaker&sort=date-desc", JSON_HEX_QUOT | JSON_HEX_APOS) ?>);
		updateMediaList(<?= json_encode("personID=" . $apiResult["data"]["id"] . "&context=speaker", JSON_HEX_QUOT | JSON_HEX_APOS) ?>, "#interventionListContainer");
		updateMediaList(<?= json_encode("personID=" . $apiResult["data"]["id"] . "&context=NER&sort=date-desc", JSON_HEX_QUOT | JSON_HEX_APOS) ?>, "#nerListContainer");
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
			locale: <?= json_encode($lang, JSON_HEX_QUOT | JSON_HEX_APOS) ?>
		});
	});
</script>
<?php
}
?>