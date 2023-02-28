
<?php
$typeImageFit = "cover";
if ($entity["data"]["type"] == "organisation") {
	$typeImageFit = "contain";
}
?>

<div style="height: 100%; overflow: auto;">
	<div class="detailsHeader mb-0 p-0" style="border: none;">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row">
					<div class="col flex-grow-0 detailsThumbnailContainer" style="width: 70px; height: 70px; flex-basis: 70px; overflow: visible;">
						<div class="rounded-circle" style="top: 1px;">
							<img src="<?= $entity["data"]["attributes"]["thumbnailURI"] ?>" alt="..." style="position: absolute; object-fit: <?= $typeImageFit ?>;"/>
						</div>
					</div>
					<div class="col">
						<h3 style="hyphens: auto; white-space: normal;"><a href="<?= $config["dir"]["root"] ?>/<?= $entity["data"]["type"] ?>/<?= $entity["data"]["id"] ?>"><?= $entity["data"]["attributes"]["label"] ?></a></h3>
						<?php 
						if (isset($entity["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<a href="../organisation/<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>" class="partyIndicator" data-faction="<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>"><?= $entity["data"]["relationships"]["faction"]["data"]["attributes"]["labelAlternative"][0] ?></a>
						<?php 
						}
						?>
						<div><?= $entity["data"]["attributes"]["abstract"] ?></div>
						<a class="d-block mt-1" target="_blank" href="<?= $entity["data"]["attributes"]["websiteURI"] ?>"><?= $entity["data"]["attributes"]["websiteURI"] ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>