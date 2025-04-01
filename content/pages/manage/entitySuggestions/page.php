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
					<h2><?php echo L::manageEntities; ?></h2>
					<div id="entitiesDetailsDiv" class="contentContainer" style="display: none">
						<h3>Entity Suggestion Details</h3>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable float-right mb-3"><span class="icon-cancel"></span><span class="d-none d-md-inline">Back to table</span></button>
						<table class="col-12 table-striped table-hover" id="entitiesDetails" data-toggle="table">
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
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable float-right mt-3"><span class="icon-cancel"></span><span class="d-none d-md-inline">Back to table</span></button>
					</div>
					<div id="entitiesDiv" class="contentContainer">
						<h3>Entitysuggestions</h3>
						<table class="table table-striped table-hover"
							   id="entitiesTable">
							<thead>
							<tr>
								<th scope="col" data-sortable="true">Label</th>
								<th scope="col" data-sortable="true">ID</th>
								<th scope="col" data-sortable="true">Count</th>
								<th scope="col">Action</th>
							</tr>
							</thead>
							<tbody>
							</tbody>
						</table><br><br><br>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css?v=<?= $config["version"] ?>" media="all">
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/highlight.min.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/apiResult.js?v=<?= $config["version"] ?>"></script>

<script type="application/javascript">
	$(function() {

		$('#entitiesTable').bootstrapTable({
			url: config["dir"]["root"] + "/server/ajaxServer.php?a=entitysuggestionGetTable",
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
				},
				{
					field: "EntitysuggestionExternalID",
					title: "WikidataID",
					formatter: function(value, row) {

						return '<a href="https://www.wikidata.org/wiki/'+value+'" target="_blank">'+value+' </a>';

					}
				},
				{
					field: "EntitysuggestionCount",
					title: "Affected Sessions"
				},
				{
					field: "EntitysuggestionID",
					title: "Action",
					formatter: function(value, row) {
						return "<span class='entitysuggestiondetails icon-popup btn btn-outline-secondary btn-sm' data-id='"+value+"'></span>\n" +
							"                                            <a href='"+config["dir"]["root"]+"/manage/data/entities/new?wikidataID="+row["EntitysuggestionExternalID"]+"&entitySuggestionID="+row["EntitysuggestionID"]+"' target='_blank' class='icon-plus btn btn-outline-secondary btn-sm' data-id='"+row["EntitysuggestionID"]+"'></a>"
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
						$(".contentContainer").not("#entitiesDetailsDiv").slideUp();
						$("#entitiesDetailsDiv").slideDown();
					}
				}
			})
		});

		$(".entitiesToggleDetailsAndTable").on("click", function() {

			$(".contentContainer").not("#entitiesDiv").slideUp();
			$("#entitiesDiv").slideDown();

		});


	})
</script>
<style type="text/css">
	.formItem {
		display: none;
	}
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