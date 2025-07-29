<?php
/**
 * Statistics indexing integration for cronUpdater.php
 * This file provides helper functions to integrate statistics indexing 
 * with the existing cronUpdater workflow (word events eliminated)
 */

require_once(__DIR__ . '/../modules/indexing/functions.main.php');

/**
 * Setup statistics indexing during cronUpdater startup
 */
function cronUpdaterSetupStatistics($parliamentCode) {
    error_log("CronUpdater: Setting up statistics indexing for parliament: $parliamentCode");
    
    $result = setupStatisticsIndexing($parliamentCode);
    
    if ($result['success']) {
        error_log("CronUpdater: Statistics indexing setup successful");
    } else {
        error_log("CronUpdater: Statistics indexing setup failed: " . ($result['message'] ?? 'Unknown error'));
    }
    
    return $result;
}

/**
 * Process a single speech with statistics indexing during cronUpdater execution
 */
function cronUpdaterProcessSpeechStatistics($speechData, $parliamentCode) {
    // Only process if statistics indexing is enabled
    if (!isStatisticsIndexingEnabled($parliamentCode)) {
        return ['success' => true, 'message' => 'Statistics indexing not enabled, skipping'];
    }
    
    try {
        // Use optimized statistics-only processing for incremental updates
        $result = processStatisticsForSpeechOptimized($speechData, $parliamentCode);
        
        if ($result['success']) {
            error_log("CronUpdater: Statistics indexing successful for speech: " . $speechData['id']);
        } else {
            error_log("CronUpdater: Statistics indexing failed for speech: " . $speechData['id'] . " - " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("CronUpdater: Statistics indexing exception for speech: " . $speechData['id'] . " - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check statistics indexing status during cronUpdater execution
 */
function cronUpdaterCheckStatisticsStatus($parliamentCode) {
    $enabled = isStatisticsIndexingEnabled($parliamentCode);
    
    if ($enabled) {
        error_log("CronUpdater: Statistics indexing is ENABLED for parliament: $parliamentCode");
    } else {
        error_log("CronUpdater: Statistics indexing is DISABLED for parliament: $parliamentCode");
    }
    
    return $enabled;
}

/**
 * Log statistics indexing stats during cronUpdater execution
 */
function cronUpdaterLogStatisticsStats($parliamentCode) {
    try {
        $ESClient = getApiOpenSearchClient();
        if (is_array($ESClient) && isset($ESClient["errors"])) {
            error_log("CronUpdater: Cannot get statistics indexing stats - OpenSearch client error");
            return;
        }
        
        $statisticsIndex = 'optv_statistics_' . strtolower($parliamentCode);
        
        // Get statistics document count
        if ($ESClient->indices()->exists(['index' => $statisticsIndex])) {
            $statisticsCount = $ESClient->count(['index' => $statisticsIndex]);
            error_log("CronUpdater: Statistics index has " . $statisticsCount['count'] . " documents");
        }
        
    } catch (Exception $e) {
        error_log("CronUpdater: Error getting statistics indexing stats: " . $e->getMessage());
    }
}

/**
 * Bulk reprocess existing speeches with statistics indexing
 * This can be used when running cronUpdater with statistics indexing for the first time
 */
function cronUpdaterBulkReprocessStatistics($parliamentCode, $batchSize = 50) {
    error_log("CronUpdater: Starting bulk reprocessing with statistics indexing");
    
    $result = bulkProcessExistingSpeeches($parliamentCode, $batchSize);
    
    if ($result['success']) {
        error_log("CronUpdater: Bulk reprocessing completed - Processed: " . $result['processed'] . ", Errors: " . $result['errors']);
    } else {
        error_log("CronUpdater: Bulk reprocessing failed - " . ($result['error'] ?? 'Unknown error'));
    }
    
    return $result;
}

/**
 * Periodic cleanup during incremental updates
 * Call this after processing a batch of speeches (e.g., every 100 speeches)
 */
function cronUpdaterPeriodicCleanup($parliamentCode, $speechesProcessed = 0) {
    // Only run cleanup every 100 speeches processed OR if forced
    if ($speechesProcessed % 100 !== 0 && $speechesProcessed > 0) {
        return ['success' => true, 'message' => 'Cleanup not due yet', 'next_cleanup_at' => $speechesProcessed + (100 - ($speechesProcessed % 100))];
    }
    
    error_log("CronUpdater: Running periodic statistics cleanup after $speechesProcessed speeches");
    
    try {
        require_once(__DIR__ . '/../modules/indexing/functions.statistics.php');
        
        $result = smartStatisticsCleanup($parliamentCode, false);
        
        if ($result['success'] && isset($result['cleanup_performed']) && $result['cleanup_performed']) {
            error_log("CronUpdater: Cleanup completed - cleaned " . $result['deleted_cleaned'] . " deleted docs, reclaimed " . $result['space_reclaimed_mb'] . "MB");
        } else if ($result['success']) {
            error_log("CronUpdater: Cleanup check completed - " . $result['message'] . " (deleted: " . $result['deleted_percentage'] . "%)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("CronUpdater: Periodic cleanup error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Final cleanup at end of cronUpdater run
 */
function cronUpdaterFinalCleanup($parliamentCode, $totalSpeechesProcessed) {
    if ($totalSpeechesProcessed == 0) {
        return ['success' => true, 'message' => 'No speeches processed, no cleanup needed'];
    }
    
    error_log("CronUpdater: Running final statistics cleanup after processing $totalSpeechesProcessed speeches total");
    
    try {
        require_once(__DIR__ . '/../modules/indexing/functions.statistics.php');
        
        // Force cleanup at end of run if we processed any speeches
        $result = smartStatisticsCleanup($parliamentCode, $totalSpeechesProcessed >= 10);
        
        if ($result['success'] && isset($result['cleanup_performed']) && $result['cleanup_performed']) {
            error_log("CronUpdater: Final cleanup completed - cleaned " . $result['deleted_cleaned'] . " deleted docs, reclaimed " . $result['space_reclaimed_mb'] . "MB");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("CronUpdater: Final cleanup error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>