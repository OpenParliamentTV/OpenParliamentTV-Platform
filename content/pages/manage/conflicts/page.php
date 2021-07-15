<?php include_once(__DIR__ . '/../../../header.php'); ?>
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
							<td>".$conflictStat["ConflictSubject"]."</td>
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

			<?php
			//TODO Auth




			$conflicts = getConflicts();
			foreach ($conflicts as $k=>$conflict) {

				echo "
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


				</tbody>
			</table>
			<script type="application/javascript">
				$("#conflictsTable").on("click", "tr.clickable", function() {
					window.location = window.location+"/"+$(this).data("conflictid");
				});
			</script>


		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../../footer.php'); ?>