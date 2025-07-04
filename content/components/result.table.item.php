<?php 
if (isset($result_item["_finds"]) && count($result_item['_finds']) > 0) {
	$snippets = $result_item["_finds"];
} else {
	$snippets = null;
}
$paramStr = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams));

?>
<tr class="resultItem" data-speech-id="<?= $result_item["id"] ?>" data-faction="<?= $mainFaction ? $mainFaction["id"] : '' ?>">
	<td><?= $result_item["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] ?> / <?= $result_item["relationships"]["session"]["data"]["attributes"]["number"] ?></td>
	<td><?= $formattedDate ?></td>
	<td><?= $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></td>
	<td><?= $highlightedName ?></td>
	<td><?= $formattedDuration ?></td>
	<td><?= $result_item["attributes"]["lastChanged"] ?></td>
	<?php if ($_SESSION["userdata"]["role"] == "admin") { ?>
		<td>
			<div class="form-check form-switch">
				<input class="form-check-input aligned-switch" type="checkbox" id="aligned-<?= $result_item["id"] ?>" <?= $result_item["attributes"]["aligned"] ? "checked" : "" ?> data-speech-id="<?= $result_item["id"] ?>" disabled>
			</div>
		</td>
		<td>
			<div class="form-check form-switch">
				<input class="form-check-input public-switch" type="checkbox" id="public-<?= $result_item["id"] ?>" <?= $result_item["attributes"]["public"] ? "checked" : "" ?> data-speech-id="<?= $result_item["id"] ?>" disabled>
			</div>
		</td>
	<?php } ?>
	<td>
		<div class="list-group list-group-horizontal">
			<a class="list-group-item list-group-item-action" title="<?= L::view(); ?>" href="<?= $config["dir"]["root"]; ?>/media/<?= $result_item["id"] ?>" target="_blank"><span class="icon-eye"></span></a>
			<?php if ($_SESSION["userdata"]["role"] == "admin") { ?>
				<a class="list-group-item list-group-item-action" title="<?= L::edit(); ?>" href="<?= $config["dir"]["root"]; ?>/manage/media/<?= $result_item["id"] ?>"><span class="icon-pencil"></span></a>
			<?php } ?>
			<a class="list-group-item list-group-item-action" title="API" href="<?= $config["dir"]["root"]; ?>/api/v1/media/<?= $result_item["id"] ?>" target="_blank"><span class="icon-code"></span></a>
		</div>
	</td>
</tr>