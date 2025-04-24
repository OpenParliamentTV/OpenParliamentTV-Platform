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
							<div id="peopleToolbar">
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="person"><span class="icon-ccw"></span> Re-sync external data for all people</button>
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="memberOfParliament"><span class="icon-ccw"></span> Re-sync external data for all members of parliament</button>
							</div>
							<table id="peopleTable"></table>
                        </div>
						<div class="tab-pane bg-white fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
							<div id="organisationsToolbar">
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="organisation"><span class="icon-ccw"></span> Re-sync external data for all organisations</button>
							</div>
							<table id="organisationsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
							<div id="documentsToolbar">
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="legalDocument"><span class="icon-ccw"></span> Re-sync external data for all legal documents</button>
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="officialDocument"><span class="icon-ccw"></span> Re-sync external data for all official documents</button>
							</div>
							<table id="documentsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
							<div id="termsToolbar">
								<button type="button" class="btn btn-outline-success btn-sm ms-1 mb-1 additionalDataServiceButton" data-type="term"><span class="icon-ccw"></span> Re-sync external data for all terms</button>
							</div>
							<table id="termsTable"></table>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>

<div class="modal fade" id="successRunAdditionalDataService" tabindex="-1" role="dialog" aria-labelledby="successRunCronDialogLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successRunCronDialogLabel">Run ADS for <span class="adc-type"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                The additionalDataService for type <span class="adc-type"></span> should run now in background.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

	$(function(){

		$("main").on("click",".additionalDataServiceButton", function() {
            $.ajax({
                url:"<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
                dataType:"json",
                data:{"a":"runAdditionalDataService", "type": $(this).data("type")},
                tmpType: $(this).data("type"),
                method:"post",
                success: function(ret) {
                    //TODO: Check for success return parameter
                    $(".adc-type").html(this.tmpType);
                    $('#successRunAdditionalDataService').modal('show');
                }
            })
        });
		
		function renderActionButtons(id, type, subtype) {
			const viewButton = '<a class="list-group-item list-group-item-action" ' +
				'title="<?= L::view; ?>" ' +
				'href="<?= $config["dir"]["root"]; ?>/' + type + '/' + id + '" ' +
				'target="_blank">' +
				'<span class="icon-eye"></span>' +
				'</a>';
			
			const editButton = '<a class="list-group-item list-group-item-action" ' +
				'title="<?= L::edit; ?>" ' +
				'href="<?= $config["dir"]["root"]; ?>/manage/entities/' + type + '/' + id + '">' +
				'<span class="icon-pencil"></span>' +
				'</a>';
			
			const apiButton = '<a class="list-group-item list-group-item-action" ' +
				'title="API" ' +
				'href="<?= $config["dir"]["root"]; ?>/api/v1/' + type + '/' + id + '" ' +
				'target="_blank">' +
				'<span class="icon-code"></span>' +
				'</a>';
			
			const adsButton = '<a class="list-group-item list-group-item-action ads-button" ' +
				'title="Update from ADS" ' +
				'data-type="' + type + '" ' +
				'data-id="' + id + '" ' +
				'data-subtype="' + subtype + '">' +
				'<span class="icon-ccw"></span>' +
				'</a>';
			
			// Combine all buttons in a horizontal list group
			return '<div class="list-group list-group-horizontal">' +
				viewButton +
				editButton +
				apiButton +
				adsButton +
				'</div>';
		}
		
		// Add click handler for ADS buttons
		$(document).on('click', '.ads-button', function() {
			// Store reference to the clicked button
			const $button = $(this);
			
			$button.removeClass("list-group-item-success");
			$button.removeClass("list-group-item-danger");
			$button.addClass("working");
			
			// Get entity information from button data attributes
			const entityType = $button.data('type');
			const entityId = $button.data('id');
			const entitySubtype = $button.data('subtype');
			
			// Determine the correct type for the ADS service
			// Some subtypes need to be used directly instead of the main type
			let adsType = entityType;
			if (entitySubtype === "memberOfParliament" || 
				entitySubtype === "person" || 
				entitySubtype === "legalDocument" || 
				entitySubtype === "officialDocument") {
				adsType = entitySubtype;
			}

			// Make AJAX call to update entity data
			$.ajax({
				url: "<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
				dataType: "json",
				data: {
					"a": "runAdditionalDataServiceForSpecificEntities", 
					"type": [adsType],
					"ids": [entityId],
					"language": "de"
				},
				method: "post",
				success: function(response) {
					$button.removeClass("working");
					
					// Check if the response indicates success
					if (response.success === "true") {
						$button.addClass("list-group-item-success");
						console.log("ADS update successful: ", response);
					} else {
						$button.addClass("list-group-item-danger");
						console.error("ADS update failed:", response);
					}
				},
				error: function(xhr, status, error) {
					$button.removeClass("working");
					$button.addClass("list-group-item-danger");
					console.error("AJAX error:", status, error);
				}
			});
		});
		
		$('#peopleTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=person",
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "server",
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#peopleToolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "PersonLastChanged",
			sortOrder: "desc",
			queryParams: function(params) {
				// Add search parameter to the query
				if (params.search) {
					params.search = params.search;
				}
				return params;
			},
			columns: [
				{
					field: "PersonThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-person",
					formatter: function(value, row) {
						return '<div class="thumbnailContainer"><div class="rounded-circle">' + 
							(value ? 
								'<img src="' + value + '" alt="..."/>' : 
								'<span class="icon-type-person" style="position: absolute;top: 48%;left: 50%;font-size: 20px;transform: translateX(-50%) translateY(-50%);"></span>'
							) + 
							'</div></div>';
					}
				},
				{
					field: "PersonLabel",
					title: "<?= L::name; ?>",
					sortable: true,
					formatter: function(value, row, index) {
						let tmpAltLabels = [];
						let searchText = value;
						if (row["PersonLabelAlternative"]) {
							try {
								let parsedAltLabels = JSON.parse(row["PersonLabelAlternative"]);
								if (Array.isArray(parsedAltLabels) && parsedAltLabels.length > 0) {
									tmpAltLabels = parsedAltLabels;
									searchText += ' ' + parsedAltLabels.join(' ');
								}
							} catch (e) {
								// If JSON parsing fails, just use the main label
							}
						}
						
						// Get the current search term
						let searchTerm = $('#peopleTable').bootstrapTable('getOptions').searchText;
						let displayText = value;
						
						// Only highlight if the search term matches the label or alternative labels
						if (searchTerm && (value.toLowerCase().includes(searchTerm.toLowerCase()) || 
							tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase())))) {
							// Create a case-insensitive regex
							let regex = new RegExp(searchTerm, 'gi');
							// Replace matches with highlighted version, but only in the text content
							displayText = displayText.replace(regex, match => `<em>${match}</em>`);
						}
						
						if (tmpAltLabels.length > 0) {
							let altLabelsText = tmpAltLabels.join(', ');
							if (searchTerm && tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase()))) {
								// Create a case-insensitive regex
								let regex = new RegExp(searchTerm, 'gi');
								// Replace matches with highlighted version, but only in the text content
								altLabelsText = altLabelsText.replace(regex, match => `<em>${match}</em>`);
							}
							displayText += '<br><span class="less-opacity">' + altLabelsText + '</span>';
						}
						
						// Add ID to searchable text but don't include it in the display
						searchText += ' ' + row["PersonID"];
						
						return '<span data-search="' + searchText + '">' + displayText + '</span>';
					}
				},
				{
					field: "PersonID",
					title: "Wikidata ID",
					sortable: true,
					formatter: function(value, row) {
						return "<a href='https://www.wikidata.org/wiki/"+value+"' target='_blank'>"+value+"</a>";
					}
				},
				{
					field: "PersonPartyOrganisationID",
					title: "<?= L::party; ?>",
					sortable: true,
					formatter: function(value, row) {
						if (!value || !row["PartyLabel"]) {
							return "-";
						}
						return "<span class='partyIndicator partyIndicatorInline' data-party='"+row["PartyLabel"]+"'>"+row["PartyLabel"]+"</span>";
					}
				},
				{
					field: "PersonFactionOrganisationID",
					title: "<?= L::faction; ?>",
					sortable: true,
					formatter: function(value, row) {
						if (!value || !row["FactionLabel"]) {
							return "-";
						}
						return "<span class='partyIndicator partyIndicatorInline' data-faction='"+value+"'>"+row["FactionLabel"]+"</span>";
					}
				},
				{
					field: "PersonLastChanged",
					title: "<?= L::lastChanged; ?>",
					sortable: true,
					formatter: function(value) {
						if (value) {
							return new Date(value).toLocaleString('de');
						}
						return "-";
					}
				},
				{
					field: "PersonID",
					title: "<?= L::actions; ?>",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "person", row["PersonType"]);

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
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#organisationsToolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "OrganisationLastChanged",
			sortOrder: "desc",
			columns: [
				{
					field: "OrganisationThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-organisation",
					formatter: function(value, row) {
						return '<div class="thumbnailContainer"><div class="rounded-circle">' + 
							(value ? 
								'<img src="' + value + '" alt="..."/>' : 
								'<span class="icon-type-organisation" style="position: absolute;top: 48%;left: 50%;font-size: 20px;transform: translateX(-50%) translateY(-50%);"></span>'
							) + 
							'</div></div>';
					}
				},
				{
					field: "OrganisationLabel",
					title: "<?= L::name; ?>",
					sortable: true,
					formatter: function(value, row, index) {
						let tmpAltLabels = [];
						let searchText = value;
						if (row["OrganisationLabelAlternative"]) {
							try {
								let parsedAltLabels = JSON.parse(row["OrganisationLabelAlternative"]);
								if (Array.isArray(parsedAltLabels) && parsedAltLabels.length > 0) {
									tmpAltLabels = parsedAltLabels;
									searchText += ' ' + parsedAltLabels.join(' ');
								}
							} catch (e) {
								// If JSON parsing fails, just use the main label
							}
						}
						
						// Get the current search term
						let searchTerm = $('#organisationsTable').bootstrapTable('getOptions').searchText;
						let displayText = value;
						
						// Only highlight if the search term matches the label or alternative labels
						if (searchTerm && (value.toLowerCase().includes(searchTerm.toLowerCase()) || 
							tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase())))) {
							// Create a case-insensitive regex
							let regex = new RegExp(searchTerm, 'gi');
							// Replace matches with highlighted version, but only in the text content
							displayText = displayText.replace(regex, match => `<em>${match}</em>`);
						}
						
						if (tmpAltLabels.length > 0) {
							let altLabelsText = tmpAltLabels.join(', ');
							if (searchTerm && tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase()))) {
								// Create a case-insensitive regex
								let regex = new RegExp(searchTerm, 'gi');
								// Replace matches with highlighted version, but only in the text content
								altLabelsText = altLabelsText.replace(regex, match => `<em>${match}</em>`);
							}
							displayText += '<br><span class="less-opacity">' + altLabelsText + '</span>';
						}
						
						// Add ID to searchable text but don't include it in the display
						searchText += ' ' + row["OrganisationID"];
						
						return '<span data-search="' + searchText + '">' + displayText + '</span>';
					}
				},
				{
					field: "OrganisationID",
					title: "Wikidata ID",
					sortable: true,
					formatter: function(value, row) {
						return "<a href='https://www.wikidata.org/wiki/"+value+"' target='_blank'>"+value+"</a>";
					}
				},
				{
					field: "OrganisationLastChanged",
					title: "<?= L::lastChanged; ?>",
					sortable: true,
					formatter: function(value) {
						if (value) {
							return new Date(value).toLocaleString('de');
						}
						return "-";
					}
				},
				{
					field: "OrganisationID",
					title: "<?= L::actions; ?>",
					class: "minWidthColumn",
					sortable: true,
					formatter: function(value, row) {

						return renderActionButtons(value, "organisation", row["OrganisationType"]);

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
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#documentsToolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "DocumentLastChanged",
			sortOrder: "desc",
			columns: [
				{
					field: "DocumentThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-document",
					formatter: function(value, row) {
						return '<div class="thumbnailContainer"><div class="rounded-circle">' + 
							(value ? 
								'<img src="' + value + '" alt="..."/>' : 
								'<span class="icon-type-document" style="position: absolute;top: 48%;left: 50%;font-size: 20px;transform: translateX(-50%) translateY(-50%);"></span>'
							) + 
							'</div></div>';
					}
				},
				{
					field: "DocumentLabel",
					title: "<?= L::title; ?>",
					sortable: true,
					formatter: function(value, row, index) {
						let tmpAltLabels = [];
						let searchText = value;
						if (row["DocumentLabelAlternative"]) {
							try {
								let parsedAltLabels = JSON.parse(row["DocumentLabelAlternative"]);
								if (Array.isArray(parsedAltLabels) && parsedAltLabels.length > 0) {
									tmpAltLabels = parsedAltLabels;
									searchText += ' ' + parsedAltLabels.join(' ');
								}
							} catch (e) {
								// If JSON parsing fails, just use the main label
							}
						}
						
						// Get the current search term
						let searchTerm = $('#documentsTable').bootstrapTable('getOptions').searchText;
						let displayText = value;
						
						// Only highlight if the search term matches the label or alternative labels
						if (searchTerm && (value.toLowerCase().includes(searchTerm.toLowerCase()) || 
							tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase())))) {
							// Create a case-insensitive regex
							let regex = new RegExp(searchTerm, 'gi');
							// Replace matches with highlighted version, but only in the text content
							displayText = displayText.replace(regex, match => `<em>${match}</em>`);
						}
						
						if (tmpAltLabels.length > 0) {
							let altLabelsText = tmpAltLabels.join(', ');
							if (searchTerm && tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase()))) {
								// Create a case-insensitive regex
								let regex = new RegExp(searchTerm, 'gi');
								// Replace matches with highlighted version, but only in the text content
								altLabelsText = altLabelsText.replace(regex, match => `<em>${match}</em>`);
							}
							displayText += '<br><span class="less-opacity">' + altLabelsText + '</span>';
						}
						
						// Add ID to searchable text but don't include it in the display
						searchText += ' ' + row["DocumentID"];
						
						return '<span data-search="' + searchText + '">' + displayText + '</span>';
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
					field: "DocumentLastChanged",
					title: "<?= L::lastChanged; ?>",
					sortable: true,
					formatter: function(value) {
						if (value) {
							return new Date(value).toLocaleString('de');
						}
						return "-";
					}
				},
				{
					field: "DocumentID",
					title: "<?= L::actions; ?>",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "document", row["DocumentType"]);

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
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#termsToolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "TermLastChanged",
			sortOrder: "desc",
			columns: [
				{
					field: "TermThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-term",
					formatter: function(value, row) {
						return '<div class="thumbnailContainer"><div class="rounded-circle">' + 
							(value ? 
								'<img src="' + value + '" alt="..."/>' : 
								'<span class="icon-type-term" style="position: absolute;top: 48%;left: 50%;font-size: 20px;transform: translateX(-50%) translateY(-50%);"></span>'
							) + 
							'</div></div>';
					}
				},
				{
					field: "TermLabel",
					title: "<?= L::label; ?>",
					sortable: true,
					formatter: function(value, row, index) {
						let tmpAltLabels = [];
						let searchText = value;
						if (row["TermLabelAlternative"]) {
							try {
								let parsedAltLabels = JSON.parse(row["TermLabelAlternative"]);
								if (Array.isArray(parsedAltLabels) && parsedAltLabels.length > 0) {
									tmpAltLabels = parsedAltLabels;
									searchText += ' ' + parsedAltLabels.join(' ');
								}
							} catch (e) {
								// If JSON parsing fails, just use the main label
							}
						}
						
						// Get the current search term
						let searchTerm = $('#termsTable').bootstrapTable('getOptions').searchText;
						let displayText = value;
						
						// Only highlight if the search term matches the label or alternative labels
						if (searchTerm && (value.toLowerCase().includes(searchTerm.toLowerCase()) || 
							tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase())))) {
							// Create a case-insensitive regex
							let regex = new RegExp(searchTerm, 'gi');
							// Replace matches with highlighted version, but only in the text content
							displayText = displayText.replace(regex, match => `<em>${match}</em>`);
						}
						
						if (tmpAltLabels.length > 0) {
							let altLabelsText = tmpAltLabels.join(', ');
							if (searchTerm && tmpAltLabels.some(label => label.toLowerCase().includes(searchTerm.toLowerCase()))) {
								// Create a case-insensitive regex
								let regex = new RegExp(searchTerm, 'gi');
								// Replace matches with highlighted version, but only in the text content
								altLabelsText = altLabelsText.replace(regex, match => `<em>${match}</em>`);
							}
							displayText += '<br><span class="less-opacity">' + altLabelsText + '</span>';
						}
						
						// Add ID to searchable text but don't include it in the display
						searchText += ' ' + row["TermID"];
						
						return '<span data-search="' + searchText + '">' + displayText + '</span>';
					}
				},
				{
					field: "TermID",
					title: "Wikidata ID",
					sortable: true,
					formatter: function(value, row) {
						return "<a href='https://www.wikidata.org/wiki/"+value+"' target='_blank'>"+value+"</a>";
					}
				},
				{
					field: "TermLastChanged",
					title: "<?= L::lastChanged; ?>",
					sortable: true,
					formatter: function(value) {
						if (value) {
							return new Date(value).toLocaleString('de');
						}
						return "-";
					}
				},
				{
					field: "TermID",
					title: "<?= L::actions; ?>",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "term", row["TermType"]);

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