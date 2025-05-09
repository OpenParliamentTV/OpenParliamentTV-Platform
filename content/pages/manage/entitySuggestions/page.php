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
				<div class="col-12 mainContainer">
					<h2><?= L::manageEntitySuggestions; ?></h2>
					<div class="card mb-3">
						<div class="card-body">
							
						</div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="suggested-entities-tab" data-bs-toggle="tab" data-bs-target="#suggested-entities" role="tab" aria-controls="suggested-entities" aria-selected="true"><span class="icon-lightbulb"></span> <?= L::suggestions; ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="suggested-entities" role="tabpanel" aria-labelledby="suggested-entities-tab">
							<div id="entitiesDiv" class="contentContainer">
								<table id="entitiesTable"></table>
							</div>
                        </div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<div class="modal fade" id="entityDetailsModal" tabindex="-1" aria-labelledby="entityDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h1 class="modal-title fs-5" id="entityDetailsModalLabel">Modal title</h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="entitiesDetailsDiv" class="contentContainer" style="display: none">
					<table class="col-12 table-striped" id="entitiesDetails" data-toggle="table">
						<thead>
							<tr>
								<th data-field="0">Label</th>
								<th data-field="1">Value</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>External ID</td>
								<td id="entitiesDetailsExternalID"></td>
							</tr>
							<tr>
								<td>Label</td>
								<td id="entitiesDetailsLabel"></td>
							</tr>
							<tr>
								<td>NEL Type</td>
								<td id="entitiesDetailsType"></td>
							</tr>
							<tr>
								<td>Content</td>
								<td id="entitiesDetailsContent"></td>
							</tr>
							<tr>
								<td>Media Count</td>
								<td id="entitiesDetailsMediaCount"></td>
							</tr>
							<tr>
								<td>Was found in</td>
								<td id="entitiesDetailsContext"></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary rounded-pill">Save changes</button>
			</div>
		</div>
	</div>
</div>

<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css?v=<?= $config["version"] ?>" media="all">
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/highlight.min.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/apiResult.js?v=<?= $config["version"] ?>"></script>

<script type="application/javascript">
	$(function() {

		$('#entitiesTable').bootstrapTable({
			url: config["dir"]["root"] + "/server/ajaxServer.php?a=entitysuggestionGetTable",
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "server",
			dataField: "rows",
			totalField: "total",
			search: true,
			searchAlign: "left",
			serverSort: true,
			columns: [
				{
					field: "EntitysuggestionLabel",
					title: "Label",
					sortable: true
				},
				{
					field: "EntitysuggestionExternalID",
					title: "WikidataID",
					sortable: true,
					formatter: function(value, row) {

						return '<a href="https://www.wikidata.org/wiki/'+value+'" target="_blank">'+value+' </a>';

					}
				},
				{
					field: "EntitysuggestionCount",
					title: "Affected Sessions",
					sortable: true
				},
				{
					field: "EntitysuggestionContent",
					title: "Score",
					sortable: false,
					formatter: function(value, row) {
						
						// Completely unnecessary, just having fun ;)

						let valueJSON = JSON.parse(row["EntitysuggestionContent"]);
						let score = valueJSON["score"];
						let canvasId = 'score-chart-' + row.EntitysuggestionID;
						
						// Create canvas element
						let canvas = `<div class="position-relative" style="width: 50px; height: 50px;"><canvas id="${canvasId}"></canvas><div class="chartCenterLabel">${(score * 100).toFixed(1)}%</div></div>`;
						
						// Initialize chart after a brief delay to ensure DOM element exists
						setTimeout(() => {
							const ctx = document.getElementById(canvasId);
							new Chart(ctx, {
								type: 'doughnut',
								data: {
									datasets: [{
										data: [score, 1 - score],
										backgroundColor: [
											'rgba(46, 204, 113, 0.8)',
											'rgba(220, 220, 220, 0.8)'
										],
										borderWidth: 0
									}]
								},
								options: {
									responsive: true,
									aspectRatio: 1,
									legend: {
										display: false
									},
									tooltips: {
										enabled: false
									},
									cutoutPercentage: 80,
									animation: {
										animateRotate: true
									}
								}
							});
						}, 50);
						
						return canvas;
					}
				},
				{
					field: "EntitysuggestionID",
					title: "Action",
					sortable: false,
					formatter: function(value, row) {
						return "<div class='list-group list-group-horizontal'><span class='entitysuggestiondetails list-group-item list-group-item-action' title='<?= L::viewDetails; ?>' data-id='"+value+"' data-bs-toggle='modal' data-bs-target='#entityDetailsModal'><span class='icon-eye'></span></span><a href='"+config["dir"]["root"]+"/manage/entities/new?wikidataID="+row["EntitysuggestionExternalID"]+"&entitySuggestionID="+row["EntitysuggestionID"]+"' target='_blank' class='list-group-item list-group-item-action' data-id='"+row["EntitysuggestionID"]+"'><span class='icon-plus'></span></a></div>"
					}
				}
			]
		});


		$(".mainContainer").on("click", ".entitysuggestiondetails",function() {

			$.ajax({
				url: config["dir"]["root"] + "/server/ajaxServer.php",
				data: {"a":"entitysuggestionGet","id":$(this).data("id")},
				success: function(ret) {
					if (ret["success"] == "true") {
						let wikiIDRegex = new RegExp("Q[0-9]+");
						$("#entitiesDetailsExternalID").html((wikiIDRegex.test(ret["return"]["EntitysuggestionExternalID"]) ? '<a href="https://www.wikidata.org/wiki/'+ret["return"]["EntitysuggestionExternalID"]+'" target="_blank">'+ret["return"]["EntitysuggestionExternalID"]+'</a>' : ret["return"]["EntitysuggestionExternalID"]));
						$("#entitiesDetailsLabel").html(ret["return"]["EntitysuggestionLabel"]);
						$("#entitiesDetailsType").html(ret["return"]["EntitysuggestionType"]);
						$("#entitiesDetailsMediaCount").html(Object.keys(ret["return"]["EntitysuggestionContext"]).length);
						//$("#entitiesDetailsContent").html(ret["return"]["EntitysuggestionContent"]);
						$("#entitiesDetailsContent").empty();
						$("#entitiesDetailsContent").jsonView(ret["return"]["EntitysuggestionContent"]);

						$("#entitiesDetailsContext").html("");
						for (let item in ret["return"]["EntitysuggestionContext"]) {
							$("#entitiesDetailsContext").append('<a href="<?=$config["dir"]["root"]?>/media/'+item+'" target="_blank">'+item+'</a><br>');
						}
						$("#entitiesDetailsDiv").show();
					}
				}
			});	

		});

		const entityDetailsModal = document.getElementById('entityDetailsModal')
		entityDetailsModal.addEventListener('hidden.bs.modal', event => {
			$("#entitiesDetailsDiv").hide();
		});


	})
</script>
<style type="text/css">
	#entitiesDetailsContent {
		background: #fafafa;
		color: #986801;
		max-height: 300px;
		overflow: auto;
		margin-bottom: 20px;
	}
	#entitiesDetailsContent .b {
		color: #383a42;
	}
	#entitiesDetailsContent li > span:not(.num):not(.null):not(.q):not(.block),
	#entitiesDetailsContent .str, #entitiesDetailsContent a {
		color: #50a14f;
	}
</style>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>