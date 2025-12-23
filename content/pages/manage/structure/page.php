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
                <div class="col-12">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="electoralPeriods-tab" data-bs-toggle="tab" data-bs-target="#electoralPeriods" role="tab" aria-controls="electoralPeriods" aria-selected="true"><span class="icon-check me-2"></span><?= L::electoralPeriods(); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions" role="tab" aria-controls="sessions" aria-selected="false"><span class="icon-group me-2"></span><?= L::sessions(); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="agendaItems-tab" data-bs-toggle="tab" data-bs-target="#agendaItems" role="tab" aria-controls="agendaItems" aria-selected="false"><span class="icon-list-numbered me-2"></span><?= L::agendaItems(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="electoralPeriods" role="tabpanel" aria-labelledby="electoralPeriods-tab">
							<div id="electoralPeriodsToolbar">
							</div>
							<table id="electoralPeriodsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
							<div id="sessionsToolbar">
								<select id="electoralPeriodFilter" class="form-select form-select-sm d-inline-block w-auto me-2">
									<option value=""><?= L::all(); ?> <?= L::electoralPeriods(); ?></option>
								</select>
							</div>
							<table id="sessionsTable"></table>
                        </div>
                        <div class="tab-pane bg-white fade" id="agendaItems" role="tabpanel" aria-labelledby="agendaItems-tab">
							<div id="agendaItemsToolbar">
								<select id="agendaItemsElectoralPeriodFilter" class="form-select form-select-sm d-inline-block w-auto me-2">
									<option value=""><?= L::all(); ?> <?= L::electoralPeriods(); ?></option>
								</select>
								<select id="agendaItemsSessionFilter" class="form-select form-select-sm d-inline-block w-auto me-2">
									<option value=""><?= L::all(); ?> <?= L::sessions(); ?></option>
								</select>
							</div>
							<table id="agendaItemsTable"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">

	$(function(){
		
		function renderActionButtons(id, type, subtype) {
			const viewButton = '<a class="list-group-item list-group-item-action" ' +
				'title="<?= L::view(); ?>" ' +
				'href="<?= $config["dir"]["root"]; ?>/' + type + '/' + id + '" ' +
				'target="_blank">' +
				'<span class="icon-eye"></span>' +
				'</a>';
			
			const editButton = '<a class="list-group-item list-group-item-action" ' +
				'title="<?= L::edit(); ?>" ' +
				'href="<?= $config["dir"]["root"]; ?>/manage/structure/' + type + '/' + id + '">' +
				'<span class="icon-pencil"></span>' +
				'</a>';
			
			const apiButton = '<a class="list-group-item list-group-item-action" ' +
				'title="API" ' +
				'href="<?= $config["dir"]["root"]; ?>/api/v1/' + type + '/' + id + '" ' +
				'target="_blank">' +
				'<span class="icon-code"></span>' +
				'</a>';
			
			// Combine all buttons in a horizontal list group
			return '<div class="list-group list-group-horizontal">' +
				viewButton +
				editButton +
				apiButton +
				'</div>';
		}
		
		$('#electoralPeriodsTable').bootstrapTable({
			url: config["dir"]["root"] + "/api/v1/",
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "server",
			pageSize: 10,
			pageList: [10, 25, 50, 100],
			dataField: "data",
			totalField: "total",
			search: true,
			searchAlign: "left",
			toolbar: "#electoralPeriodsToolbar",
			toolbarAlign: "right",
			serverSort: true,
			queryParams: function(params) {
				// Create a new params object with only the necessary parameters
				var queryParams = {
					action: "getItemsFromDB",
					itemType: "electoralPeriod"
				};
				
				// Add search parameter if it exists
				if (params.search) {
					queryParams.search = params.search;
				}
				
				// Add pagination parameters
				var page = parseInt(params.page) || 1;
				var pageSize = parseInt(params.pageSize) || 10;
				queryParams.limit = pageSize;
				queryParams.offset = (page - 1) * pageSize;
				
				// Add sort parameters if they exist
				if (params.sort) {
					queryParams.sort = params.sort;
					queryParams.order = params.order;
				}
				
				return queryParams;
			},
			responseHandler: function(res) {
				// Return the response directly if it has the expected format
				if (res && res.data && res.total !== undefined) {
					return res;
				}
				
				// Fallback for unexpected response format
				return {
					total: 0,
					rows: []
				};
			},
			columns: [
				{
					field: "Parliament",
					title: "<?= L::parliament(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodNumber",
					title: "<?= L::electoralPeriod(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodDateStart",
					title: "<?= L::dateStart(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodDateEnd",
					title: "<?= L::dateEnd(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodID",
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {
						return renderActionButtons(value, "electoralPeriod", row["ElectoralPeriodType"]);
					}
				}
			]
		});

		// Load electoral periods for both filters
		$.ajax({
			url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=electoralPeriod",
			method: "GET",
			success: function(response) {
				if (response.data) {
					// Populate both electoral period filters
					const filters = $("#electoralPeriodFilter, #agendaItemsElectoralPeriodFilter");
					response.data.forEach(function(period) {
						filters.append(`<option value="${period.ElectoralPeriodID}"><?= L::electoralPeriod(); ?> ${period.ElectoralPeriodNumber}</option>`);
					});
				}
			}
		});

		// Initialize variables to store all data
		var allSessionsData = [];
		var allAgendaItemsData = [];
		
		// Function to load all sessions data
		function loadAllSessionsData() {
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/",
				method: "GET",
				data: {
					action: "getItemsFromDB",
					itemType: "session",
					electoralPeriodID: $("#electoralPeriodFilter").val(),
					limit: 1000, // Load a large number to get all data
					offset: 0
				},
				success: function(response) {
					allSessionsData = response.data || [];
					
					// Initialize the table with all data
					$('#sessionsTable').bootstrapTable('load', {
						total: allSessionsData.length,
						rows: allSessionsData
					});
				},
				error: function(xhr, status, error) {
					// Error handling without console log
				}
			});
		}
		
		// Function to load all agenda items data
		function loadAllAgendaItemsData() {
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=agendaItem&electoralPeriodID=" + $("#agendaItemsElectoralPeriodFilter").val() + "&sessionID=" + $("#agendaItemsSessionFilter").val() + "&limit=1000&offset=0",
				method: "GET",
				success: function(response) {
					allAgendaItemsData = response.data || [];
					
					// Initialize the table with all data
					$('#agendaItemsTable').bootstrapTable('load', {
						total: allAgendaItemsData.length,
						rows: allAgendaItemsData
					});
				},
				error: function(xhr, status, error) {
					// Error handling without console log
				}
			});
		}
		
		// Function to load sessions for the agenda items session filter
		function loadSessionsForAgendaItemsFilter(electoralPeriodID) {
			const sessionFilter = $("#agendaItemsSessionFilter");
			
			// Clear and disable session dropdown if no electoral period selected
			if (!electoralPeriodID) {
				sessionFilter.html('<option value=""><?= L::all(); ?> <?= L::sessions(); ?></option>').prop('disabled', true);
				loadAllAgendaItemsData();
				return;
			}
			
			// Load sessions for the selected electoral period
			$.ajax({
				url: config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=session&electoralPeriodID=" + electoralPeriodID + "&limit=1000&offset=0",
				method: "GET",
				success: function(response) {
					if (response.data) {
						// Clear and update session dropdown
						sessionFilter.html('<option value=""><?= L::all(); ?> <?= L::sessions(); ?></option>');
						
						response.data.forEach(function(session) {
							sessionFilter.append(`<option value="${session.SessionID}"><?= L::session(); ?> ${session.SessionNumber}</option>`);
						});
						
						sessionFilter.prop('disabled', false);
					}
					
					// Load agenda items data after updating the session dropdown
					loadAllAgendaItemsData();
				},
				error: function(xhr, status, error) {
					// Error handling without console log
				}
			});
		}
		
		$('#sessionsTable').bootstrapTable({
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "client", // Change to client-side pagination
			pageSize: 10,
			pageList: [10, 25, 50, 100],
			search: true,
			searchAlign: "left",
			toolbar: "#sessionsToolbar",
			toolbarAlign: "right",
			columns: [
				{
					field: "Parliament",
					title: "<?= L::parliament(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodNumber",
					title: "<?= L::electoralPeriod(); ?>",
					sortable: true
				},
				{
					field: "SessionNumber",
					title: "<?= L::session(); ?>",
					sortable: true
				},
				{
					field: "SessionDateStart",
					title: "<?= L::dateStart(); ?>",
					sortable: true
				},
				{
					field: "SessionDateEnd",
					title: "<?= L::dateEnd(); ?>",
					sortable: true
				},
				{
					field: "SessionID",
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {
						return renderActionButtons(value, "session", row["SessionType"]);
					}
				}
			]
		});
		
		// Load all sessions data when the page loads
		loadAllSessionsData();
		
		// Handle electoral period filter change for sessions
		$("#electoralPeriodFilter").on("change", function() {
			loadAllSessionsData();
		});

		$('#agendaItemsTable').bootstrapTable({
			classes: "table table-striped",
			locale: "<?= $lang; ?>",
			pagination: true,
			sidePagination: "client", // Change to client-side pagination
			pageSize: 10,
			pageList: [10, 25, 50, 100],
			search: true,
			searchAlign: "left",
			toolbar: "#agendaItemsToolbar",
			toolbarAlign: "right",
			columns: [
				{
					field: "Parliament",
					title: "<?= L::parliament(); ?>",
					sortable: true
				},
				{
					field: "ElectoralPeriodNumber",
					title: "<?= L::electoralPeriod(); ?>",
					sortable: true
				},
				{
					field: "SessionNumber",
					title: "<?= L::session(); ?>",
					sortable: true
				},
				{
					field: "AgendaItemTitle",
					title: "<?= L::agendaItem(); ?>",
					class: "w-100",
					sortable: true
				},
				{
					field: "AgendaItemOrder",
					title: "<?= L::order(); ?>",
					sortable: true
				},
				{
					field: "AgendaItemID",
					title: "",
					class: "minWidthColumn",
					sortable: false,
					formatter: function(value, row) {
						return renderActionButtons(value, "agendaItem", row["AgendaItemType"]);
					}
				}
			]
		});
		
		// Load all agenda items data when the page loads
		loadAllAgendaItemsData();
		
		// Handle electoral period filter change for agenda items
		$("#agendaItemsElectoralPeriodFilter").on("change", function() {
			const electoralPeriodID = $(this).val();
			loadSessionsForAgendaItemsFilter(electoralPeriodID);
		});
		
		// Handle session filter change for agenda items
		$("#agendaItemsSessionFilter").on("change", function() {
			loadAllAgendaItemsData();
		});

	})

</script>
<?php
    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>