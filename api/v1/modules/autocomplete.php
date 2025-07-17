<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");
require_once (__DIR__."/../../../modules/search/functions.php");

function fulltextAutocomplete($text) {
    if (!isset($text)) {
        return createApiErrorMissingParameter('text');
    }

    if (strlen($text) <= 2) {
        return createApiErrorInvalidLength('text', 3);
    }

    try {
        // Use the original autocomplete function which already returns the right format
        $autocompleteResult = searchAutocomplete($text);
        
        return createApiSuccessResponse($autocompleteResult);
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => $e->getMessage()]
        );
    }
}

function agendaItemAutocomplete($query) {
    if (!isset($query)) {
        return createApiErrorMissingParameter('q');
    }

    if (strlen($query) <= 2) {
        return createApiErrorInvalidLength('q', 3);
    }

    try {
        $autocompleteResult = searchAgendaItemAutocomplete($query);
        return createApiSuccessResponse($autocompleteResult);
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => $e->getMessage()]
        );
    }
}

function entitiesAutocomplete($query) {
    if (!isset($query)) {
        return createApiErrorMissingParameter('q');
    }

    if (strlen($query) <= 2) {
        return createApiErrorInvalidLength('q', 3);
    }

    try {
        global $config;
        $results = [];
        $maxResults = 8;
        
        // Get database connection
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            return createApiErrorDatabaseConnection();
        }
        
        // Helper function to highlight search term in text
        $highlightText = function($text, $query) {
            if (empty($text) || empty($query)) {
                return $text;
            }
            // Case-insensitive highlighting
            return preg_replace('/(' . preg_quote($query, '/') . ')/i', '<em>$1</em>', $text);
        };
        
        // Helper function to get the most relevant labelAlternative
        $getRelevantLabelAlternative = function($jsonText, $query, $mainLabel) use ($highlightText) {
            if (empty($jsonText) || empty($query)) {
                return null;
            }
            $decoded = json_decode($jsonText, true);
            if (!is_array($decoded) || empty($decoded)) {
                return null;
            }
            
            // Check if main label has a hit
            $mainLabelHasHit = stripos($mainLabel, $query) !== false;
            
            // If main label has hit, return first alternative (if exists)
            if ($mainLabelHasHit) {
                return $highlightText($decoded[0], $query);
            }
            
            // Check if first alternative has hit
            if (stripos($decoded[0], $query) !== false) {
                return $highlightText($decoded[0], $query);
            }
            
            // Find first alternative that has a hit
            foreach ($decoded as $alternative) {
                if (stripos($alternative, $query) !== false) {
                    return $highlightText($alternative, $query);
                }
            }
            
            // Fallback to first alternative (shouldn't happen due to our WHERE clause)
            return $highlightText($decoded[0], $query);
        };
        
        // 1. People with type memberOfParliament
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $peopleMP = $db->getAll("SELECT PersonID, PersonLabel, PersonLabelAlternative, PersonType FROM ?n WHERE PersonType = 'memberOfParliament' AND (LOWER(PersonLabel) LIKE LOWER(?s) OR LOWER(PersonFirstName) LIKE LOWER(?s) OR LOWER(PersonLastName) LIKE LOWER(?s) OR LOWER(PersonLabelAlternative) LIKE LOWER(?s)) ORDER BY PersonLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Person"],
                "%".$query."%",
                "%".$query."%", 
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($peopleMP as $person) {
                $results[] = [
                    'id' => $person['PersonID'],
                    'type' => 'person',
                    'label' => $highlightText($person['PersonLabel'], $query),
                    'labelAlternative' => $getRelevantLabelAlternative($person['PersonLabelAlternative'], $query, $person['PersonLabel'])
                ];
            }
        }
        
        // 2. Other people (all without type memberOfParliament)
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $peopleOther = $db->getAll("SELECT PersonID, PersonLabel, PersonLabelAlternative, PersonType FROM ?n WHERE PersonType != 'memberOfParliament' AND (LOWER(PersonLabel) LIKE LOWER(?s) OR LOWER(PersonFirstName) LIKE LOWER(?s) OR LOWER(PersonLastName) LIKE LOWER(?s) OR LOWER(PersonLabelAlternative) LIKE LOWER(?s)) ORDER BY PersonLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Person"],
                "%".$query."%",
                "%".$query."%",
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($peopleOther as $person) {
                $results[] = [
                    'id' => $person['PersonID'],
                    'type' => 'person',
                    'label' => $highlightText($person['PersonLabel'], $query),
                    'labelAlternative' => $getRelevantLabelAlternative($person['PersonLabelAlternative'], $query, $person['PersonLabel'])
                ];
            }
        }
        
        // 3. Organisations
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $organisations = $db->getAll("SELECT OrganisationID, OrganisationLabel, OrganisationLabelAlternative FROM ?n WHERE LOWER(OrganisationLabel) LIKE LOWER(?s) OR LOWER(OrganisationLabelAlternative) LIKE LOWER(?s) ORDER BY OrganisationLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Organisation"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($organisations as $org) {
                $results[] = [
                    'id' => $org['OrganisationID'],
                    'type' => 'organisation',
                    'label' => $highlightText($org['OrganisationLabel'], $query),
                    'labelAlternative' => $getRelevantLabelAlternative($org['OrganisationLabelAlternative'], $query, $org['OrganisationLabel'])
                ];
            }
        }
        
        // 4. Terms
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $terms = $db->getAll("SELECT TermID, TermLabel, TermLabelAlternative FROM ?n WHERE LOWER(TermLabel) LIKE LOWER(?s) OR LOWER(TermLabelAlternative) LIKE LOWER(?s) ORDER BY TermLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Term"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($terms as $term) {
                $results[] = [
                    'id' => $term['TermID'],
                    'type' => 'term',
                    'label' => $highlightText($term['TermLabel'], $query),
                    'labelAlternative' => $getRelevantLabelAlternative($term['TermLabelAlternative'], $query, $term['TermLabel'])
                ];
            }
        }
        
        // 5. Documents
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $documents = $db->getAll("SELECT DocumentID, DocumentLabel, DocumentLabelAlternative FROM ?n WHERE LOWER(DocumentLabel) LIKE LOWER(?s) OR LOWER(DocumentLabelAlternative) LIKE LOWER(?s) ORDER BY DocumentLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Document"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($documents as $doc) {
                $results[] = [
                    'id' => $doc['DocumentID'],
                    'type' => 'document',
                    'label' => $highlightText($doc['DocumentLabel'], $query),
                    'labelAlternative' => $getRelevantLabelAlternative($doc['DocumentLabelAlternative'], $query, $doc['DocumentLabel'])
                ];
            }
        }
        
        return createApiSuccessResponse($results);
        
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => $e->getMessage()]
        );
    }
}
?>