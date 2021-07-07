<article class="resultItem col" data-speech-id="<?= $result_item["id"] ?>" data-faction="<?= $result_item["relationships"]["organisations"]["data"][0]["id"] ?>">
	<div class="resultContent partyIndicator" data-faction="<?= $result_item["relationships"]["organisations"]["data"][0]["id"] ?>">
		<a style="display: block;" href='<?= $config["dir"]["root"] ?>/media/<?= $result_item["id"].$paramStr ?>'>
			<div class="icon-play-1"></div>
			<div class="resultDuration"><?= $formattedDuration ?></div>
			<div class="resultDate"><?= $formattedDate ?></div>
			<div class="resultMeta">
				<?= $highlightedName .' ('.$result_item["relationships"]["organisations"]["data"][0]["attributes"]["labelAlternative"].')' ?>
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
		if (isset($result_item["_finds"]) && count($result_item["_finds"]) > 0) {
			echo '<div class="resultSnippets">';
		}
		if (isset($result_item['_finds'])) {
			foreach($result_item['_finds'] as $result) {
				?>
				<a class="resultSnippet" href='media/<?= $result_item["id"].$paramStr.'#t='.$result['data-start'] ?>' title="▶ Ausschnitt direkt abspielen"><?= $result['context'] ?></a>
				<?php
			}
		}
		
		if (isset($result_item["finds"]) && count($result_item['finds']) > 0) {
			echo '</div>';
			echo '<div class="resultTimeline">';
		}

		if (isset($result_item['finds'])) {
			?>
			<span class="badge badge-primary badge-pill"><?=count($result_item["finds"])?></span>
			<?php
			foreach($result_item['finds'] as $result) {
			
					$leftPercent = 100 * ((float)$result["data-start"] / $result_item["_source"]["meta"]["duration"]);
					$widthPercent  = 100 * (($result['data-end'] - $result['data-start']) / $result_item["_source"]["meta"]['duration']);
				?>
				<div class="hit" style="left: <?= $leftPercent ?>%; width: <?= $widthPercent ?>%;"></div>
				<?php
			}
		}
		
		if (isset($result_item["finds"]) && count($result_item['finds']) > 0) {
			echo '</div>';
		}
		?>
	</div>
</article>