<?php
/**
 * Statistics Indexing Processor
 * Unified processor for both full rebuild and incremental updates of statistics indices
 */

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../modules/utilities/functions.php');
require_once(__DIR__ . '/../modules/utilities/functions.api.php');
require_once(__DIR__ . '/../modules/indexing/functions.main.php');

// Command line arguments
$argv = $_SERVER['argv'] ?? [];
$parliamentCode = 'DE';
$batchSize = 200; // Optimal batch size for performance
$startFrom = 0;
$totalLimit = null;
$isIncremental = false;
$mediaIds = [];

// Parse command line arguments
foreach ($argv as $arg) {
    if (strpos($arg, '--parliament=') === 0) {
        $parliamentCode = substr($arg, 13);
    } elseif (strpos($arg, '--batch-size=') === 0) {
        $batchSize = (int)substr($arg, 13);
    } elseif (strpos($arg, '--start-from=') === 0) {
        $startFrom = (int)substr($arg, 13);
    } elseif (strpos($arg, '--limit=') === 0) {
        $totalLimit = (int)substr($arg, 8);
    } elseif (strpos($arg, '--incremental') === 0) {
        $isIncremental = true;
    } elseif (strpos($arg, '--media-ids=') === 0) {
        $mediaIds = explode(',', substr($arg, 12));
        $isIncremental = true;
    }
}

// Progress and lock file paths
define("STATISTICSINDEXER_PROGRESS_FILE", __DIR__ . "/progress/statisticsIndexer_" . $parliamentCode . ".json");
$progressFile = STATISTICSINDEXER_PROGRESS_FILE;
$lockFile = __DIR__ . '/statisticsIndexer_' . $parliamentCode . '.lock';

// Initialize progress tracking with helper function
$initialData = [
    'processName' => 'statisticsIndexing',
    'statusDetails' => $isIncremental ? 'Starting incremental statistics update...' : 'Starting full statistics rebuild...',
    'totalDbMediaItems' => 0,
    'processedMediaItems' => 0,
    'words_indexed' => 0,
    'statistics_updated' => 0,
    'performance' => [
        'start_time' => time(),
        'avg_docs_per_second' => 0
    ]
];
initBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, $initialData);

// Handle reset command if requested
if (is_cli() && isset($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--reset') === 0) {
            echo "Resetting statistics indexer progress for $parliamentCode...\n";
            $result = resetProgressStatus($parliamentCode);
            echo "Reset completed. Status: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
            exit(0);
        }
    }
}

// Check for existing statistics indexer lock
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    
    // Use longer timeout for full rebuilds (4 hours), shorter for incremental (1 hour)
    $timeout = $isIncremental ? 3600 : 14400; // 1 hour for incremental, 4 hours for full rebuild
    
    if ($lockAge < $timeout) {
        logger('error', "Statistics indexer already running for $parliamentCode");
        
        // Set error status in progress file before exiting
        updateProgress([
            'status' => 'error',
            'statusDetails' => 'Statistics indexer already running',
            'errors' => [['message' => 'Process already running', 'time' => time()]]
        ]);
        exit(1);
    } else {
        // Remove stale lock due to timeout
        unlink($lockFile);
        logger('info', "Removed stale statistics indexer lock file for $parliamentCode (timeout exceeded)");
    }
}

// Check if cronUpdater is running for this parliament to prevent conflicts
$cronUpdaterLockFile = __DIR__ . "/cronUpdater_" . $parliamentCode . ".lock";
if (file_exists($cronUpdaterLockFile)) {
    $lockAge = time() - filemtime($cronUpdaterLockFile);
    
    if ($lockAge < 5400) { // 90 minutes timeout (same as cronUpdater)
        logger('error', "CronUpdater is running for $parliamentCode. Skipping statistics indexing to prevent conflicts.");
        
        // Set error status in progress file before exiting
        updateProgress([
            'status' => 'error',
            'statusDetails' => 'CronUpdater is running. Skipping to prevent conflicts.',
            'errors' => [['message' => 'CronUpdater conflict', 'time' => time()]]
        ]);
        exit(1);
    } else {
        // Remove stale cronUpdater lock if it's too old
        unlink($cronUpdaterLockFile);
        logger('info', "Removed stale cronUpdater lock file for $parliamentCode.");
    }
}

// Create lock file
touch($lockFile);

function updateProgress($data) {
    updateBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, $data);
}

function logger($type = "info", $msg) {
    global $parliamentCode;
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . "/statisticsIndexer_{$parliamentCode}.log";
    $logEntry = "$timestamp - $type: $msg" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Reset statistics indexer progress status to allow new runs
 * This can be called from API or command line to recover from stuck states
 */
function resetProgressStatus($parliamentCode) {
    $progressFile = __DIR__ . '/progress/statisticsIndexer_' . $parliamentCode . '.json';
    $lockFile = __DIR__ . '/statisticsIndexer_' . $parliamentCode . '.lock';
    
    // Remove lock file if it exists
    if (file_exists($lockFile)) {
        unlink($lockFile);
        logger('info', "Manually removed lock file for recovery");
    }
    
    // Reset progress status using helper function
    $resetData = [
        'processName' => 'statisticsIndexing',
        'statusDetails' => 'Ready for indexing (manually reset)',
        'totalDbMediaItems' => 0,
        'processedMediaItems' => 0,
        'words_indexed' => 0,
        'statistics_updated' => 0,
        'performance' => [
            'start_time' => null,
            'avg_docs_per_second' => 0
        ],
        'reset_time' => time(),
        'reset_reason' => 'Manual recovery reset'
    ];
    
    initBaseProgressFile($progressFile, $resetData);
    finalizeBaseProgressFile($progressFile, 'idle', 'Ready for indexing (manually reset)');
    logger('info', "Progress status reset for recovery");
    
    // Return data for compatibility
    $currentProgress = @file_get_contents($progressFile);
    return $currentProgress ? json_decode($currentProgress, true) : $resetData;
}

// Register shutdown handler to ensure lock and progress are handled
register_shutdown_function(function() use ($lockFile) {
    // Always try to remove the lock file
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    
    // Handle progress finalization on crash using helper functions
    if (function_exists('finalizeBaseProgressFile')) {
        if (file_exists(STATISTICSINDEXER_PROGRESS_FILE)) {
            $currentProgressJson = @file_get_contents(STATISTICSINDEXER_PROGRESS_FILE);
            $currentProgress = $currentProgressJson ? json_decode($currentProgressJson, true) : null;
            
            if (is_array($currentProgress) && $currentProgress["status"] === "running") {
                $error = error_get_last();
                $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
                
                if ($error && in_array($error['type'], $fatalErrorTypes)) {
                    $logMessageDetail = "Statistics Indexer exited unexpectedly: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
                    logErrorToBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, $logMessageDetail, "FATAL");
                    finalizeBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, "error_shutdown", "Process terminated unexpectedly due to fatal error.");
                } else {
                    $logMessageDetail = "Statistics Indexer exited unexpectedly while 'running'.";
                    logErrorToBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, $logMessageDetail, "CRASH");
                    finalizeBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, "error_shutdown", "Process terminated unexpectedly.");
                }
            }
        }
    }
});

try {
    logger('info', "Starting statistics indexer - Mode: " . ($isIncremental ? 'incremental' : 'full_rebuild') . ", Parliament: $parliamentCode");
    
    updateProgress([
        'status' => 'running',
        'statusDetails' => 'Initializing statistics indexing...'
    ]);

    // Initialize OpenSearch client using the standard function
    $client = getApiOpenSearchClient();
    if (is_array($client) && isset($client["errors"])) {
        throw new Exception("Failed to initialize OpenSearch client: " . ($client["errors"][0]["detail"] ?? "Unknown error"));
    }

    // Determine processing mode
    if ($isIncremental && !empty($mediaIds)) {
        // Process specific media IDs
        logger('info', "Processing specific media IDs: " . implode(', ', array_slice($mediaIds, 0, 5)) . (count($mediaIds) > 5 ? '...' : ''));
        processSpecificMediaIds($client, $parliamentCode, $mediaIds);
        
    } elseif ($isIncremental) {
        // Incremental update - process recently updated items
        logger('info', "Processing incremental updates");
        processIncrementalUpdates($client, $parliamentCode, $batchSize);
        
    } else {
        // Full rebuild
        logger('info', "Starting full statistics rebuild");
        performFullRebuild($client, $parliamentCode, $batchSize, $startFrom, $totalLimit);
    }

    finalizeBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, 'completed_successfully', 'Statistics indexing completed successfully');
    logger('info', "Statistics indexing completed successfully");

} catch (Exception $e) {
    logger('error', "Critical error: " . $e->getMessage());
    logErrorToBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, $e->getMessage(), "EXCEPTION");
    finalizeBaseProgressFile(STATISTICSINDEXER_PROGRESS_FILE, 'error_critical', 'Critical error: ' . $e->getMessage());
    exit(1);
}

/**
 * Perform full statistics index rebuild
 */
function performFullRebuild($client, $parliamentCode, $batchSize, $startFrom, $totalLimit) {
    global $progressData;
    
    updateProgress(['statusDetails' => 'Setting up statistics indices for full rebuild...']);
    
    // Setup indices with optimized settings for bulk operations
    setupStatisticsIndices($client, $parliamentCode, true);
    
    // Get total count from main index
    $mainIndexName = "openparliamenttv_" . strtolower($parliamentCode);
    $countResponse = $client->count([
        'index' => $mainIndexName,
        'body' => [
            'query' => [
                'bool' => [
                    'must' => [
                        ['exists' => ['field' => 'attributes.textContents']],
                        ['range' => ['attributes.textContentsCount' => ['gt' => 0]]]
                    ]
                ]
            ]
        ]
    ]);
    
    $totalItems = $countResponse['count'] ?? 0;
    updateProgress(['totalDbMediaItems' => $totalItems]);
    
    if ($totalItems == 0) {
        updateProgress(['statusDetails' => 'No documents with text content found in main index']);
        return;
    }
    
    logger('info', "Processing $totalItems documents in batches of $batchSize");
    
    // Process documents in batches using scroll API
    $processed = 0;
    $wordsIndexed = 0;
    $statisticsUpdated = 0;
    $startTime = time();
    
    $scrollParams = [
        'index' => $mainIndexName,
        'scroll' => '5m',
        'size' => $batchSize,
        'body' => [
            'query' => [
                'bool' => [
                    'must' => [
                        ['exists' => ['field' => 'attributes.textContents']],
                        ['range' => ['attributes.textContentsCount' => ['gt' => 0]]]
                    ]
                ]
            ]
        ]
    ];
    
    if ($startFrom > 0) {
        $scrollParams['body']['from'] = $startFrom;
    }
    
    $response = $client->search($scrollParams);
    $scrollId = $response['_scroll_id'];
    
    while (true) {
        $hits = $response['hits']['hits'] ?? [];
        if (empty($hits)) break;
        
        $batchResults = processBatchWithProperStatistics($client, $parliamentCode, $hits);
        $processed += count($hits);
        $wordsIndexed += $batchResults['words'];
        $statisticsUpdated += $batchResults['stats'];
        
        // Calculate performance metrics
        $elapsed = time() - $startTime;
        $avgDocsPerSecond = $elapsed > 0 ? round($processed / $elapsed, 2) : 0;
        
        updateProgress([
            'processedMediaItems' => $processed,
            'words_indexed' => $wordsIndexed,
            'statistics_updated' => $statisticsUpdated,
            'statusDetails' => "Processing batch: $processed/$totalItems documents",
            'performance' => ['avg_docs_per_second' => $avgDocsPerSecond]
        ]);
        
        logger('info', "Processed $processed/$totalItems documents ($avgDocsPerSecond docs/sec)");
        
        if ($totalLimit && $processed >= $totalLimit) break;
        
        // Get next batch
        $response = $client->scroll([
            'scroll_id' => $scrollId,
            'scroll' => '5m'
        ]);
        
        if (empty($response['hits']['hits'])) break;
    }
    
    // Clean up scroll
    try {
        $client->clearScroll(['scroll_id' => $scrollId]);
    } catch (Exception $e) {
        logger('warn', "Could not clear scroll: " . $e->getMessage());
    }
    
    // Restore optimal index settings
    restoreOptimalIndexSettings($client, $parliamentCode);
    
    logger('info', "Full rebuild completed: $processed documents, $wordsIndexed words, $statisticsUpdated statistics");
}

/**
 * Process specific media IDs for incremental updates
 */
function processSpecificMediaIds($client, $parliamentCode, $mediaIds) {
    global $progressData;
    
    updateProgress([
        'totalDbMediaItems' => count($mediaIds),
        'statusDetails' => 'Processing specific media items...'
    ]);
    
    // Use correct index naming pattern consistent with rest of codebase
    global $config;
    $mainIndexName = "openparliamenttv_" . ($config['parliament'][$parliamentCode]['OpenSearch']['index'] ?? strtolower($parliamentCode));
    
    // Get documents by IDs with comprehensive error handling
    try {
        $response = $client->mget([
            'index' => $mainIndexName,
            'body' => [
                'ids' => $mediaIds
            ]
        ]);
        
        // Validate OpenSearch response structure
        if (!isset($response['docs']) || !is_array($response['docs'])) {
            throw new Exception("Invalid OpenSearch mget response structure");
        }
        
        $docs = [];
        $errorCount = 0;
        
        foreach ($response['docs'] as $doc) {
            // Handle different response scenarios:
            // 1. Document found: has 'found' => true and '_source'
            // 2. Document not found: has 'found' => false
            // 3. Index error: has 'error' but no 'found' key
            
            if (isset($doc['error'])) {
                $errorCount++;
                logger('warn', "Document error: " . json_encode($doc['error']));
                continue;
            }
            
            if (isset($doc['found']) && $doc['found'] === true && isset($doc['_source']['textContents'])) {
                $docs[] = [
                    '_source' => $doc['_source']
                ];
            } elseif (isset($doc['found']) && $doc['found'] === false) {
                // Document not found - this is normal, just log for debugging
                logger('info', "Document not found: " . ($doc['_id'] ?? 'unknown'));
            } else {
                logger('warn', "Unexpected document structure: " . json_encode($doc));
            }
        }
        
        if ($errorCount > 0) {
            logger('warn', "Encountered $errorCount errors while fetching documents from index: $mainIndexName");
        }
        
    } catch (Exception $e) {
        logger('error', "Failed to fetch documents from OpenSearch: " . $e->getMessage());
        throw new Exception("OpenSearch mget operation failed: " . $e->getMessage());
    }
    
    if (!empty($docs)) {
        $batchResults = processBatch($client, $parliamentCode, $docs);
        
        updateProgress([
            'processedMediaItems' => count($mediaIds), // Count all requested items, not just those with text
            'words_indexed' => $batchResults['words'],
            'statistics_updated' => $batchResults['stats'],
            'statusDetails' => 'Completed processing specific media items'
        ]);
    } else {
        // Even if no items had text content, we still processed all requested items
        updateProgress([
            'processedMediaItems' => count($mediaIds),
            'words_indexed' => 0,
            'statistics_updated' => 0,
            'statusDetails' => 'Completed processing specific media items (no text content found)'
        ]);
    }
}

/**
 * Process incremental updates for recently changed items
 */
function processIncrementalUpdates($client, $parliamentCode, $batchSize) {
    global $progressData, $mediaIds;
    
    // If specific media IDs are provided, process them incrementally
    if (!empty($mediaIds)) {
        logger('info', "Processing incremental updates for " . count($mediaIds) . " media items");
        
        updateProgress([
            'statusDetails' => 'Processing incremental statistics updates...'
        ]);
        
        processSpecificMediaIds($client, $parliamentCode, $mediaIds);
        return;
    }
    
    // If no specific media IDs provided, fall back to full rebuild
    logger('info', "No specific media IDs provided for incremental update, performing full rebuild");
    
    updateProgress([
        'statusDetails' => 'No media IDs specified, running full rebuild...'
    ]);
    
    performFullRebuild($client, $parliamentCode, $batchSize, 0, null);
}

/**
 * Process a batch of documents using proper statistics functions
 */
function processBatchWithProperStatistics($client, $parliamentCode, $hits) {
    $wordsIndexed = 0;
    $statisticsUpdated = 0;
    
    foreach ($hits as $hit) {
        $source = $hit['_source'];
        $speechData = $source;
        $speechData['id'] = $hit['_id'];
        
        try {
            // Use the proper statistics processing function
            $result = indexSpeechStatistics($speechData, strtolower($parliamentCode));
            
            if ($result['success']) {
                // Count words from the result
                if (isset($result['results']['statistics']['aggregations_updated'])) {
                    $statisticsUpdated += $result['results']['statistics']['aggregations_updated'];
                }
                if (isset($result['results']['statistics']['unique_words'])) {
                    $wordsIndexed += $result['results']['statistics']['unique_words'];
                }
            } else {
                logger('error', "Statistics processing failed for speech {$speechData['id']}: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            logger('error', "Exception processing speech {$speechData['id']}: " . $e->getMessage());
        }
    }
    
    return ['words' => $wordsIndexed, 'stats' => $statisticsUpdated];
}



/**
 * Setup statistics indices with optimal bulk indexing settings
 */
function setupStatisticsIndices($client, $parliamentCode, $isFullRebuild = false) {
    $indices = [
        "optv_statistics_" . strtolower($parliamentCode)
    ];
    
    foreach ($indices as $indexName) {
        if ($isFullRebuild) {
            // Delete existing index for clean rebuild
            try {
                $client->indices()->delete(['index' => $indexName]);
                logger('info', "Deleted existing index: $indexName");
            } catch (Exception $e) {
                // Index might not exist, which is fine
            }
        }
        
        // Create index with optimal settings for bulk operations
        if (!$client->indices()->exists(['index' => $indexName])) {
            $client->indices()->create([
                'index' => $indexName,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0, // No replicas during bulk indexing
                        'refresh_interval' => -1, // Disable refresh during indexing
                        'index.translog.sync_interval' => '30s',
                        'index.translog.durability' => 'async'
                    ],
                    'mappings' => getStatisticsIndexMapping($indexName)
                ]
            ]);
            logger('info', "Created index with bulk settings: $indexName");
        }
    }
}

/**
 * Restore optimal index settings after bulk operations
 */
function restoreOptimalIndexSettings($client, $parliamentCode) {
    $indices = [
        "optv_statistics_" . strtolower($parliamentCode)
    ];
    
    foreach ($indices as $indexName) {
        try {
            $client->indices()->putSettings([
                'index' => $indexName,
                'body' => [
                    'settings' => [
                        'number_of_replicas' => 1, // Restore replicas
                        'refresh_interval' => '1s', // Restore normal refresh
                        'index.translog.sync_interval' => '5s',
                        'index.translog.durability' => 'request'
                    ]
                ]
            ]);
            
            // Force refresh
            $client->indices()->refresh(['index' => $indexName]);
            
            logger('info', "Restored optimal settings for: $indexName");
        } catch (Exception $e) {
            logger('warn', "Could not restore settings for $indexName: " . $e->getMessage());
        }
    }
}

/**
 * Get index mapping based on index name
 */
function getStatisticsIndexMapping($indexName) {
    // Only statistics index mapping needed - word_events index eliminated
    return [
        'properties' => [
            'aggregation_type' => ['type' => 'keyword'],
            'speaker_id' => ['type' => 'keyword'],
            'party_id' => ['type' => 'keyword'],
            'word' => ['type' => 'keyword'],
            'count' => ['type' => 'integer'],
            'speech_count' => ['type' => 'integer'],
            'date' => ['type' => 'long'],
            'date_string' => ['type' => 'date']
        ]
    ];
}