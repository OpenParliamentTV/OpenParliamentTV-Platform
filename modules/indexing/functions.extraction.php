<?php


/**
 * Extract main speaker ID from speech annotations (context: main-speaker)
 */
function extractMainSpeakerId($speechData) {
    if (!isset($speechData['annotations']['data'])) {
        return null;
    }
    
    $annotations = $speechData['annotations']['data'];
    
    foreach ($annotations as $annotation) {
        if ($annotation['type'] === 'person' && 
            isset($annotation['attributes']['context']) && 
            $annotation['attributes']['context'] === 'main-speaker') {
            return $annotation['id'];
        }
    }
    
    return null;
}

/**
 * Extract main speaker faction from speech annotations (context: main-speaker-faction)
 */
function extractMainSpeakerFaction($speechData) {
    if (!isset($speechData['annotations']['data'])) {
        return null;
    }
    
    $annotations = $speechData['annotations']['data'];
    
    foreach ($annotations as $annotation) {
        if ($annotation['type'] === 'organisation' && 
            isset($annotation['attributes']['context']) && 
            $annotation['attributes']['context'] === 'main-speaker-faction') {
            
            return [
                'id' => $annotation['id'],
                'label' => null // Labels not needed - use ID only for performance
            ];
        }
    }
    
    return null;
}

// Legacy functions extractPartyInfo() and extractSpeakerId() removed - use extractMainSpeakerFaction() and extractMainSpeakerId() directly

/**
 * Extract session ID from speech data
 */
function extractSessionId($speechData) {
    return $speechData['relationships']['session']['data']['id'] ?? null;
}

/**
 * Extract date from speech data - returns timestamp for better OpenSearch performance
 */
function extractSpeechDate($speechData) {
    // Try timestamp first (best for OpenSearch operations)
    if (isset($speechData['attributes']['dateStartTimestamp'])) {
        return $speechData['attributes']['dateStartTimestamp'];
    }
    
    // Try multiple date field locations in order of preference
    $dateFields = [
        'attributes.dateStart',     // Primary field found in real documents
        'attributes.date',          // Fallback
        'attributes.dateCreated',   // Alternative
        'relationships.session.data.attributes.date' // Session date fallback
    ];
    
    foreach ($dateFields as $field) {
        $parts = explode('.', $field);
        $value = $speechData;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                $value = null;
                break;
            }
        }
        
        if ($value) {
            // Convert to timestamp if it's a date string
            if (is_string($value)) {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
            return $value;
        }
    }
    
    return null;
}

/**
 * Extract date as string (YYYY-MM-DD) for display purposes
 */
function extractSpeechDateString($speechData) {
    $timestamp = extractSpeechDate($speechData);
    if ($timestamp) {
        return date('Y-m-d', is_numeric($timestamp) ? $timestamp : strtotime($timestamp));
    }
    return null;
}

/**
 * Extract date as daily string (YYYY-MM-DD) for aggregation purposes
 * Daily aggregation for optimal performance during indexing
 */
function extractSpeechDailyString($speechData) {
    $timestamp = extractSpeechDate($speechData);
    if ($timestamp) {
        $ts = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        return date('Y-m-d', $ts);
    }
    return null;
}

/**
 * Extract clean text from speech data (from existing sentences)
 */
function extractCleanText($speechData) {
    $cleanText = '';
    
    // Check if speech has any text content
    if (!isset($speechData['attributes']['textContents']) || 
        !is_array($speechData['attributes']['textContents']) ||
        empty($speechData['attributes']['textContents'])) {
        return $cleanText;
    }
    
    // Check textContentsCount if available
    if (isset($speechData['attributes']['textContentsCount']) && $speechData['attributes']['textContentsCount'] == 0) {
        return $cleanText;
    }
    
    foreach ($speechData['attributes']['textContents'] as $textContent) {
        if (!isset($textContent['textBody']) || !is_array($textContent['textBody'])) continue;
        
        foreach ($textContent['textBody'] as $textBodyItem) {
            if (!isset($textBodyItem['sentences']) || !is_array($textBodyItem['sentences'])) continue;
            
            foreach ($textBodyItem['sentences'] as $sentence) {
                if (!isset($sentence['text']) || empty($sentence['text'])) continue;
                $cleanText .= $sentence['text'] . ' ';
            }
        }
    }
    
    return trim($cleanText);
}