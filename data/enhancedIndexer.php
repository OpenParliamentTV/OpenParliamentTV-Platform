<?php
/**
 * Enhanced Indexing Batch Processor
 * Processes all documents from main index with progress tracking
 */

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../modules/utilities/functions.api.php');
require_once(__DIR__ . '/../modules/indexing/functions.main.php');

// Command line arguments
$argv = $_SERVER['argv'] ?? [];
$parliamentCode = 'DE'; // Default
$batchSize = 200; // Increased batch size for better performance
$startFrom = 0;
$totalLimit = null; // null = process all

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
    }
}

// Progress file path
$progressFile = __DIR__ . '/progress/enhancedIndexer_' . $parliamentCode . '.json';
$lockFile = __DIR__ . '/progress/enhancedIndexer_' . $parliamentCode . '.lock';

// Check for existing lock
if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true);
    $lockAge = time() - ($lockData['timestamp'] ?? 0);
    
    if ($lockAge < 3600) { // 1 hour timeout
        error_log("Enhanced indexer already running for $parliamentCode (PID: " . ($lockData['pid'] ?? 'unknown') . ")");
        exit(1);
    } else {
        // Remove stale lock
        unlink($lockFile);
    }
}

// Create lock file
$lockData = [
    'pid' => getmypid(),
    'timestamp' => time(),
    'parliament' => $parliamentCode
];
file_put_contents($lockFile, json_encode($lockData));

// Initialize progress
$progressData = [
    'status' => 'starting',
    'parliament' => $parliamentCode,
    'total_documents' => 0,
    'processed_documents' => 0,
    'successful_documents' => 0,
    'failed_documents' => 0,
    'current_batch' => 0,
    'total_batches' => 0,
    'words_indexed' => 0,
    'statistics_updated' => 0,
    'start_time' => time(),
    'last_update' => time(),
    'estimated_completion' => null,
    'current_document_id' => null,
    'error_messages' => [],
    'performance' => [
        'avg_docs_per_second' => 0,
        'avg_words_per_doc' => 0,
        'current_batch_time' => 0
    ]
];

function updateProgress($data) {
    global $progressFile, $progressData;
    $progressData = array_merge($progressData, $data);
    $progressData['last_update'] = time();
    
    // Calculate performance metrics
    $elapsed = time() - $progressData['start_time'];
    if ($elapsed > 0 && $progressData['processed_documents'] > 0) {
        $progressData['performance']['avg_docs_per_second'] = round($progressData['processed_documents'] / $elapsed, 2);
        
        if ($progressData['total_documents'] > 0) {
            $remaining = $progressData['total_documents'] - $progressData['processed_documents'];
            $avgRate = $progressData['performance']['avg_docs_per_second'];
            if ($avgRate > 0) {
                $progressData['estimated_completion'] = time() + ($remaining / $avgRate);
            }
        }
    }
    
    if ($progressData['processed_documents'] > 0 && $progressData['words_indexed'] > 0) {
        $progressData['performance']['avg_words_per_doc'] = round($progressData['words_indexed'] / $progressData['processed_documents'], 0);
    }
    
    file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT));
}

function cleanup() {
    global $lockFile;
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

// Register cleanup on exit
register_shutdown_function('cleanup');

try {
    updateProgress(['status' => 'initializing']);
    
    // Connect to OpenSearch
    $ESClient = getApiOpenSearchClient();
    if (is_array($ESClient) && isset($ESClient["errors"])) {
        throw new Exception('Failed to connect to OpenSearch: ' . json_encode($ESClient["errors"]));
    }
    
    // Setup statistics indexing with clean rebuild (deletes existing indices first)
    updateProgress(['status' => 'setting_up_indices']);
    echo "Setting up statistics indexing (clean rebuild)...\n";
    $setupResult = setupStatisticsIndexing(strtolower($parliamentCode), true); // true = clean rebuild
    if (!$setupResult['success']) {
        throw new Exception('Failed to setup statistics indexing: ' . ($setupResult['message'] ?? 'Unknown error'));
    }
    echo "Statistics indexing setup complete (indices cleaned and recreated).\n";
    
    // Optimize index settings for bulk indexing
    $indexName = 'optv_statistics_' . strtolower($parliamentCode);
    echo "Optimizing index settings for bulk indexing...\n";
    $ESClient->indices()->putSettings([
        'index' => $indexName,
        'body' => [
            'settings' => [
                'refresh_interval' => -1, // Disable refresh during indexing
                'number_of_replicas' => 0, // No replicas during indexing  
                'index.translog.sync_interval' => '30s', // Less frequent syncing
                'index.translog.durability' => 'async' // Async durability for speed
            ]
        ]
    ]);
    echo "Index optimized for bulk operations.\n";
    
    // Get total document count
    updateProgress(['status' => 'counting_documents']);
    $mainIndex = 'openparliamenttv_' . strtolower($parliamentCode);
    
    $countQuery = [
        'index' => $mainIndex,
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
    
    $countResult = $ESClient->count($countQuery);
    $totalDocuments = $countResult['count'] ?? 0;
    
    if ($totalLimit) {
        $totalDocuments = min($totalDocuments, $totalLimit);
    }
    
    if ($totalDocuments == 0) {
        updateProgress(['status' => 'completed', 'message' => 'No documents found to process']);
        cleanup();
        exit(0);
    }
    
    $totalBatches = ceil($totalDocuments / $batchSize);
    
    updateProgress([
        'status' => 'processing',
        'total_documents' => $totalDocuments,
        'total_batches' => $totalBatches
    ]);
    
    echo "Statistics Indexing for $parliamentCode\n";
    echo "Total documents: $totalDocuments\n";
    echo "Batch size: $batchSize\n";
    echo "Total batches: $totalBatches\n\n";
    
    // Use scroll API for large datasets to avoid the 10,000 result window limit
    $processedTotal = 0;
    $successfulTotal = 0;
    $failedTotal = 0;
    $wordsTotal = 0;
    $statsTotal = 0;
    
    // Initial scroll search
    $scrollParams = [
        'index' => $mainIndex,
        'size' => $batchSize,
        'scroll' => '5m', // Keep scroll context alive for 5 minutes
        'body' => [
            'query' => [
                'bool' => [
                    'must' => [
                        ['exists' => ['field' => 'attributes.textContents']],
                        ['range' => ['attributes.textContentsCount' => ['gt' => 0]]]
                    ]
                ]
            ],
            'sort' => ['_doc'] // Use _doc sort for better scroll performance
        ]
    ];
    
    $scrollResult = $ESClient->search($scrollParams);
    $scrollId = $scrollResult['_scroll_id'];
    $batch = 0;
    
    while (true) {
        $documents = $scrollResult['hits']['hits'] ?? [];
        
        if (empty($documents)) {
            break;
        }
        
        $batch++;
        $batchStartTime = time();
        $currentBatchSize = count($documents);
        
        updateProgress([
            'current_batch' => $batch,
            'status' => 'processing_batch',
            'current_document_id' => "batch_" . $batch
        ]);
        
        echo "Processing batch $batch/$totalBatches (docs: $currentBatchSize)\n";
        
        $batchSuccessful = 0;
        $batchFailed = 0;
        $batchWords = 0;
        $batchStats = 0;
        
        foreach ($documents as $doc) {
            // Check if we've reached the limit before processing this document
            if ($totalLimit && $processedTotal >= $totalLimit) {
                echo "Reached processing limit of $totalLimit documents\n";
                break 2; // Break out of both foreach and while loops
            }
            
            $speechData = $doc['_source'];
            $speechData['id'] = $doc['_id'];
            
            updateProgress(['current_document_id' => $doc['_id']]);
            
            try {
                $result = indexSpeechStatistics($speechData, strtolower($parliamentCode));
                
                if ($result['success']) {
                    $batchSuccessful++;
                    
                    // Word events eliminated - only track statistics
                    if (isset($result['results']['statistics']['aggregations_updated'])) {
                        $batchStats += $result['results']['statistics']['aggregations_updated'];
                    }
                    if (isset($result['results']['statistics']['unique_words'])) {
                        $batchWords += $result['results']['statistics']['unique_words'];
                    }
                } else {
                    $batchFailed++;
                    error_log("Statistics indexing failed for document " . $doc['_id'] . ": " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                $batchFailed++;
                error_log("Exception processing document " . $doc['_id'] . ": " . $e->getMessage());
            }
            
            $processedTotal++;
        }
        
        $successfulTotal += $batchSuccessful;
        $failedTotal += $batchFailed;
        $wordsTotal += $batchWords;
        $statsTotal += $batchStats;
        
        $batchTime = time() - $batchStartTime;
        
        updateProgress([
            'processed_documents' => $processedTotal,
            'successful_documents' => $successfulTotal,
            'failed_documents' => $failedTotal,
            'words_indexed' => $wordsTotal,
            'statistics_updated' => $statsTotal,
            'performance' => array_merge($progressData['performance'], [
                'current_batch_time' => $batchTime
            ])
        ]);
        
        echo "  Batch completed: $batchSuccessful successful, $batchFailed failed\n";
        echo "  Unique words processed: $batchWords, Statistics updated: $batchStats\n";
        echo "  Batch time: {$batchTime}s\n\n";
        
        // Continue scrolling
        try {
            $scrollResult = $ESClient->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '5m'
            ]);
            $scrollId = $scrollResult['_scroll_id'];
        } catch (Exception $e) {
            echo "Scroll error: " . $e->getMessage() . "\n";
            break;
        }
        
        // Small delay to prevent overwhelming the system
        usleep(100000); // 0.1 second
    }
    
    // Clear scroll context
    try {
        $ESClient->clearScroll(['scroll_id' => $scrollId]);
    } catch (Exception $e) {
        // Ignore scroll clear errors
    }
    
    // Restore index settings for normal operation
    echo "Restoring index settings for normal operation...\n";
    $ESClient->indices()->putSettings([
        'index' => $indexName,
        'body' => [
            'settings' => [
                'refresh_interval' => '1s', // Normal refresh interval
                'index.translog.sync_interval' => '5s', // Normal sync interval
                'index.translog.durability' => 'request' // Normal durability
            ]
        ]
    ]);
    
    // Force refresh to make documents searchable
    $ESClient->indices()->refresh(['index' => $indexName]);
    echo "Index settings restored and refreshed.\n";
    
    // Optimize index by cleaning up deleted documents and merging segments
    updateProgress(['status' => 'optimizing_index']);
    echo "Optimizing index (cleaning deleted documents and merging segments)...\n";
    $optimizeStartTime = time();
    
    try {
        // Get stats before optimization
        $statsBefore = $ESClient->indices()->stats(['index' => $indexName]);
        $deletedBefore = $statsBefore['indices'][$indexName]['total']['docs']['deleted'] ?? 0;
        $sizeBefore = $statsBefore['indices'][$indexName]['total']['store']['size_in_bytes'] ?? 0;
        
        // Force merge to clean up deleted documents and optimize segments
        $ESClient->indices()->forcemerge([
            'index' => $indexName,
            'max_num_segments' => 1, // Merge to single segment for maximum optimization
            'only_expunge_deletes' => false // Full optimization
        ]);
        
        // Get stats after optimization
        sleep(2); // Give OpenSearch time to update stats
        $statsAfter = $ESClient->indices()->stats(['index' => $indexName]);
        $deletedAfter = $statsAfter['indices'][$indexName]['total']['docs']['deleted'] ?? 0;
        $sizeAfter = $statsAfter['indices'][$indexName]['total']['store']['size_in_bytes'] ?? 0;
        
        $deletedCleaned = $deletedBefore - $deletedAfter;
        $spaceReclaimed = $sizeBefore - $sizeAfter;
        $optimizeTime = time() - $optimizeStartTime;
        
        echo "Index optimization completed in {$optimizeTime}s:\n";
        echo "  - Deleted documents cleaned: " . number_format($deletedCleaned) . "\n";  
        echo "  - Space reclaimed: " . round($spaceReclaimed / 1024 / 1024, 1) . " MB\n";
        echo "  - Final index size: " . round($sizeAfter / 1024 / 1024 / 1024, 2) . " GB\n";
        
    } catch (Exception $e) {
        echo "Warning: Index optimization failed: " . $e->getMessage() . "\n";
        error_log("Enhanced indexer optimization error: " . $e->getMessage());
        // Continue anyway - optimization failure shouldn't fail the entire process
    }
    
    // Final status
    updateProgress([
        'status' => 'completed',
        'current_document_id' => null,
        'completion_time' => time()
    ]);
    
    echo "Statistics indexing completed!\n";
    echo "Total processed: $processedTotal documents\n";
    echo "Successful: $successfulTotal\n";
    echo "Failed: $failedTotal\n";
    echo "Unique words processed: $wordsTotal\n";
    echo "Statistics updated: $statsTotal\n";
    
} catch (Exception $e) {
    updateProgress([
        'status' => 'error',
        'error_messages' => [$e->getMessage()],
        'completion_time' => time()
    ]);
    
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Statistics indexing error: " . $e->getMessage());
    exit(1);
}

cleanup();
?>