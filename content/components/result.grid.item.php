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
					$duration = (float)$result_item["attributes"]["duration"];
					$start = (float)$snippet["data-start"];
					$end = (float)$snippet['data-end'];
					// Clamp into the [0,100]% track: alignment timecodes can exceed
					// the media duration (e.g. when the aligned audio was longer
					// than the published clip), which would otherwise push the .hit
					// outside .hitTimeline/.resultItem and break the layout.
					$leftPercent = max(0, min(100, 100 * ($start / $duration)));
					$rawWidth = 100 * (($end - $start) / $duration);
					$widthPercent = max(0, min(100 - $leftPercent, $rawWidth));
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