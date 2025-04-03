<?php
session_start();
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

include_once(__DIR__ . '/../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", "results");

if ($auth["meta"]["requestStatus"] != "success") {
    echo "Not authorized";
} else {

	if (!function_exists("L")) {
		require_once(__DIR__."/../../i18n.class.php");
		$i18n = new i18n(__DIR__.'/../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../langcache/', 'de');
		$i18n->init();
	}

	require_once(__DIR__.'/../../modules/utilities/functions.entities.php');
	require_once(__DIR__."/../../modules/search/include.search.php");
	require_once(__DIR__."/../../api/v1/api.php");

	$parameter = $_REQUEST;
	$parameter["action"] = "search";
	$parameter["itemType"] = "media";
	$result = apiV1($parameter);

	$result_items = $result["data"];

	$totalResults = $result["meta"]["results"]["total"];

	$totalResultString = $totalResults;

	if ($totalResults >= 10000 ) {
		$totalResultString = "> ".$totalResults;
	}

	$findsString = '';

	//echo "<pre style='color:#FFF'>";
	//print_r($result);

	// ELSE FINISHES AT END OF FILE
	if ($totalResults != 0) {
?>
	<div class="filterSummary row">
		<div class="col-12 col-sm-6 mb-3 mb-sm-0 px-0 px-sm-2"><label class="col-form-label px-0 me-0 me-sm-1 col-12 col-sm-auto text-center text-sm-left"><?= $findsString ?><strong><?= $totalResultString ?></strong> <?= L::speechesFound; ?></label>
			<button type="button" id="play-submit" class="btn btn-sm btn-outline-primary col-12 col-sm-auto" style="background-color: var(--highlight-color); color: var(--primary-bg-color);"><?= L::autoplayAll; ?><span class="icon-play-1"></span></button>
		</div>
		<div class="col-12 col-sm-6 pr-0 pr-sm-2" style="text-align: right;">
			<label class="col-form-label" for="sort"><?= L::sortBy; ?></label>
			<select style="width: auto;" class="form-select form-select-sm ms-1 d-inline-block" id="sort" name="sort">
				<option value="relevance" selected><?= L::relevance; ?></option>
				<option value="topic-asc"><?= L::topic; ?> (<?= L::sortByAsc; ?>)</option>
				<option value="topic-desc"><?= L::topic; ?> (<?= L::sortByDesc; ?>)</option>
				<option value="date-asc"><?= L::date; ?> (<?= L::sortByAsc; ?>)</option>
				<option value="date-desc"><?= L::date; ?> (<?= L::sortByDesc; ?>)</option>
			</select>
		</div>
	</div>
	<div class="resultList row">
		<div class="table-responsive bootstrap-table">
			<div class="fixed-table-container">
				<table id="manageMediaOverviewTable" class="table table-striped">
					<thead>
						<tr>
							<th>
								<div class="th-inner">ID</div>
							</th>
							<th>
								<div class="th-inner">Date</div>
							</th>
							<th>
								<div class="th-inner">Agenda Item</div>
							</th>
							<th>
								<div class="th-inner">Main Speaker</div>
							</th>
							<th>
								<div class="th-inner">Duration</div>
							</th>
							<th>
								<div class="th-inner">Actions</div>
							</th>
						</tr>
					</thead>
					<tbody>
					<?php
					$paramStr = "";
					$allowedParams = array_intersect_key($_REQUEST,array_flip(array("q","name","person","personID","context","party","faction","factionID","parliament","electoralPeriod","electoralPeriodID","agendaItemID","dateFrom","dateTo","gender","degree","abgeordnetenwatchID","fragDenStaatID","organisation","organisationID","documentID","termID","sessionNumber","sessionID","page","sort")));

					$sortFactor = null;
					$currentDate = null;
					$currentAgendaItem = null;
					if (isset($_REQUEST["sort"]) && strlen($_REQUEST["sort"]) > 3) {
						$sortFactor = (strpos($_REQUEST["sort"], 'date') !== false) ? 'date' : 'topic';
					}
					
					foreach($result_items as $result_item) {
						
						$formattedDuration = gmdate("i:s", $result_item["attributes"]['duration']);

						$formattedDate = date("d.m.Y", strtotime($result_item["attributes"]["dateStart"]));
						
						$mainSpeaker = getMainSpeakerFromPeopleArray($result_item["annotations"]['data'], $result_item["relationships"]["people"]['data']);
						$mainFaction = getMainFactionFromOrganisationsArray($result_item["annotations"]['data'], $result_item["relationships"]["organisations"]['data']);

						if ($sortFactor == 'date' && $formattedDate != $currentDate) {
							echo '<div class="sortDivider"><b>'.$formattedDate.'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
							$currentDate = $formattedDate;
						} elseif ($sortFactor == 'topic' && $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"] != $currentAgendaItem) {
							echo '<div class="sortDivider"><b>'.$formattedDate.' - '.$result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"].'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
							$currentAgendaItem = $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"];
						}

						$highlightedName = $mainSpeaker['attributes']['label'];
						if (strlen($_REQUEST['person']) > 1) {
							$highlightedName = str_replace($_REQUEST['person'], '<em>'.$_REQUEST['person'].'</em>', $highlightedName);
						}

						include 'result.table.item.php';
					}
					?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
    <?php
    //print_r($result);
    ?>
    <script type="text/javascript">

        resultsAttributes = <?= json_encode($result["meta"]["attributes"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>

    </script>
	<?php 
	include_once('result.pagination.php');
} else {
	?>
	<div class="filterSummary row">
		<div class="col alert alert-info"><?= L::noRelevant; ?> <?= L::speechesFound; ?></div>
	</div>

<?php 
}
}
?>