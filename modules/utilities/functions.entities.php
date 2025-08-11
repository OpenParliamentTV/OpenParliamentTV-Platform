<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/safemysql.class.php");
require_once(__DIR__."/functions.api.php");

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

function getMainSpeakerFromPeopleArray($annotationsArray, $peopleArray) {

    if (!is_array($annotationsArray) || !is_array($peopleArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker") {
            foreach ($peopleArray as $person) {
                if ($person["id"] == $annotation["id"]) {
                    return $person;
                }
            }
        }
    }

    return null;
}

function getMainFactionFromOrganisationsArray($annotationsArray, $organisationsArray) {

    if (!is_array($annotationsArray) || !is_array($organisationsArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker-faction") {
            foreach ($organisationsArray as $organisation) {
                if ($organisation["id"] == $annotation["id"]) {
                    return $organisation;
                }
            }
        }
    }

    return null;
}

?>