<?php 
if (isset($result_item["_finds"]) && count($result_item['_finds']) > 0) {
	$snippets = $result_item["_finds"];
} else {
	$snippets = null;
}
$paramStr = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams));

?>
<tr class="resultItem" data-speech-id="<?= $result_item["id"] ?>" data-faction="<?= $mainFaction["id"] ?>">
	<td><?= $result_item["id"] ?></td>
	<td><?= $formattedDate ?></td>
	<td><?= $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></td>
	<td><?= $highlightedName ?></td>
	<td><?= $formattedDuration ?></td>
	<td>Action Buttons</td>
</tr>