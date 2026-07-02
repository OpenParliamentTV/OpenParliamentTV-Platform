<?php
/**
 * Layout / style configuration for the meta-image renderers (OG / social card
 * images served via content/client/images/meta-image.php).
 *
 * The cards share a "header band": a fixed-height region at the top in which a
 * leading play glyph (left) and the entity thumbnail/icon (right corner) are
 * vertically centred, and the title block (title + optional subtitle or party
 * badge) is vertically centred independently. Below the band comes the abstract,
 * and a footer (speech count left, OPTV wordmark right). The dynamic layout maths
 * lives in render.php (metaImageDrawCenteredTitleBlock, metaImageWrapText, …).
 *
 * Quote images keep their own light/dark themes inline in quote.php; the light
 * theme uses the same palette as below.
 */

return [
    'canvas' => [
        'width'  => 1200,
        'height' => 630,
    ],

    // Palette — light theme. background #f9fafb, foreground #535263.
    'colors' => [
        'background'  => [249, 250, 251],
        'text'        => [83, 82, 99],
        'textMuted'   => [142, 142, 153],
        'iconBg'      => [255, 255, 255],
        'badgeBorder' => [142, 142, 153],
        'divider'     => [223, 224, 229],
        'resultLine'  => [205, 206, 213],
    ],

    'fonts' => [
        // Two weights only: "normal" text is Open Sans Light, "bold"/emphasis
        // (titles, names, the count number, the "Parliament TV" wordmark) is
        // Open Sans Regular.
        'primary' => __DIR__ . '/OpenSans-Light.ttf',   // normal text
        'bold'    => __DIR__ . '/OpenSans-Regular.ttf',  // emphasised / headings
        // The FrameTrail entity icons. WOFF2 can't be rendered by GD; this TTF
        // (added alongside the woff2) carries the same glyph codepoints.
        'icons'   => __DIR__ . '/../../content/client/fonts/FrameTrail_Icons/frametrail-icons.ttf',
    ],

    'logo' => [
        'path' => __DIR__ . '/optv-logo.png',
    ],

    // FrameTrail icon glyph codepoints (hex), mirroring the icon-type-* CSS
    // classes in content/client/css/frametrail-icons.css.
    'iconCodes' => [
        'person'       => 'e8bd',
        'organisation' => 'f19c',
        'term'         => 'e8b4',
        'document'     => 'f0f6',
        'search'       => 'e801', // icon-search (magnifier)
        'play'         => 'e84c', // icon-play (leading indicator, left side)
        // Structural / date-based types (no photo) reuse the glyphs the web UI
        // uses for them (buildEntityMeta in modules/routing/handlers.php).
        'session'         => 'e8bb', // icon-group
        'electoralPeriod' => 'e870', // icon-check
        'agendaItem'      => 'f0cb', // icon-list-numbered
    ],

    // Per-entity-type thumbnail fit/position, mirroring the CSS rules in
    // content/client/css/style.css (.rounded-circle img) and the inline
    // $typeImageFit/$typeImagePosition in content/components/entity.preview*.php:
    //   - person/term/document: cover, anchored to the top (don't crop faces)
    //   - organisation: contain (whole logo), centered, scaled to 0.7 (padding)
    'thumbnailFit' => [
        'default'      => ['fit' => 'cover',   'position' => 'top'],
        'organisation' => ['fit' => 'contain', 'position' => 'center', 'scale' => 0.7],
    ],

    // Shared header band: a leading play glyph (left) and the thumbnail (right
    // corner) are centred vertically in the band; the title block is centred
    // independently. The play glyph is a fixed size (not tied to the title).
    'header' => [
        'marginX'       => 64,
        'bandTop'       => 52,
        'bandHeight'    => 184,
        'thumbDiameter' => 144,
        'playSize'      => 84,  // leading play glyph
        'colX'          => 190, // text column left (clears the play glyph)
        'colRightGap'   => 44,  // gap between text column and the right thumbnail
        'nameBadgeGap'  => 34,  // gap between name and an inline party badge
        'secondaryGap'  => 10,  // gap between title and subtitle / wrapped badge
    ],

    // Footer: speech count (left) + OPTV mark & wordmark (right) over a divider.
    'footer' => [
        'baselineY'     => 578,
        'dividerY'      => 508,
        'countSize'     => 30,
        'wordmarkSize'  => 30,
        'markHeight'    => 80,
        'markGap'       => 28,
        'dividerExtend' => 0.22, // divider extends this fraction of the wordmark width past it (left)
    ],

    // ---- per-type layouts ----

    // person / organisation / term / document
    // Two type sizes only: the title, and one "normal" size for everything else
    // (subtitle, abstract). NB: text drawn via gd-text (metaImageDrawText) renders
    // at 0.75 × the nominal size, whereas the footer count/wordmark use raw
    // imagetttext. So to match the footer's visual 30, the gd-text body size is
    // 30 / 0.75 = 40, and the title is sized up proportionally. All text uses the
    // foreground colour; hierarchy is by weight, not size or greying.
    'entity' => [
        'fontSize' => ['title' => 56, 'subtitle' => 40, 'abstract' => 40, 'badge' => 24],
        'titleLineHeight' => 1.1,
        'titleMaxLines'   => 2,
        'abstract' => ['y' => 256, 'maxLines' => 4, 'maxChars' => 180, 'lineHeight' => 1.35],
    ],

    // media page without a quote selection (title in band, speaker + date below)
    'media' => [
        // Speaker name at the same size as the title; party badge sized up to match.
        // Below the name a 3-line "abstract" shows the start of the speech.
        'fontSize' => ['title' => 54, 'speaker' => 54, 'abstract' => 40, 'date' => 40, 'badge' => 30],
        'titleLineHeight' => 1.12,
        'titleMaxLines'   => 2,
        'speaker'  => ['y' => 228],
        'abstract' => ['y' => 308, 'maxLines' => 3, 'lineHeight' => 1.35],
    ],

    // search results: magnifier + query in the band, stylised result previews below
    'search' => [
        'iconSize'        => 60,
        'fontSize'        => ['query' => 58],
        'queryLineHeight' => 1.1,
        'queryMaxLines'   => 2,
        'queryMaxChars'   => 70,
        // 5 result previews in a row; the last is faded (more results continue).
        'results' => ['count' => 5, 'x' => 64, 'y' => 282, 'boxW' => 150, 'boxH' => 172, 'gap' => 24],
    ],

    // default / fallback (brand thumbnail + page title)
    'default' => [
        'thumbnail' => ['x' => 60, 'y' => 150, 'width' => 300, 'height' => 300],
        'textArea'  => ['x' => 410, 'width' => 730],
        'fontSize'  => ['title' => 54, 'description' => 40],
        'title'       => ['y' => 215, 'maxLines' => 3, 'lineHeight' => 1.15],
        'description' => ['y' => 385, 'maxLines' => 4, 'maxChars' => 210, 'lineHeight' => 1.35],
    ],
];
