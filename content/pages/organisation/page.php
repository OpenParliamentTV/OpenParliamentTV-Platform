<?php 
include_once(__DIR__ . '/../../header.php'); 
?>
<main class="container-fluid subpage">
	<div class="detailsHeader">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<img src="<?= $apiResult["data"]["attributes"]["thumbnailURI"] ?>" alt="..." class="img-fluid" style="position: absolute; top: 50%; transform: translateY(-50%) translateX(-50%);left: 50%;width: 80%;">
						</div>
					</div>
					<div class="col">
						<h2><?= $apiResult["data"]["attributes"]["labelAlternative"] ?></h2>
						<div><?= $apiResult["data"]["attributes"]["label"] ?></div>
						<div><?= $apiResult["data"]["attributes"]["abstract"] ?></div>
						<a href="<?= $apiResult["data"]["attributes"]["websiteURI"] ?>"><?= $apiResult["data"]["attributes"]["websiteURI"] ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span> Related Media</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="people-tab" data-toggle="tab" href="#people" role="tab" aria-controls="people" aria-selected="false"><span class="icon-torso"></span> People</a>
				</li>
				<li class="nav-item ml-auto">
					<a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span> Data</a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
					<div id="speechListContainer">
						<div class="resultWrapper">
							<?php include_once('content.result.php'); ?>
						</div>
						<div class="loadingIndicator">
							<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade" id="people" role="tabpanel" aria-labelledby="people-tab">
					[CONTENT]
				</div>
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					[ITEM DATA]
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js"></script>
<?php 
	$subType = ($apiResult["data"]["attributes"]["type"] == "faction" || 
				$apiResult["data"]["attributes"]["type"] == "party") ? 
					$apiResult["data"]["attributes"]["type"] : "organisation";
?>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("<?= $subType ?>ID=<?= $apiResult["data"]["id"] ?>");
	});
</script>