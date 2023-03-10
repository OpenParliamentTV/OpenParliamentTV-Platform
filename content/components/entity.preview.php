
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
							<?php if ($entity["data"]["attributes"]["thumbnailURI"] || $entity["data"]["type"] == "person") { ?>
								<img src="<?= $entity["data"]["attributes"]["thumbnailURI"] ?>" alt="..." style="position: absolute; object-fit: <?= $typeImageFit ?>;"/>
							<?php } else if ($entity["data"]["type"] == "document") { ?>
								<span class="icon-doc-text" style="position: absolute;top: 50%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } else if ($entity["data"]["type"] == "organisation") { ?>
								<span class="icon-bank" style="position: absolute;top: 50%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } else if ($entity["data"]["type"] == "term") { ?>
								<span class="icon-tag-1" style="position: absolute;top: 50%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } ?>
							
						</div>
					</div>
					<div class="col">
						<h3 style="hyphens: auto; white-space: normal;"><a href="<?= $config["dir"]["root"] ?>/<?= $entity["data"]["type"] ?>/<?= $entity["data"]["id"] ?>"><?= $entity["data"]["attributes"]["label"] ?></a></h3>
						<?php 
						if (isset($entity["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<a href="../organisation/<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>" class="partyIndicator" data-faction="<?= $entity["data"]["relationships"]["faction"]["data"]["id"] ?>"><?= $entity["data"]["relationships"]["faction"]["data"]["attributes"]["label"] ?></a>
						<?php 
						}
						if ($entity["data"]["type"] != "person" && isset($entity["data"]["attributes"]["labelAlternative"][0])) { ?>
							<div><?= $entity["data"]["attributes"]["labelAlternative"][0] ?></div>
						<?php 
						}
						if (isset($entity["data"]["attributes"]["abstract"]) && $entity["data"]["attributes"]["abstract"] != "undefined") {
						?>
							<div class="mt-2"><?= $entity["data"]["attributes"]["abstract"] ?></div>
						<?php 
						}
						?>
						<a class="d-block mt-1" target="_blank" href="<?= $entity["data"]["attributes"]["websiteURI"] ?>"><?= $entity["data"]["attributes"]["websiteURI"] ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>