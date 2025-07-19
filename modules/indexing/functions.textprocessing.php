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
    
    // Split on whitespace, symbols, separators, and punctuation EXCEPT hyphens and slashes between alphanumeric characters
    // This preserves hyphenated compound words like "Riester-Rente", "COVID-19" and slash combinations like "CDU/CSU"
    $splitPattern = '/[\s\p{S}\p{Z}]|[\p{P}&&[^\/-]]|(?<![\p{L}\p{N}])[\/-]+|[\/-]+(?![\p{L}\p{N}])/u';
    
    $words = preg_split($splitPattern, strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    
    // Filter and clean words
    $cleanWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        
        // Remove punctuation from start/end but preserve hyphens, slashes, and apostrophes
        $word = preg_replace('/^[\p{P}\p{S}\p{Z}&&[^\'\/-]]+|[\p{P}\p{S}\p{Z}&&[^\'\/-]]+$/u', '', $word);
        
        // Keep only letters, numbers, international characters, apostrophes, hyphens, and slashes
        $word = preg_replace('/[^a-zäöüßàáâãåæçèéêëìíîïðñòóôõøùúûüýþÿā-žА-я0-9\'\/-]/ui', '', $word);
        
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
 * Normalize a word (basic cleaning only - no stemming)
 */
function normalizeWord($word) {
    // Convert to lowercase and trim
    $normalized = strtolower(trim($word));
    
    // Remove punctuation from start/end but preserve hyphens, slashes, and apostrophes
    $normalized = preg_replace('/^[\p{P}\p{S}\p{Z}&&[^\'\/-]]+|[\p{P}\p{S}\p{Z}&&[^\'\/-]]+$/u', '', $normalized);
    
    // Keep only letters (international), numbers, apostrophes for contractions, hyphens, and slashes
    $normalized = preg_replace('/[^a-zäöüßàáâãåæçèéêëìíîïðñòóôõøùúûüýþÿā-žА-я0-9\'\/-]/ui', '', $normalized);
    
    // Remove leading/trailing apostrophes, hyphens, and slashes (but keep internal ones)
    $normalized = trim($normalized, '\'-/');
    
    // Filter out words shorter than 3 characters (reduces noise)
    if (strlen($normalized) < 3) {
        return '';
    }
    
    // Return normalized word without stemming
    // OpenSearch can handle stemming at search time with better quality
    return $normalized;
}

/**
 * Apply stemming using OpenSearch analyzer
 */
function applyStemming($word) {
    static $ESClient = null;
    static $stemmingAvailable = null;
    
    // Initialize OpenSearch client once
    if ($ESClient === null) {
        $ESClient = getApiOpenSearchClient();
        if (is_array($ESClient) && isset($ESClient["errors"])) {
            // If OpenSearch is not available, return original word
            $stemmingAvailable = false;
            error_log("OpenSearch client not available for stemming, falling back to original words");
            return $word;
        }
    }
    
    // If stemming was determined to be unavailable, don't retry
    if ($stemmingAvailable === false) {
        return $word;
    }
    
    try {
        // Use the German stemmer analyzer we configured
        $response = $ESClient->indices()->analyze([
            'body' => [
                'tokenizer' => 'standard',
                'filter' => [
                    'lowercase',
                    [
                        'type' => 'stemmer',
                        'name' => 'light_german'
                    ]
                ],
                'text' => $word
            ]
        ]);
        
        if (isset($response['tokens'][0]['token'])) {
            $stemmingAvailable = true;
            $stemmed = $response['tokens'][0]['token'];
            // Log first few successful stems to confirm it's working
            static $loggedCount = 0;
            if ($loggedCount < 5) {
                error_log("Stemming working: '$word' -> '$stemmed'");
                $loggedCount++;
            }
            return $stemmed;
        } else {
            error_log("Stemming response missing tokens for word '$word': " . json_encode($response));
        }
    } catch (Exception $e) {
        // If stemming fails, log the error and return original word
        error_log("Stemming failed for word '$word': " . $e->getMessage());
        $stemmingAvailable = false;
    }
    
    return $word;
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