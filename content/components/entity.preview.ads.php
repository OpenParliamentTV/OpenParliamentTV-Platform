<?php
require_once(__DIR__ . '/../../modules/utilities/security.php');
// Handle POST data for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entity'])) {
	$entity = json_decode($_POST['entity'], true);
}

if (!isset($entity) || !isset($entity['data'])) {
	return;
}

require_once(__DIR__."/../../modules/i18n/language.php");

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
						<div class="rounded-circle" style="top: 1px;">
							<?php if (!empty($entity["data"]["attributes"]["thumbnailURI"])) { ?>
								<img src="<?= hAttr($entity["data"]["attributes"]["thumbnailURI"]) ?>" alt="..." style="position: absolute; object-fit: <?= hAttr($typeImageFit) ?>; object-position: <?= hAttr($typeImagePosition) ?>;"/>
							<?php } else if ($entity["data"]["type"] == "person" || $entity["data"]["type"] == "document") { ?>
								<span class="icon-type-<?= hAttr($entity["data"]["type"]) ?>" style="position: absolute;top: 47%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } else if ($entity["data"]["type"] == "organisation" || $entity["data"]["type"] == "term") { ?>
								<span class="icon-type-<?= hAttr($entity["data"]["type"]) ?>" style="position: absolute;top: 50%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
							<?php } ?>
						</div>
					</div>
					<div class="col">
						<h3 class="mb-0" style="white-space: normal;"><?= h($entity["data"]["attributes"]["label"]) ?></h3>
						<?php
						if (isset($entity["data"]["attributes"]["labelAlternative"]) && !empty($entity["data"]["attributes"]["labelAlternative"])) { ?>
							<div class="less-opacity"><?= h(implode(', ', $entity["data"]["attributes"]["labelAlternative"])) ?></div>
						<?php 
						}

						if (isset($entity["data"]["attributes"]["abstract"]) && $entity["data"]["attributes"]["abstract"] != "undefined") {
							?>
							<div class="mt-2 truncate-lines" style="-webkit-line-clamp: 5;"><?= h($entity["data"]["attributes"]["abstract"]) ?></div>
						<?php 
						}
						?>

						<div class="mt-2">
							<?php
							// Wikidata link
							if ($entity["data"]["type"] == "person" || $entity["data"]["type"] == "organisation" || $entity["data"]["type"] == "term") { ?>
								<a class="btn btn-sm me-2 mb-2" href="https://wikidata.org/wiki/<?= hAttr($entity["data"]["id"]) ?>" target="_blank">
									<span>Wikidata</span><img class="ms-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
								</a>
							<?php } else if (isset($entity["data"]["attributes"]["wikidataID"])) { ?>
								<a class="btn btn-sm me-2 mb-2" href="https://wikidata.org/wiki/<?= hAttr($entity["data"]["attributes"]["wikidataID"]) ?>" target="_blank">
									<span>Wikidata</span><img class="ms-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikidata-sm.png">
								</a>
							<?php }

							// Wikipedia link
							if (isset($entity["data"]["attributes"]["additionalInformation"]["wikipedia"]["url"])) { ?>
								<a class="btn btn-sm me-2 mb-2" href="<?= hAttr($entity["data"]["attributes"]["additionalInformation"]["wikipedia"]["url"]) ?>" target="_blank">
									<span>Wikipedia</span><img class="ms-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/wikipedia.svg">
								</a>
							<?php }

							// Abgeordnetenwatch link
							if (isset($entity["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"])) { ?>
								<a class="btn btn-sm me-2 mb-2" href="https://abgeordnetenwatch.de/politician/<?= hAttr($entity["data"]["attributes"]["additionalInformation"]["abgeordnetenwatchID"]) ?>" target="_blank">
									<span>Abgeordnetenwatch</span><img class="ms-2" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/abgeordnetenwatch-sm.png">
								</a>
							<?php }

							// Original Source link for documents
							if (isset($entity["data"]["attributes"]["sourceURI"]) && !empty($entity["data"]["attributes"]["sourceURI"])) { ?>
								<a class="btn btn-sm me-2 mb-2" href="<?= hAttr($entity["data"]["attributes"]["sourceURI"]) ?>" target="_blank">
									<span class="icon-link"></span> Original <?= L::document(); ?>
								</a>
							<?php }

							// Website link
							if (isset($entity["data"]["attributes"]["websiteURI"]) && !empty($entity["data"]["attributes"]["websiteURI"])) { ?>
								<a class="btn btn-sm me-2 mb-2" href="<?= hAttr($entity["data"]["attributes"]["websiteURI"]) ?>" target="_blank">
									<span class="icon-link"></span> Website
								</a>
							<?php }

							// Social media links
							if (isset($entity["data"]["attributes"]["socialMediaIDs"]) && !empty($entity["data"]["attributes"]["socialMediaIDs"])) {
								foreach ($entity["data"]["attributes"]["socialMediaIDs"] as $social) { ?>
									<a class="btn btn-sm me-2 mb-2" href="<?= hAttr($social['id']) ?>" target="_blank">
										<span class="icon-link"></span> <?= h(ucfirst($social['label'])) ?>
									</a>
								<?php }
							} ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>