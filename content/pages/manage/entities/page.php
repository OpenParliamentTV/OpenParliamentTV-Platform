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
			<h2><?php echo L::manageEntities; ?></h2>

			<table class="table table-striped table-hover"
				   id="entitiesTable"
				   data-toggle="table"
				   data-sortable="true"
                   data-pagination="true"
                   data-search="true">

				<thead>
				<tr>
					<th scope="col" data-sortable="true">Type</th>
					<th scope="col" data-sortable="true">Label</th>
					<th scope="col" data-sortable="true">ID</th>
					<th scope="col" data-sortable="true">Count</th>
				</tr>
				</thead>
				<tbody>

			<?php

            if (!$db) {
                $db = new SafeMySQL(array(
                    'host'	=> $config["platform"]["sql"]["access"]["host"],
                    'user'	=> $config["platform"]["sql"]["access"]["user"],
                    'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                    'db'	=> $config["platform"]["sql"]["db"]
                ));
            }

            $entitysuggestions = $db->getAll("SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount FROM ?n;", $config["platform"]["sql"]["tbl"]["Entitysuggestion"]);

			foreach ($entitysuggestions as $entity) {
				echo "
					<tr>
							<td>".$entity["EntitysuggestionType"]."</td>
							<td>".$entity["EntitysuggestionLabel"]."</td>
							<td>".$entity["EntitysuggestionExternalID"]."</td>
							<td>".$entity["EntitysuggestionCount"]."</td>
					</tr>";

			}
			?>
                </tbody>
			</table><br><br><br>


		</div>
	</div>
</main>
    <script type="application/javascript">
        $(function() {
            /*$('#entitiesTable').bootstrapTable({
                pagination: true,
                sidePagination: "client"
            })*/
        })
    </script>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>