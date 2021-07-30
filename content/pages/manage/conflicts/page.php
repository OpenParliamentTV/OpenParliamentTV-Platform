<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');


?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::manageConflicts; ?></h2>

			<table class="table table-striped table-hover"
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
            include_once(__DIR__."/../../../../modules/utilities/functions.conflicts.php");

			//TODO Auth

            if (!$db) {
                $db = new SafeMySQL(array(
                    'host'	=> $config["platform"]["sql"]["access"]["host"],
                    'user'	=> $config["platform"]["sql"]["access"]["user"],
                    'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                    'db'	=> $config["platform"]["sql"]["db"]
                ));
            }

            $conflictsStats = $db->getAll("SELECT `ConflictSubject`, COUNT(`ConflictID`) as ConflictCount FROM ?n WHERE 1 GROUP BY `ConflictSubject`", $config["platform"]["sql"]["tbl"]["Conflict"]);
            $conflictsStatsOverall = 0;
			foreach ($conflictsStats as $conflictStat) {
                $conflictsStatsOverall += $conflictStat["ConflictCount"];
				echo "
					<tr>
							<td><a href='?search[subject][]=".$conflictStat["ConflictSubject"]."'>".$conflictStat["ConflictSubject"]."</a></td>
							<td>".$conflictStat["ConflictCount"]."</td>
					</tr>";

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
            //TODO Auth

            $pagination = "server"; // "client" or anything else for server/ajax

            if ($pagination == "client") {

            //$conflicts = getConflicts("all"); // If we want to do pagination at client side
            $conflicts = getConflicts("all",10,0); // If we want to do pagination at server side
            //print_r($conflicts);
            $tableConflictsBody = "";
            foreach ($conflicts as $k=>$conflict) {

                $tableConflictsBody .= "
					<tr data-conflictid='".$conflict["ConflictID"]."' data-conflictidentifier='".$conflict["ConflictIdentifier"]."' data-conflictrival='".$conflict["ConflictRival"]."' class='clickable' style='cursor:pointer;'>
							<td>".$conflict["ConflictID"]."</td>
							<td>".$conflict["ConflictEntity"]."</td>
							<td>".$conflict["ConflictIdentifier"]."</td>
							<td>".$conflict["ConflictRival"]."</td>
							<td>".$conflict["ConflictSubject"]."</td>
							<td>".$conflict["ConflictDescription"]."</td>
							<td>".$conflict["ConflictDate"]."</td>
							<td>".$conflict["ConflictResolved"]."</td>
					</tr>";

            }

            ?>


            <table class="table table-striped table-hover"
				   id="conflictsTable"
				   data-toggle="table"
				   data-search="true"
				   data-sortable="true"
				   data-pagination="true"
				   data-show-extended-pagination="true"
				   data-show-columns="true">

				<thead>
				<tr>
					<th scope="col" data-visible="false">ID</th>
					<th scope="col">Entity</th>
					<th scope="col">Identifier</th>
					<th scope="col">Rival</th>
					<th scope="col">Subject</th>
					<th scope="col" data-visible="false">Description</th>
					<th scope="col">Date</th>
					<th scope="col">Resolved</th>
				</tr>
				</thead>
				<tbody>

			        <?=$tableConflictsBody?>


				</tbody>
			</table>
			<script type="application/javascript">
				$("#conflictsTable").on("click", "tr.clickable", function() {
					window.location = window.location+"/"+$(this).data("conflictid");
				});
			</script>
            <?php

            } else {

            ?>
                <table class="table table-striped table-hover" id="conflictsTable"></table>
                <script type="application/javascript">
                    $(function() {
                        $('#conflictsTable').bootstrapTable({
                            url: config["dir"]["root"] + "/server/ajaxServer.php?a=conflictsTable<?=(($_REQUEST["search"]["subject"]) ? "&search=".http_build_query($_REQUEST["search"]) : "")?>",
                            pagination: true,
                            sidePagination: "server",
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


            <?php
            }


            ?>

		</div>
	</div>
</main>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>