<?php

/*
header('Content-Type: application/json');
print_r(mergeAlignmentOutputWithTextObject($exampleAlignmentOutput, $exampleTextObject));
*/

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

function textObjectToAlignmentInput($inputTextObject, $mediaFileURI, $mediaID) {
	
	$outputXML = '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><meta charset="utf-8"/></head><body>';

	$outputXML .= textObjectToHTMLString($inputTextObject, $mediaFileURI, $mediaID, true);

	$outputXML .= '</body></html>';

	return $outputXML;
}

function mergeAlignmentOutputWithTextObject($alignmentOutput, $inputTextObject) {
	
	$fragmentCnt = 0;

	if (is_string($inputTextObject)) {
		$inputTextObject = json_decode($inputTextObject, 1);
		if (!$inputTextObject) {
			echo 'Input text could not be parsed as JSON.';
		}
	} else {
		echo 'Input text needs to be a String';
	}

	if (is_string($alignmentOutput)) {
		$alignmentOutput = json_decode($alignmentOutput, 1);
		if (!$alignmentOutput) {
			echo 'Alignment Output could not be parsed as JSON.';
		}
	} else {
		echo 'Alignment Output needs to be a String';
	}

	foreach ($inputTextObject['textBody'] as $paragraphIndex => $paragraph) {
		
		if ($paragraph['type'] == 'speech') {
			foreach ($paragraph['sentences'] as $sentenceIndex => $sentence) {
				$fragmentID = 's'.sprintf('%06d', ++$fragmentCnt);
				foreach ($alignmentOutput['fragments'] as $fragment) {
					if ($fragment['id'] == $fragmentID) {
						
						$inputTextObject['textBody'][$paragraphIndex]['sentences'][$sentenceIndex]['timeStart'] = $fragment['begin'];
						$inputTextObject['textBody'][$paragraphIndex]['sentences'][$sentenceIndex]['timeEnd'] = $fragment['end'];

					}
				}
			}
		}

		$inputTextObject['textBody'][$paragraphIndex]['text'] = simpleTextBodyArrayToHTMLString($inputTextObject['textBody'][$paragraphIndex]);
	}

	$newTextHTML = '';
	foreach ($inputTextObject['textBody'] as $paragraphIndex => $paragraph) {
		foreach ($paragraph['sentences'] as $sentenceIndex => $sentence) {
			$newTextHTML .= $inputTextObject['textBody'][$paragraphIndex]['text'];
		}
	}
	$inputTextObject['textHTML'] = $newTextHTML;

	return json_encode($inputTextObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

}


?>