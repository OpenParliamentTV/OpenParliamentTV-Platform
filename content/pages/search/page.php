<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<main class="container-fluid">
	<?php 
	// Home state = no active search criteria. Computed up front so both the
	// filter bar's landing intro and the result grid below agree on it.
	$hasValidSearchCriteria = isset($_REQUEST["q"]) || isset($_REQUEST["personID"]) || isset($_REQUEST["organisationID"]) || isset($_REQUEST["documentID"]) || isset($_REQUEST["termID"]) || isset($_REQUEST["sessionID"]) || isset($_REQUEST["agendaItemID"]) || isset($_REQUEST["electoralPeriodID"]);

	// Include the filter bar component with all features enabled for search page
	$showSearchBar = true;
	$showParliamentFilter = false;
	$showToggleButton = true;
	$showFactionChart = true;
	$showDateRange = true;
	$showSearchSuggestions = true;
	$showAdvancedFilters = false;
	$showHomeIntro = true; // render claim + examples slots; JS toggles them with the search state
	$homeIntroInitiallyHidden = $hasValidSearchCriteria; // avoid a flash when landing on a query
	include_once(__DIR__ . '/../../components/search.filterbar.php'); 
	?>
	<div class="row m-0" style="position: relative; z-index: 1">
		<div id="speechListContainer" class="col">
			<div class="resultWrapper">
				<?php 
				// Add showHome parameter for search page when no valid search criteria present
				// ($hasValidSearchCriteria is computed above, before the filter bar include)
				if (!$hasValidSearchCriteria) {
					$_REQUEST['showHome'] = 1;
				}
				include_once(__DIR__ . '/../../components/result.grid.php'); 
				?>
			</div>
			<div class="loadingIndicator">
				<div class="workingSpinner" style="position: fixed; top: 65%;"></div>
			</div>
		</div>
	</div>
</main>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/filterController.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/mediaResults.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/search/client/search.js?v=<?= $config["version"] ?>"></script>