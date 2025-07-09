<?php
/**
 * Enhanced indexing integration for cronUpdater.php
 * This file provides helper functions to integrate enhanced indexing 
 * with the existing cronUpdater workflow
 */

require_once(__DIR__ . '/../modules/indexing/functions.main.php');

/**
 * Setup enhanced indexing during cronUpdater startup
 */
function cronUpdaterSetupEnhanced($parliamentCode) {
    error_log("CronUpdater: Setting up enhanced indexing for parliament: $parliamentCode");
    
    $result = setupEnhancedIndexing($parliamentCode);
    
    if ($result['success']) {
        error_log("CronUpdater: Enhanced indexing setup successful");
    } else {
        error_log("CronUpdater: Enhanced indexing setup failed: " . ($result['message'] ?? 'Unknown error'));
    }
    
    return $result;
}

/**
 * Process a single speech with enhanced indexing during cronUpdater execution
 */
function cronUpdaterProcessSpeechEnhanced($speechData, $parliamentCode) {
    // Only process if enhanced indexing is enabled
    if (!isEnhancedIndexingEnabled($parliamentCode)) {
        return ['success' => true, 'message' => 'Enhanced indexing not enabled, skipping'];
    }
    
    try {
        $result = integrateSpeechWithEnhancedIndexing($speechData, $parliamentCode);
        
        if ($result['success']) {
            error_log("CronUpdater: Enhanced indexing successful for speech: " . $speechData['id']);
        } else {
            error_log("CronUpdater: Enhanced indexing failed for speech: " . $speechData['id'] . " - " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("CronUpdater: Enhanced indexing exception for speech: " . $speechData['id'] . " - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check enhanced indexing status during cronUpdater execution
 */
function cronUpdaterCheckEnhancedStatus($parliamentCode) {
    $enabled = isEnhancedIndexingEnabled($parliamentCode);
    
    if ($enabled) {
        error_log("CronUpdater: Enhanced indexing is ENABLED for parliament: $parliamentCode");
    } else {
        error_log("CronUpdater: Enhanced indexing is DISABLED for parliament: $parliamentCode");
    }
    
    return $enabled;
}

/**
 * Log enhanced indexing statistics during cronUpdater execution
 */
function cronUpdaterLogEnhancedStats($parliamentCode) {
    try {
        $ESClient = getApiOpenSearchClient();
        if (is_array($ESClient) && isset($ESClient["errors"])) {
            error_log("CronUpdater: Cannot get enhanced indexing stats - OpenSearch client error");
            return;
        }
        
        $wordEventsIndex = 'optv_word_events_' . strtolower($parliamentCode);
        $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
        
        // Get document counts
        if ($ESClient->indices()->exists(['index' => $wordEventsIndex])) {
            $wordEventsCount = $ESClient->count(['index' => $wordEventsIndex]);
            error_log("CronUpdater: Word events index has " . $wordEventsCount['count'] . " documents");
        }
        
        if ($ESClient->indices()->exists(['index' => $statisticsIndex])) {
            $statisticsCount = $ESClient->count(['index' => $statisticsIndex]);
            error_log("CronUpdater: Statistics index has " . $statisticsCount['count'] . " documents");
        }
        
    } catch (Exception $e) {
        error_log("CronUpdater: Error getting enhanced indexing stats: " . $e->getMessage());
    }
}

/**
 * Bulk reprocess existing speeches with enhanced indexing
 * This can be used when running cronUpdater with enhanced indexing for the first time
 */
function cronUpdaterBulkReprocessEnhanced($parliamentCode, $batchSize = 50) {
    error_log("CronUpdater: Starting bulk reprocessing with enhanced indexing");
    
    $result = bulkProcessExistingSpeeches($parliamentCode, $batchSize);
    
    if ($result['success']) {
        error_log("CronUpdater: Bulk reprocessing completed - Processed: " . $result['processed'] . ", Errors: " . $result['errors']);
    } else {
        error_log("CronUpdater: Bulk reprocessing failed - " . ($result['error'] ?? 'Unknown error'));
    }
    
    return $result;
}
?>