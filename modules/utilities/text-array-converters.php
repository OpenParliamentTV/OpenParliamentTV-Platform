<?php

function textObjectToHTMLString($inputTextObject, $mediaFileURI, $mediaID, $autoAddIDs = false) {
	
	$sentenceID = 0;

	if (is_string($inputTextObject)) {
		$inputTextObject = json_decode($inputTextObject, 1);
		if (!$inputTextObject) {
			//echo 'Input text could not be parsed as JSON.';
		}
	} else {
		//echo 'Input text needs to be a String';
	}
	
	$outputHTML = '<div data-media-file-uri="'.$mediaFileURI.'" data-media-id="'.$mediaID.'">';

	if (!isset($inputTextObject['textBody'])) {
		return '';
	}

	foreach ($inputTextObject['textBody'] as $paragraph) {
		
		$outputHTML .= '<p data-type="'.$paragraph['type'].'">';

		$sentences = $paragraph['sentences'];

		foreach ($sentences as $sentence) {

			$idAttribute = '';
			$timeAttributes = '';
			
			if ($autoAddIDs && $paragraph['type'] == 'speech') {
				$idAttribute = ' id="s'.sprintf('%06d', ++$sentenceID).'"';
			}

			if (isset($sentence['timeStart']) && isset($sentence['timeEnd'])) {
				
				$timeAttributes = ' class="timebased" data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';

			}

			$sentenceText = (is_array($sentence)) ? $sentence['text'] : $sentence;
			$outputHTML .= '<span'.$idAttribute.$timeAttributes.'>'.$sentenceText.' </span>';
		}

		$outputHTML .= '</p>';
	}

	$outputHTML .= '</div>';

	return $outputHTML;

}

function simpleTextBodyArrayToHTMLString($textBody) {
	
	$outputHTML = '<p data-type="'.$textBody['type'].'">';

	//TODO: REMOVE QUICK FIX 
	/*
	if (count($paragraph['sentences']) == 1) {
		$sentences = $paragraph['sentences'][0];
	} else {
		$sentences = $paragraph['sentences'];
	}
	*/
	$sentences = $textBody['sentences'];

	foreach ($sentences as $sentence) {

		$timeAttributes = '';

		if (isset($sentence['timeStart']) && isset($sentence['timeEnd'])) {
			
			$timeAttributes = ' class="timebased" data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';

		}

		$sentenceText = (is_array($sentence)) ? $sentence['text'] : $sentence;
		$outputHTML .= '<span'.$timeAttributes.'>'.$sentenceText.' </span>';
	}

	$outputHTML .= '</p>';

	return $outputHTML;
}



?>