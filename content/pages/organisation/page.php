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
								<img src="<?= $apiResult["data"]["attributes"]["thumbnailURI"]?>" alt="..." style="position: absolute; top: 50%; transform: translateY(-50%) translateX(-50%);left: 50%;width: 80%;object-fit: contain;object-position: center;">
							<?php } else { ?>
								<span class="icon-bank" style="position: absolute;top: 48%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } ?>
						</div>
						<?php if ($apiResult["data"]["attributes"]["thumbnailURI"]) { ?>
						<div class="copyrightInfo"><span class="icon-info-circled"></span><span class="copyrightText"><?php echo L::source; ?>: <?php echo html_entity_decode($apiResult["data"]["attributes"]["thumbnailCreator"]); ?>, <?= $apiResult["data"]["attributes"]["thumbnailLicense"] ?></span></div>
						<?php } ?>
					</div>
					<div class="col">
						<h2><?= $apiResult["data"]["attributes"]["label"] ?></h2>
						<?php if (isset($apiResult["data"]["attributes"]["labelAlternative"][0])) { ?>
							<div class="less-opacity"><?= $apiResult["data"]["attributes"]["labelAlternative"][0] ?></div>
						<?php } ?>
						<?php if ($apiResult["data"]["attributes"]["abstract"] && $apiResult["data"]["attributes"]["abstract"] != "undefined") { ?>
							<div class="mt-2"><?= $apiResult["data"]["attributes"]["abstract"] ?></div>
							<a class="btn btn-sm mr-2 mt-2" href="<?= $apiResult["data"]["attributes"]["additionalInformation"]["wikipedia"]["url"] ?>" target="_blank">
								<span><?php echo L::moreAt; ?> Wikipedia</span><img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikipedia.svg">
							</a>
						<?php } ?>
						<!--
						<div class="mt-1"><a href="<?= $apiResult["data"]["attributes"]["websiteURI"] ?>"><?= $apiResult["data"]["attributes"]["websiteURI"] ?></a></div>
						-->
					</div>
				</div>
			</div>
			<div class="col-12 col-md-4 col-lg-3">
				<?php include_once(__DIR__ . '/../../components/entity.links.php'); ?>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<?php if ($apiResult["data"]["attributes"]["type"] == "faction" || $apiResult["data"]["attributes"]["type"] == "party") { ?>
					<li class="nav-item">
						<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?php echo L::relatedMedia ?></span></a>
					</li>
				<?php } ?>
				<?php if ($config["display"]["ner"]) { ?>
					<li class="nav-item">
						<a class="nav-link <?= (($apiResult["data"]["attributes"]["type"] == "faction" || $apiResult["data"]["attributes"]["type"] == "party") ? "" : "active") ?>" id="ner-tab" data-toggle="tab" href="#ner" role="tab" aria-controls="ner" aria-selected="true"><span class="icon-annotations"></span><span class="nav-item-label"><?php echo L::automaticallyDetectedInSpeeches ?><span class="alert ml-1 px-1 py-0 alert-warning" data-toggle="modal" data-target="#nerModal"><span class="icon-attention mr-1"></span><u>beta</u></span></span></a>
					</li>
				<?php } ?>
				<li class="nav-item ml-auto">
					<a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span><span class="nav-item-label d-none d-sm-inline"><?php echo L::data ?></span></a>
				</li>
			</ul>
			<div class="tab-content">
				<?php if ($apiResult["data"]["attributes"]["type"] == "faction" || $apiResult["data"]["attributes"]["type"] == "party") { ?>
					<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
						<div id="speechListContainer">
							<div class="resultWrapper"></div>
							<div class="loadingIndicator">
								<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
							</div>
						</div>
					</div>
				<?php } ?>
				<?php if ($config["display"]["ner"]) { ?>
					<div class="tab-pane fade <?= (($apiResult["data"]["attributes"]["type"] == "faction" || $apiResult["data"]["attributes"]["type"] == "party") ? "" : "show active") ?>" id="ner" role="tabpanel" aria-labelledby="ner-tab">
						<div id="nerListContainer">
							<div class="resultWrapper"></div>
							<div class="loadingIndicator">
								<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
							</div>
						</div>
					</div>
				<?php } ?>
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
                <h5 class="modal-title" id="nerModalTitle"><?php echo L::automaticallyDetected; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body"><?php echo L::messageAutomaticallyDetected; ?></div>
        </div>
    </div>
</div>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js?v=<?= $config["version"] ?>"></script>
<?php 
	$contextString = ($apiResult["data"]["attributes"]["type"] == "faction" || 
				$apiResult["data"]["attributes"]["type"] == "party") ? 
					"&context=main-speaker-".$apiResult["data"]["attributes"]["type"] : "proceedingsReference";
?>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("organisationID=<?= $apiResult["data"]["id"] ?><?= $contextString ?>&sort=date-desc");
		updateMediaList("organisationID=<?= $apiResult["data"]["id"] ?>&context=NER&sort=date-desc", "#nerListContainer");
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