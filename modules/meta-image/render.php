<?php
/**
 * Low-level GD drawing helpers shared by the meta-image page renderers
 * (entity / media / search / default) in page.php.
 *
 * Builds on the Box/Color text classes in gd-text.php (the same library the
 * quote renderer uses) and on the existing entity-thumbnail cache in
 * modules/images/functions.php (optvImageEnsureCached) so social cards composite
 * the same locally-cached thumbnails the site already serves.
 */

require_once(__DIR__ . '/gd-text.php');

// gd-text's Box renders glyphs at getFontSizeInPoints() = 0.75 * fontSize, so a
// raw imagettfbbox() at the nominal size over-measures. Use this factor whenever
// measuring the on-canvas width/height of text drawn via metaImageDrawText().
const META_IMAGE_GDTEXT_FACTOR = 0.75;
// gd-text's default baseline factor (Box::$baseline): a top-aligned line's
// baseline sits at boxTop + lineHeight*fontSize*(1 - 0.2).
const META_IMAGE_GDTEXT_BASELINE = 0.2;

/**
 * Width/height a string occupies on canvas when drawn via metaImageDrawText()
 * (i.e. through gd-text's Box), accounting for the 0.75 size factor.
 *
 * @return array [width, height] in pixels
 */
function metaImageTextMetrics($fontPath, $fontSize, $text) {
    $bbox = imagettfbbox($fontSize * META_IMAGE_GDTEXT_FACTOR, 0, $fontPath, $text);
    return [$bbox[2] - $bbox[0], $bbox[1] - $bbox[7]];
}

/**
 * Y of the first line's baseline for text drawn via metaImageDrawText() with a
 * box top at $topY (top vertical align), matching gd-text's positioning. Lets
 * inline elements (e.g. the party pill) align to the text baseline like CSS.
 */
function metaImageTextBaselineY($topY, $fontSize, $lineHeight) {
    return $topY + ($lineHeight * $fontSize) * (1 - META_IMAGE_GDTEXT_BASELINE);
}

/**
 * Create a filled truecolor canvas with alpha support.
 *
 * @param int   $w
 * @param int   $h
 * @param int[] $bgRgb [r,g,b]
 * @return resource|\GdImage
 */
function metaImageCanvas($w, $h, array $bgRgb) {
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagealphablending($img, true);
    $bg = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
    imagefill($img, 0, 0, $bg);
    return $img;
}

/**
 * Draw the OPTV mark (optv-logo.png) scaled to $height, top-left at ($x,$y).
 * Returns the rendered width (0 if the asset is missing).
 */
function metaImageDrawMark($img, array $cfg, $x, $y, $height) {
    $logoPath = $cfg['logo']['path'];
    if (!is_file($logoPath)) {
        return 0;
    }
    $logo = @imagecreatefrompng($logoPath);
    if (!$logo) {
        return 0;
    }
    imagealphablending($logo, true);
    imagesavealpha($logo, true);
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    $nw = (int) round($lw * ($height / $lh));

    $resized = imagecreatetruecolor($nw, $height);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $logo, 0, 0, 0, 0, $nw, $height, $lw, $lh);
    imagecopy($img, $resized, (int) $x, (int) $y, 0, 0, $nw, $height);

    imagedestroy($logo);
    imagedestroy($resized);
    return $nw;
}

/**
 * Footer shared by all cards: speech count on the left, OPTV mark + wordmark on
 * the right, over a divider rule. $countText may be null to omit the left side.
 */
/**
 * Draw a text block wrapped to at most $maxLines (gd-text-aware), truncating the
 * last line with an ellipsis on overflow. Guarantees the block never grows past
 * $maxLines lines, so it can't collide with content below.
 */
function metaImageDrawWrappedText($img, $fontPath, $fontSize, array $rgb, $text, $x, $y, $w, $maxLines, $lineHeight) {
    $lines = metaImageWrapText($fontPath, $fontSize, $text, $w, $maxLines);
    $lhpx = $lineHeight * $fontSize;
    foreach ($lines as $i => $line) {
        metaImageDrawText($img, $fontPath, $fontSize, $rgb, $line, $x, (int) round($y + $i * $lhpx), $w, $lhpx, 'left', 'top', $lineHeight);
    }
}

/**
 * Format a speech count as "<n> <speeches>", or "> 10.000 <speeches>" once the
 * API's 10k result cap is hit. Returns null for null/zero (count then omitted).
 */
function metaImageFormatCount($n) {
    if ($n === null) {
        return null;
    }
    $n = (int) $n;
    if ($n <= 0) {
        return null;
    }
    if ($n >= 10000) {
        return '> 10.000 ' . L::speeches();
    }
    return number_format($n, 0, ',', '.') . ' ' . L::speeches();
}

function metaImageDrawFooter($img, array $cfg, $countText) {
    $f  = $cfg['footer'];
    $cw = $cfg['canvas']['width'];
    $marginX  = $cfg['header']['marginX'];
    $baseline = $f['baselineY'];
    $wmSize   = $f['wordmarkSize'];
    $fg = $cfg['colors']['text'];
    $fgCol = imagecolorallocate($img, $fg[0], $fg[1], $fg[2]);

    // Right: mark + wordmark. The wordmark is two-tone — the first word ("Open")
    // in the light weight, the rest ("Parliament TV") in the regular weight.
    $brand = L::brand();
    $sp = strpos($brand, ' ');
    $word1 = $sp !== false ? substr($brand, 0, $sp) : $brand;     // "Open"
    $word2 = $sp !== false ? substr($brand, $sp) : '';            // " Parliament TV"
    $b1 = imagettfbbox($wmSize, 0, $cfg['fonts']['primary'], $word1);
    $b2 = $word2 !== '' ? imagettfbbox($wmSize, 0, $cfg['fonts']['bold'], $word2) : [0, 0, 0, 0, 0, 0, 0, 0];
    $w1 = $b1[2] - $b1[0];
    $w2 = $b2[2] - $b2[0];

    $markH = $f['markHeight'];
    $logo = @imagecreatefrompng($cfg['logo']['path']);
    $markRenderW = $logo ? (int) round(imagesx($logo) * ($markH / imagesy($logo))) : 0;
    if ($logo) { imagedestroy($logo); }

    $totalW = $markRenderW + $f['markGap'] + $w1 + $w2;
    $rightX = $cw - $marginX - $totalW;

    // Divider rule above the footer, foreground colour. It bleeds off the right
    // edge of the image (right end at the canvas edge, not the margin) and runs
    // left a bit longer than the mark+wordmark block.
    $dividerLen = $totalW * (1 + ($f['dividerExtend'] ?? 0.4));
    imagefilledrectangle($img, (int) round($cw - $dividerLen), $f['dividerY'], $cw, $f['dividerY'], $fgCol);

    // Mark: a touch bigger and sitting slightly higher than the wordmark centre.
    metaImageDrawMark($img, $cfg, $rightX, (int) round($baseline - $wmSize * 0.52 - $markH / 2), $markH);
    $textX = $rightX + $markRenderW + $f['markGap'];
    imagettftext($img, $wmSize, 0, (int) round($textX - $b1[0]), (int) $baseline, $fgCol, $cfg['fonts']['primary'], $word1);
    if ($word2 !== '') {
        imagettftext($img, $wmSize, 0, (int) round($textX + $w1 - $b2[0]), (int) $baseline, $fgCol, $cfg['fonts']['bold'], $word2);
    }

    // Left: "<n> <speeches>" — number in the regular weight, label in the light
    // weight (muted).
    if ($countText !== null && $countText !== '') {
        $parts  = explode(' ', $countText, 2);
        $number = $parts[0];
        $label  = $parts[1] ?? '';
        $size   = $f['countSize'];
        $nBox   = imagettfbbox($size, 0, $cfg['fonts']['bold'], $number);
        imagettftext($img, $size, 0, (int) round($marginX - $nBox[0]), (int) $baseline, $fgCol, $cfg['fonts']['bold'], $number);
        if ($label !== '') {
            // All text uses the foreground colour; the lighter weight (primary)
            // carries the emphasis difference, not a greyed-out colour.
            imagettftext($img, $size, 0, (int) round($marginX + ($nBox[2] - $nBox[0]) + 12), (int) $baseline, $fgCol, $cfg['fonts']['primary'], $label);
        }
    }
}

/**
 * Draw a wrapped/truncated text block via gd-text's Box.
 *
 * @param int[]  $rgb    font color [r,g,b]
 * @param string $hAlign left|center|right
 * @param string $vAlign top|center|bottom
 */
function metaImageDrawText($img, $fontPath, $fontSize, array $rgb, $text, $x, $y, $w, $h, $hAlign = 'left', $vAlign = 'top', $lineHeight = 1.3) {
    if ($text === null || $text === '') {
        return;
    }
    $box = new Box($img);
    $box->setFontFace($fontPath);
    $box->setFontColor(new Color($rgb[0], $rgb[1], $rgb[2]));
    $box->setFontSize($fontSize);
    $box->setLineHeight($lineHeight);
    $box->setBox($x, $y, $w, $h);
    $box->setTextAlign($hAlign, $vAlign);
    // We pre-wrap every line ourselves (metaImageWrapText). Disable gd-text's own
    // wrapping so a boundary measurement disagreement can't re-wrap a line onto
    // the next one (which previously caused overlapping abstract text).
    $box->setTextWrapping(TextWrapping::NoWrap);
    $box->draw($text);
}

/**
 * Truncate a string to a maximum length on a word boundary, appending an
 * ellipsis. Mirrors the quote renderer's truncation behaviour.
 */
function metaImageTruncate($text, $maxChars) {
    $text = trim((string) $text);
    if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
        return $text;
    }
    $cut = mb_substr($text, 0, $maxChars);
    $sp  = mb_strrpos($cut, ' ');
    $text = $sp ? mb_substr($cut, 0, $sp) : $cut;
    return $text . ' […]';
}

/**
 * Draw an icon-font glyph (by hex codepoint) centered at ($cx,$cy) at the given
 * point size and colour. The codepoints are PUA chars (e.g. U+E8BD); GD needs the
 * UTF-8 encoding, not the raw bytes, so we go via an HTML entity.
 */
function metaImageDrawGlyphCentered($img, $code, $cx, $cy, $fontSize, array $rgb, $fontPath) {
    if ($code === null || !is_file($fontPath)) {
        return;
    }
    $char = mb_convert_encoding('&#x' . $code . ';', 'UTF-8', 'HTML-ENTITIES');
    $bbox = @imagettfbbox($fontSize, 0, $fontPath, $char);
    if ($bbox === false) {
        return;
    }
    $glyphW = $bbox[2] - $bbox[0];
    $glyphH = $bbox[1] - $bbox[7];
    $tx = (int) round($cx - $glyphW / 2 - $bbox[0]);
    $ty = (int) round($cy + $glyphH / 2 - $bbox[1]);
    $col = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagettftext($img, $fontSize, 0, $tx, $ty, $col, $fontPath, $char);
}

/**
 * Render a FrameTrail entity-type icon glyph centered in a light circle, used as
 * the thumbnail fallback.
 */
function metaImageDrawIcon($img, $type, $x, $y, $diameter, array $cfg) {
    $cx = $x + $diameter / 2;
    $cy = $y + $diameter / 2;

    // Light circle background to match the UI avatar look.
    $bg = $cfg['colors']['iconBg'];
    $bgCol = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    imagefilledellipse($img, (int) $cx, (int) $cy, $diameter, $diameter, $bgCol);

    metaImageDrawGlyphCentered(
        $img, $cfg['iconCodes'][$type] ?? null, $cx, $cy,
        (int) round($diameter * 0.5), $cfg['colors']['text'], $cfg['fonts']['icons']
    );
}

/**
 * Draw the leading play glyph (icon-play) centered at ($cx,$cy), ~title-sized.
 */
function metaImageDrawPlayIcon($img, $cx, $cy, $size, array $cfg) {
    metaImageDrawGlyphCentered(
        $img, $cfg['iconCodes']['play'] ?? null, $cx, $cy,
        (int) round($size), $cfg['colors']['text'], $cfg['fonts']['icons']
    );
}

/**
 * Compute the shared header-band geometry: where the play glyph (left), the
 * thumbnail/icon (right corner) and the text column live. The play glyph and the
 * thumbnail are centred vertically in the band.
 *
 * @return array band geometry (keys: top,height,centerY,thumbX,thumbY,thumbD,colX,colW,playCx,playSize)
 */
function metaImageHeaderBand(array $cfg) {
    $h  = $cfg['header'];
    $cw = $cfg['canvas']['width'];
    $d  = $h['thumbDiameter'];
    $centerY = $h['bandTop'] + $h['bandHeight'] / 2;
    $thumbX  = $cw - $h['marginX'] - $d;
    $colX    = $h['colX'];
    return [
        'top' => $h['bandTop'], 'height' => $h['bandHeight'], 'centerY' => $centerY,
        'thumbX' => $thumbX, 'thumbY' => (int) round($centerY - $d / 2), 'thumbD' => $d,
        'colX' => $colX, 'colW' => $thumbX - $colX - $h['colRightGap'],
        'playCx' => $h['marginX'] + $h['playSize'] / 2, 'playSize' => $h['playSize'],
    ];
}

/**
 * Draw the leading play glyph centred in the band (left side).
 */
function metaImageDrawHeaderPlay($img, array $cfg, array $band) {
    metaImageDrawPlayIcon($img, $band['playCx'], $band['centerY'], $band['playSize'], $cfg);
}

/**
 * Word-wrap $text to fit $maxWidth at $fontSize (measuring the way gd-text draws
 * it), up to $maxLines lines. The final line is truncated with an ellipsis when
 * the text overflows.
 *
 * @return array list of line strings (1..$maxLines)
 */
function metaImageWrapText($fontPath, $fontSize, $text, $maxWidth, $maxLines, $ellipsis = ' […]') {
    // Normalise all whitespace (incl. NBSP / other Unicode spaces) to single
    // regular spaces using the /u flag, so word-splitting is locale-independent:
    // a set LC_CTYPE otherwise makes PCRE \s match the 0xA0 byte of a UTF-8 NBSP,
    // splitting words mid-codepoint and corrupting the rendered text.
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') {
        return [''];
    }
    $words = explode(' ', $text);
    $lines = [];
    $cur = '';
    foreach ($words as $word) {
        $try = $cur === '' ? $word : $cur . ' ' . $word;
        list($w) = metaImageTextMetrics($fontPath, $fontSize, $try);
        if ($w <= $maxWidth || $cur === '') {
            $cur = $try;
            continue;
        }
        // $word doesn't fit on the current line.
        if (count($lines) + 1 >= $maxLines) {
            // No more lines left → truncate the current line with an ellipsis.
            return array_merge($lines, [metaImageTruncateToWidth($fontPath, $fontSize, $cur, $maxWidth, $ellipsis)]);
        }
        $lines[] = $cur;
        $cur = $word;
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines;
}

/**
 * Drop trailing words from $text until $text.$ellipsis fits within $maxWidth.
 */
function metaImageTruncateToWidth($fontPath, $fontSize, $text, $maxWidth, $ellipsis) {
    while ($text !== '') {
        list($w) = metaImageTextMetrics($fontPath, $fontSize, $text . $ellipsis);
        if ($w <= $maxWidth) {
            return $text . $ellipsis;
        }
        $pos = strrpos($text, ' ');
        if ($pos === false) {
            return rtrim($ellipsis);
        }
        $text = substr($text, 0, $pos);
    }
    return rtrim($ellipsis);
}

/**
 * Rendered width of a party badge for a given label/size (text + padding + border),
 * matching metaImageDrawPartyBadge. Used to decide inline vs. wrapped placement.
 */
function metaImageBadgeWidth(array $cfg, $label, $fontSize) {
    $label = trim((string) $label);
    if ($label === '') {
        return 0;
    }
    $bb = imagettfbbox($fontSize, 0, $cfg['fonts']['primary'], $label);
    return ($bb[2] - $bb[0]) + 24 + 2; // 2*padX + border
}

/**
 * The dynamic core: compose and draw a title block (title wrapped to ≤ maxLines,
 * an optional inline-or-wrapped party badge, or an optional subtitle line) and
 * vertically centre the whole block within the header band. Mirrors the design
 * variants: badge flows inline after the name when it fits, otherwise onto its
 * own line; with no subtitle/badge a single title line sits centred in the band.
 *
 * @param array $a keys: bandTop, bandHeight, x, w, title, titleFont, titleSize,
 *                 titleLH, titleMaxLines, color, mutedColor; optional subtitle,
 *                 subtitleSize; optional badge ['label','color'], badgeSize,
 *                 badgeGap, secondaryGap.
 * @return int Y of the block bottom.
 */
function metaImageDrawCenteredTitleBlock($img, array $cfg, array $a) {
    $x = $a['x'];
    $w = $a['w'];
    $titleFont = $a['titleFont'];
    $titleSize = $a['titleSize'];
    $titleLHpx = $a['titleLH'] * $titleSize;

    $lines    = metaImageWrapText($titleFont, $titleSize, $a['title'], $w, $a['titleMaxLines']);
    $numLines = count($lines);

    // Badge: inline after the last line if it fits, else on its own line.
    $badge        = $a['badge'] ?? null;
    $badgeInline  = false;
    $badgeWrapped = false;
    if ($badge && !empty($badge['label'])) {
        list($lastW) = metaImageTextMetrics($titleFont, $titleSize, $lines[$numLines - 1]);
        $badgeW = metaImageBadgeWidth($cfg, $badge['label'], $a['badgeSize']);
        if ($lastW + $a['badgeGap'] + $badgeW <= $w) {
            $badgeInline = true;
        } else {
            $badgeWrapped = true;
        }
    }

    // Subtitle may be a single string or an array of rows (each on its own line).
    $subtitleLines = [];
    if (!empty($a['subtitle'])) {
        $subtitleLines = is_array($a['subtitle'])
            ? array_values(array_filter($a['subtitle'], function ($s) { return trim((string) $s) !== ''; }))
            : [$a['subtitle']];
    }
    $hasSubtitle = count($subtitleLines) > 0;
    $subRowH     = $a['subtitleSize'] * 1.35; // top-to-top spacing of subtitle rows
    $secondaryH  = 0;
    if ($hasSubtitle) {
        $secondaryH = $a['secondaryGap'] + count($subtitleLines) * $subRowH;
    } elseif ($badgeWrapped) {
        $secondaryH = $a['secondaryGap'] + ($a['badgeSize'] + 14);
    }

    $blockH   = $numLines * $titleLHpx + $secondaryH;
    $blockTop = $a['bandTop'] + ($a['bandHeight'] - $blockH) / 2;
    // Cap how high the block can rise: never above where a 2-line title would sit
    // when centred. Taller blocks (e.g. very long agenda-item titles) then keep
    // this top and grow downward instead of pushing further up.
    $maxTop = $a['bandTop'] + ($a['bandHeight'] - 2 * $titleLHpx) / 2;
    if ($blockTop < $maxTop) {
        $blockTop = $maxTop;
    }

    // Title lines.
    foreach ($lines as $i => $line) {
        metaImageDrawText(
            $img, $titleFont, $titleSize, $a['color'], $line,
            $x, (int) round($blockTop + $i * $titleLHpx), $w, $titleLHpx, 'left', 'top', $a['titleLH']
        );
    }

    // Inline badge on the last line, aligned to that line's baseline.
    if ($badgeInline) {
        $lastTop  = $blockTop + ($numLines - 1) * $titleLHpx;
        list($lastW) = metaImageTextMetrics($titleFont, $titleSize, $lines[$numLines - 1]);
        // Nudge the pill up a few px from the strict text baseline for optical alignment.
        $baseline = metaImageTextBaselineY($lastTop, $titleSize, $a['titleLH']) - 5;
        metaImageDrawPartyBadge($img, $badge['label'], (int) round($x + $lastW + $a['badgeGap']), 0, $cfg, $a['badgeSize'], $badge['color'] ?? null, $baseline);
    }

    // Secondary: subtitle row(s) (normal weight, foreground colour), or a wrapped badge.
    $secTop = $blockTop + $numLines * $titleLHpx + $a['secondaryGap'];
    if ($hasSubtitle) {
        foreach ($subtitleLines as $j => $sline) {
            metaImageDrawText(
                $img, $cfg['fonts']['primary'], $a['subtitleSize'], $a['color'],
                metaImageTruncate($sline, $a['subtitleMaxChars'] ?? 64), $x, (int) round($secTop + $j * $subRowH), $w, $a['subtitleSize'] * 1.4, 'left', 'top'
            );
        }
    } elseif ($badgeWrapped) {
        metaImageDrawPartyBadge($img, $badge['label'], $x, (int) round($secTop), $cfg, $a['badgeSize'], $badge['color'] ?? null);
    }

    return (int) round($blockTop + $blockH);
}

/**
 * Draw the stylised "search results" graphic: a row of $count cards, each an
 * outlined rectangle with a few text lines and one highlighted match bar (varied
 * per card so the row reads organically).
 */
function metaImageDrawSearchResults($img, array $cfg, array $r) {
    $bg = $cfg['colors']['background'];
    // Deterministic pseudo-random in [0,1) from two ints (stable across renders).
    $rand = function ($a, $b) {
        $h = (($a + 1) * 73856093) ^ (($b + 1) * 19349663);
        return (($h >> 6) & 0x3FF) / 1024.0;
    };
    $alloc = function ($c) use ($img) {
        return imagecolorallocate($img, (int) $c[0], (int) $c[1], (int) $c[2]);
    };

    $pad = 16;
    for ($i = 0; $i < $r['count']; $i++) {
        $faded = ($i === $r['count'] - 1);
        // Fade the last card toward the background ("more results continue").
        $tint = function ($c) use ($faded, $bg) {
            return $faded ? metaImageBlend($c, $bg, 0.6) : $c;
        };
        // Hard-coded party colours (from the DB) for the box borders, just for
        // looks: CDU/CSU, DIE LINKE, DIE GRÜNEN, SPD; the faded last box stays grey.
        $partyHex = ['#000000', '#bc3475', '#4a932b', '#df0b25'];
        $partyRgb = (!$faded && isset($partyHex[$i])) ? metaImageHexToRgb($partyHex[$i]) : null;
        $border = $partyRgb ?: $tint($cfg['colors']['resultLine']);
        $title  = $tint([165, 166, 175]); // title lines (medium)
        $snip   = $tint($cfg['colors']['resultLine']); // snippet lines (light)
        $barBg  = $tint($cfg['colors']['divider']); // hit-timeline track
        $hit    = $tint(metaImageBlend($cfg['colors']['text'], $bg, 0.35)); // .hit (≈0.5 opacity fg)

        $bx = $r['x'] + $i * ($r['boxW'] + $r['gap']);
        $by = $r['y'];
        $innerW = $r['boxW'] - 2 * $pad;
        $lx = $bx + $pad;

        metaImageRoundedRect($img, $bx, $by, $bx + $r['boxW'], $by + $r['boxH'], 6, $alloc($border));

        // Title: 2 thicker lines.
        for ($t = 0; $t < 2; $t++) {
            $ly = $by + 18 + $t * 16;
            $w  = (int) round($innerW * (0.55 + 0.4 * $rand($i, $t)));
            imagefilledrectangle($img, $lx, $ly, $lx + $w, $ly + 7, $alloc($title));
        }
        // Snippets: 4 thin lines.
        for ($s = 0; $s < 4; $s++) {
            $ly = $by + 66 + $s * 15;
            $w  = (int) round($innerW * (0.45 + 0.5 * $rand($i, $s + 5)));
            imagefilledrectangle($img, $lx, $ly, $lx + $w, $ly + 3, $alloc($snip));
        }
        // Hit timeline at the bottom: a track with a few hit markers.
        $tlY = $by + $r['boxH'] - 26;
        $tlH = 8;
        imagefilledrectangle($img, $lx, $tlY, $bx + $r['boxW'] - $pad, $tlY + $tlH, $alloc($barBg));
        $hits = 2 + (int) round(2 * $rand($i, 99)); // 2–4 hits
        $hitCol = $alloc($hit);
        for ($h = 0; $h < $hits; $h++) {
            $hx = $lx + (int) round($innerW * (0.04 + 0.84 * $rand($i, $h + 20)));
            $hw = 5 + (int) round(15 * $rand($i, $h + 40));
            imagefilledrectangle($img, $hx, $tlY, min($hx + $hw, $bx + $r['boxW'] - $pad), $tlY + $tlH, $hitCol);
        }
    }
}

/**
 * Blend colour $c toward $bg by factor $t (0 = $c, 1 = $bg). Used for fading.
 */
function metaImageBlend(array $c, array $bg, $t) {
    return [
        $c[0] + ($bg[0] - $c[0]) * $t,
        $c[1] + ($bg[1] - $c[1]) * $t,
        $c[2] + ($bg[2] - $c[2]) * $t,
    ];
}

/**
 * Make everything outside the inscribed circle of a square $diameter canvas
 * fully transparent (alpha-channel circular mask).
 */
function metaImageApplyCircleMask($thumb, $diameter) {
    $cx = $diameter / 2;
    $cy = $diameter / 2;
    $r2 = ($diameter / 2) * ($diameter / 2);
    imagealphablending($thumb, false);
    for ($py = 0; $py < $diameter; $py++) {
        for ($px = 0; $px < $diameter; $px++) {
            $dx = $px - $cx + 0.5;
            $dy = $py - $cy + 0.5;
            if (($dx * $dx + $dy * $dy) > $r2) {
                $rgba = imagecolorat($thumb, $px, $py);
                imagesetpixel($thumb, $px, $py, ($rgba & 0x00FFFFFF) | (127 << 24));
            }
        }
    }
    imagesavealpha($thumb, true);
}

/**
 * Composite a circular entity thumbnail at ($x,$y) with the given diameter,
 * applying the same per-type fit/position rules as the web UI (see the
 * 'thumbnailFit' map in config.php, mirroring style.css / entity.preview*.php):
 *   - cover + top   (person/term/document): fill the circle, anchor crop to top
 *   - contain + center + scale (organisation): whole logo, padded, centered
 * Falls back to the entity icon when no cached thumbnail is available.
 *
 * Relies on optvImageEnsureCached() (modules/images/functions.php) which fetches
 * + caches the Wikimedia source on a miss, falling back to a stale/empty result.
 */
function metaImageDrawThumbnail($img, $type, $id, $x, $y, $diameter, array $cfg) {
    $path = (function_exists('optvImageEnsureCached') && $id !== '')
        ? optvImageEnsureCached($type, $id)
        : null;

    if ($path === null || !is_file($path)) {
        metaImageDrawIcon($img, $type, $x, $y, $diameter, $cfg);
        return;
    }

    $src = @imagecreatefromstring((string) file_get_contents($path));
    if ($src === false) {
        metaImageDrawIcon($img, $type, $x, $y, $diameter, $cfg);
        return;
    }

    $sw = imagesx($src);
    $sh = imagesy($src);

    $rule = $cfg['thumbnailFit'][$type] ?? $cfg['thumbnailFit']['default'];
    $fit      = $rule['fit'] ?? 'cover';
    $position = $rule['position'] ?? 'center';
    $scale    = $rule['scale'] ?? 1.0;

    // Own RGBA canvas at target size; a light circle behind the image provides
    // the padding backdrop for "contain" logos and any transparency.
    $thumb = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagefilledrectangle($thumb, 0, 0, $diameter, $diameter, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
    $bg = $cfg['colors']['iconBg'];
    imagealphablending($thumb, true);
    imagefilledellipse($thumb, (int) ($diameter / 2), (int) ($diameter / 2), $diameter, $diameter, imagecolorallocate($thumb, $bg[0], $bg[1], $bg[2]));
    imagealphablending($thumb, false);

    if ($fit === 'contain') {
        // Fit the whole image inside a centred box scaled by $scale (no crop).
        $boxW = $diameter * $scale;
        $boxH = $diameter * $scale;
        $ratio = min($boxW / $sw, $boxH / $sh);
        $dw = (int) round($sw * $ratio);
        $dh = (int) round($sh * $ratio);
        $dx = (int) round(($diameter - $dw) / 2);
        $dy = (int) round(($diameter - $dh) / 2);
        imagecopyresampled($thumb, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    } else {
        // cover: crop to a square, horizontally centred; vertically per $position.
        $side = min($sw, $sh);
        $sx = (int) (($sw - $side) / 2);
        $sy = ($position === 'top') ? 0 : (int) (($sh - $side) / 2);
        imagecopyresampled($thumb, $src, 0, 0, $sx, $sy, $diameter, $diameter, $side, $side);
    }
    imagedestroy($src);

    metaImageApplyCircleMask($thumb, $diameter);

    imagealphablending($img, true);
    imagecopy($img, $thumb, $x, $y, 0, 0, $diameter, $diameter);
    imagedestroy($thumb);
}

/**
 * Convert a CSS hex colour ("#bc3475" / "bc3475" / "#abc") to an [r,g,b] array,
 * or null if it isn't a usable hex colour.
 */
function metaImageHexToRgb($hex) {
    if (!is_string($hex)) {
        return null;
    }
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return null;
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Stroke a 1px rounded rectangle (matching the .partyIndicator border-radius).
 */
function metaImageRoundedRect($img, $x1, $y1, $x2, $y2, $r, $color) {
    $d = $r * 2;
    imageline($img, $x1 + $r, $y1, $x2 - $r, $y1, $color);
    imageline($img, $x1 + $r, $y2, $x2 - $r, $y2, $color);
    imageline($img, $x1, $y1 + $r, $x1, $y2 - $r, $color);
    imageline($img, $x2, $y1 + $r, $x2, $y2 - $r, $color);
    imagearc($img, $x1 + $r, $y1 + $r, $d, $d, 180, 270, $color);
    imagearc($img, $x2 - $r, $y1 + $r, $d, $d, 270, 360, $color);
    imagearc($img, $x1 + $r, $y2 - $r, $d, $d, 90, 180, $color);
    imagearc($img, $x2 - $r, $y2 - $r, $d, $d, 0, 90, $color);
}

/**
 * Render a party/faction pill at ($x,$y), mirroring the web UI's .partyIndicator
 * (faction-coloured rounded border, card-bg fill, muted label). Returns the total
 * width drawn so callers can flow content after it; 0 if nothing was drawn.
 *
 * @param array|null $borderRgb faction colour [r,g,b]; falls back to a neutral border.
 * @param float|null $baselineY when set, the label's text baseline is aligned to
 *                   this Y (CSS-like inline baseline) and $y is ignored.
 */
function metaImageDrawPartyBadge($img, $label, $x, $y, array $cfg, $fontSize = 22, array $borderRgb = null, $baselineY = null) {
    $label = trim((string) $label);
    if ($label === '') {
        return 0;
    }
    $fontPath = $cfg['fonts']['primary'];
    $padX = 12;
    $padY = 7;
    $radius = 5;

    $bbox = imagettfbbox($fontSize, 0, $fontPath, $label);
    $textW = $bbox[2] - $bbox[0];
    $textH = $bbox[1] - $bbox[7];
    $boxW = $textW + 2 * $padX;
    $boxH = $textH + 2 * $padY;

    // Baseline alignment: place the box top so the label baseline hits $baselineY
    // (text baseline = boxTop + padY - bbox[7]).
    if ($baselineY !== null) {
        $y = (int) round($baselineY - $padY + $bbox[7]);
    }

    $border = $borderRgb ?: $cfg['colors']['badgeBorder'];
    $borderCol = imagecolorallocate($img, $border[0], $border[1], $border[2]);
    imagesetthickness($img, 2);
    metaImageRoundedRect($img, $x, $y, $x + $boxW, $y + $boxH, $radius, $borderCol);
    imagesetthickness($img, 1);

    $rgb = $cfg['colors']['text'];
    $col = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagettftext($img, $fontSize, 0, (int) ($x + $padX - $bbox[0]), (int) ($y + $padY - $bbox[7]), $col, $fontPath, $label);

    return $boxW;
}

/**
 * Encode a GD image to PNG bytes (matching the quote renderer's output).
 */
function metaImagePng($img) {
    ob_start();
    imagepng($img, null, 9, PNG_ALL_FILTERS);
    return ob_get_clean();
}
