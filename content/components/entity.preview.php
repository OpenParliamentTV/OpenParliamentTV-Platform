<?php
require_once(__DIR__ . '/../../modules/utilities/security.php');

$typeImageFit = "cover";
$typeImagePosition = "top";
if ($entity["data"]["type"] == "organisation") {
	$typeImageFit = "contain";
	$typeImagePosition = "center";
}
?>

<div style="height: 100%; overflow: auto;">
	<div class="detailsHeader mb-0 p-0" style="border: none;">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row">
					<div class="col flex-grow-0 detailsThumbnailContainer" style="width: 70px; height: 70px; flex-basis: 70px; overflow: visible;">
						<a href="<?= $config["dir"]["root"] ?>/<?= hAttr($entity["data"]["type"]) ?>/<?= hAttr($entity["data"]["id"]) ?>" class="text-decoration-none">
							<div class="rounded-circle" style="top: 1px;">
								<?php if ($entity["data"]["attributes"]["thumbnailURI"]) { ?>
									<img src="<?= hAttr($entity["data"]["attributes"]["thumbnailURI"]) ?>" alt="..." style="position: absolute; object-fit: <?= hAttr($typeImageFit) ?>; object-position: <?= hAttr($typeImagePosition) ?>;"/>
								<?php } else if ($entity["data"]["type"] == "person" || $entity["data"]["type"] == "document") { ?>
									<span class="icon-type-<?= hAttr($entity["data"]["type"]) ?>" style="position: absolute;top: 47%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
								<?php } else if ($entity["data"]["type"] == "organisation" || $entity["data"]["type"] == "term") { ?>
									<span class="icon-type-<?= hAttr($entity["data"]["type"]) ?>" style="position: absolute;top: 50%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
								<?php } ?>
							</div>
						</a>
					</div>
					<div class="col">
						<?php 
						if (isset($entity["data"]["relationships"]["faction"]["data"]["id"])) {
						?>
							<h3 class="mb-0" style="white-space: normal;"><a href="<?= $config["dir"]["root"] ?>/<?= hAttr($entity["data"]["type"]) ?>/<?= hAttr($entity["data"]["id"]) ?>"><?= h($entity["data"]["attributes"]["label"]) ?></a><a href="../organisation/<?= hAttr($entity["data"]["relationships"]["faction"]["data"]["id"]) ?>" class="partyIndicator ms-2" data-faction="<?= hAttr($entity["data"]["relationships"]["faction"]["data"]["id"]) ?>" style="font-size: 14px;"><?= h($entity["data"]["relationships"]["faction"]["data"]["attributes"]["label"]) ?></a></h3>
						<?php 
						} else {
						?>
							<h3 class="mb-0" style="white-space: normal;"><a href="<?= $config["dir"]["root"] ?>/<?= hAttr($entity["data"]["type"]) ?>/<?= hAttr($entity["data"]["id"]) ?>"><?= h($entity["data"]["attributes"]["label"]) ?></a></h3>
						<?php
						}
						if ($entity["data"]["type"] != "person" && isset($entity["data"]["attributes"]["labelAlternative"][0])) { ?>
							<div class="less-opacity"><?= h($entity["data"]["attributes"]["labelAlternative"][0]) ?></div>
						<?php 
						}
						if (isset($entity["data"]["attributes"]["abstract"]) && $entity["data"]["attributes"]["abstract"] != "undefined") {
						?>
							<a href="<?= $config["dir"]["root"] ?>/<?= hAttr($entity["data"]["type"]) ?>/<?= hAttr($entity["data"]["id"]) ?>" class="text-decoration-none">
								<div class="mt-2 truncate-lines" style="-webkit-line-clamp: 5;"><?= h($entity["data"]["attributes"]["abstract"]) ?></div>
							</a>
						<?php 
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>