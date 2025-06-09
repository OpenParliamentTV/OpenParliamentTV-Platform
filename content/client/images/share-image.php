<?php
// Ensure no output before headers
ob_start();

// Set proper headers
header("Content-type: image/png");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: 0");

require_once(__DIR__."/../../../modules/media/include.media.php");
require_once(__DIR__."/../../../modules/image-quote/functions.php");

$quote = '';
if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
	$quote = getQuoteFromRequestParams($_REQUEST['t'], $_REQUEST['f'], $textContentsHTML);
}

$author = $mainSpeaker['attributes']['label'];

if (isset($mainFaction['attributes']['label'])) {
	$authorSecondary = $mainFaction['attributes']['label'].' | '.$formattedDate;
} else {
	$authorSecondary = $formattedDate;
}

$imageData = renderImageQuote($_REQUEST['c'], $quote, $author, $authorSecondary);
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