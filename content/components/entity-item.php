<?php defined('OPTV') or die(); ?>
<?php
/**
 * One entity, rendered as the platform's `.entityItem` card.
 *
 * Every surface that displays an entity in its reduced form goes through here —
 * the media page's relationships list (via entity-preview-small.php), the
 * statistics panels, the session and electoral-period lists. The client-rendered
 * surfaces (search suggestions, the entity-page statistics cards) build the same
 * DOM from content/client/js/entity-item.js; the two files are mirrors and must
 * be changed together.
 *
 * Input is the flat preview node entityBuildPreviewNodes() produces
 * (modules/entities/preview.php), which is what makes a media annotation, a
 * statistics row and a database row interchangeable here. Everything a single
 * surface needs on top of that — where the card links to, what number it shows,
 * which context line it carries — arrives through $entityItemOptions rather than
 * through more node fields.
 *
 * @var array $entityItem         A preview node.
 * @var array $entityItemOptions  Rendering options, all optional:
 *      href           string  Where the card links to (defaults to the node's).
 *      labelHtml      string  Ready-escaped label markup, overriding `label`
 *                             (the search suggestions use it to <em>-highlight
 *                             the matched substring).
 *      count          int     A number to show as a pill.
 *      countLabel     string  What the number means; becomes its title.
 *      score          float   Relative weight, drawn as a proportional bar.
 *      scoreMax       float   What a full bar means (defaults to `score`).
 *      contextLabel   string  Role line, prefixed with the megaphone icon.
 *      buttonHtml     string  Ready-built markup for the corner button.
 *      showThumbnail  bool    Render the avatar (default true; a type with no
 *                             image of its own opts out and gets a text card).
 *      classes        string  Extra classes on the wrapper (default 'col').
 *      dataAttrs      array   Extra data-* attributes, keyed without prefix.
 */

require_once(__DIR__ . '/../../modules/utilities/security.php');

$entityNode = $entityItem ?? [];
$entityOptions = $entityItemOptions ?? [];

$entityType = $entityNode['type'] ?? '';
$entityFactionID = $entityNode['faction']['id'] ?? '';
$entityHref = $entityOptions['href'] ?? ($entityNode['href'] ?? '');
$entityShowThumbnail = $entityOptions['showThumbnail'] ?? true;

$entityClasses = 'entityItem ' . ($entityOptions['classes'] ?? 'col');

// The container carries the faction colour the same way the speech card does:
// .partyIndicator[data-faction] rules are generated per faction in head.php, and
// an entity with no faction falls back to the plain bordered card.
$entityContainerClasses = 'entityContainer' . ($entityFactionID !== '' ? ' partyIndicator' : '');

// A person's faction is shown as the same inline indicator the speech card and
// the filter chips use, rather than as plain text: it is the platform's standard
// way of naming a faction and it carries the colour. Organisations that ARE a
// faction skip it -- the card is already tinted, and repeating the name below the
// title says nothing.
$entityFactionLabel = $entityNode['faction']['label'] ?? '';
$entityShowFactionBadge = ($entityType === 'person' && $entityFactionID !== '' && $entityFactionLabel !== '');

// A person's secondary label IS their faction (entityBuildPreviewNode decides
// that), so once the badge carries it there is nothing left to put on its own
// line. Anything else — an alternative spelling, a document's author — stays.
$entitySecondaryLabel = $entityNode['secondaryLabel'] ?? '';
if ($entityShowFactionBadge && $entitySecondaryLabel === $entityFactionLabel) {
    $entitySecondaryLabel = '';
}

$entityScore = $entityOptions['score'] ?? null;
$entityScoreMax = $entityOptions['scoreMax'] ?? $entityScore;
$entityBarPercent = ($entityScore !== null && $entityScoreMax > 0)
    ? max(0, min(100, 100 * ($entityScore / $entityScoreMax)))
    : null;
?>
<div class="<?= hAttr($entityClasses) ?>" data-type="<?= hAttr($entityType) ?>" data-entity-id="<?= hAttr($entityNode['id'] ?? '') ?>"<?php
foreach (($entityOptions['dataAttrs'] ?? []) as $dataName => $dataValue) {
	echo ' data-'.hAttr($dataName).'="'.hAttr($dataValue).'"';
} ?>>
	<div class="<?= hAttr($entityContainerClasses) ?>" data-faction="<?= hAttr($entityFactionID) ?>">
		<a href="<?= hAttr($entityHref) ?>">
			<?php if ($entityShowThumbnail): ?>
			<div class="thumbnailContainer">
				<div class="rounded-circle">
					<?php if (!empty($entityNode['thumbnailURI'])): ?>
					<img src="<?= hAttr($entityNode['thumbnailURI']) ?>" alt=""/>
					<?php else: ?>
					<span class="icon-type-<?= hAttr($entityType) ?>"></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
			<div class="entityBody">
				<?php if (!empty($entityOptions['typeLabel'])): ?>
				<div class="entityType"><?= h($entityOptions['typeLabel']) ?></div>
				<?php endif; ?>
				<div class="entityTitle"><?php
				// labelHtml is markup the caller already escaped — the search
				// suggestions pass an <em>-highlighted label through it.
				echo $entityOptions['labelHtml'] ?? h($entityNode['label'] ?? '');
				// The indicator trails the name inline, as it does on the speech
				// card, and wraps with it when the card is too narrow.
				if ($entityShowFactionBadge) {
					echo ' <span class="partyIndicator partyIndicatorInline" data-faction="'.hAttr($entityFactionID).'">'.h($entityFactionLabel).'</span>';
				}
				?></div>
				<?php if ($entitySecondaryLabel !== ''): ?>
				<div class="entitySecondary break-lines truncate-lines"><?= h($entitySecondaryLabel) ?></div>
				<?php endif; ?>
				<?php if (!empty($entityOptions['contextLabel'])): ?>
				<div class="entityContext"><span class="icon-megaphone"></span><?= h($entityOptions['contextLabel']) ?></div>
				<?php endif; ?>
				<?php if (isset($entityOptions['count']) && $entityOptions['count'] > 0): ?>
				<div class="entityCount"<?php if (!empty($entityOptions['countLabel'])) {
					echo ' title="'.hAttr($entityOptions['countLabel']).'"';
				} ?>><span class="badge rounded-pill"><?= h(number_format($entityOptions['count'])) ?></span></div>
				<?php endif; ?>
				<?php if ($entityBarPercent !== null): ?>
				<div class="entityBar"><span style="width: <?= hAttr(round($entityBarPercent, 2)) ?>%;"></span></div>
				<?php endif; ?>
			</div>
		</a>
		<?php if (!empty($entityOptions['buttonHtml'])) {
			echo $entityOptions['buttonHtml'];
		} ?>
	</div>
</div>
