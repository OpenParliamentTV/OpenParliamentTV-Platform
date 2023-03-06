<div class="entityLinksContainer">
	<?php if ($apiResult["data"]["type"] == "person" || $apiResult["data"]["type"] == "organisation") { ?>
		<a class="d-block text-right mb-2" href="https://wikidata.org/wiki/<?= $apiResult["data"]["id"] ?>" target="_blank">
			<span class="">Wikidata</span>
			<img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
		</a>
	<?php } else if (isset($apiResult["data"]["attributes"]["wikidataID"])) { ?>
		<a class="d-block text-right mb-2" href="https://wikidata.org/wiki/<?= $apiResult["data"]["attributes"]["wikidataID"] ?>" target="_blank">
			<span class="">Wikidata</span>
			<img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
		</a>
	<?php } ?>
	<?php if (isset($apiResult["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"])) { ?>
		<a class="d-block text-right mb-2" href="https://abgeordnetenwatch.de/politician/<?= $apiResult["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"] ?>" target="_blank">
			<span class="">Abgeordnetenwatch</span>
			<img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/abgeordnetenwatch-sm.png">
		</a>
	<?php } ?>
</div>