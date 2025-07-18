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
        $maxResults = 6;
        
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
            
            // Try exact case-insensitive match first
            $highlighted = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<em>$1</em>', $text);
            
            // If no match and query/text differ when normalized, try accent-insensitive highlighting
            if ($highlighted === $text) {
                $normalizedQuery = convertAccentsAndSpecialToNormal(strtolower($query));
                $normalizedText = convertAccentsAndSpecialToNormal(strtolower($text));
                
                if (strpos($normalizedText, $normalizedQuery) !== false) {
                    // Find position in normalized text
                    $pos = strpos($normalizedText, $normalizedQuery);
                    $len = strlen($normalizedQuery);
                    
                    // Extract the corresponding segment from original text
                    // This is a simplified approach - may not be perfect for complex cases
                    $beforeText = substr($text, 0, $pos);
                    $matchText = substr($text, $pos, $len);
                    $afterText = substr($text, $pos + $len);
                    
                    $highlighted = $beforeText . '<em>' . $matchText . '</em>' . $afterText;
                }
            }
            
            return $highlighted;
        };
        
        // Helper function to create excerpt for document alternative labels
        $createDocumentExcerpt = function($text, $query, $highlightText) {
            if (empty($text)) {
                return null;
            }
            
            $words = explode(' ', $text);
            $wordCount = count($words);
            
            // If 7 words or less, return the whole text highlighted
            if ($wordCount <= 7) {
                return $highlightText($text, $query);
            }
            
            // Find which word contains the search term (case-insensitive and accent-insensitive)
            $hitWordIndex = -1;
            $normalizedQuery = convertAccentsAndSpecialToNormal(strtolower($query));
            
            foreach ($words as $index => $word) {
                $normalizedWord = convertAccentsAndSpecialToNormal(strtolower($word));
                if (strpos($normalizedWord, $normalizedQuery) !== false) {
                    $hitWordIndex = $index;
                    break;
                }
            }
            
            // If no hit found in any word, fallback to first 7 words
            if ($hitWordIndex === -1) {
                $excerpt = implode(' ', array_slice($words, 0, 7));
                return $excerpt . '...';
            }
            
            // Calculate excerpt window (maximum 7 words total, centered around the hit)
            $maxWords = 7;
            $wordsBeforeHit = min(3, $hitWordIndex);
            $wordsAfterHit = min(3, $wordCount - 1 - $hitWordIndex);
            
            // Adjust to use available space if one side has fewer words
            if ($wordsBeforeHit < 3) {
                $wordsAfterHit = min($wordsAfterHit, $maxWords - 1 - $wordsBeforeHit);
            }
            if ($wordsAfterHit < 3) {
                $wordsBeforeHit = min($wordsBeforeHit, $maxWords - 1 - $wordsAfterHit);
            }
            
            $startIndex = max(0, $hitWordIndex - $wordsBeforeHit);
            $endIndex = min($wordCount - 1, $hitWordIndex + $wordsAfterHit);
            
            // Extract the excerpt
            $excerptWords = array_slice($words, $startIndex, $endIndex - $startIndex + 1);
            $excerpt = implode(' ', $excerptWords);
            
            // Add ellipsis if needed
            $prefix = $startIndex > 0 ? '...' : '';
            $suffix = $endIndex < $wordCount - 1 ? '...' : '';
            
            return $prefix . $highlightText($excerpt, $query) . $suffix;
        };
        
        // Helper function to get the most relevant labelAlternative
        $getRelevantLabelAlternative = function($jsonText, $query, $mainLabel, $entityType) use ($highlightText, $createDocumentExcerpt) {
            if (empty($jsonText) || empty($query)) {
                return null;
            }
            $decoded = json_decode($jsonText, true);
            if (!is_array($decoded) || empty($decoded)) {
                return null;
            }
            
            // Helper function to check for accent-insensitive match
            $hasAccentInsensitiveMatch = function($text, $query) {
                if (empty($text) || empty($query)) return false;
                
                // First try exact case-insensitive match
                if (stripos($text, $query) !== false) return true;
                
                // Then try accent-insensitive match
                $normalizedText = convertAccentsAndSpecialToNormal(strtolower($text));
                $normalizedQuery = convertAccentsAndSpecialToNormal(strtolower($query));
                
                return strpos($normalizedText, $normalizedQuery) !== false;
            };
            
            // Check if main label has a hit
            $mainLabelHasHit = $hasAccentInsensitiveMatch($mainLabel, $query);
            
            // For person entities: only show alternative if there's a hit in alternatives
            if ($entityType === 'person') {
                // Only return alternative if there's a hit in the alternatives
                foreach ($decoded as $alternative) {
                    if ($hasAccentInsensitiveMatch($alternative, $query)) {
                        return $highlightText($alternative, $query);
                    }
                }
                return null; // No hit in alternatives for person
            }
            
            // For other entity types (organisation, term): use original logic
            if ($entityType === 'organisation' || $entityType === 'term') {
                // If main label has hit, return first alternative (if exists)
                if ($mainLabelHasHit) {
                    return $highlightText($decoded[0], $query);
                }
                
                // Find first alternative that has a hit
                foreach ($decoded as $alternative) {
                    if ($hasAccentInsensitiveMatch($alternative, $query)) {
                        return $highlightText($alternative, $query);
                    }
                }
                
                return null;
            }
            
            // For document entities: use original logic but apply excerpt formatting
            if ($entityType === 'document') {
                $relevantAlternative = null;
                
                // If main label has hit, return first alternative (original behavior)
                if ($mainLabelHasHit && !empty($decoded[0])) {
                    return $createDocumentExcerpt($decoded[0], $query, $highlightText);
                }
                
                // Check if first alternative has hit
                if (!empty($decoded[0]) && $hasAccentInsensitiveMatch($decoded[0], $query)) {
                    return $createDocumentExcerpt($decoded[0], $query, $highlightText);
                }
                
                // Find first alternative that has a hit
                foreach ($decoded as $alternative) {
                    if ($hasAccentInsensitiveMatch($alternative, $query)) {
                        return $createDocumentExcerpt($alternative, $query, $highlightText);
                    }
                }
                
                return null;
            }
            
            return null;
        };
        
        // Helper function to check for accent-insensitive match (reusable outside getRelevantLabelAlternative)
        $hasAccentInsensitiveMatch = function($text, $query) {
            if (empty($text) || empty($query)) return false;
            
            // First try exact case-insensitive match
            if (stripos($text, $query) !== false) return true;
            
            // Then try accent-insensitive match
            $normalizedText = convertAccentsAndSpecialToNormal(strtolower($text));
            $normalizedQuery = convertAccentsAndSpecialToNormal(strtolower($query));
            
            return strpos($normalizedText, $normalizedQuery) !== false;
        };
        
        // 1. People with type memberOfParliament
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $peopleMP = $db->getAll("SELECT p.PersonID, p.PersonLabel, p.PersonLabelAlternative, p.PersonType, p.PersonFactionOrganisationID, p.PersonThumbnailURI, ofr.OrganisationLabel as FactionLabel FROM ?n AS p LEFT JOIN ?n as ofr ON ofr.OrganisationID = p.PersonFactionOrganisationID WHERE p.PersonType = 'memberOfParliament' AND (LOWER(p.PersonLabel) LIKE LOWER(?s) OR LOWER(p.PersonFirstName) LIKE LOWER(?s) OR LOWER(p.PersonLastName) LIKE LOWER(?s) OR LOWER(p.PersonLabelAlternative) LIKE LOWER(?s)) ORDER BY p.PersonLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Person"],
                $config["platform"]["sql"]["tbl"]["Organisation"],
                "%".$query."%",
                "%".$query."%", 
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($peopleMP as $person) {
                // Check if there's a visible match in main label or alternatives
                $mainLabelHasHit = $hasAccentInsensitiveMatch($person['PersonLabel'], $query) ||
                                  $hasAccentInsensitiveMatch($person['PersonFirstName'], $query) ||
                                  $hasAccentInsensitiveMatch($person['PersonLastName'], $query);
                
                $alternativeLabel = $getRelevantLabelAlternative($person['PersonLabelAlternative'], $query, $person['PersonLabel'], 'person');
                
                // Only include person if there's a visible match
                if ($mainLabelHasHit || $alternativeLabel !== null) {
                    $result = [
                        'id' => $person['PersonID'],
                        'type' => 'person',
                        'label' => $highlightText($person['PersonLabel'], $query),
                        'labelAlternative' => $alternativeLabel,
                        'thumbnailURI' => $person['PersonThumbnailURI']
                    ];
                    
                    // Add faction information if available
                    if ($person['PersonFactionOrganisationID'] && $person['FactionLabel']) {
                        $result['faction'] = [
                            'id' => $person['PersonFactionOrganisationID'],
                            'label' => $person['FactionLabel']
                        ];
                    }
                    
                    $results[] = $result;
                }
            }
        }
        
        // 2. Other people (all without type memberOfParliament)
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $peopleOther = $db->getAll("SELECT p.PersonID, p.PersonLabel, p.PersonLabelAlternative, p.PersonType, p.PersonFactionOrganisationID, p.PersonThumbnailURI, ofr.OrganisationLabel as FactionLabel FROM ?n AS p LEFT JOIN ?n as ofr ON ofr.OrganisationID = p.PersonFactionOrganisationID WHERE p.PersonType != 'memberOfParliament' AND (LOWER(p.PersonLabel) LIKE LOWER(?s) OR LOWER(p.PersonFirstName) LIKE LOWER(?s) OR LOWER(p.PersonLastName) LIKE LOWER(?s) OR LOWER(p.PersonLabelAlternative) LIKE LOWER(?s)) ORDER BY p.PersonLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Person"],
                $config["platform"]["sql"]["tbl"]["Organisation"],
                "%".$query."%",
                "%".$query."%",
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($peopleOther as $person) {
                // Check if there's a visible match in main label or alternatives
                $mainLabelHasHit = $hasAccentInsensitiveMatch($person['PersonLabel'], $query) ||
                                  $hasAccentInsensitiveMatch($person['PersonFirstName'], $query) ||
                                  $hasAccentInsensitiveMatch($person['PersonLastName'], $query);
                
                $alternativeLabel = $getRelevantLabelAlternative($person['PersonLabelAlternative'], $query, $person['PersonLabel'], 'person');
                
                // Only include person if there's a visible match
                if ($mainLabelHasHit || $alternativeLabel !== null) {
                    $result = [
                        'id' => $person['PersonID'],
                        'type' => 'person',
                        'label' => $highlightText($person['PersonLabel'], $query),
                        'labelAlternative' => $alternativeLabel,
                        'thumbnailURI' => $person['PersonThumbnailURI']
                    ];
                    
                    // Add faction information if available
                    if ($person['PersonFactionOrganisationID'] && $person['FactionLabel']) {
                        $result['faction'] = [
                            'id' => $person['PersonFactionOrganisationID'],
                            'label' => $person['FactionLabel']
                        ];
                    }
                    
                    $results[] = $result;
                }
            }
        }
        
        // 3. Organisations
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $organisations = $db->getAll("SELECT OrganisationID, OrganisationLabel, OrganisationLabelAlternative, OrganisationThumbnailURI FROM ?n WHERE LOWER(OrganisationLabel) LIKE LOWER(?s) OR LOWER(OrganisationLabelAlternative) LIKE LOWER(?s) ORDER BY OrganisationLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Organisation"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($organisations as $org) {
                // Check if there's a visible match in main label or alternatives
                $mainLabelHasHit = $hasAccentInsensitiveMatch($org['OrganisationLabel'], $query);
                $alternativeLabel = $getRelevantLabelAlternative($org['OrganisationLabelAlternative'], $query, $org['OrganisationLabel'], 'organisation');
                
                // Only include organisation if there's a visible match
                if ($mainLabelHasHit || $alternativeLabel !== null) {
                    $results[] = [
                        'id' => $org['OrganisationID'],
                        'type' => 'organisation',
                        'label' => $highlightText($org['OrganisationLabel'], $query),
                        'labelAlternative' => $alternativeLabel,
                        'thumbnailURI' => $org['OrganisationThumbnailURI']
                    ];
                }
            }
        }
        
        // 4. Terms
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $terms = $db->getAll("SELECT TermID, TermLabel, TermLabelAlternative, TermThumbnailURI FROM ?n WHERE LOWER(TermLabel) LIKE LOWER(?s) OR LOWER(TermLabelAlternative) LIKE LOWER(?s) ORDER BY TermLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Term"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($terms as $term) {
                // Check if there's a visible match in main label or alternatives
                $mainLabelHasHit = $hasAccentInsensitiveMatch($term['TermLabel'], $query);
                $alternativeLabel = $getRelevantLabelAlternative($term['TermLabelAlternative'], $query, $term['TermLabel'], 'term');
                
                // Only include term if there's a visible match
                if ($mainLabelHasHit || $alternativeLabel !== null) {
                    $results[] = [
                        'id' => $term['TermID'],
                        'type' => 'term',
                        'label' => $highlightText($term['TermLabel'], $query),
                        'labelAlternative' => $alternativeLabel,
                        'thumbnailURI' => $term['TermThumbnailURI']
                    ];
                }
            }
        }
        
        // 5. Documents - prioritize legalDocument type first
        if (count($results) < $maxResults) {
            $remainingSlots = $maxResults - count($results);
            $documents = $db->getAll("SELECT DocumentID, DocumentLabel, DocumentLabelAlternative, DocumentType, DocumentThumbnailURI FROM ?n WHERE LOWER(DocumentLabel) LIKE LOWER(?s) OR LOWER(DocumentLabelAlternative) LIKE LOWER(?s) ORDER BY CASE WHEN DocumentType = 'legalDocument' THEN 0 ELSE 1 END, DocumentLabel ASC LIMIT ?i",
                $config["platform"]["sql"]["tbl"]["Document"],
                "%".$query."%",
                "%".$query."%",
                $remainingSlots
            );
            
            foreach ($documents as $doc) {
                // Check if there's a visible match in main label or alternatives
                $mainLabelHasHit = $hasAccentInsensitiveMatch($doc['DocumentLabel'], $query);
                $alternativeLabel = $getRelevantLabelAlternative($doc['DocumentLabelAlternative'], $query, $doc['DocumentLabel'], 'document');
                
                // Only include document if there's a visible match
                if ($mainLabelHasHit || $alternativeLabel !== null) {
                    $results[] = [
                        'id' => $doc['DocumentID'],
                        'type' => 'document',
                        'label' => $highlightText($doc['DocumentLabel'], $query),
                        'labelAlternative' => $alternativeLabel,
                        'thumbnailURI' => $doc['DocumentThumbnailURI']
                    ];
                }
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