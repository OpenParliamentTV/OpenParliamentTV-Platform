<?php
require_once(__DIR__ . '/gd-text.php');
// Shared GD helpers (metaImageDrawThumbnail: circular speaker photo / icon fallback).
require_once(__DIR__ . '/render.php');

function renderQuoteImage($theme, $text, $author, $authorSecondary, $speakerId = '', $cfg = null) {
	// Ensure proper UTF-8 encoding
	$text = mb_convert_encoding($text, 'UTF-8', 'auto');
	$author = mb_convert_encoding($author, 'UTF-8', 'auto');
	$authorSecondary = mb_convert_encoding($authorSecondary, 'UTF-8', 'auto');
	
	// Truncate text if too long
	$maxCharacters = 250;
	if (mb_strlen($text) >= $maxCharacters) {
		// truncate string
		$stringCut = mb_substr($text, 0, $maxCharacters);
		$endPoint = mb_strrpos($stringCut, ' ');

		//if the string doesn't contain any space then it will cut without word basis.
		$text = $endPoint ? mb_substr($stringCut, 0, $endPoint) : mb_substr($stringCut, 0);
		$text .= ' [...]';
	}
	
	// Set image dimensions
	$width = 1120;
	$height = 600;
	
	// Create image with alpha channel support
	$image = imagecreatetruecolor($width, $height);
	if (!$image) {
		return false;
	}
	
	// Enable alpha channel
	imagesavealpha($image, true);
	imagealphablending($image, true);
	
	// Set background color based on theme
	if ($theme === 'd') {
		$bgColor = imagecolorallocate($image, 48, 49, 57);
		$textColor = new Color(255, 255, 255);
	} else {
		// Light theme — shared palette: background #f9fafb, foreground #535263.
		$bgColor = imagecolorallocate($image, 249, 250, 251);
		$textColor = new Color(83, 82, 99);
	}
	imagefill($image, 0, 0, $bgColor);
	
	// Bottom row: the speaker thumbnail (far left) and the OPTV mark (far right)
	// sit vertically centred on the same line as the author text.
	$rowCenterY = 495;

	// Load the compact OPTV mark (denser dome variant) — the white variant on the
	// dark theme, the default (dark) mark on the light theme.
	if ($theme === 'd') {
		$logoPath = ($cfg['logo']['compactWhitePath'] ?? null) ?: (__DIR__ . '/../../content/client/images/optv-mark-compact-white.png');
	} else {
		$logoPath = ($cfg['logo']['compactPath'] ?? null) ?: (__DIR__ . '/../../content/client/images/optv-mark-compact.png');
	}
	if (!file_exists($logoPath)) {
		$logoPath = __DIR__ . '/optv-mark.png';
	}
	$logo = imagecreatefrompng($logoPath);
	if (!$logo) {
		return false;
	}

	// Enable alpha channel for logo
	imagealphablending($logo, true);
	imagesavealpha($logo, true);

	// Resize the mark, keeping the compact mark's aspect ratio.
	$logoWidth = imagesx($logo);
	$logoHeight = imagesy($logo);
	$newLogoWidth = 130;
	$newLogoHeight = (int) round($newLogoWidth * $logoHeight / $logoWidth);

	// Create resized logo
	$logoResized = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
	imagealphablending($logoResized, false);
	imagesavealpha($logoResized, true);
	imagecopyresampled($logoResized, $logo, 0, 0, 0, 0, $newLogoWidth, $newLogoHeight, $logoWidth, $logoHeight);

	// Place the mark bottom-right with generous right/bottom padding, centred on the row.
	$logoX = $width - $newLogoWidth - 60;
	$logoY = (int) round($rowCenterY - $newLogoHeight / 2);
	imagecopy($image, $logoResized, $logoX, $logoY, 0, 0, $newLogoWidth, $newLogoHeight);
	imagedestroy($logo);
	imagedestroy($logoResized);

	// Speaker thumbnail (cached photo, else the person icon) at the far left,
	// centred on the same row as the mark. The author text is shifted right to
	// clear it; without config we fall back to the old full-width text column.
	$textX = 60;
	if ($cfg !== null && function_exists('metaImageDrawThumbnail')) {
		$thumbD = 100;
		$thumbX = 60;
		$thumbY = (int) round($rowCenterY - $thumbD / 2);
		metaImageDrawThumbnail($image, 'person', (string) $speakerId, $thumbX, $thumbY, $thumbD, $cfg);
		$textX = $thumbX + $thumbD + 30;
	}

	// Load font
	$fontPath = __DIR__ . '/OpenSans-Regular.ttf';
	if (!file_exists($fontPath)) {
		return false;
	}
	
	// Calculate font size based on text length
	$fontSize = getFontSize(mb_strlen($text));
	$lineHeight = 1.5;
	
	// Create text boxes
	$quoteBox = new Box($image);
	$quoteBox->setFontFace($fontPath);
	$quoteBox->setFontColor($textColor);
	$quoteBox->setFontSize($fontSize);
	$quoteBox->setLineHeight($lineHeight);
	$quoteBox->setBox(60, 110, ($width - 120), ($height - 330));
	$quoteBox->setTextAlign('center', 'center');
	$quoteBox->draw($text);
	
	$authorBox = new Box($image);
	$authorBox->setFontFace($fontPath);
	$authorBox->setFontColor($textColor);
	$authorBox->setFontSize(34);
	$authorBox->setLineHeight($lineHeight);
	$authorBox->setBox($textX, ($height - 180), ($width - $textX - 250), 80);
	$authorBox->setTextAlign('left', 'bottom');
	$authorBox->draw($author);
	
	$authorSecondaryBox = new Box($image);
	$authorSecondaryBox->setFontFace($fontPath);
	$authorSecondaryBox->setFontColor($textColor);
	$authorSecondaryBox->setFontSize(34);
	$authorSecondaryBox->setLineHeight($lineHeight);
	$authorSecondaryBox->setBox($textX, ($height - 120), ($width - $textX - 250), 80);
	$authorSecondaryBox->setTextAlign('left', 'center');
	$authorSecondaryBox->draw($authorSecondary);
	
	$quotationMarkBox = new Box($image);
	$quotationMarkBox->setFontFace($fontPath);
	$quotationMarkBox->setFontColor($textColor);
	$quotationMarkBox->setFontSize(160);
	$quotationMarkBox->setBox(30, -10, 100, 100);
	$quotationMarkBox->setTextAlign('left', 'top');
	$quotationMarkBox->draw("\xE2\x80\x9C");
	
	// Output image
	ob_start();
	imagepng($image, null, 9, PNG_ALL_FILTERS);
	$imageData = ob_get_clean();
	imagedestroy($image);
	
	return $imageData;
}

function getFontSize($textLength) {
	$baseSize = 70;
	$fontSize = $baseSize - ($textLength/8);
	return $fontSize;
}

function getQuoteFromRequestParams($timings, $fragments, $transcriptHTML) {
	$quoteString = '';

	$t = explode(",", $timings);
	$start = $t[0];
	if (count($t) > 1) $end = $t[1];
	$f = explode(",", $fragments);
	$prefix = $f[0];
	$suffix = $f[1];

	if ( empty( $transcriptHTML ) ) {
        return false;
    }

    // converts all special characters to utf-8
    $transcriptHTML = mb_convert_encoding($transcriptHTML, 'HTML-ENTITIES', 'UTF-8');

    // creating new document
    $htmlDOC = new DOMDocument('1.0', 'utf-8');

    //turning off some errors
    libxml_use_internal_errors(true);

    // it loads the content without adding enclosing html/body tags and also the doctype declaration
    $htmlDOC->LoadHTML($transcriptHTML, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

	$words = array();
	
	foreach($htmlDOC->getElementsByTagName('p') as $pElem) {
		// don't include comments in selection
		if ($pElem->getAttribute('data-type') != "comment") {
			foreach($pElem->getElementsByTagName('span') as $spanElem) {
		        if (!empty($spanElem->getAttribute('data-start'))) {
		        	$words[] = $spanElem;
		        }
		    } 
		}
	}

	if ($start && $end) {
		for ($i = 0; $i < count($words); $i++) {
			$wordStart = $words[$i]->getAttribute("data-start");
			if ($wordStart >= $start && $end > $wordStart) {
				$quoteString .= $words[$i]->nodeValue;
			}
		}

		return $quoteString;
		/*
		if (prefix && suffix) {
			// console.log(prefix, suffix);
			var matches = Array.from(document.querySelectorAll(".share-match"));
			var matchesHash = matches
				.map(function(t) {
					var root = t.innerText
						.trim()
						.replace(/[^\w\s]|_/g, "")
						.replace(/\s+/g, "")
						.toLowerCase()
						.trim();
					return root.substr(0, 1).toUpperCase() + root.substr(1, 3);
				})
				.join("");

			// console.log(matchesHash);

			var prefixMatch = matchesHash.indexOf(prefix);
			if (prefixMatch > 0) {
				matches
					.slice(
						0,
						matchesHash.substring(0, prefixMatch).split(/(?=[A-Z])/)
						.length
					)
					.forEach(function(m) {
						m.classList.add("share-mismatch");
						m.classList.remove("share-match");
					});
			}

			var suffixMatch = matchesHash.indexOf(suffix);
			if (suffixMatch < matchesHash.length - 1 - suffix.length) {
				// matches.slice(0, matchesHash.substring(suffixMatch).split(/(?=[A-Z])/).length - 1).forEach(function (m) {
				matches
					.slice(
						matches.length -
						matchesHash.substring(suffixMatch).split(/(?=[A-Z])/).length +
						suffix.split(/(?=[A-Z])/).length
					)
					.forEach(function(m) {
						m.classList.add("share-mismatch");
						m.classList.remove("share-match");
					});
			}

			// console.log(prefixMatch, suffixMatch);
		}
		*/
	}
}
?>
