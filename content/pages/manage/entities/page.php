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
					<h2><?= L::manageEntities; ?></h2>
					<div class="card mb-3">
						<div class="card-body">
							<a href="<?= $config["dir"]["root"] ?>/manage/entities/new" class="btn btn-outline-success btn-sm me-1"><span class="icon-plus"></span> New Entity</a>
						</div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="people-tab" data-bs-toggle="tab" data-bs-target="#people" role="tab" aria-controls="people" aria-selected="true"><span class="icon-type-person"></span> <?= L::personPlural; ?></a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="organisations-tab" data-bs-toggle="tab" data-bs-target="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><span class="icon-type-organisation"></span> <?= L::organisations; ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" role="tab" aria-controls="documents" aria-selected="false"><span class="icon-type-document"></span> <?= L::documents; ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="terms-tab" data-bs-toggle="tab" data-bs-target="#terms" role="tab" aria-controls="terms" aria-selected="false"><span class="icon-type-term"></span> <?= L::terms; ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="people" role="tabpanel" aria-labelledby="people-tab">
							<table id="peopleTable"></table>
                        </div>
						<div class="tab-pane bg-white fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
							<table id="organisationsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
							<table id="documentsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
							<table id="termsTable"></table>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>

<script type="text/javascript">

	$(function(){

		function renderActionButtons(type, id) {
			return '<div class="list-group list-group-horizontal"><a class="list-group-item list-group-item-action" title="<?= L::view; ?>" href="<?= $config["dir"]["root"]; ?>/person/' +id+ '" target="_blank"><span class="icon-eye"></span></a><a class="list-group-item list-group-item-action" title="<?= L::edit; ?>" href="<?= $config["dir"]["root"]; ?>/manage/entities/' +type+ '/' +id+ '"><span class="icon-pencil"></span></a></div>';
		}
		
		$('#peopleTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=person",
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
					field: "PersonLabel",
					title: "Name",
					sortable: true
				},
				{
					field: "PersonID",
					title: "ID",
					sortable: true
				},
				{
					field: "PersonGender",
					title: "Gender",
					sortable: true
				},
				{
					field: "PersonPartyOrganisationID",
					title: "Party",
					sortable: true,
					formatter: function(value, row) {

						return row["PartyLabel"]+" ("+value+")"

					}
				},
				{
					field: "PersonFactionOrganisationID",
					title: "Party",
					sortable: true,
					formatter: function(value, row) {

						return row["FactionLabel"]+" ("+value+")"

					}
				},
				{
					field: "PersonID",
					title: "Actions",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons("person", value);

					}
				}
			]
		});

		$('#organisationsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=organisation",
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
					field: "OrganisationLabel",
					title: "Name",
					sortable: true,
					formatter: function(value, row) {
						let tmpAltLabel = "";
						let tmpAltLabels = JSON.parse(row["OrganisationLabelAlternative"]);
						if (Array.isArray(tmpAltLabels)) {
							tmpAltLabel = ", "+tmpAltLabels[0];
						}
						return value + tmpAltLabel;
					}
				},
				{
					field: "OrganisationID",
					title: "ID",
					sortable: true
				},
				{
					field: "OrganisationType",
					title: "Type",
					sortable: true
				},
				{
					field: "OrganisationID",
					title: "Actions",
					sortable: true,
					formatter: function(value, row) {

						return renderActionButtons("organisation", value);

					}
				}
			]
		});

		$('#documentsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=document",
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
					field: "DocumentLabel",
					title: "Name",
					sortable: true,
					formatter: function(value, row) {
						let tmpAltLabel = "";
						let tmpAltLabels = JSON.parse(row["DocumentLabelAlternative"]);
						if (Array.isArray(tmpAltLabels)) {
							tmpAltLabel = ", "+tmpAltLabels[0];
						}
						return value + tmpAltLabel;
					}
				},
				{
					field: "DocumentID",
					title: "ID (wID)",
					sortable: true,
					formatter: function(value, row) {
						let tmpWID = "";
						if (row["DocumentWikidataID"]) {
							tmpWID = ", <a href='https://www.wikidata.org/wiki/"+row["DocumentWikidataID"]+"'>"+row["DocumentWikidataID"]+"</a>";
						}
						return value + tmpWID;
					}
				},
				{
					field: "DocumentType",
					title: "Type",
					sortable: true
				},
				{
					field: "DocumentID",
					title: "Actions",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons("document", value);

					}
				}
			]
		});

		$('#termsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=term",
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
					field: "TermLabel",
					title: "Name",
					sortable: true,
					formatter: function(value, row) {
						let tmpAltLabel = "";
						let tmpAltLabels = JSON.parse(row["TermLabelAlternative"]);
						if (Array.isArray(tmpAltLabels)) {
							tmpAltLabel = ", "+tmpAltLabels[0];
						}
						return value + tmpAltLabel;
					}
				},
				{
					field: "TermID",
					title: "ID",
					sortable: true,
					formatter: function(value, row) {
						return "<a href='https://www.wikidata.org/wiki/"+value+"'>"+value+"</a>";
					}
				},
				{
					field: "TermType",
					title: "Type",
					sortable: true
				},
				{
					field: "TermID",
					title: "Actions",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons("term", value);

					}
				}
			]
		});


	})

</script>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>