<div class="entityLinksContainer d-flex flex-row flex-md-column mt-3 mt-md-0">
	<?php if ($apiResult["data"]["type"] == "person" || $apiResult["data"]["type"] == "organisation") { ?>
		<div class="text-right">
			<a class="btn btn-sm mr-2 mr-md-0 mb-0 mb-md-2" href="https://wikidata.org/wiki/<?= $apiResult["data"]["id"] ?>" target="_blank">
				<span>Wikidata</span><img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
			</a>
		</div>
	<?php } else if (isset($apiResult["data"]["attributes"]["wikidataID"])) { ?>
		<div class="text-right">
			<a class="btn btn-sm mr-2 mr-md-0 text-right mb-0 mb-md-2" href="https://wikidata.org/wiki/<?= $apiResult["data"]["attributes"]["wikidataID"] ?>" target="_blank">
				<span>Wikidata</span><img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
			</a>
		</div>
	<?php } ?>
	<?php if (isset($apiResult["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"])) { ?>
		<div class="text-right">
			<a class="btn btn-sm mr-2 mr-md-0 text-right mb-0 mb-md-2" href="https://abgeordnetenwatch.de/politician/<?= $apiResult["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"] ?>" target="_blank">
				<span>Abgeordnetenwatch</span><img class="ml-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/abgeordnetenwatch-sm.png">
			</a>
		</div>
	<?php } ?>
</div>