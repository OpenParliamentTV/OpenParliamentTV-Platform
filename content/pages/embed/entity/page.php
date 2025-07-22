<?php

require_once(__DIR__ . "/../../../../modules/utilities/security.php");
require_once(__DIR__."/../../../../modules/utilities/functions.entities.php");
$flatDataArray = flattenEntityJSON($apiResult["data"]);
?>
<main class="mt-0" style="height: 100%">
	<div class="detailsHeader mb-0" style="height: 100%">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<img src="<?= hAttr($apiResult["data"]["attributes"]["thumbnailURI"]) ?>" alt="..." style="position: absolute;">
						</div>
						<div class="copyrightInfo"><span class="icon-info-circled"></span><span class="copyrightText"><?= L::source(); ?>: <?= safeHtml($apiResult["data"]["attributes"]["thumbnailCreator"]) ?>, <?= safeHtml($apiResult["data"]["attributes"]["thumbnailLicense"]) ?></span></div>
					</div>
					<div class="col">
						<h2><?= h($apiResult["data"]["attributes"]["label"]) ?></h2>
						<?php 
						if (isset($apiResult["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<a href="../organisation/<?= hAttr($apiResult["data"]["relationships"]["faction"]["data"]["id"]) ?>" class="partyIndicator" data-faction="<?= hAttr($apiResult["data"]["relationships"]["faction"]["data"]["id"]) ?>" target="_blank"><?= h($apiResult["data"]["relationships"]["faction"]["data"]["attributes"]["label"]) ?></a>
						<?php 
						}
						?>
						<div><?= h($apiResult["data"]["attributes"]["abstract"]) ?></div>
						<a target="_blank" href="<?= hAttr($apiResult["data"]["attributes"]["websiteURI"]) ?>"><?= h($apiResult["data"]["attributes"]["websiteURI"]) ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<a target="_blank" class="d-block btn" href="<?= $config["dir"]["root"] ?>/<?= hAttr($apiResult["data"]["type"]) ?>/<?= hAttr($apiResult["data"]["id"]) ?>" role="button" style="position: absolute; bottom: 20px; left: 20px; width: calc(100% - 40px);">Open Entity in New Tab</a>
</main>
<script type="text/javascript">
	$(document).ready( function() {
		$('body').addClass('ready');
	});
</script>