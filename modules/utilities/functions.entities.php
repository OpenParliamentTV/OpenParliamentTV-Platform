<?php

function flattenEntityJSON($array, $prefix = '') {
    $flat = array();
    $sep = ".";
    
    if (!is_array($array)) $array = (array)$array;
    
    foreach($array as $key => $value) {
        
        if ($key == 'type' || 
            $key == 'relationships' || 
            $key == 'gender' || 
            $key == 'socialMediaIDs') {
            continue;
        }

        //$_key = ltrim($prefix.$sep.$key, ".");
        $_key = $key;
        
        if (is_array($value) || is_object($value)) {
            // Iterate this one too
            $flat = array_merge($flat, flattenEntityJSON($value, $_key));
        } else {
            $flat[$_key] = $value;
        }
    }
    
    return $flat;
}

function getMainSpeakerFromPeopleArray($peopleArray) {
    
    foreach ($peopleArray as $person) {
        if ($person['attributes']['context'] == 'main-speaker') {
            $mainSpeaker = $person;
            break;
        }
    }

    if (!isset($mainSpeaker)) {
        $mainSpeaker = $peopleArray[0];
    }
    
    return $mainSpeaker;
}

function getMainFactionFromOrganisationsArray($organisationsArray) {
    
    foreach ($organisationsArray as $organisation) {
        if ($organisation['attributes']['context'] == 'main-speaker-faction') {
            $mainFaction = $organisation;
            break;
        }
    }

    if (!isset($mainFaction)) {
        //$mainFaction = $organisationsArray[0];
        $mainFaction = null;
    }

    return $mainFaction;
}


?>