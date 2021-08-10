<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {


include_once(__DIR__ . '/../../header.php'); 
?>
<main class="container-fluid subpage">
	<div class="detailsHeader">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col flex-grow-0 detailsThumbnailContainer">
						<div class="rounded-circle">
							<?php 
							if (isset($apiResult["data"]["attributes"]["thumbnailURI"])) {
								
								echo '<img src="'.$apiResult["data"]["attributes"]["thumbnailURI"].'" alt="..." class="img-fluid" style="position: absolute;height: 100%;object-fit: cover;">';

							} else {
								
								echo '<span class="icon-tag-1" style="position: absolute;top: 50%;left: 50%;font-size: 50px;transform: translateX(-50%) translateY(-50%);"></span>';

							}
							?>
						</div>
					</div>
					<div class="col">
						<h2><?= $apiResult["data"]["attributes"]["label"] ?></h2>
						<div><?= $apiResult["data"]["attributes"]["labelAlternative"] ?></div>
						<div><?= $apiResult["data"]["attributes"]["abstract"] ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true"><span class="icon-hypervideo"></span><span class="nav-item-label d-none d-sm-inline"><?php echo L::relatedMedia ?></span></a>
				</li>
				<li class="nav-item ml-auto">
					<a class="nav-link" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span><span class="nav-item-label d-none d-sm-inline"><?php echo L::data ?></span></a>
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
				<div class="tab-pane fade bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
					[ITEM DATA]
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js"></script>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("termID=<?= $apiResult["data"]["id"] ?>");
	});
</script>

<?php
}
?>