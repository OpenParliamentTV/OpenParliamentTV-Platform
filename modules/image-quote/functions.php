<?php
require_once('gd-text.php');

function renderImageQuote($theme = 'l', $text = '', $author = '', $authorSecondary = '') {
	
	$maxCharacters = 250;
	if (strlen($text) >= $maxCharacters) {
		// truncate string
		$stringCut = substr($text, 0, $maxCharacters);
		$endPoint = strrpos($stringCut, ' ');

		//if the string doesn't contain any space then it will cut without word basis.
		$text = $endPoint? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
		$text .= ' [...]';
	}

	$imageWidth = 1120;
	$imageHeight = 600;
	
	$fontSize = getFontSize(strlen($text));
	$lineHeight = 1.5;
	$font = 'OpenSans-Regular.ttf';

	$image = imagecreatetruecolor($imageWidth, $imageHeight);

	switch ($theme) {
		case 'd':
			// dark
			$backgroundColor = imagecolorallocate($image, 48, 49, 57);
			$fontColor = new Color(255, 255, 255);
			break;
		case 'l':
			// light
			$backgroundColor = imagecolorallocate($image, 243, 244, 245);
			$fontColor = new Color(115, 116, 124);
			break;
		default:
			$backgroundColor = imagecolorallocate($image, 243, 244, 245);
			$fontColor = new Color(115, 116, 124);
			break;
	}

	imagefill($image, 0, 0, $backgroundColor);

	$logo = imagecreatefrompng('optv-logo.png');
	$logoPercent = 0.2;
	// Get new dimensions
	list($width, $height) = getimagesize('optv-logo.png');
	$newLogoWidth = $width * $logoPercent;
	$newLogoHeight = $height * $logoPercent;
	$logoResized = $logo;
	imagecopyresampled($image, $logoResized, ($imageWidth - $newLogoWidth - 30), ($imageHeight - $newLogoHeight), 0, 0, $newLogoWidth, $newLogoHeight, $width, $height);

	$quoteBox = new Box($image);
	$quoteBox->setFontFace(__DIR__.'/'.$font);
	$quoteBox->setFontColor($fontColor);
	$quoteBox->setFontSize($fontSize);
	$quoteBox->setLineHeight($lineHeight);
	$quoteBox->setBox(60, 110, ($imageWidth - 120), ($imageHeight - 330));
	$quoteBox->setTextAlign('center', 'center');
	$quoteBox->draw($text);

	$authorBox = new Box($image);
	$authorBox->setFontFace(__DIR__.'/'.$font);
	$authorBox->setFontColor($fontColor);
	$authorBox->setFontSize(34);
	$authorBox->setLineHeight($lineHeight);
	$authorBox->setBox(60, ($imageHeight - 180), ($imageWidth - 300), 80);
	$authorBox->setTextAlign('left', 'bottom');
	$authorBox->draw($author);

	$authorSecondaryBox = new Box($image);
	$authorSecondaryBox->setFontFace(__DIR__.'/'.$font);
	$authorSecondaryBox->setFontColor($fontColor);
	$authorSecondaryBox->setFontSize(34);
	$authorSecondaryBox->setLineHeight($lineHeight);
	$authorSecondaryBox->setBox(60, ($imageHeight - 120), ($imageWidth - 300), 80);
	$authorSecondaryBox->setTextAlign('left', 'center');
	$authorSecondaryBox->draw($authorSecondary);

	$quotationMarkBox = new Box($image);
	$quotationMarkBox->setFontFace(__DIR__.'/'.$font);
	$quotationMarkBox->setFontColor($fontColor);
	$quotationMarkBox->setFontSize(160);
	$quotationMarkBox->setBox(30, -10, 100, 100);
	$quotationMarkBox->setTextAlign('left', 'top');
	$quotationMarkBox->draw("“");

	return imagepng($image, null, 9, PNG_ALL_FILTERS);
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

    // do whatever you want to do with this code now

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
		for ($i = 1; $i < count($words); $i++) {
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
