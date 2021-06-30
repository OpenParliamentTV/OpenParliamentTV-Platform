<?php

textObjectToHTMLString('{
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
	    		"text": "Guten Morgen, liebe Kolleginnen und Kollegen!",
	    		"timeStart": 2.45768,
	    		"timeEnd": 5.087213976
	    	},
	    	{
	    		"text": "Nehmen Sie bitte Platz.",
	    		"timeStart": 5.092234,
	    		"timeEnd": 7.197823
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
}');

function textObjectToHTMLString($inputTextObject) {
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
			$timeAttributes = '';
			
			if ($sentence['timeStart'] && $sentence['timeEnd']) {
				
				$timeAttributes = ' data-start="'.$sentence['timeStart'].'" data-end="'.$sentence['timeEnd'].'"';

			}
			
			$outputHTML .= '<span'.$timeAttributes.'>'.$sentence['text'].'</span>';
		}

		$outputHTML .= '</p>';
	}

	$outputHTML .= '</div>';

	echo '<pre>';
	print_r($outputHTML);
	echo '</pre>';

}

function textObjectToAlignmentInput($inputTextObject) {
	if (is_string($inputTextObject)) {
		$inputTextObject = json_decode($inputTextObject, 1);
		if (!$inputTextObject) {
			echo 'Input text could not be parsed as JSON.';
		}
	} else {
		echo 'Input text needs to be a String';
	}
}

function alignmentOutputToTextObject($alignmentOutput) {
	
}


?>