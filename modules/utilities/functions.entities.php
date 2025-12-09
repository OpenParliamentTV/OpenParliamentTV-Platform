<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/safemysql.class.php");
require_once(__DIR__."/../../api/v1/utilities.php");

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

function getRoleFromMainSpeakerAnnotation($annotationsArray) {

    if (!is_array($annotationsArray)) {
        return null;
    }

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker") {
            if (isset($annotation["attributes"]["additionalInformation"]["role"]) && 
                !empty($annotation["attributes"]["additionalInformation"]["role"])) {
                return $annotation["attributes"]["additionalInformation"]["role"];
            }
        }
    }

    return null;
}

function translateContextValue($value, $prefix = 'context') {
    if (!$value) {
        return null;
    }
    
    // Security: Only allow alphanumeric characters, dashes, and underscores
    // This prevents potential injection attacks through translation keys
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
        return $value; // Return original if contains invalid characters
    }
    
    // Security: Limit length to prevent excessively long translation keys
    if (strlen($value) > 50) {
        return $value; // Return original if too long
    }
    
    // Convert dash-separated values to camelCase (e.g., "interim-president" -> "interimPresident")
    // For single-word values like "Bundeskanzler", just use as-is
    if (strpos($value, '-') !== false) {
        $camelCaseValue = lcfirst(implode('', array_map('ucfirst', explode('-', $value))));
        $translationKey = $prefix . $camelCaseValue;
    } else {
        // For single words, just prefix and lowercase the first letter
        $translationKey = $prefix . lcfirst($value);
    }
    
    // Try to get translation, fallback to original if not found
    $translatedValue = L::$translationKey();
    
    // If translation key equals the returned value, it means no translation was found
    if ($translatedValue === $translationKey) {
        return $value; // Fallback to original
    }
    
    return $translatedValue;
}

?>