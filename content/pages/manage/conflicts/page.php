<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');
    // Ensure API functions and the apiV1 dispatcher are available for programmatic call
    require_once(__DIR__ . '/../../../../modules/utilities/functions.api.php');
    require_once(__DIR__ . '/../../../../api/v1/api.php');

?>
<main class="container-fluid subpage">
	<div class="row">
		<?php include_once(__DIR__ . '/../sidebar.php'); ?>
		<div class="sidebar-content">
			<div class="row" style="position: relative; z-index: 1">
				<div class="col-12">
					<h2><?= L::manageConflicts; ?></h2>

					<table class="table table-striped"
						   id="conflictsTableStats"
						   data-toggle="table"
						   data-sortable="true">

						<thead>
						<tr>
							<th scope="col">Type</th>
							<th scope="col">Count</th>
						</tr>
						</thead>
						<tbody>

					<?php
                    // Call the API to get conflict statistics
                    $statsResponse = apiV1([
                        'action' => 'getItemsFromDB',
                        'itemType' => 'conflict',
                        'getStats' => true,
                        'includeResolved' => false // Or true if you want stats for all conflicts
                    ]);

                    $conflictsStatsOverall = 0;
                    if (isset($statsResponse['meta']['requestStatus']) && $statsResponse['meta']['requestStatus'] == 'success' && isset($statsResponse['data'])) {
                        foreach ($statsResponse['data'] as $conflictStat) {
                            $conflictsStatsOverall += $conflictStat["ConflictCount"];
                            echo "
                            <tr>
                                <td><a href='?search[subject][]=".htmlspecialchars($conflictStat["ConflictSubject"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($conflictStat["ConflictSubject"], ENT_QUOTES, 'UTF-8')."</a></td>
                                <td>".htmlspecialchars($conflictStat["ConflictCount"], ENT_QUOTES, 'UTF-8')."</td>
                            </tr>";
                        }
                    } else {
                        // Handle error or display a message if stats could not be fetched
                        echo "<tr><td colspan='2'>Could not load conflict statistics.</td></tr>";
                        if (isset($statsResponse['errors'])) {
                            // Optionally log or display more detailed error information
                            // error_log(json_encode($statsResponse['errors']));
                        }
                    }
                    echo "
                    <tr>
                        <td>Total</td>
                        <td>".$conflictsStatsOverall."</td>
                    </tr>";
					?>
						</tbody>
					</table><br><br><br>

                    <?php
                    // Server-side pagination is now the only option for the main table
                    ?>
                    <table class="table table-striped" id="conflictsTable"></table>
                    <script type="application/javascript">
                        $(function() {
                            let apiUrl = config["dir"]["root"] + "/api/v1/?action=getItemsFromDB&itemType=conflict";
                            const searchParams = new URLSearchParams(window.location.search);
                            if (searchParams.has('search[subject][]')) {
                                let subjectSearch = searchParams.getAll('search[subject][]');
                                if(subjectSearch.length > 0) {
                                    apiUrl += "&search=" + encodeURIComponent(subjectSearch[0]); // Changed from searchString to search to match API param
                                }
                            }

                            $('#conflictsTable').bootstrapTable({
                                url: apiUrl,
                                pagination: true,
                                sidePagination: "server",
                                dataField: "data", 
                                totalField: "total", 
                                queryParams: function (params) {
                                    var apiParams = {};
                                    apiParams.limit = params.limit;
                                    apiParams.offset = params.offset;
                                    if (params.search) {
                                        apiParams.search = params.search; // Ensure this matches the API's expected search parameter name
                                    }
                                    if (params.sort) {
                                        apiParams.sort = params.sort;
                                        apiParams.order = params.order;
                                    }
                                    return apiParams;
                                },
                                columns: [
                                    {
                                        field: "ConflictID",
                                        title: "ID",
                                        formatter: function(value, row) {
                                            return '<div data-id="'+row["ConflictID"]+'" class="conflictsDetailToggle">'+value+' <i class="icon-right-open-big"> </i></div>';
                                        }
                                    },
                                    {
                                        field: "ConflictEntity",
                                        title: "Entity"
                                    },
                                    {
                                        field: "ConflictIdentifier",
                                        title: "Identifier"
                                    },
                                    {
                                        field: "ConflictRival",
                                        title: "Rival"
                                    },
                                    {
                                        field: "ConflictSubject",
                                        title: "Subject"
                                    },
                                    {
                                        field: "ConflictDescription",
                                        title: "Description",
                                        formatter: function(value, row) {
                                            return '<div id="conflicts-detail-'+row["ConflictID"]+'" class="conflictsDetail">'+value+'</div>';
                                        }
                                    },
                                    {
                                        field: "ConflictDate",
                                        title: "Date"
                                    },
                                    {
                                        field: "ConflictResolved",
                                        title: "Resolved"
                                    }
                                ]
                            });
                        });
                        $(document).on("click",".conflictsDetailToggle", function() {
                           $("#conflicts-detail-" + $(this).data("id")).toggleClass("conflictsDetail");
                        });
                    </script>
                    <style>
                        .conflictsDetailToggle {
                            white-space: nowrap;
                            cursor: pointer;
                        }
                        .conflictsDetail {
                            max-height: 100px;
                            max-width: 100px;
                            overflow:hidden;
                            cursor: pointer;
                        }
                    </style>

				</div>
			</div>
		</div>
	</div>
</main>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>