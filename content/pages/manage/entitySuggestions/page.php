<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$userId = isset($_SESSION["userdata"]["id"]) ? $_SESSION["userdata"]["id"] : null;
$auth = auth($userId, "requestPage", $pageType);

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
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="suggested-entities-tab" data-bs-toggle="tab" data-bs-target="#suggested-entities" role="tab" aria-controls="suggested-entities" aria-selected="true"><span class="icon-lightbulb"></span> <?= L::suggestions(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="suggested-entities" role="tabpanel" aria-labelledby="suggested-entities-tab">
                            <div id="cleanup-messages"></div>
                            <div id="toolbar"></div>
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
				<button type="button" class="w-100 btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

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
					<div class="col-6 ps-0">
                        <button type="button" class="btn btn-primary w-100" id="modalAddEntitySubmitBtn" disabled><span class="icon-plus"></span> <?= L::manageEntitiesNew(); ?></button>
                    </div>
					<div class="col-6 pe-0">
                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal"><?= L::cancel(); ?></button>
                    </div>
                </div>
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
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=entitySuggestion",
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "server",
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#toolbar",
			toolbarAlign: "right",
			serverSort: true,
			sortName: "EntitysuggestionCount",
			sortOrder: "desc",
			uniqueId: "EntitysuggestionID",
			queryParams: function(params) {
				var apiParams = {};
				apiParams.limit = params.limit || params.pageSize;
				apiParams.offset = params.offset || (params.pageNumber - 1) * (params.limit || params.pageSize);
				apiParams.search = params.search || params.searchText;
				apiParams.sort = params.sort || params.sortName;
				apiParams.order = params.order || params.sortOrder;
				return apiParams;
			},
			columns: [
				{
					field: "EntitysuggestionLabel",
					title: "Label",
					class: "w-100",
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
					title: "Affected Speeches",
					sortable: true
				},
				{
					field: "EntitysuggestionID",
					title: "Action",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {
                        let viewDetailsButton = "<span class='entitysuggestiondetails list-group-item list-group-item-action' title='<?= L::viewDetails(); ?>' data-id='"+value+"' data-bs-toggle='modal' data-bs-target='#entityDetailsModal'><span class='icon-eye'></span></span>";
                        let addEntityButton = "<button type='button' class='list-group-item list-group-item-action' title='Add Entity' " +
                                              "data-bs-toggle='modal' data-bs-target='#addEntityModal' " +
                                              "data-wikidata-id='" + row["EntitysuggestionExternalID"] + "' " +
                                              "data-entity-suggestion-id='" + row["EntitysuggestionID"] + "'>" +
                                              "<span class='icon-plus'></span></button>";
						return "<div class='list-group list-group-horizontal'>" + viewDetailsButton + addEntityButton + "</div>";
					}
				}
			]
		});

        const cleanupButton = `
            <button id="cleanupSuggestionsBtn" class="btn" title="` + localizedLabels.cleanup + `">
                <span class="icon-magic"></span> ` + localizedLabels.cleanup + `
            </button>
        `;
        $('#toolbar').append(cleanupButton);

        $('body').on('click', '#cleanupSuggestionsBtn', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).addClass('working');

            $('#cleanup-messages').empty().removeClass('alert alert-success alert-danger');

            $.ajax({
                url: config["dir"]["root"] + "/api/v1/",
                type: "POST",
                data: {
                    action: "cleanup",
                    itemType: "entity-suggestions"
                },
                dataType: "json",
                success: function(response) {
                    $btn.prop('disabled', false).removeClass('working');
                    if (response && response.meta && response.meta.requestStatus === 'success') {
                        var cleanedCount = response.data.cleanedCount || 0;
                        $('#cleanup-messages').addClass('alert alert-success').html(localizedLabels.messageCleanupSuccess.replace('{count}', cleanedCount));
                        $('#entitiesTable').bootstrapTable('refresh');
                    } else {
                        var errorMessage = (response && response.errors && response.errors[0] && response.errors[0].detail) ? response.errors[0].detail : 'An unknown error occurred during cleanup.';
                        $('#cleanup-messages').addClass('alert alert-danger').html(errorMessage);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $btn.prop('disabled', false).removeClass('working');
                    $('#cleanup-messages').addClass('alert alert-danger').html('An AJAX error occurred: ' + textStatus + ' - ' + errorThrown);
                }
            });
        });

		$(".mainContainer").on("click", ".entitysuggestiondetails",function() {

			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				data: {
					"action":"getItemsFromDB", 
					"itemType":"entitySuggestion", 
					"id":$(this).data("id"), 
					"idType": "internal"
				},
				success: function(ret) {
					if (ret && ret.data && ret.meta && ret.meta.requestStatus === "success") {
						let entityData = ret.data;
						let wikiIDRegex = new RegExp("Q[0-9]+");
						$("#entitiesDetailsExternalID").html((wikiIDRegex.test(entityData.EntitysuggestionExternalID) ? '<a href="https://www.wikidata.org/wiki/'+entityData.EntitysuggestionExternalID+'" target="_blank">'+entityData.EntitysuggestionExternalID+'</a>' : entityData.EntitysuggestionExternalID));
						$("#entitiesDetailsLabel").html(entityData.EntitysuggestionLabel);
						$("#entitiesDetailsType").html(entityData.EntitysuggestionType);
						$("#entitiesDetailsMediaCount").html(Object.keys(entityData.EntitysuggestionContext).length);
						$("#entitiesDetailsContent").empty();
						$("#entitiesDetailsContent").jsonView(entityData.EntitysuggestionContent);

						$("#entitiesDetailsContext").html("");
						for (let item in entityData.EntitysuggestionContext) {
							$("#entitiesDetailsContext").append('<a href="<?=$config["dir"]["root"]?>/media/'+item+'" target="_blank">'+item+'</a><br>');
						}
						$("#entitiesDetailsDiv").show();
					} else {
						console.error("Error fetching entity suggestion details:", ret);
						alert("Could not load entity details.");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error("AJAX error fetching entity suggestion details:", textStatus, errorThrown);
					alert("An error occurred while fetching entity details.");
				}
			});	

		});

		const entityDetailsModal = document.getElementById('entityDetailsModal')
		entityDetailsModal.addEventListener('hidden.bs.modal', event => {
			$("#entitiesDetailsDiv").hide();
		});

        // JavaScript for Add Entity Modal
        const addEntityModal = document.getElementById('addEntityModal');
        if (addEntityModal) {
            addEntityModal.addEventListener('show.bs.modal', function (event) {
                const modalBody = addEntityModal.querySelector('.modal-body');
                modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                const componentUrl = '<?= $config["dir"]["root"] ?>/content/components/entity.form.php';
                const button = event.relatedTarget;
                
                // Data to be set as attributes on the loaded form
                const wikidataIdFromButton = button && button.dataset.wikidataId ? button.dataset.wikidataId : null;
                const entitySuggestionIdFromButton = button && button.dataset.entitySuggestionId ? button.dataset.entitySuggestionId : null;

                $(modalBody).load(componentUrl, function(response, status, xhr) {
                    if (status == "error") {
                        modalBody.innerHTML = "Error loading entity form: " + xhr.status + " " + xhr.statusText;
                    } else {
                        // Initialize the form with the data, if available
                        // The form itself will handle these via its data attributes upon initialization
                        const $theForm = $(modalBody).find('#entityAddForm');
                        if (wikidataIdFromButton) {
                            $theForm.data('wikidata-id', wikidataIdFromButton);
                        }
                        if (entitySuggestionIdFromButton) {
                            $theForm.data('entity-suggestion-id', entitySuggestionIdFromButton);
                        }
                        // Manually trigger initialization if needed, though the form script should run on load
                        // if (typeof $theForm.formComponent === 'function') { 
                        //    $theForm.formComponent(); 
                        // }

                        // Logic to enable/disable modal's submit button based on the form's internal submit button
                        const $formInternalSubmitBtn = $theForm.find('#entityAddFormSubmitBtn');
                        const $modalSubmitBtn = $('#modalAddEntitySubmitBtn');
                        
                        // Initial state
                        $modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));

                        // Use a MutationObserver to watch for changes in the form's submit button's disabled state
                        const observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.attributeName === "disabled") {
                                    $modalSubmitBtn.prop('disabled', $formInternalSubmitBtn.prop('disabled'));
                                }
                            });
                        });
                        if($formInternalSubmitBtn.length) {
                           observer.observe($formInternalSubmitBtn[0], { attributes: true });
                        }
                        
                        // Store observer on modal to disconnect when hidden
                        $(addEntityModal).data('observer', observer);
                    }
                });
            });

            addEntityModal.addEventListener('hidden.bs.modal', function (event) {
                // Clean up: disconnect observer if it exists
                const observer = $(addEntityModal).data('observer');
                if (observer) {
                    observer.disconnect();
                    $(addEntityModal).removeData('observer');
                }
                // Clear the modal body to ensure fresh load next time
                const modalBody = addEntityModal.querySelector('.modal-body');
                modalBody.innerHTML = ''; 
                // Reset modal submit button state
                $('#modalAddEntitySubmitBtn').prop('disabled', true);
            });

            // Handle click on modal's Add Entity button
            $('body').on('click', '#modalAddEntitySubmitBtn', function() {
                const $form = $('#addEntityModal .modal-body #entityAddForm');
                if ($form.length) {
                    $form.submit(); // Trigger the form submission
                }
            });
        }

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

    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>