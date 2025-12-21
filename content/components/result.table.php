<?php
session_start();
require_once(__DIR__."/../../config.php");
require_once(__DIR__ . '/../../modules/utilities/security.php');
applySecurityHeaders();

include_once(__DIR__ . '/../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", "results");

if ($auth["meta"]["requestStatus"] != "success") {
    echo "Not authorized";
} else {

	require_once(__DIR__.'/../../modules/i18n/language.php');

	require_once(__DIR__.'/../../modules/utilities/functions.entities.php');
	require_once(__DIR__."/../../modules/search/include.search.php");
	require_once(__DIR__."/../../api/v1/api.php");

	$parameter = $_REQUEST;
	$parameter["action"] = "search";
	$parameter["itemType"] = "media";
	$result = apiV1($parameter);

	$result_items = $result["data"];

	$totalResults = $result["meta"]["results"]["total"];
	$totalHits = $result["meta"]["results"]["totalHits"] ?? 0;
	
	$totalResultString = $totalResults;

	if ($totalResults >= 10000 ) {
		$totalResultString = "> ".$totalResults;
	}

	$findsString = '';
	if (isset($_REQUEST["q"]) && !empty($_REQUEST["q"])) {
		if ($totalHits > 0) {
			$findsString = str_replace(
				['{hits}', '{speeches}'],
				['<strong>'.$totalHits.'</strong>', '<strong>'.$totalResultString.'</strong>'],
				L::hitsInSpeeches()
			);
		} else {
			$findsString = '<strong>'.$totalResultString.'</strong> '.L::speechesFound();
		}
	} else {
		$findsString = '<strong>'.$totalResultString.'</strong> '.L::speechesFound();
	}

	//echo "<pre style='color:#FFF'>";
	//print_r($result);

	// ELSE FINISHES AT END OF FILE
	if ($totalResults != 0) {
?>
	<div class="filterSummary row">
		<div class="col-12 col-sm-6 mb-3 mb-sm-0 px-0 px-sm-2">
			<label class="col-form-label px-0 me-0 me-sm-1 col-12 col-sm-auto text-center text-sm-left"><?= $findsString ?></label>
		</div>
		<div class="col-12 col-sm-6 pr-0 pr-sm-2" style="text-align: right;">
			<label class="col-form-label" for="sort"><?= L::sortBy(); ?></label>
			<select style="width: auto;" class="form-select form-select-sm ms-1 d-inline-block" id="sort" name="sort">
				<option value="relevance" selected><?= L::relevance(); ?></option>
				<option value="topic-asc"><?= L::topic(); ?> (<?= L::sortByAsc(); ?>)</option>
				<option value="topic-desc"><?= L::topic(); ?> (<?= L::sortByDesc(); ?>)</option>
				<option value="date-asc"><?= L::date(); ?> (<?= L::sortByAsc(); ?>)</option>
				<option value="date-desc"><?= L::date(); ?> (<?= L::sortByDesc(); ?>)</option>
				<option value="duration-asc"><?= L::duration(); ?> (<?= L::sortByDurationAsc(); ?>)</option>
				<?php if ($_SESSION["userdata"]["role"] == "admin") { ?>
					<option value="changed-asc"><?= L::changeDate(); ?> (<?= L::sortByAsc(); ?>)</option>
					<option value="changed-desc"><?= L::changeDate(); ?> (<?= L::sortByDesc(); ?>)</option>
				<?php } ?>
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
								<div class="th-inner"><?= L::session(); ?></div>
							</th>
							<th>
								<div class="th-inner"><?= L::date(); ?></div>
							</th>
							<th>
								<div class="th-inner"><?= L::agendaItem(); ?></div>
							</th>
							<th>
								<div class="th-inner"><?= L::contextmainSpeaker(); ?></div>
							</th>
							<th>
								<div class="th-inner"><?= L::duration(); ?></div>
							</th>
							<th>
								<div class="th-inner"><?= L::lastChanged(); ?></div>
							</th>
							<?php if ($_SESSION["userdata"]["role"] == "admin") { ?>
								<th>
									<div class="th-inner">Aligned</div>
								</th>
								<th>
									<div class="th-inner">Public</div>
								</th>
							<?php } ?>
							<th>
								<div class="th-inner"></div>
							</th>
						</tr>
					</thead>
					<tbody>
					<?php
					$paramStr = "";
					$allowedParams = filterAllowedSearchParams($_REQUEST, 'media');

					$sortFactor = null;
					$currentDate = null;
					$currentAgendaItem = null;
					if (isset($_REQUEST["sort"]) && strlen($_REQUEST["sort"]) > 3) {
						if (strpos($_REQUEST["sort"], 'date') !== false) {
							$sortFactor = 'date';
						} elseif (strpos($_REQUEST["sort"], 'duration') !== false) {
							$sortFactor = 'duration';
						} else {
							$sortFactor = 'topic';
						}
					}
					
					foreach($result_items as $result_item) {
						
						$duration = $result_item["attributes"]['duration'];
						$formattedDuration = $duration >= 3600 ? 
							gmdate("H:i:s", $duration) : 
							gmdate("i:s", $duration);

						$formattedDate = date("d.m.Y", strtotime($result_item["attributes"]["dateStart"]));
						
						$mainSpeaker = getMainSpeakerFromPeopleArray($result_item["annotations"]['data'] ?? [], $result_item["relationships"]["people"]['data'] ?? []);
						$mainFaction = getMainFactionFromOrganisationsArray($result_item["annotations"]['data'] ?? [], $result_item["relationships"]["organisations"]['data'] ?? []);
						$mainSpeakerRole = getRoleFromMainSpeakerAnnotation($result_item["annotations"]['data'] ?? []);

						$speakerName = $mainSpeaker ? $mainSpeaker['attributes']['label'] : '';
						$factionName = $mainFaction ? $mainFaction['attributes']['label'] : '';
						$roleName = $mainSpeakerRole ? translateContextValue($mainSpeakerRole) : '';
						
						// Show speaker name with faction if available, otherwise with role
						$speakerDisplay = $speakerName;
						if ($factionName) {
							$speakerDisplay .= ' (' . $factionName . ')';
						} elseif ($roleName) {
							$speakerDisplay .= ' (' . $roleName . ')';
						}

						$highlightedName = $speakerDisplay;
						if (isset($_REQUEST['person']) && strlen($_REQUEST['person']) > 1) {
							// Escape user input before using in HTML, then apply highlighting safely
							$searchTerm = h($_REQUEST['person']);
							$highlightedName = str_replace($searchTerm, '<em>'.$searchTerm.'</em>', h($highlightedName));
						} else {
							$highlightedName = h($highlightedName);
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
        <?php

        if (!$filterableFactions) {
            $factions = apiV1(array("action" => "search", "itemType" => "organisations", "type" => "faction", "filterable" => 1));
            $filterableFactions = [];
            foreach ($factions["data"] as $faction) {
                $filterableFactions[] = $faction["id"];
            }
        }

        $result["meta"]["attributes"]["resultsPerFaction"] = array_filter(
            $result["meta"]["attributes"]["resultsPerFaction"],
            fn ($k) => in_array($k, $filterableFactions),
            ARRAY_FILTER_USE_KEY
        );

        ?>

        resultsAttributes = <?= json_encode($result["meta"]["attributes"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) ?>

    </script>
	<?php 
	include_once('result.pagination.php');
} else {
	?>
	<div class="filterSummary row">
		<div class="col alert alert-info"><?= L::noRelevant(); ?> <?= L::speechesFound(); ?></div>
	</div>

<?php 
}
}
?>