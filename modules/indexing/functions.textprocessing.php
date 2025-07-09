<?php

/**
 * Load stopwords from config or use default German stopwords
 */
function getStopwords($config = null) {
    if ($config && isset($config['excludedStopwords'])) {
        return $config['excludedStopwords'];
    }
    
    return [
        'der', 'die', 'das', 'und', 'oder', 'aber', 'in', 'zu', 'mit', 'auf', 
        'für', 'von', 'bei', 'nach', 'über', 'unter', 'durch', 'vor', 'gegen',
        'ohne', 'um', 'am', 'im', 'zum', 'zur', 'vom', 'beim', 'ich', 'du',
        'er', 'sie', 'es', 'wir', 'ihr', 'sie', 'mein', 'dein', 'sein',
        'ihr', 'unser', 'euer', 'ist', 'sind', 'war', 'waren', 'hat', 'haben',
        'wird', 'werden', 'kann', 'könnte', 'soll', 'sollte', 'muss', 'müssen',
        'auch', 'noch', 'nur', 'schon', 'dann', 'doch', 'hier', 'da', 'so'
    ];
}

/**
 * Check if a word is a stopword
 */
function isStopword($word, $config = null) {
    $stopwords = getStopwords($config);
    return in_array(strtolower($word), $stopwords);
}

/**
 * Tokenize text into words
 */
function tokenizeWords($text) {
    // Remove HTML tags if any
    $text = strip_tags($text);
    
    // Comprehensive list of punctuation and special characters to split on
    // Using Unicode character classes and explicit character codes to avoid quote conflicts
    $splitPattern = '/[\s\p{P}\p{S}\p{Z}]+/u';
    
    $words = preg_split($splitPattern, strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    
    // Filter and clean words
    $cleanWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        
        // Remove any remaining punctuation from start/end using regex
        $word = preg_replace('/^[\p{P}\p{S}\p{Z}]+|[\p{P}\p{S}\p{Z}]+$/u', '', $word);
        
        // Keep only letters, including international characters and apostrophes in contractions
        $word = preg_replace('/[^a-zäöüßàáâãåæçèéêëìíîïðñòóôõøùúûüýþÿā-žА-я\']/ui', '', $word);
        
        // Filter out words that are too short, purely numeric, or don't contain letters
        if (strlen($word) >= 2 && 
            !is_numeric($word) && 
            preg_match('/[\p{L}]/u', $word) &&
            !preg_match('/^[\'\-\.]+$/u', $word)) { // Exclude words that are only punctuation
            $cleanWords[] = $word;
        }
    }
    return $cleanWords;
}

/**
 * Normalize a word (basic cleaning)
 */
function normalizeWord($word) {
    // Convert to lowercase and trim
    $normalized = strtolower(trim($word));
    
    // Remove punctuation from start/end using regex
    $normalized = preg_replace('/^[\p{P}\p{S}\p{Z}]+|[\p{P}\p{S}\p{Z}]+$/u', '', $normalized);
    
    // Keep only letters (international) and apostrophes for contractions
    $normalized = preg_replace('/[^a-zäöüßàáâãåæçèéêëìíîïðñòóôõøùúûüýþÿā-žА-я\']/ui', '', $normalized);
    
    // Remove leading/trailing apostrophes (but keep internal ones for contractions)
    $normalized = trim($normalized, '\'');
    
    // TODO: Add stemming here if needed (requires external library)
    
    return $normalized;
}

/**
 * Extract word frequencies from speech data using existing sentence structure
 */
function getWordFrequenciesFromSpeech($speechData, $config = null) {
    $frequencies = [];
    
    // Check if speech has any text content
    if (!isset($speechData['attributes']['textContents']) || 
        !is_array($speechData['attributes']['textContents']) ||
        empty($speechData['attributes']['textContents'])) {
        return $frequencies;
    }
    
    // Check textContentsCount if available
    if (isset($speechData['attributes']['textContentsCount']) && $speechData['attributes']['textContentsCount'] == 0) {
        return $frequencies;
    }
    
    // Iterate through existing sentence structure
    foreach ($speechData['attributes']['textContents'] as $textContent) {
        if (!isset($textContent['textBody']) || !is_array($textContent['textBody'])) continue;
        
        foreach ($textContent['textBody'] as $textBodyItem) {
            if (!isset($textBodyItem['sentences']) || !is_array($textBodyItem['sentences'])) continue;
            
            foreach ($textBodyItem['sentences'] as $sentence) {
                if (!isset($sentence['text']) || empty($sentence['text'])) continue;
                
                $words = tokenizeWords($sentence['text']);
                
                foreach ($words as $word) {
                    $normalized = normalizeWord($word);
                    
                    // Skip stopwords and very short words
                    if (!isStopword($normalized, $config) && strlen($normalized) >= 2) {
                        $frequencies[$normalized] = ($frequencies[$normalized] ?? 0) + 1;
                    }
                }
            }
        }
    }
    
    return $frequencies;
}

/**
 * Extract word frequencies from text (legacy function for backward compatibility)
 */
function getWordFrequencies($text, $config = null) {
    $words = tokenizeWords($text);
    $frequencies = [];
    
    foreach ($words as $word) {
        $normalized = normalizeWord($word);
        
        // Skip stopwords and very short words
        if (!isStopword($normalized, $config) && strlen($normalized) >= 2) {
            $frequencies[$normalized] = ($frequencies[$normalized] ?? 0) + 1;
        }
    }
    
    return $frequencies;
}

/**
 * Truncate text to specified length
 */
function truncateContext($text, $maxLength = 200) {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    
    // Try to cut at word boundary
    $truncated = substr($text, 0, $maxLength);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . '...';
}