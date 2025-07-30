<?php
/**
 * Statistics Indexing Processor
 * Unified processor for both full rebuild and incremental updates of statistics indices
 */

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config.php');
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
$progressFile = __DIR__ . '/progress/statisticsIndexer_' . $parliamentCode . '.json';
$lockFile = __DIR__ . '/progress/statisticsIndexer_' . $parliamentCode . '.lock';

// Check for existing statistics indexer lock
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true);
    $lockAge = time() - ($lockData['timestamp'] ?? 0);
    
    // Use longer timeout for full rebuilds (4 hours), shorter for incremental (1 hour)
    $timeout = $isIncremental ? 3600 : 14400; // 1 hour for incremental, 4 hours for full rebuild
    
    if ($lockAge < $timeout) {
        error_log("Statistics indexer already running for $parliamentCode (PID: " . ($lockData['pid'] ?? 'unknown') . ")");
        exit(1);
    } else {
        // Remove stale lock
        unlink($lockFile);
    }
}

// Check if cronUpdater is running to prevent conflicts
$cronUpdaterLockFile = __DIR__ . "/cronUpdater.lock";
if (file_exists($cronUpdaterLockFile)) {
    $lockAge = time() - filemtime($cronUpdaterLockFile);
    
    if ($lockAge < 5400) { // 90 minutes timeout (same as cronUpdater)
        error_log("CronUpdater is running for $parliamentCode. Skipping statistics indexing to prevent conflicts.");
        exit(1);
    } else {
        // Remove stale cronUpdater lock if it's too old
        unlink($cronUpdaterLockFile);
        error_log("Removed stale cronUpdater lock file.");
    }
}

// Create lock file
$lockData = [
    'pid' => getmypid(),
    'timestamp' => time(),
    'parliament' => $parliamentCode,
    'mode' => $isIncremental ? 'incremental' : 'full_rebuild'
];
file_put_contents($lockFile, json_encode($lockData));

// Initialize progress tracking
$progressData = [
    'processName' => 'statisticsIndexing',
    'status' => 'starting',
    'statusDetails' => $isIncremental ? 'Starting incremental statistics update...' : 'Starting full statistics rebuild...',
    'totalDbMediaItems' => 0,
    'processedMediaItems' => 0,
    'words_indexed' => 0,
    'statistics_updated' => 0,
    'performance' => [
        'start_time' => time(),
        'avg_docs_per_second' => 0
    ],
    'errors' => []
];

function updateProgress($data) {
    global $progressFile, $progressData;
    $progressData = array_merge($progressData, $data);
    file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
}

function logMessage($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$level] Statistics Indexer: $message");
}

// Register shutdown handler to clean up lock file
register_shutdown_function(function() use ($lockFile, $progressFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    
    // Final progress update
    global $progressData;
    if ($progressData['status'] === 'running' || $progressData['status'] === 'starting') {
        $progressData['status'] = 'completed_successfully';
        $progressData['statusDetails'] = 'Statistics indexing completed successfully';
        file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
    }
});

try {
    logMessage('INFO', "Starting statistics indexer - Mode: " . ($isIncremental ? 'incremental' : 'full_rebuild') . ", Parliament: $parliamentCode");
    
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
        logMessage('INFO', "Processing specific media IDs: " . implode(', ', array_slice($mediaIds, 0, 5)) . (count($mediaIds) > 5 ? '...' : ''));
        processSpecificMediaIds($client, $parliamentCode, $mediaIds);
        
    } elseif ($isIncremental) {
        // Incremental update - process recently updated items
        logMessage('INFO', "Processing incremental updates");
        processIncrementalUpdates($client, $parliamentCode, $batchSize);
        
    } else {
        // Full rebuild
        logMessage('INFO', "Starting full statistics rebuild");
        performFullRebuild($client, $parliamentCode, $batchSize, $startFrom, $totalLimit);
    }

    updateProgress([
        'status' => 'completed_successfully',
        'statusDetails' => 'Statistics indexing completed successfully'
    ]);
    
    logMessage('INFO', "Statistics indexing completed successfully");

} catch (Exception $e) {
    logMessage('ERROR', "Critical error: " . $e->getMessage());
    updateProgress([
        'status' => 'error',
        'statusDetails' => 'Critical error: ' . $e->getMessage(),
        'errors' => [['message' => $e->getMessage(), 'time' => time()]]
    ]);
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
    
    logMessage('INFO', "Processing $totalItems documents in batches of $batchSize");
    
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
        
        logMessage('INFO', "Processed $processed/$totalItems documents ($avgDocsPerSecond docs/sec)");
        
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
        logMessage('WARN', "Could not clear scroll: " . $e->getMessage());
    }
    
    // Restore optimal index settings
    restoreOptimalIndexSettings($client, $parliamentCode);
    
    logMessage('INFO', "Full rebuild completed: $processed documents, $wordsIndexed words, $statisticsUpdated statistics");
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
    
    $mainIndexName = "optv_main_" . strtolower($parliamentCode);
    
    // Get documents by IDs
    $response = $client->mget([
        'index' => $mainIndexName,
        'body' => [
            'ids' => $mediaIds
        ]
    ]);
    
    $docs = [];
    foreach ($response['docs'] as $doc) {
        if ($doc['found'] && isset($doc['_source']['textContents'])) {
            $docs[] = [
                '_source' => $doc['_source']
            ];
        }
    }
    
    if (!empty($docs)) {
        $batchResults = processBatch($client, $parliamentCode, $docs);
        
        updateProgress([
            'processedMediaItems' => count($docs),
            'words_indexed' => $batchResults['words'],
            'statistics_updated' => $batchResults['stats'],
            'statusDetails' => 'Completed processing specific media items'
        ]);
    }
}

/**
 * Process incremental updates for recently changed items
 */
function processIncrementalUpdates($client, $parliamentCode, $batchSize) {
    // For now, redirect to full rebuild
    // In the future, this could query for recently updated documents
    logMessage('INFO', "Incremental updates not yet implemented, performing full rebuild");
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
                logMessage('ERROR', "Statistics processing failed for speech {$speechData['id']}: " . ($result['error'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            logMessage('ERROR', "Exception processing speech {$speechData['id']}: " . $e->getMessage());
        }
    }
    
    return ['words' => $wordsIndexed, 'stats' => $statisticsUpdated];
}



/**
 * Setup statistics indices with optimal bulk indexing settings
 */
function setupStatisticsIndices($client, $parliamentCode, $isFullRebuild = false) {
    $indices = [
        "optv_statistics_" . strtolower($parliamentCode),
        "optv_word_events_" . strtolower($parliamentCode)
    ];
    
    foreach ($indices as $indexName) {
        if ($isFullRebuild) {
            // Delete existing index for clean rebuild
            try {
                $client->indices()->delete(['index' => $indexName]);
                logMessage('INFO', "Deleted existing index: $indexName");
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
            logMessage('INFO', "Created index with bulk settings: $indexName");
        }
    }
}

/**
 * Restore optimal index settings after bulk operations
 */
function restoreOptimalIndexSettings($client, $parliamentCode) {
    $indices = [
        "optv_statistics_" . strtolower($parliamentCode),
        "optv_word_events_" . strtolower($parliamentCode)
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
            
            logMessage('INFO', "Restored optimal settings for: $indexName");
        } catch (Exception $e) {
            logMessage('WARN', "Could not restore settings for $indexName: " . $e->getMessage());
        }
    }
}

/**
 * Get index mapping based on index name
 */
function getStatisticsIndexMapping($indexName) {
    if (strpos($indexName, 'word_events') !== false) {
        return [
            'properties' => [
                'word' => ['type' => 'keyword'],
                'speech_id' => ['type' => 'keyword'],
                'speaker_id' => ['type' => 'keyword'],
                'date' => ['type' => 'date'],
                'position_in_speech' => ['type' => 'integer']
            ]
        ];
    } else {
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
}