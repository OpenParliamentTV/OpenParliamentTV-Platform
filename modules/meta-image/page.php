<?php
/**
 * Meta-image renderers for non-quote pages (entity / media / search / default).
 * Each returns PNG bytes. They build on the shared GD helpers in render.php and
 * the layout config in config.php. Localized labels use the L:: class (the
 * caller initialises LanguageManager with the configured default language).
 *
 * Layout: a shared "header band" holds a leading play glyph (left), the
 * thumbnail/icon (right corner) and a vertically-centred title block (title +
 * optional subtitle or party badge). Below it: an abstract / type-specific body.
 * A footer (speech count left, OPTV wordmark right) closes every card. The
 * dynamic positioning lives in render.php's metaImageDrawCenteredTitleBlock().
 */

require_once(__DIR__ . '/render.php');

function metaImageBaseCanvas(array $cfg) {
    return metaImageCanvas($cfg['canvas']['width'], $cfg['canvas']['height'], $cfg['colors']['background']);
}

/** Full-width left/right margins for body text below the band. */
function metaImageBodyBox(array $cfg) {
    $m = $cfg['header']['marginX'];
    return [$m, $cfg['canvas']['width'] - 2 * $m];
}

/**
 * person / organisation / term / document card.
 *
 * @param array       $data      $apiResult['data'] (JSON:API entity node)
 * @param string|null $countText pre-formatted "<n> <speeches>" footer text
 */
function renderEntityImage($type, array $data, array $cfg, $countText = null) {
    $L = $cfg['entity'];
    $attrs = $data['attributes'] ?? [];
    $id = (string) ($data['id'] ?? '');
    $img = metaImageBaseCanvas($cfg);

    $band = metaImageHeaderBand($cfg);
    metaImageDrawHeaderPlay($img, $cfg, $band);
    metaImageDrawThumbnail($img, $type, $id, $band['thumbX'], $band['thumbY'], $band['thumbD'], $cfg);

    // Persons carry a faction → party badge; other types show their alt label.
    $faction = $data['relationships']['faction']['data'] ?? null;
    $badge = null;
    $subtitle = null;
    if ($type === 'person' && $faction && !empty($faction['attributes']['label'])) {
        $badge = ['label' => $faction['attributes']['label'], 'color' => metaImageHexToRgb($faction['attributes']['color'] ?? '')];
    } elseif ($type !== 'person' && !empty($attrs['labelAlternative'][0])) {
        $subtitle = $attrs['labelAlternative'][0];
    }

    metaImageDrawCenteredTitleBlock($img, $cfg, [
        'bandTop' => $band['top'], 'bandHeight' => $band['height'], 'x' => $band['colX'], 'w' => $band['colW'],
        'title' => (string) ($attrs['label'] ?? ''), 'titleFont' => $cfg['fonts']['bold'], 'titleSize' => $L['fontSize']['title'],
        'titleLH' => $L['titleLineHeight'], 'titleMaxLines' => $L['titleMaxLines'],
        'color' => $cfg['colors']['text'], 'mutedColor' => $cfg['colors']['textMuted'],
        'subtitle' => $subtitle, 'subtitleSize' => $L['fontSize']['subtitle'], 'subtitleMaxChars' => 64,
        'badge' => $badge, 'badgeSize' => $L['fontSize']['badge'],
        'badgeGap' => $cfg['header']['nameBadgeGap'], 'secondaryGap' => $cfg['header']['secondaryGap'],
    ]);

    // Abstract (full width, below the band).
    $abstract = (string) ($attrs['abstract'] ?? '');
    if ($abstract === 'undefined') {
        $abstract = '';
    }
    list($ax, $aw) = metaImageBodyBox($cfg);
    metaImageDrawWrappedText(
        $img, $cfg['fonts']['primary'], $L['fontSize']['abstract'], $cfg['colors']['text'],
        $abstract, $ax, $L['abstract']['y'], $aw, $L['abstract']['maxLines'], $L['abstract']['lineHeight']
    );

    metaImageDrawFooter($img, $cfg, $countText);

    $png = metaImagePng($img);
    imagedestroy($img);
    return $png;
}

/**
 * Info card for structural / date-based types without a photo or abstract
 * (session, electoralPeriod, agendaItem): a type glyph + title + subtitle.
 */
function renderInfoImage($type, $title, $subtitle, array $cfg, $countText = null) {
    $L = $cfg['entity'];
    $img = metaImageBaseCanvas($cfg);

    $band = metaImageHeaderBand($cfg);
    metaImageDrawHeaderPlay($img, $cfg, $band);
    metaImageDrawIcon($img, $type, $band['thumbX'], $band['thumbY'], $band['thumbD'], $cfg);

    metaImageDrawCenteredTitleBlock($img, $cfg, [
        'bandTop' => $band['top'], 'bandHeight' => $band['height'], 'x' => $band['colX'], 'w' => $band['colW'],
        'title' => (string) $title, 'titleFont' => $cfg['fonts']['bold'], 'titleSize' => $L['fontSize']['title'],
        // Agenda-item titles can be long/unpredictable — allow more lines; the
        // block's top is clamped so it grows downward rather than upward.
        'titleLH' => $L['titleLineHeight'], 'titleMaxLines' => 4,
        'color' => $cfg['colors']['text'], 'mutedColor' => $cfg['colors']['textMuted'],
        'subtitle' => $subtitle, 'subtitleSize' => $L['fontSize']['subtitle'], 'subtitleMaxChars' => 90,
        'badge' => null, 'badgeSize' => $L['fontSize']['badge'],
        'badgeGap' => $cfg['header']['nameBadgeGap'], 'secondaryGap' => $cfg['header']['secondaryGap'],
    ]);

    metaImageDrawFooter($img, $cfg, $countText);

    $png = metaImagePng($img);
    imagedestroy($img);
    return $png;
}

/**
 * media page card (no quote selection): play glyph + speech title in the band,
 * speaker thumbnail in the right corner, speaker name (regular) + party badge
 * below, and the date in the footer's left slot (where other cards show the
 * speech count).
 *
 * @param array $args ['speakerId','title','speaker','faction','factionColor','date']
 */
function renderMediaImage(array $args, array $cfg) {
    $L = $cfg['media'];
    $img = metaImageBaseCanvas($cfg);

    $band = metaImageHeaderBand($cfg);
    metaImageDrawHeaderPlay($img, $cfg, $band);
    metaImageDrawThumbnail($img, 'person', (string) ($args['speakerId'] ?? ''), $band['thumbX'], $band['thumbY'], $band['thumbD'], $cfg);

    // Speech title (big) in the band.
    metaImageDrawCenteredTitleBlock($img, $cfg, [
        'bandTop' => $band['top'], 'bandHeight' => $band['height'], 'x' => $band['colX'], 'w' => $band['colW'],
        'title' => (string) ($args['title'] ?? ''), 'titleFont' => $cfg['fonts']['bold'], 'titleSize' => $L['fontSize']['title'],
        'titleLH' => $L['titleLineHeight'], 'titleMaxLines' => $L['titleMaxLines'],
        'color' => $cfg['colors']['text'], 'mutedColor' => $cfg['colors']['textMuted'],
        'subtitle' => null, 'subtitleSize' => $L['fontSize']['speaker'],
        'badge' => null, 'badgeSize' => 24,
        'badgeGap' => $cfg['header']['nameBadgeGap'], 'secondaryGap' => $cfg['header']['secondaryGap'],
    ]);

    // Speaker name (regular weight) + party badge, below the band — same badge
    // treatment as the entity cards.
    list($bx) = metaImageBodyBox($cfg);
    $name = (string) ($args['speaker'] ?? '');
    $nameFont = $cfg['fonts']['bold'];
    $nameSize = $L['fontSize']['speaker'];
    $nameLH = 1.3;
    metaImageDrawText($img, $nameFont, $nameSize, $cfg['colors']['text'], $name, $bx, $L['speaker']['y'], $cfg['canvas']['width'], $nameSize * 1.4, 'left', 'top', $nameLH);
    if (!empty($args['faction'])) {
        list($nameW) = metaImageTextMetrics($nameFont, $nameSize, $name);
        $baseline = metaImageTextBaselineY($L['speaker']['y'], $nameSize, $nameLH) - 5;
        metaImageDrawPartyBadge(
            $img, $args['faction'], (int) round($bx + $nameW + $cfg['header']['nameBadgeGap']), 0,
            $cfg, $L['fontSize']['badge'], metaImageHexToRgb($args['factionColor'] ?? ''), $baseline
        );
    }

    // "Abstract": the start of the speech transcript (if available), 3 lines.
    if (!empty($args['abstract'])) {
        metaImageDrawWrappedText(
            $img, $cfg['fonts']['primary'], $L['fontSize']['abstract'], $cfg['colors']['text'],
            $args['abstract'], $bx, $L['abstract']['y'], metaImageBodyBox($cfg)[1], $L['abstract']['maxLines'], $L['abstract']['lineHeight']
        );
    }

    // Footer: the date sits in the count slot (regular weight).
    metaImageDrawFooter($img, $cfg, $args['date'] ?? null);

    $png = metaImagePng($img);
    imagedestroy($img);
    return $png;
}

/**
 * search results card: magnifier + composed query in the band, stylised result
 * previews below, result count in the footer.
 *
 * @param array $args ['query','count']
 */
function renderSearchImage(array $args, array $cfg) {
    $L = $cfg['search'];
    $h = $cfg['header'];
    $img = metaImageBaseCanvas($cfg);

    $centerY = $h['bandTop'] + $h['bandHeight'] / 2;

    // Magnifier glyph, left, centred in the band.
    $iconSize = $L['iconSize'];
    metaImageDrawGlyphCentered($img, $cfg['iconCodes']['search'], $h['marginX'] + $iconSize / 2, $centerY, $iconSize, $cfg['colors']['text'], $cfg['fonts']['icons']);

    // Query column spans from after the magnifier to the right margin. The
    // endpoint already composed/quoted the criteria label.
    $colX = (int) round($h['marginX'] + $iconSize + 30);
    $colW = $cfg['canvas']['width'] - $h['marginX'] - $colX;
    $query = trim((string) ($args['query'] ?? ''));
    metaImageDrawCenteredTitleBlock($img, $cfg, [
        'bandTop' => $h['bandTop'], 'bandHeight' => $h['bandHeight'], 'x' => $colX, 'w' => $colW,
        'title' => $query, 'titleFont' => $cfg['fonts']['bold'], 'titleSize' => $L['fontSize']['query'],
        'titleLH' => $L['queryLineHeight'], 'titleMaxLines' => $L['queryMaxLines'],
        'color' => $cfg['colors']['text'], 'mutedColor' => $cfg['colors']['textMuted'],
        'subtitle' => null, 'subtitleSize' => 30, 'badge' => null, 'badgeSize' => 24,
        'badgeGap' => 20, 'secondaryGap' => 10,
    ]);

    metaImageDrawSearchResults($img, $cfg, $L['results']);

    metaImageDrawFooter($img, $cfg, metaImageFormatCount($args['count'] ?? null));

    $png = metaImagePng($img);
    imagedestroy($img);
    return $png;
}

/**
 * default / fallback card: brand thumbnail + page title + description.
 */
function renderDefaultImage($title, $description, $thumbnailPath, array $cfg) {
    $L = $cfg['default'];
    $img = metaImageBaseCanvas($cfg);

    if ($thumbnailPath && is_file($thumbnailPath)) {
        $src = @imagecreatefromstring((string) file_get_contents($thumbnailPath));
        if ($src !== false) {
            imagecopyresampled(
                $img, $src,
                $L['thumbnail']['x'], $L['thumbnail']['y'], 0, 0,
                $L['thumbnail']['width'], $L['thumbnail']['height'], imagesx($src), imagesy($src)
            );
            imagedestroy($src);
        }
    }

    $tx = $L['textArea']['x'];
    $tw = $L['textArea']['width'];
    metaImageDrawText(
        $img, $cfg['fonts']['bold'], $L['fontSize']['title'], $cfg['colors']['text'],
        metaImageTruncate($title, 120), $tx, $L['title']['y'], $tw,
        $L['fontSize']['title'] * $L['title']['maxLines'] * 1.3, 'left', 'top', $L['title']['lineHeight']
    );
    metaImageDrawWrappedText(
        $img, $cfg['fonts']['primary'], $L['fontSize']['description'], $cfg['colors']['text'],
        $description, $tx, $L['description']['y'], $tw, $L['description']['maxLines'], $L['description']['lineHeight']
    );

    metaImageDrawFooter($img, $cfg, null);

    $png = metaImagePng($img);
    imagedestroy($img);
    return $png;
}
