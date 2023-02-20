<?php

include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {
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
							<img src="<?= $apiResult["data"]["attributes"]["thumbnailURI"] ?>" alt="..." style="position: absolute;">
						</div>
						<div class="copyrightInfo"><span class="icon-info-circled"></span><span class="copyrightText"><?php echo L::source; ?>: <?php echo html_entity_decode($apiResult["data"]["attributes"]["thumbnailCreator"]); ?>, <?= $apiResult["data"]["attributes"]["thumbnailLicense"] ?></span></div>
					</div>
					<div class="col">
						<h2><?= $apiResult["data"]["attributes"]["label"] ?></h2>
						<?php 
						if (isset($apiResult["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<a href="../organisation/<?= $apiResult["data"]["relationships"]["faction"]["data"]["id"] ?>" class="partyIndicator" data-faction="<?= $apiResult["data"]["relationships"]["faction"]["data"]["id"] ?>" target="_blank"><?= $apiResult["data"]["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] ?></a>
						<?php 
						}
						?>
						<div><?= $apiResult["data"]["attributes"]["abstract"] ?></div>
						<a target="_blank" href="<?= $apiResult["data"]["attributes"]["websiteURI"] ?>"><?= $apiResult["data"]["attributes"]["websiteURI"] ?></a>
					</div>
				</div>
				<div class="row">
					<div class="col">
						<a target="_blank" class="d-block mt-3 btn btn-primary" href="<?= $config["dir"]["root"] ?>/<?= $apiResult["data"]["type"] ?>/<?= $apiResult["data"]["id"] ?>" role="button">Open Entity in New Tab</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<script type="text/javascript">
	$(document).ready( function() {
		$('body').addClass('ready');
	});
</script>
<?php
}
?>