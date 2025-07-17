<?php

require_once(__DIR__.'/functions.textprocessing.php');
require_once(__DIR__.'/functions.extraction.php');

/**
 * Index word events for a single speech
 */
function indexSpeechWordEvents($speechData, $parliamentCode = 'de') {
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        return ['success' => false, 'error' => 'Failed to initialize OpenSearch client'];
    }
    
    global $config;
    
    // Extract speech metadata using proper main-speaker context
    $speechId = $speechData['id'];
    $date = extractSpeechDateString($speechData); // Use date string for word events (yyyy-MM-dd format)
    $speakerId = extractMainSpeakerId($speechData); // Use main-speaker
    $sessionId = extractSessionId($speechData);
    $party = extractMainSpeakerFaction($speechData); // Use main-speaker-faction
    
    // Get text contents
    if (!isset($speechData['attributes']['textContents'])) {
        return ['success' => false, 'error' => 'No text contents found'];
    }
    
    $bulkData = [];
    $position = 0;
    $sentenceIndex = 0;
    
    // Iterate through text contents and existing sentences
    foreach ($speechData['attributes']['textContents'] as $textContent) {
        if (!isset($textContent['textBody'])) continue;
        
        foreach ($textContent['textBody'] as $textBodyItem) {
            if (!isset($textBodyItem['sentences'])) continue;
            
            foreach ($textBodyItem['sentences'] as $sentence) {
                $sentenceText = $sentence['text'];
                $timeStart = isset($sentence['timeStart']) ? floatval($sentence['timeStart']) : null;
                $timeEnd = isset($sentence['timeEnd']) ? floatval($sentence['timeEnd']) : null;
                $words = tokenizeWords($sentenceText);
                
                // Generate sentence ID for reference
                $sentenceId = $speechId . '_sent_' . $sentenceIndex;
                $positionInSentence = 0;
                
                foreach ($words as $word) {
                    $normalizedWord = normalizeWord($word);
                    
                    // Skip empty normalized words (too short, punctuation-only, etc.)
                    if (empty($normalizedWord)) {
                        $position++;
                        $positionInSentence++;
                        continue;
                    }
                    
                    // Skip stopwords for events index
                    if (isStopword($normalizedWord, $config)) {
                        $position++;
                        $positionInSentence++;
                        continue;
                    }
                    
                    $wordEvent = [
                        'word' => $normalizedWord,
                        'speech_id' => $speechId,
                        'speaker_id' => $speakerId,
                        'party_id' => $party['id'] ?? null,
                        'party_label' => $party['label'] ?? null,
                        'date' => $date,
                        'position_in_speech' => $position,
                        'time_start' => $timeStart,
                        'time_end' => $timeEnd,
                        'sentence_id' => $sentenceId,
                        'position_in_sentence' => $positionInSentence
                    ];
                    
                    $bulkData[] = [
                        'index' => [
                            '_index' => 'optv_word_events_' . strtolower($parliamentCode),
                            '_id' => $speechId . '_' . $position
                        ]
                    ];
                    $bulkData[] = $wordEvent;
                    
                    $position++;
                    $positionInSentence++;
                    
                    // Bulk index in batches to avoid memory issues
                    if (count($bulkData) >= 2000) { // 1000 documents
                        try {
                            $ESClient->bulk(['body' => $bulkData]);
                            $bulkData = [];
                        } catch (Exception $e) {
                            error_log("Error bulk indexing word events: " . $e->getMessage());
                            return ['success' => false, 'error' => $e->getMessage()];
                        }
                    }
                }
                
                $sentenceIndex++;
            }
        }
    }
    
    // Index remaining items
    if (!empty($bulkData)) {
        try {
            $ESClient->bulk(['body' => $bulkData]);
        } catch (Exception $e) {
            error_log("Error bulk indexing remaining word events: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    return [
        'success' => true, 
        'words_indexed' => $position
    ];
}

/**
 * Test word events indexing with a single speech
 */
function testWordEventsIndexing($speechData, $parliamentCode = 'de') {
    $result = indexSpeechWordEvents($speechData, $parliamentCode);
    
    if ($result['success']) {
        error_log("Word events indexing test successful: " . json_encode($result));
    } else {
        error_log("Word events indexing test failed: " . $result['error']);
    }
    
    return $result;
}