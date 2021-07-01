<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

$exampleTextObject = '{
	"type": "proceedings",
	"sourceURI": "example.xml",
	"creator": "Deutscher Bundestag",
	"license": "Public Domain",
	"language": "DE-de",
	"originTextID": null,
	"textBody": [
	  {
	    "type": "speech",
	    "speaker": "Hermann Otto Solms",
	    "speakerstatus": "mainSpeaker",
	    "text": "Guten Morgen, liebe Kolleginnen und Kollegen! Nehmen Sie bitte Platz.",
	    "sentences": [
	    	{
	    		"text": "Guten Morgen, liebe Kolleginnen und Kollegen!"
	    	},
	    	{
	    		"text": "Nehmen Sie bitte Platz."
	    	}
	    ]
	  },
	  {
	    "type": "comment",
	    "speaker": null,
	    "speakerstatus": null,
	    "text": "(Lachen bei Abgeordneten der AfD)",
	    "sentences": [
	    	{
	    		"text": "(Lachen bei Abgeordneten der AfD)"
	    	}
	    ]
	  }
	]
}';

$exampleAlignmentOutput = '{
 "fragments": [
  {
   "begin": "2.45768", 
   "children": [], 
   "end": "5.087213976", 
   "id": "s000001", 
   "language": "deu", 
   "lines": [
    "Guten Morgen, liebe Kolleginnen und Kollegen! "
   ]
  }, 
  {
   "begin": "5.092234", 
   "children": [], 
   "end": "7.197823", 
   "id": "s000002", 
   "language": "deu", 
   "lines": [
    "Nehmen Sie bitte Platz. "
   ]
  }
 ]
}';

//print_r(textObjectToHTMLString($exampleTextObject));
//print_r(textObjectToAlignmentInput($exampleTextObject));

header('Content-Type: application/json');
print_r(mergeAlignmentOutputWithTextObject($exampleAlignmentOutput, $exampleTextObject));

function textObjectToHTMLString($inputTextObject, $autoAddIDs = false) {
	
	$sentenceID = 0;

	if (is_string($inputTextObject)) {
		$inputTextObject = json_decode($inputTextObject, 1);
		if (!$inputTextObject) {
			echo 'Input text could not be parsed as JSON.';
		}
	} else {
		echo 'Input text needs to be a String';
	}
	
	$outputHTML = '<div>';

	foreach ($inputTextObject['textBody'] as $paragraph) {
		
		$outputHTML .= '<p data-type="'.$paragraph['type'].'">';
		
		foreach ($paragraph['sentences'] as $sentence) {
			$idAttribute = '';
			$timeAttributes = '';
			
			if ($autoAddIDs && $paragraph['type'] == 'speech') {
				$idAttribute = ' id="s'.sprintf('%06d', ++$sentenceID).'"';
			}

			if ($sentence['timeStart'] && $sentence['timeEnd']) {
				
				$timeAttributes = ' class="timebased" data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';

			}
			
			$outputHTML .= '<span'.$idAttribute.$timeAttributes.'>'.$sentence['text'].'</span>';
		}

		$outputHTML .= '</p>';
	}

	$outputHTML .= '</div>';

	return $outputHTML;

}

function textObjectToAlignmentInput($inputTextObject) {
	
	$outputXML = '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><meta charset="utf-8"/></head><body>';

	$outputXML .= textObjectToHTMLString($inputTextObject, true);

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
	}

	return json_encode($inputTextObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

}


?>