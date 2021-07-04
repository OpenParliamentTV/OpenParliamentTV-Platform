<?php	
	error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
	if (!function_exists("L")) {
		require_once(__DIR__."/../../../i18n.class.php");
		$i18n = new i18n(__DIR__.'/../../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../../langcache/', 'de');
		$i18n->init();
	}
	//require_once(__DIR__."/../../../modules/search/functions.php");
	//require_once(__DIR__."/../../../modules/search/include.search.php");
	/*

	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';
	*/
	require_once(__DIR__."/../../../api/v1/api.php");


	if (!$_REQUEST["a"] || count($_REQUEST) < 2) {
	?>
	


	<div class="row justify-content-center">
		<div id="introHint" class="col-11 col-md-8 col-lg-6 col-xl-5">
			<img src="content/client/images/arrow.png" class="bigArrow d-none d-md-inline">
			<div class="introHintText">
				<b><?= $indexCount ?> <?php echo L::speeches; ?></b>
				<ul>
					<li><?php echo L::featureBullet1; ?></li>
					<li><?php echo L::featureBullet2; ?></li>
				</ul>
				<?php echo L::examples; ?>: <br>
			</div>
		</div>
	</div>

	<?php
	} else {

		$parameter = $_REQUEST;
		$parameter["action"] = "search";
		$parameter["itemType"] = "media";
		$result = apiV1($parameter);

		/*
		echo '<pre>';
		print_r($result);
		echo '</pre>';
		*/

		$result_items = $result["data"];

		$totalResults = $result["meta"]["results"]["total"];
		
		$totalResultString = $totalResults;

		if ($totalResults >= 10000 ) {
			$totalResultString = "> ".$totalResults;
		}

		//TODO: Check where to get totalFinds
		/*
		if ($result["totalFinds"] > 0) {
			$findsString = '<strong>'.$result["totalFinds"].'</strong> Treffer in ';
		} else {
			$findsString = '';
		}
		*/
		$findsString = '';
	
	// ELSE FINISHES AT END OF FILE

	/*
	echo '<pre>';
	print_r($result_items[0]);
	echo '</pre>';
	*/
?>
<div class="filterSummary row">
	<div class="col-6" style="padding-left: 10px;"><label class="col-form-label"><?= $findsString ?><strong><?= $totalResultString ?></strong> Reden gefunden</label>
	<button type="button" id="play-submit" class="btn btn-sm btn-outline-primary">Alle automatisch abspielen<span class="icon-play-1"></span></button></div>
	<div class="col-6" style="text-align: right; padding-right: 10px;">
		<label class="col-form-label" for="sort">Sortieren nach</label>
		<select style="width: auto;" class="custom-select custom-select-sm" id="sort" name="sort">
			<option value="relevance" selected>Relevanz</option>
			<option value="topic-asc">Thema (aufsteigend)</option>
			<option value="topic-desc">Thema (absteigend)</option>
			<option value="date-asc">Datum (aufsteigend)</option>
			<option value="date-desc">Datum (absteigend)</option>
		</select>
	</div>
</div>
<div class="resultList row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
	<?php
	$paramStr = "";
	$allowedParams = array_intersect_key($_REQUEST,array_flip(array("q","name","party","electoralPeriod","electoralPeriodID","agendaItemID","timefrom","timeto","gender","degree","aw_uuid","personID","organisationID","documentID","termID","sessionNumber","sessionID","page","sort")));
	$paramCount = 1;
	foreach ($allowedParams as $k=>$v) {
		if ($paramCount == 1) {
			$paramPrefix = "?";
		} else {
			$paramPrefix = "&";
		}
		if (is_array($v)) {
			foreach ($v as $i) {
				if (strlen($i) == 0) { continue; }
				$paramStr .= $paramPrefix.$k."[]=".$i;
			}
		} else if (strlen($v) > 0) {
			$paramStr .= $paramPrefix.$k."=".$_REQUEST[$k];
		}
		$paramCount++;

	}

	$sortFactor = null;
	$currentDate = null;
	$currentAgendaItem = null;
	if (isset($_REQUEST["sort"]) && strlen($_REQUEST["sort"]) > 3) {
		$sortFactor = (strpos($_REQUEST["sort"], 'date') !== false) ? 'date' : 'topic';
	}
	
	foreach($result_items as $result_item) {
		
		$formattedDuration = gmdate("i:s", $result_item["attributes"]['duration']);

		$formattedDate = date("d.m.Y", strtotime($result_item["attributes"]["dateStart"]));

		if ($sortFactor == 'date' && $formattedDate != $currentDate) {
			echo '<div class="sortDivider"><b>'.$formattedDate.'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
			$currentDate = $formattedDate;
		} elseif ($sortFactor == 'topic' && $result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"] != $currentAgendaItem) {
			echo '<div class="sortDivider"><b>'.$formattedDate.' - '.$result_item["_source"]["meta"]["agendaItemSecondTitle"].'</b><span class="icon-down" style="font-size: 0.9em;"></span></div>';
			$currentAgendaItem = $result_item["relationships"]["agendaItem"]["data"]["attributes"]["officialTitle"];
		}

		$highlightedName = $result_item["relationships"]["people"]["data"][0]["attributes"]["label"];
		if (strlen($_REQUEST['person']) > 1) {
			$highlightedName = str_replace($_REQUEST['person'], '<em>'.$_REQUEST['person'].'</em>', $highlightedName);
		}

		include 'content.result.item.php';
	}
	?>
</div>
<?php 
	include_once('content.result.pagination.php');
}
?>