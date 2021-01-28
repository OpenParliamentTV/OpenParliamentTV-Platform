
<table class="table table-borderless" id="conflictCompareTable">
<?php
require_once(__DIR__."/../../../../../modules/media/functions.media.php");
$media1 = getMedia($conflict["ConflictIdentifier"]);
$media2 = getMedia($conflict["ConflictRival"]);

foreach($media1 as $key=>$field) {

	if (!is_array($field)) {

		$different_class = (($media2[$key] == $field)) ? "conflictSame" : "conflictDifference";

		echo '
					<tr class="'.$different_class.'">
						<th colspan="4"><h3>'.$key.'</h3></th>
					</tr>
					<tr class="'.$different_class.'">
						<td>'.((strlen($field) > 100) ? '<textarea class="container-fluid">'.$field.'</textarea>' : $field).'</td>
						<td><input type="radio" name="'.$key.'" value='.$media1["MediaID"].'"></td>
						<td><input type="radio" name="'.$key.'" value='.$media2["MediaID"].'"></td>
						<td>'.((strlen($media2[$key]) > 100) ? '<textarea class="container-fluid">'.$media2[$key].'</textarea>' : $media2[$key]).'</td>
					</tr>
					<tr>
						<td colspan="4"><hr></td>
					</tr>
		';


	}


}

?>


</table>