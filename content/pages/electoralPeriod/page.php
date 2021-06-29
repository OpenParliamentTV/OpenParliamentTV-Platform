<?php 
include_once(__DIR__ . '/../../header.php'); 
?>
<main class="container subpage">
	<div class="detailsHeader">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<div class="row align-items-center">
					<div class="col-4 col-md-3 col-lg-2">
						<div class="rounded-circle">
							<span class="icon-check" style="position: absolute;top: 50%;left: 50%;font-size: 70px;transform: translateX(-50%) translateY(-50%);"></span>
						</div>
					</div>
					<div class="col-6 col-md-9 col-lg-10">
						<div><?= $apiResult["data"]["attributes"]["parliamentLabel"] ?></div>
						<h2><?= $apiResult["data"]["attributes"]["number"] ?>. Electoral Period</h2>
						<div><?php 
							$formattedDateStart = date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateStart"]));
							$formattedDateEnd = ($apiResult["data"]["attributes"]["dateEnd"]) ? date("d.m.Y", strtotime($apiResult["data"]["attributes"]["dateEnd"])) : "";
							echo $formattedDateStart." – ".$formattedDateEnd; 
						?></div>
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
					<a class="nav-link" id="sessions-tab" data-toggle="tab" href="#sessions" role="tab" aria-controls="sessions" aria-selected="false"><span class="icon-group"></span> Sessions</a>
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
				<div class="tab-pane fade" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
					[CONTENT]
				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/searchResults.js"></script>
<script type="text/javascript">
	$(document).ready( function() {
		updateMediaList("electoralPeriodID=<?= $apiResult["data"]["id"] ?>");
	});
</script>