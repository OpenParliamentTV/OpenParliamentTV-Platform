<?php 
require_once(__DIR__ . '/../../modules/utilities/security.php');

if (isset($result_item["_finds"]) && count($result_item['_finds']) > 0) {
	$snippets = $result_item["_finds"];
} else {
	$snippets = null;
}
$paramStr = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams));

?>
<article class="resultItem col<?= ($snippets !== null) ? ' snippets' : '' ?>" data-speech-id="<?= hAttr($result_item["id"]) ?>" data-faction="<?= $mainFaction ? hAttr($mainFaction["id"]) : '' ?>">
	<div class="resultContent partyIndicator" data-faction="<?= $mainFaction ? hAttr($mainFaction["id"]) : '' ?>">
		<a style="display: block;" href='<?= $config["dir"]["root"] ?>/media/<?= hAttr($result_item["id"]."?".$paramStr) ?>'>
			<div class="icon-play-1"></div>
			<div class="resultDuration"><?= $formattedDuration ?></div>
			<div class="resultDate"><?= $formattedDate ?></div>
			<div class="resultMeta">
				<?php 
				if (isset($mainFaction['attributes']['label'])) {
					echo $highlightedName .' <span class="partyIndicator partyIndicatorInline" data-faction="'.hAttr($mainFaction['id']).'">'.h($mainFaction['attributes']['label']).'</span>';
				} else {
					echo $highlightedName;
				}
				?>
			</div>
			<hr>
			<?php
			if ($sortFactor == null || $sortFactor != 'topic') {
			?>
				<div class="resultTitle"><?= h($result_item["relationships"]["agendaItem"]["data"]["attributes"]["title"]) ?>
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
				<a class="resultSnippet" href='media/<?= hAttr($result_item["id"]."?".$paramStr.'&t='.$snippet['data-start']) ?>' title="▶ Ausschnitt direkt abspielen"><?= $snippet['context'] ?></a>
				<?php
			}
		}
		
		if ($snippets) {
			echo '</div>';
			echo '<div class="hitTimeline">';
		}

		if ($snippets && $result_item["attributes"]['duration'] !== 0) {
			?>
			<!-- <span class="termFrequency badge badge-primary badge-pill"><?= h($result_item["highlight_count"]) ?></span> -->
			<?php
			foreach($snippets as $snippet) {
				if ($result_item["attributes"]['duration'] > 0) {
					$leftPercent = 100 * ((float)$snippet["data-start"] / $result_item["attributes"]["duration"]);
					$widthPercent  = 100 * (($snippet['data-end'] - $snippet['data-start']) / $result_item["attributes"]['duration']);
					?>
					<div class="hit" style="left: <?= hAttr($leftPercent) ?>%; width: <?= hAttr($widthPercent) ?>%;"></div>
					<?php
				}
			}
		}
		
		if ($snippets) {
			echo '</div>';
		}
		?>
	</div>
</article>