<?php
require_once('gd-text.php');

function renderImageQuote($theme = 'light', $text = '') {
	
	$maxCharacters = 250;
	if (strlen($text) >= $maxCharacters) {
		// truncate string
		$stringCut = substr($text, 0, $maxCharacters);
		$endPoint = strrpos($stringCut, ' ');

		//if the string doesn't contain any space then it will cut without word basis.
		$text = $endPoint? substr($stringCut, 0, $endPoint) : substr($stringCut, 0);
		$text .= ' [...]';
	}

	$imageWidth = 1080;
	$imageHeight = 800;
	
	$fontSize = getFontSize(strlen($text));
	$lineHeight = 1.5;
	$font = 'OpenSans-Regular.ttf';

	$image = imagecreatetruecolor($imageWidth, $imageHeight);

	switch ($theme) {
		case 'dark':
			$backgroundColor = imagecolorallocate($image, 48, 49, 57);
			$fontColor = new Color(255, 255, 255);
			break;
		case 'light':
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
	$quoteBox->setBox(40, 100, ($imageWidth - 80), ($imageHeight - 280));
	$quoteBox->setTextAlign('center', 'center');
	$quoteBox->draw($text);

	$quotationMarkBox = new Box($image);
	$quotationMarkBox->setFontFace(__DIR__.'/'.$font);
	$quotationMarkBox->setFontColor($fontColor);
	$quotationMarkBox->setFontSize(180);
	$quotationMarkBox->setBox(40, 0, 100, 100);
	$quotationMarkBox->setTextAlign('left', 'top');
	$quotationMarkBox->draw("“");

	return imagepng($image, null, 9, PNG_ALL_FILTERS);
}

function getFontSize($textLength) {
	$baseSize = 80;
	$fontSize = $baseSize - ($textLength/8);
	return $fontSize;
}


?>
