<?php defined('OPTV') or die(); ?>
<?php
/**
 * One speech, rendered as the platform's `.resultItem` card.
 *
 * Every surface that displays a media item goes through here — the search grid
 * and the entity detail tabs (via result-grid-item.php) and the playlist page.
 * The two client-rendered surfaces (the player's queue panel, the notifications
 * list) build the same DOM from content/client/js/media-item.js; the two files
 * are mirrors and must be changed together.
 *
 * Input is the flat queue node mediaQueueNode() produces (modules/entities/
 * media.php), which is what makes a search hit, a stored playlist item and a
 * queue row interchangeable here. Everything a single surface needs on top of
 * that — where the card links to, its position, what you can do with it —
 * arrives through $mediaItemOptions rather than through more node fields.
 *
 * @var array $mediaItem         A mediaQueueNode()
 * @var array $mediaItemOptions  Rendering options, all optional except `href`:
 *      href              string  Where the card links to.
 *      position          int     Position number to show in the top-left gutter.
 *      speakerHtml       string  Ready-escaped speaker markup, overriding
 *                                attributes.speaker (the search grid uses it to
 *                                <em>-highlight a ?person= match).
 *      showTitle         bool    Render the agenda item title (default true;
 *                                the grid drops it when already grouping by it).
 *      snippets          array   Raw `_finds` — matched passages to quote.
 *      snippetHrefBase   string  Base URL for snippet links; `&t=<start>` is
 *                                appended (defaults to `href`).
 *      actionsHtml       string  Ready-built buttons for the footer row.
 *      classes           string  Extra classes on the <article> (default 'col').
 *      dataAttrs         array   Extra data-* attributes, keyed without prefix.
 */

require_once(__DIR__ . '/../../modules/utilities/security.php');

$itemAttributes = $mediaItem["attributes"] ?? [];
$itemOptions = $mediaItemOptions ?? [];

$itemSnippets = !empty($itemOptions["snippets"]) ? $itemOptions["snippets"] : null;
$itemSegments = !empty($mediaItem["segments"]) ? $mediaItem["segments"] : null;
$itemDuration = (float)($itemAttributes["duration"] ?? 0);
$itemFaction = $itemAttributes["factionID"] ?? '';

// The hit count stands in for the passages on cards that carry the segments but
// not the text to quote them with — a stored playlist has no search behind it.
$itemHitCount = (int)($itemAttributes["findCount"] ?? 0);
$showHitCount = $itemHitCount > 0 && $itemSnippets === null;

$itemClasses = 'resultItem ' . ($itemOptions["classes"] ?? 'col');
if ($itemSegments) {
    $itemClasses .= ' hits';
}
if (isset($itemOptions["position"])) {
    $itemClasses .= ' numbered';
}

$formattedDuration = $itemDuration >= 3600 ? gmdate("H:i:s", $itemDuration) : gmdate("i:s", $itemDuration);
$formattedDate = !empty($itemAttributes["dateStart"]) ? date("d.m.Y", strtotime($itemAttributes["dateStart"])) : '';
$speakerHtml = $itemOptions["speakerHtml"] ?? h($itemAttributes["speaker"] ?? '');
$snippetHrefBase = $itemOptions["snippetHrefBase"] ?? ($itemOptions["href"] ?? '');
?>
<article class="<?= hAttr($itemClasses) ?>" data-speech-id="<?= hAttr($mediaItem["id"]) ?>" data-faction="<?= hAttr($itemFaction) ?>"<?php
foreach (($itemOptions["dataAttrs"] ?? []) as $dataName => $dataValue) {
	echo ' data-'.hAttr($dataName).'="'.hAttr($dataValue).'"';
} ?>>
	<div class="resultContent partyIndicator" data-faction="<?= hAttr($itemFaction) ?>">
		<a class="resultLink" href="<?= hAttr($itemOptions["href"] ?? '') ?>">
			<div class="icon-play-1"></div>
			<?php if (isset($itemOptions["position"])): ?>
			<div class="resultPosition"><?= h($itemOptions["position"]) ?></div>
			<?php endif; ?>
			<div class="resultDuration"><?= h($formattedDuration) ?></div>
			<div class="resultDate"><?= h($formattedDate) ?><?php
			if ($showHitCount) {
				echo '<span class="resultHitCount" title="'.hAttr(L::playlistSegment()).'"><span class="icon-search"></span>'.h($itemHitCount).'</span>';
			}
			?></div>
			<div class="resultMeta">
				<?php
				if (!empty($itemAttributes["faction"])) {
					echo $speakerHtml.' <span class="partyIndicator partyIndicatorInline" data-faction="'.hAttr($itemFaction).'">'.h($itemAttributes["faction"]).'</span>';
				} else {
					echo $speakerHtml;
				}
				?>
			</div>
			<hr>
			<?php if (($itemOptions["showTitle"] ?? true)): ?>
			<div class="resultTitle"><?= h($itemAttributes["title"] ?? '') ?></div>
			<?php endif; ?>
		</a>
		<?php
		if ($itemSnippets) {
			echo '<div class="resultSnippets">';
			foreach ($itemSnippets as $snippet) {
				// context is the search highlight HTML OpenSearch produced (safe
				// <em> markup), so it goes out unescaped — as it does everywhere
				// search results are quoted.
				?>
				<a class="resultSnippet" href="<?= hAttr($snippetHrefBase.'&t='.$snippet['data-start']) ?>" title="<?= hAttr(L::playlistSegment()) ?>"><?= $snippet['context'] ?></a>
				<?php
			}
			echo '</div>';
		}

		if (!empty($itemOptions["actionsHtml"])) {
			echo '<div class="resultActions">'.$itemOptions["actionsHtml"].'</div>';
		}

		if ($itemSegments && $itemDuration > 0) {
			echo '<div class="hitTimeline">';
			foreach ($itemSegments as $segment) {
				// Clamp into the [0,100]% track: alignment timecodes can exceed
				// the media duration (e.g. when the aligned audio was longer
				// than the published clip), which would otherwise push the .hit
				// outside .hitTimeline/.resultItem and break the layout.
				$leftPercent = max(0, min(100, 100 * ((float)$segment["start"] / $itemDuration)));
				$rawWidth = 100 * (((float)$segment["end"] - (float)$segment["start"]) / $itemDuration);
				$widthPercent = max(0, min(100 - $leftPercent, $rawWidth));
				echo '<div class="hit" style="left: '.hAttr($leftPercent).'%; width: '.hAttr($widthPercent).'%;"></div>';
			}
			echo '</div>';
		}
		?>
	</div>
</article>
