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
					<div class="card mb-3">
						<div class="card-body">
							<button type="button" id="openAddEntityModalBtn" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#addEntityModal"><span class="icon-plus"></span> <?= L::manageEntitiesNew(); ?></button>
						</div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="people-tab" data-bs-toggle="tab" data-bs-target="#people" role="tab" aria-controls="people" aria-selected="true"><span class="icon-type-person"></span> <?= L::personPlural(); ?></a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="organisations-tab" data-bs-toggle="tab" data-bs-target="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><span class="icon-type-organisation"></span> <?= L::organisations(); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" role="tab" aria-controls="documents" aria-selected="false"><span class="icon-type-document"></span> <?= L::documents(); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="terms-tab" data-bs-toggle="tab" data-bs-target="#terms" role="tab" aria-controls="terms" aria-selected="false"><span class="icon-type-term"></span> <?= L::terms(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="people" role="tabpanel" aria-labelledby="people-tab">
							<div id="peopleToolbar"></div>
							<table id="peopleTable"></table>
                        </div>
						<div class="tab-pane bg-white fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
							<div id="organisationsToolbar"></div>
							<table id="organisationsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
							<div id="documentsToolbar"></div>
							<table id="documentsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
							<div id="termsToolbar"></div>
							<table id="termsTable"></table>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>

<!-- Modal for Adding New Entity -->
<div class="modal fade" id="addEntityModal" tabindex="-1" aria-labelledby="addEntityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEntityModalLabel"><?= L::manageEntitiesNew(); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded here by AJAX -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row w-100">
                    <div class="col-7 ps-0">
                        <button type="button" class="btn btn-primary w-100" id="modalAddEntitySubmitBtn" disabled><span class="icon-plus"></span> <?= L::manageEntitiesNew(); ?></button>
                    </div>
					<div class="col-5 pe-0">
                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal"><span class="icon-cancel"></span> <?= L::cancel(); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

	$(function(){
		
		function renderActionButtons(id, type, subtype) {
			const viewButton = '<a class="list-group-item list-group-item-action" ' +
				'title="<?= L::view(); ?>" ' +
				'href="<?= $config["dir"]["root"]; ?>/' + type + '/' + id + '" ' +
				'target="_blank">' +
				'<span class="icon-eye"></span>' +
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
				apiButton +
				adsButton +
				'</div>';
		}
		
		// Add click handler for ADS buttons
		$(document).on('click', '.ads-button', function() {
			const $button = $(this);
			const $row = $button.closest('tr'); 
            const $table = $row.closest('table');
            const tableId = $table.attr('id');
			
			$button.removeClass("list-group-item-success list-group-item-danger");
			$button.addClass("working");
			
			const entityType = $button.data('type');
			const entityId = $button.data('id');
			const entitySubtype = $button.data('subtype');
			
			let adsType = entityType;
			if (entitySubtype === "memberOfParliament" || 
				entitySubtype === "person" || 
				entitySubtype === "legalDocument" || 
				entitySubtype === "officialDocument") {
				adsType = entitySubtype;
			}

			// Make AJAX call to update entity data
			$.ajax({
				url: "<?= $config["dir"]["root"] ?>/api/v1/",
				dataType: "json",
				data: {
					"action":"externalData",
					"itemType": "update-entities",
					"type": [adsType],
					"ids": [entityId],
					"language": "de"
				},
				method: "post",
				success: function(response) {
					$button.removeClass("working");
					
					if (response && response.meta && response.meta.requestStatus === "success") {
                        if (tableId && entityId) {
                            $table.one('load-success.bs.table post-body.bs.table', function (e, data) { 
                                $table.off('load-success.bs.table post-body.bs.table'); 
                                
                                animateBootstrapTableRow(tableId, entityId, 'success', 2000)
                            });
                            // Trigger the table refresh to show updated data
                            // Add a cache-busting parameter to the URL for refresh
                            let currentUrl = $table.bootstrapTable('getOptions').url;
                            let cacheBustUrl = currentUrl + (currentUrl.indexOf('?') > -1 ? '&' : '?') + 't=' + new Date().getTime();
                            $table.bootstrapTable('refresh', { url: cacheBustUrl });
                        } else {
                            console.error("[ADS Update] Missing tableId or entityId for success animation.");
                        }
					} else {
						console.error("API ADS update failed:", response);
                        let errorMessage = "ADS update failed.";
                        if (response && response.errors && response.errors.length > 0) {
                            // Safely access error details
                            let firstError = response.errors[0];
                            if (typeof firstError === 'string') {
                                errorMessage = firstError;
                            } else if (firstError && (firstError.detail || firstError.title || firstError.info)) {
                                errorMessage = firstError.detail || firstError.title || firstError.info;
                            } else if (response.meta && response.meta.message) {
                                errorMessage = response.meta.message;
                            }
                        } else if (response && response.meta && response.meta.message) {
                            errorMessage = response.meta.message;
                        }
                        alert(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					$button.removeClass("working");
					console.error("ADS update AJAX error:", status, error);
                    alert("A network error occurred while updating. Please try again.");
				}
			});
		});

		// Helper function for formatting labels with alternatives
		function formatLabelWithAlternatives(value, row, alternativeLabelField, idField, tableId, type) {
			let tmpAltLabels = [];
			let searchText = String(value); // Ensure value is a string

			// 1. Parse alternative labels
			if (row[alternativeLabelField]) {
				try {
					let parsedAltLabels = JSON.parse(row[alternativeLabelField]);
					if (Array.isArray(parsedAltLabels) && parsedAltLabels.length > 0) {
						tmpAltLabels = parsedAltLabels.map(String); // Ensure alt labels are strings
						searchText += ' ' + tmpAltLabels.join(' ');
					}
				} catch (e) { 
					// If JSON parsing fails, just use the main label
					console.warn(`Failed to parse alternative labels for ${idField} ${row[idField]}:`, row[alternativeLabelField], e);
				}
			}

			// 2. Get search term
			const $table = $(`#${tableId}`);
			// Check if table and options exist before accessing searchText
			let searchTerm = ($table.length && $table.data('bootstrap.table')) ? $table.bootstrapTable('getOptions').searchText : ''; 
			let displayText = String(value); // Ensure value is a string
			let altLabelsText = tmpAltLabels.join(', ');

			// Helper for highlighting text safely
			const highlight = (text, term) => {
				if (!term || typeof text !== 'string') return text;
				// Escape regex special characters in the search term
				const escapedTerm = term.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
				const regex = new RegExp(escapedTerm, 'gi');
				return text.replace(regex, match => `<em>${match}</em>`);
			};

			// 3. Highlight main label if search term matches
			if (searchTerm && typeof value === 'string' && value.toLowerCase().includes(searchTerm.toLowerCase())) {
				displayText = highlight(value, searchTerm);
			}

			// 4. Highlight alternative labels if search term matches
			if (searchTerm && tmpAltLabels.some(label => typeof label === 'string' && label.toLowerCase().includes(searchTerm.toLowerCase()))) {
				altLabelsText = highlight(altLabelsText, searchTerm);
			}

			// 5. Construct display HTML including alternative labels if they exist
			let mainLabel = displayText;
			if (row[idField]) {
				mainLabel = `<a href="<?= $config["dir"]["root"]; ?>/${type}/${row[idField]}" target="_blank" class="fw-bolder">${displayText}</a>`;
			} else {
				mainLabel = `<span class="fw-bolder">${displayText}</span>`;
			}
			if (tmpAltLabels.length > 0) {
				mainLabel += `<span class="less-opacity d-block">${altLabelsText}</span>`;
			}

			// 6. Add ID to searchText if it exists
			if (row[idField]) {
				searchText += ' ' + row[idField];
			}

			// 7. Return final HTML, ensure searchText is properly escaped for the attribute
			const escapedSearchText = searchText.replace(/"/g, '&quot;'); // Escape quotes for the HTML attribute
			return `<span data-search="${escapedSearchText}">${mainLabel}</span>`;
		}
		
		// Helper function for formatting thumbnails
		function formatThumbnail(value, row, idField, type, iconClass) {
			const thumbnail = '<div class="thumbnailContainer"><div class="rounded-circle">' + 
				(value ? 
					'<img src="' + value + '" alt="..."/>' : 
					`<span class="${iconClass}" style="position: absolute;top: 48%;left: 50%;font-size: 20px;transform: translateX(-50%) translateY(-50%);"></span>`
				) + 
				'</div></div>';
			
			if (row[idField]) {
				return `<a href="<?= $config["dir"]["root"]; ?>/${type}/${row[idField]}" target="_blank">${thumbnail}</a>`;
			}
			return thumbnail;
		}
		
		$('#peopleTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=person",
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
			uniqueId: "PersonID",
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
						return formatThumbnail(value, row, 'PersonID', 'person', 'icon-type-person');
					}
				},
				{
					field: "PersonLabel",
					title: "<?= L::name(); ?>",
					sortable: true,
					formatter: function(value, row) {
						return formatLabelWithAlternatives(value, row, 'PersonLabelAlternative', 'PersonID', 'peopleTable', 'person');
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
					title: "<?= L::party(); ?>",
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
					title: "<?= L::faction(); ?>",
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
					title: "<?= L::lastChanged(); ?>",
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
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "person", row["PersonType"]);

					}
				}
			]
		});

		$('#organisationsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=organisation",
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
			uniqueId: "OrganisationID",
			columns: [
				{
					field: "OrganisationThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-organisation",
					formatter: function(value, row) {
						return formatThumbnail(value, row, 'OrganisationID', 'organisation', 'icon-type-organisation');
					}
				},
				{
					field: "OrganisationLabel",
					title: "<?= L::name(); ?>",
					sortable: true,
					formatter: function(value, row) {
						return formatLabelWithAlternatives(value, row, 'OrganisationLabelAlternative', 'OrganisationID', 'organisationsTable', 'organisation');
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
					title: "<?= L::lastChanged(); ?>",
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
					title: "",
					class: "minWidthColumn",
					sortable: true,
					formatter: function(value, row) {

						return renderActionButtons(value, "organisation", row["OrganisationType"]);

					}
				}
			]
		});

		$('#documentsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=document",
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
			uniqueId: "DocumentID",
			columns: [
				{
					field: "DocumentThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-document",
					formatter: function(value, row) {
						return formatThumbnail(value, row, 'DocumentID', 'document', 'icon-type-document');
					}
				},
				{
					field: "DocumentLabel",
					title: "<?= L::title(); ?>",
					sortable: true,
					formatter: function(value, row) {
						return formatLabelWithAlternatives(value, row, 'DocumentLabelAlternative', 'DocumentID', 'documentsTable', 'document');
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
					title: "<?= L::lastChanged(); ?>",
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
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "document", row["DocumentType"]);

					}
				}
			]
		});

		$('#termsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=term",
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
			uniqueId: "TermID",
			columns: [
				{
					field: "TermThumbnailURI",
					title: "",
					sortable: false,
					class: "minWidthColumn thumbnailColumn-term",
					formatter: function(value, row) {
						return formatThumbnail(value, row, 'TermID', 'term', 'icon-type-term');
					}
				},
				{
					field: "TermLabel",
					title: "<?= L::label(); ?>",
					sortable: true,
					formatter: function(value, row) {
						return formatLabelWithAlternatives(value, row, 'TermLabelAlternative', 'TermID', 'termsTable', 'term');
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
					title: "<?= L::lastChanged(); ?>",
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
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {

						return renderActionButtons(value, "term", row["TermType"]);

					}
				}
			]
		});

        // JavaScript for Add Entity Modal
        const addEntityModal = document.getElementById('addEntityModal');
        if (addEntityModal) {
            addEntityModal.addEventListener('show.bs.modal', function (event) {
                const modalBody = addEntityModal.querySelector('.modal-body');
                modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                const componentUrl = '<?= $config["dir"]["root"] ?>/content/components/entity.form.php';
                const button = event.relatedTarget; // Button that triggered the modal (if any)

                // Data to be set as attributes on the loaded form
                // For entities/page.php, these are typically not set when clicking the main "New Entity" button
                const wikidataIdFromButton = button && button.dataset.wikidataId ? button.dataset.wikidataId : null;
                const entitySuggestionIdFromButton = button && button.dataset.entitySuggestionId ? button.dataset.entitySuggestionId : null;

                $(modalBody).load(componentUrl, function(response, status, xhr) {
                    if (status == "error") {
                        modalBody.innerHTML = "Error loading entity form: " + xhr.status + " " + xhr.statusText;
                    } else {
                        const $theForm = $(modalBody).find('#entityAddForm');
                        if (wikidataIdFromButton) {
                            $theForm.data('wikidata-id', wikidataIdFromButton);
                        }
                        if (entitySuggestionIdFromButton) {
                            $theForm.data('entity-suggestion-id', entitySuggestionIdFromButton);
                        }

                        const $formInternalSubmitBtn = $theForm.find('#entityAddFormSubmitBtn');
                        const $modalSubmitBtn = $('#modalAddEntitySubmitBtn'); 
                        
                        $modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));

                        const observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.attributeName === "disabled") {
                                    $modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));
                                }
                            });
                        });
                        if($formInternalSubmitBtn.length){
                           observer.observe($formInternalSubmitBtn[0], { attributes: true });
                        }
                        $(addEntityModal).data('observer', observer);
                    }
                });
            });

            addEntityModal.addEventListener('hidden.bs.modal', function (event) {
                const observer = $(addEntityModal).data('observer');
                if (observer) {
                    observer.disconnect();
                    $(addEntityModal).removeData('observer');
                }
                const modalBody = addEntityModal.querySelector('.modal-body');
                modalBody.innerHTML = ''; 
                $('#modalAddEntitySubmitBtn').prop('disabled', true); 
            });

            // Handle click on modal's Add Entity button for this page
            $('body').on('click', '#modalAddEntitySubmitBtn', function() { 
                const $form = $('#addEntityModal .modal-body #entityAddForm');
                if ($form.length) {
                    $form.submit();
                }
            });
        }

	})

</script>
<?php

    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>