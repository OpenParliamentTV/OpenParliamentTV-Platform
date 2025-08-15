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
							<span class="icon-list-numbered" style="position: absolute;top: 50%;left: 50%;font-size: 50px;transform: translateX(-50%) translateY(-50%);"></span>
						</div>
					</div>
					<div class="col">
						<div><?= h($apiResult["data"]["attributes"]["parliamentLabel"]) ?></div>
						<div><a href="../electoralPeriod/<?= hAttr($apiResult["data"]["relationships"]["electoralPeriod"]["data"]["id"]) ?>"><?= h($apiResult["data"]["relationships"]["electoralPeriod"]["data"]["attributes"]["number"]) ?>. <?= L::electoralPeriod() ?></a> | <a href="../session/<?= hAttr($apiResult["data"]["relationships"]["session"]["data"]["id"]) ?>"><?= L::session() ?>: <?= h($apiResult["data"]["relationships"]["session"]["data"]["attributes"]["number"]) ?></a></div>
						<div class="mt-2"><?= h($apiResult["data"]["attributes"]["officialTitle"]) ?></div>
						<h2><?= h($apiResult["data"]["attributes"]["title"]) ?></h2>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs modern-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-bs-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label"><?= L::relatedMedia() ?></span></a>
				</li>
				<li class="nav-item ms-auto">
					<a class="nav-link" id="data-tab" data-bs-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span><span class="nav-item-label d-none d-sm-inline"><?= L::data() ?></span></a>
				</li>
			</ul>
			<div class="tab-content modern-tabs">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
					<div id="speechListContainer">
						<div class="resultWrapper"></div>
						<div class="loadingIndicator">
							<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					<div class="bg-white border p-3">
						<?php include_once(__DIR__ . '/../../components/entity.data.php'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/mediaResults.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript">
	$(document).ready( function() {
		<?php $actualAgendaItemIDParts = explode("-", $apiResult["data"]["id"]); ?>
		updateMediaList(<?= json_encode("agendaItemID=" . $actualAgendaItemIDParts[1] . "&sort=date-asc", JSON_HEX_QUOT | JSON_HEX_APOS) ?>);
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