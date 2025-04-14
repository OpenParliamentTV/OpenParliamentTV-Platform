<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

include_once(__DIR__ . '/../../header.php'); ?>
<main class="container-fluid">
	<?php 
	// Include the filter bar component with all features enabled for search page
	$showSearchBar = true;
	$showParliamentFilter = false;
	$showToggleButton = true;
	$showFactionChart = true;
	$showDateRange = true;
	$showSearchSuggestions = true;
	$showAdvancedFilters = false;
	include_once(__DIR__ . '/../../components/search.filterbar.php'); 
	?>
	<div class="row m-0" style="position: relative; z-index: 1">
		<div id="speechListContainer" class="col">
			<div class="resultWrapper">
				<?php include_once(__DIR__ . '/../../components/result.grid.php'); ?>
			</div>
			<div class="loadingIndicator">
				<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/search/client/search.js?v=<?= $config["version"] ?>"></script>

<?php
}
?>