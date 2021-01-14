<table class="table table-striped">
	<thead>
	<tr>
		<th scope="col">ID</th>
		<th scope="col">Entity</th>
		<th scope="col">Identifier</th>
		<th scope="col">Rival</th>
		<th scope="col">Subject</th>
		<th scope="col">Description</th>
		<th scope="col">Date</th>
	</tr>
	</thead>
	<tbody>

<?php
//TODO Auth
include_once(__DIR__."/../../config.php");
include_once(__DIR__."/../utilities/safemysql.class.php");
include_once(__DIR__."/../utilities/auth.php");
include_once(__DIR__."/../player/functions.media.php");

$dbPlatform = new SafeMySQL(array(
	'host'	=> $config["platform"]["sql"]["access"]["host"],
	'user'	=> $config["platform"]["sql"]["access"]["user"],
	'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
	'db'	=> $config["platform"]["sql"]["db"]
));

$conflicts = $dbPlatform->getAll("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Conflict"]);
foreach ($conflicts as $k=>$conflict) {

echo "
<tr data-toggle='collapse' data-target='#details-".$k."' data-conflictidentifier='".$conflict["ConflictIdentifier"]."' data-conflictrival='".$conflict["ConflictRival"]."' class='clickable' style='cursor:pointer;'>
		<td>".$conflict["ConflictID"]."</td>
		<td>".$conflict["ConflictEntity"]."</td>
		<td>".$conflict["ConflictIdentifier"]."</td>
		<td>".$conflict["ConflictRival"]."</td>
		<td>".$conflict["ConflictSubject"]."</td>
		<td>".$conflict["ConflictDescription"]."</td>
		<td>".$conflict["ConflictDate"]."</td>
</tr>
<tr>
	<td colspan='7'>
		<div id='details-".$k."' class='collapse row'>
			<div class='col first' style='height:400px; overflow:auto; max-width:400px;'><pre></pre></div>
			<div class='col second' style='height:400px; overflow:auto; max-width:400px;'><pre></pre></div>
			<div class='col third' style='height:400px; overflow:auto; max-width:400px;'>Diffs:<pre></pre></div>
		</div>
	</td>
</tr>


";


}


?>


	</tbody>
</table>

