<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__."/../../config.php");
require_once(__DIR__ . '/../../modules/utilities/security.php');
applySecurityHeaders();



include_once(__DIR__ . '/../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", "results");

if ($auth["meta"]["requestStatus"] != "success") {
    echo "Not authorized";
} else {

require_once(__DIR__."/../../modules/utilities/language.php");

require_once(__DIR__.'/../../modules/utilities/functions.entities.php');
require_once(__DIR__."/../../modules/search/include.search.php");
require_once(__DIR__."/../../api/v1/api.php");



if (!isset($_REQUEST["a"]) || count($_REQUEST) < 2 ||
	((isset($_REQUEST["queryOnly"]) && $_REQUEST["queryOnly"] == 1) && !isset($_REQUEST["q"]) && !isset($_REQUEST["personID"]) && !isset($_REQUEST["organisationID"]) && !isset($_REQUEST["documentID"]) && !isset($_REQUEST["termID"]))) {
?>

<div class="row justify-content-center">
	<div id="introHint" class="col-11 col-md-10 col-lg-8 col-xl-6">
		<img src="content/client/images/arrow.png" class="bigArrow d-none d-md-inline">
		<div class="introHintText mt-0 mt-md-2">
			<h1 class="mb-3 introClaim"><?= L::claimShort(); ?></h1>
			<div><b><span class="countup"><?= $indexCount ?></span> <?= L::speeches(); ?></b> <?= L::inTheGermanBundestag(); ?></div>
			<ul>
				<li><?= L::featureBullet1(); ?></li>
				<li><?= L::featureBullet2(); ?></li>
				<li><?= L::fromTime(); ?> 2013 <?= L::until(); ?> <?= L::today(); ?></li>
				<li><?= L::moreParliamentsSoon(); ?> <br class="d-inline d-sm-none"/>(<a href='https://openparliament.tv'> <u><?= L::moreInfo(); ?></u> </a>)</li>
			</ul>
			<!--
			<div class="text-center alert mt-3 px-1 py-0 alert-info" style="font-size: 14px;"><span class="icon-attention me-1"></span><a href="<?= $config["dir"]["root"] ?>/announcements" style="color: inherit; text-decoration: underline;"><?= L::messageAnnouncementCurrentState(); ?></a></div>
			-->
		</div>
	</div>
</div>
<div class="row justify-content-center">
	<div class="examplesContainer mt-3 mb-5 col-11 col-md-10 col-lg-8 col-xl-6"><?= L::examples(); ?>: <br>
		<a href='<?= $config["dir"]["root"] ?>/search?q=Mietpreisbremse'>Mietpreisbremse</a><a href='<?= $config["dir"]["root"] ?>/search?q=Rente'>Rente</a><a href='<?= $config["dir"]["root"] ?>/search?q=Brexit'>Brexit</a><a href='<?= $config["dir"]["root"] ?>/search?q=Pariser%20Abkommen'>Pariser Abkommen</a><a href='<?= $config["dir"]["root"] ?>/search?q=NetzDG'>NetzDG</a><a href='<?= $config["dir"]["root"] ?>/search?q=BAMF'>BAMF</a><a href='<?= $config["dir"]["root"] ?>/search?q=Klimawandel'>Klimawandel</a><a href='<?= $config["dir"]["root"] ?>/search?q=Lobbyregister'>Lobbyregister</a><a href='<?= $config["dir"]["root"] ?>/search?q=Pflegeversicherung'>Pflegeversicherung</a><a href='<?= $config["dir"]["root"] ?>/search?q=Datenschutz-Grundverordnung'>Datenschutz-Grundverordnung</a><a href='<?= $config["dir"]["root"] ?>/search?q=Katze%20Sack'>Katze im Sack</a><a href='<?= $config["dir"]["root"] ?>/search?q=Hase%20Igel'>Hase und Igel</a><a href='<?= $config["dir"]["root"] ?>/search?q=%22das%20ist%20die%20Wahrheit%22'>"das ist die Wahrheit"</a><a href='<?= $config["dir"]["root"] ?>/search?q=Tropfen%20hei%C3%9Fen%20Stein'>Tropfen auf den heißen Stein</a>
	</div>
</div>

<?php
} else {

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

// ELSE FINISHES AT END OF FILE
//echo "<pre style='color:#FFF'>";
//print_r($result);

if ($totalResults != 0) {
?>
	<div class="filterSummary row">
		<div class="col-12 col-sm-6 mb-3 mb-sm-0 px-0 px-sm-2"><label class="col-form-label px-0 me-0 me-sm-1 col-12 col-sm-auto text-center text-sm-left"><?= $findsString ?></label>
			<button type="button" id="play-submit" class="btn btn-sm btn-outline-primary rounded-pill col-12 col-sm-auto" style="background-color: var(--highlight-color); color: var(--primary-bg-color);"><?= L::autoplayAll(); ?><span class="icon-play-1"></span></button>
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
				<option value="duration-desc"><?= L::duration(); ?> (<?= L::sortByDurationDesc(); ?>)</option>
			</select>
		</div>
	</div>
	<div class="resultList row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
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
			
			$mainSpeaker = getMainSpeakerFromPeopleArray($result_item["annotations"]['data'], $result_item["relationships"]["people"]['data']);
			$mainFaction = getMainFactionFromOrganisationsArray($result_item["annotations"]['data'], $result_item["relationships"]["organisations"]['data']);

			if ($sortFactor == 'topic' && $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"] != $currentAgendaItem) {
				echo '<div class="sortDivider"><b>'.h($formattedDate).' - '.h($result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"]).'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
				$currentAgendaItem = $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"];
			} elseif ($sortFactor == 'date' && $formattedDate != $currentDate) {
				//echo '<div class="sortDivider"><b>'.$formattedDate.'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
				$currentDate = $formattedDate;
			} elseif ($sortFactor == 'duration') {
				// No divider needed for duration sorting as items are already sorted by duration
			}

			$highlightedName = $mainSpeaker['attributes']['label'];
			if (isset($_REQUEST['person']) && strlen($_REQUEST['person']) > 1) {
				// Escape user input before using in HTML, then apply highlighting safely
				$searchTerm = h($_REQUEST['person']);
				$highlightedName = str_replace($searchTerm, '<em>'.$searchTerm.'</em>', h($highlightedName));
			} else {
				$highlightedName = h($highlightedName);
			}

			include 'result.grid.item.php';
		}
		?>
	</div>
    <?php
    //print_r($result);
    ?>
    <script type="text/javascript">

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
}
?>