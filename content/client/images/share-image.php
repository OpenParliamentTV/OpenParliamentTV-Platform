<?php
// Ensure no output before headers
ob_start();

// Set proper headers
header("Content-type: image/png");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

require_once(__DIR__."/../../../modules/utilities/security.php");
require_once(__DIR__."/../../../modules/media/include.media.php");
require_once(__DIR__."/../../../modules/image-quote/functions.php");

$quote = '';
if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
	// Validate timing parameter (should be numbers, decimals, and commas)
	if (preg_match('/^[0-9.,]+$/', $_REQUEST['t'])) {
		$timings = $_REQUEST['t'];
	} else {
		$timings = '';
	}
	
	// Validate fragments parameter (should be alphanumeric, commas, and common text characters)
	if (preg_match('/^[a-zA-Z0-9,äöüÄÖÜßéèêëáàâäíìîïóòôöúùûü\s]+$/', $_REQUEST['f'])) {
		$fragments = $_REQUEST['f'];
	} else {
		$fragments = '';
	}
	
	if ($timings && $fragments) {
		// Debug what we have
		if (!isset($textContentsHTML)) {
			$quote = "textContentsHTML not set";
		} else if (empty($textContentsHTML)) {
			$quote = "textContentsHTML is empty";
		} else {
			$quote = getQuoteFromRequestParams($timings, $fragments, $textContentsHTML);
			// Fallback if quote extraction fails
			if (empty($quote)) {
				$quote = "Quote extraction returned empty - HTML length: " . strlen($textContentsHTML);
			}
		}
	}
}

$author = $mainSpeaker['attributes']['label'];

if (isset($mainFaction['attributes']['label'])) {
	$authorSecondary = $mainFaction['attributes']['label'].' | '.$formattedDate;
} else {
	$authorSecondary = $formattedDate;
}

// Validate theme parameter (only allow specific values)
$allowedThemes = ['l', 'd']; // light, dark
$theme = isset($_REQUEST['c']) && in_array($_REQUEST['c'], $allowedThemes) ? $_REQUEST['c'] : 'l';

$imageData = renderImageQuote($theme, $quote, $author, $authorSecondary);
if ($imageData === false) {
	// Output a simple error image
	$errorImage = imagecreatetruecolor(400, 200);
	$bg = imagecolorallocate($errorImage, 255, 255, 255);
	$textColor = imagecolorallocate($errorImage, 255, 0, 0);
	imagefill($errorImage, 0, 0, $bg);
	imagestring($errorImage, 5, 10, 10, 'Error generating image', $textColor);
	imagepng($errorImage);
	imagedestroy($errorImage);
} else {
	// Clear any previous output
	ob_clean();
	// Output the image data
	echo $imageData;
}

// End output buffering
ob_end_flush();
?>