<?php defined('OPTV') or die(); ?>
<?php
/**
 * Search-grid adapter for the shared speech card.
 *
 * Turns one search hit into the card's inputs — the ?person= highlighting, the
 * snippet links carrying the current filters, the add-to-playlist button — and
 * hands off to media-item.php, which owns the markup.
 *
 * @var array $mediaItem        mediaQueueNode($result_item), built by result-grid.php
 * @var array $result_item      The raw search hit (for _finds and parliament)
 * @var array $allowedParams    Current filters, carried into every link
 * @var string $highlightedName Speaker label, ?person= match wrapped in <em>
 * @var string|null $sortFactor 'topic' groups by agenda item, so the card drops its title
 */

require_once(__DIR__ . '/../../modules/utilities/security.php');

$paramStr = preg_replace('/(%5B)\d+(%5D=)/i', '$1$2', http_build_query($allowedParams));
$itemHref = $config["dir"]["root"].'/media/'.rawurlencode($result_item["id"]).'?'.$paramStr;

$itemActions = '';
// Add-to-playlist: snapshots this speech with its current search hits as
// segments, so the playlist replays exactly the passages that matched.
if (!empty($_SESSION["login"]) && !empty($config["allow"]["playlists"])) {
	// Built with sprintf rather than json_encode: this instance runs
	// serialize_precision=100, which would spell every timecode out to
	// 50 digits inside the attribute. Milliseconds are ample here.
	$itemSegmentsJson = [];
	foreach (($mediaItem["segments"] ?: []) as $segment) {
		$itemSegmentsJson[] = sprintf('{"start":%.3F,"end":%.3F}', $segment["start"], $segment["end"]);
	}
	$itemSegmentsJson = $itemSegmentsJson ? '['.implode(',', $itemSegmentsJson).']' : '';

	$itemActions = '<button type="button" class="btn btn-sm addToPlaylistButton"'
		.' data-media-id="'.hAttr($result_item["id"]).'"'
		.' data-parliament="'.hAttr($mediaItem["attributes"]["parliament"] ?? '').'"'
		.($itemSegmentsJson ? ' data-segments="'.hAttr($itemSegmentsJson).'"' : '')
		.' title="'.hAttr(L::playlistAddTo()).'"><span class="icon-plus"></span></button>';
}

$mediaItemOptions = [
	"href" => $itemHref,
	"snippetHrefBase" => $itemHref,
	"speakerHtml" => $highlightedName,
	"showTitle" => ($sortFactor == null || $sortFactor != 'topic'),
	"snippets" => $result_item["_finds"] ?? null,
	"actionsHtml" => $itemActions,
];

include 'media-item.php';
