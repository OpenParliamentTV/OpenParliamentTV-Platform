<div style="height: 100%">
	<div class="detailsHeader mb-0" style="height: 100%">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<img src="<?= $entity["data"]["attributes"]["thumbnailURI"] ?>" alt="..." style="position: absolute;">
						</div>
						<div class="copyrightInfo"><span class="icon-info-circled"></span><span class="copyrightText"><?php echo L::source; ?>: <?php echo html_entity_decode($entity["data"]["attributes"]["thumbnailCreator"]); ?>, <?= $entity["data"]["attributes"]["thumbnailLicense"] ?></span></div>
					</div>
					<div class="col">
						<h2><?= $entity["data"]["attributes"]["label"] ?></h2>
						<?php 
						if (isset($entity["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<a href="../organisation/<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>" class="partyIndicator" data-faction="<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>" target="_blank"><?= $entity["data"]["relationships"]["faction"]["data"]["attributes"]["labelAlternative"][0] ?></a>
						<?php 
						}
						?>
						<div><?= $entity["data"]["attributes"]["abstract"] ?></div>
						<a target="_blank" href="<?= $entity["data"]["attributes"]["websiteURI"] ?>"><?= $entity["data"]["attributes"]["websiteURI"] ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<a target="_blank" class="d-block btn btn-primary" href="<?= $config["dir"]["root"] ?>/<?= $entity["data"]["type"] ?>/<?= $entity["data"]["id"] ?>" role="button" style="position: absolute; bottom: 20px; left: 20px; width: calc(100% - 40px);">Open Entity in New Tab</a>
</div>