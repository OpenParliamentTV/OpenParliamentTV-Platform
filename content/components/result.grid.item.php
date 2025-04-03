<?php 
if (isset($result_item["_finds"]) && count($result_item['_finds']) > 0) {
	$snippets = $result_item["_finds"];
} else {
	$snippets = null;
}
$paramStr = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams));

?>
<article class="resultItem col<?= ($snippets !== null) ? ' snippets' : '' ?>" data-speech-id="<?= $result_item["id"] ?>" data-faction="<?= $mainFaction["id"] ?>">
	<div class="resultContent partyIndicator" data-faction="<?= $mainFaction["id"] ?>">
		<a style="display: block;" href='<?= $config["dir"]["root"] ?>/media/<?= $result_item["id"]."?".$paramStr ?>'>
			<div class="icon-play-1"></div>
			<div class="resultDuration"><?= $formattedDuration ?></div>
			<div class="resultDate"><?= $formattedDate ?></div>
			<div class="resultMeta">
				<?php 
				if (isset($mainFaction['attributes']['label'])) {
					echo $highlightedName .' ('.$mainFaction['attributes']['label'].')';
				} else {
					echo $highlightedName;
				}
				?>
			</div>
			<hr>
			<?php
			if ($sortFactor == null || $sortFactor != 'topic') {
			?>
				<div class="resultTitle"><?=$result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"]?>
				</div>
			<?php
			}
			?>
		</a>
		<?php 
		if ($snippets) {
			echo '<div class="resultSnippets">';
		}
		if ($snippets) {
			foreach($snippets as $snippet) {
				?>
				<a class="resultSnippet" href='media/<?= $result_item["id"]."?".$paramStr.'&t='.$snippet['data-start'] ?>' title="▶ Ausschnitt direkt abspielen"><?= $snippet['context'] ?></a>
				<?php
			}
		}
		
		if ($snippets) {
			echo '</div>';
			echo '<div class="resultTimeline">';
		}

		if ($snippets && $result_item["attributes"]['duration'] !== 0) {
			?>
			<span class="badge badge-primary badge-pill"><?=count($snippets)?></span>
			<?php
			foreach($snippets as $snippet) {
			
					$leftPercent = 100 * ((float)$snippet["data-start"] / $result_item["attributes"]["duration"]);
					$widthPercent  = 100 * (($snippet['data-end'] - $snippet['data-start']) / $result_item["attributes"]['duration']);
				?>
				<div class="hit" style="left: <?= $leftPercent ?>%; width: <?= $widthPercent ?>%;"></div>
				<?php
			}
		}
		
		if ($snippets) {
			echo '</div>';
		}
		?>
	</div>
</article>