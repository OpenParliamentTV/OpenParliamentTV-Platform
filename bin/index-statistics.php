<?php
/**
 * Statistics Indexing Processor
 * Unified processor for both full rebuild and incremental updates of statistics indices
 */

require_once(__DIR__ . "/../bootstrap-cli.php");
ini_set('memory_limit', '512M');

require_once(__DIR__ . '/../api/v1/utilities.php');
require_once(__DIR__ . '/../modules/statistics-index/indexer.php');
require_once(__DIR__ . '/../modules/statistics/cache.php');

// Command line arguments
$argv = $_SERVER['argv'] ?? [];
$parliamentCode = null; // Resolved below; defaults to the first configured parliament
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

// Resolve to a real configured parliament key ("de" and "DE" both land on "DE"),
// so index names, progress files and lock files all agree on one spelling.
$parliamentCode = resolveParliamentKey($parliamentCode);

// Progress and lock file paths
define("STATISTICSINDEXER_PROGRESS_FILE", optvVarPath("data/progress") . "/statisticsIndexer_" . $parliamentCode . ".json");
$progressFile = STATISTICSINDEXER_PROGRESS_FILE;
$lockFile = optvVarPath('data/progress') . '/statisticsIndexer_' . $parliamentCode . '.lock';

// Handle reset command if requested
if (optvIsCli() && isset($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--reset') === 0) {
            echo "Resetting statistics indexer progress for $parliamentCode...\n";
            $result = resetProgressStatus($parliamentCode);
            echo "Reset completed. Status: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
            exit(0);
        }
    }
}

// Wait for a running statistics indexer instead of giving up: a run carries the
// speeches an import or a public toggle just changed, and dropping it would leave
// their statistics stale. fopen mode 'x' fails while the file exists, so of
// several waiting runs exactly one gets the lock. A lock older than its holder's
// timeout is left over from a killed run.
$lockTimeouts = ['incremental' => 3600, 'full_rebuild' => 14400];
$waitLogged = false;
while (($lockHandle = @fopen($lockFile, 'x')) === false) {
    clearstatcache(true, $lockFile); // the holder may have changed since the last look
    $lockTime = @filemtime($lockFile);
    if ($lockTime === false) {
        continue; // released between the two calls
    }
    $holder = json_decode((string) @file_get_contents($lockFile), true);
    if (time() - $lockTime >= ($lockTimeouts[$holder['mode'] ?? ''] ?? $lockTimeouts['full_rebuild'])) {
        @unlink($lockFile);
        logger('info', "Removed stale statistics indexer lock file for $parliamentCode (timeout exceeded)");
        continue;
    }
    if (!$waitLogged) {
        logger('info', "Statistics indexer already running for $parliamentCode - waiting for it to finish");
        $waitLogged = true;
    }
    sleep(5);
}
fwrite($lockHandle, json_encode(['pid' => getmypid(), 'timestamp' => time(), 'mode' => $isIncremental ? 'incremental' : 'full_rebuild']));
fclose($lockHandle);

// Wait for a running import too. The importer starts its statistics run just
// before it exits, while its own lock is still in place; no new import starts
// meanwhile, because imports skip while the statistics lock exists.
$cronUpdaterLockFile = optvVarPath("data/progress") . "/cronUpdater_" . $parliamentCode . ".lock";
while (true) {
    clearstatcache(true, $cronUpdaterLockFile);
    $lockTime = @filemtime($cronUpdaterLockFile);
    if ($lockTime === false) {
        break;
    }
    if (time() - $lockTime >= 5400) { // 90 minutes timeout (same as cronUpdater)
        @unlink($cronUpdaterLockFile);
        logger('info', "Removed stale cronUpdater lock file for $parliamentCode.");
        break;
    }
    sleep(5);
}

// Initialize progress tracking only now: the progress file belongs to whichever
// run holds the lock.
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
optvProgressInit(STATISTICSINDEXER_PROGRESS_FILE, $initialData);

function updateProgress($data) {
    optvProgressUpdate(STATISTICSINDEXER_PROGRESS_FILE, $data);
}

function logger($type = "info", $msg) {
    global $parliamentCode;
    $timestamp = date('Y-m-d H:i:s');
    $logFile = optvVarFilePath("log", "statisticsIndexer_{$parliamentCode}.log");
    $logEntry = "$timestamp - $type: $msg" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Reset statistics indexer progress status to allow new runs
 * This can be called from API or command line to recover from stuck states
 */
function resetProgressStatus($parliamentCode) {
    $progressFile = optvVarPath('data/progress') . '/statisticsIndexer_' . $parliamentCode . '.json';
    $lockFile = optvVarPath('data/progress') . '/statisticsIndexer_' . $parliamentCode . '.lock';
    
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
    
    optvProgressInit($progressFile, $resetData);
    optvProgressFinalize($progressFile, 'idle', 'Ready for indexing (manually reset)');
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
    if (function_exists('optvProgressFinalize')) {
        if (file_exists(STATISTICSINDEXER_PROGRESS_FILE)) {
            $currentProgressJson = @file_get_contents(STATISTICSINDEXER_PROGRESS_FILE);
            $currentProgress = $currentProgressJson ? json_decode($currentProgressJson, true) : null;
            
            if (is_array($currentProgress) && $currentProgress["status"] === "running") {
                $error = error_get_last();
                $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
                
                if ($error && in_array($error['type'], $fatalErrorTypes)) {
                    $logMessageDetail = "Statistics Indexer exited unexpectedly: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
                    optvProgressLogError(STATISTICSINDEXER_PROGRESS_FILE, $logMessageDetail, "FATAL");
                    optvProgressFinalize(STATISTICSINDEXER_PROGRESS_FILE, "error_shutdown", "Process terminated unexpectedly due to fatal error.");
                } else {
                    $logMessageDetail = "Statistics Indexer exited unexpectedly while 'running'.";
                    optvProgressLogError(STATISTICSINDEXER_PROGRESS_FILE, $logMessageDetail, "CRASH");
                    optvProgressFinalize(STATISTICSINDEXER_PROGRESS_FILE, "error_shutdown", "Process terminated unexpectedly.");
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

    // The index just changed, so every cached statistics response describes the
    // old one. Recompute the corpus-wide ones here rather than letting the first
    // visitor after an import pay for them: unscoped general statistics alone
    // takes ~16 seconds cold on a full corpus.
    updateProgress(['statusDetails' => 'Warming statistics caches...']);
    statsCacheInvalidate($parliamentCode);
    $warm = statsCacheWarm($parliamentCode, function ($message) {
        logger('info', $message);
    });
    logger('info', "Warmed " . implode(', ', $warm['warmed']) . " in {$warm['seconds']}s");

    optvProgressFinalize(STATISTICSINDEXER_PROGRESS_FILE, 'completed_successfully', 'Statistics indexing completed successfully');
    logger('info', "Statistics indexing completed successfully");

} catch (Exception $e) {
    logger('error', "Critical error: " . $e->getMessage());
    optvProgressLogError(STATISTICSINDEXER_PROGRESS_FILE, $e->getMessage(), "EXCEPTION");
    optvProgressFinalize(STATISTICSINDEXER_PROGRESS_FILE, 'error_critical', 'Critical error: ' . $e->getMessage());
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
    $mainIndexName = getStatisticsMainIndexName($parliamentCode);
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
        
        unset($hits, $batchResults);
        
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
    global $config, $batchSize;
    
    $mediaIds = array_values(array_unique(array_filter($mediaIds)));
    
    updateProgress([
        'totalDbMediaItems' => count($mediaIds),
        'statusDetails' => 'Processing specific media items...'
    ]);
    
    $mainIndexName = getStatisticsMainIndexName($parliamentCode);
    $mgetBatchSize = min($batchSize, 50);
    $wordsIndexed = 0;
    $statisticsUpdated = 0;
    $processedChunks = 0;
    $totalChunks = (int) ceil(count($mediaIds) / $mgetBatchSize);
    
    foreach (array_chunk($mediaIds, $mgetBatchSize) as $idChunk) {
        $processedChunks++;
        
        try {
            $response = $client->mget([
                'index' => $mainIndexName,
                'body' => [
                    'ids' => $idChunk
                ]
            ]);
            
            if (!isset($response['docs']) || !is_array($response['docs'])) {
                throw new Exception("Invalid OpenSearch mget response structure");
            }
            
            $docs = [];
            $errorCount = 0;
            
            foreach ($response['docs'] as $doc) {
                if (isset($doc['error'])) {
                    $errorCount++;
                    logger('warn', "Document error: " . json_encode($doc['error']));
                    continue;
                }
                
                if (isset($doc['found']) && $doc['found'] === true && speechSourceHasTextContent($doc['_source'] ?? [])) {
                    $docs[] = [
                        '_id' => $doc['_id'],
                        '_source' => $doc['_source']
                    ];
                } elseif (isset($doc['found']) && $doc['found'] === false) {
                    logger('info', "Document not found: " . ($doc['_id'] ?? 'unknown'));
                }
            }
            
            if ($errorCount > 0) {
                logger('warn', "Encountered $errorCount errors while fetching documents from index: $mainIndexName");
            }
            
            // Clear every id we were asked to refresh, not just the ones we are
            // about to rewrite: a speech whose text was removed, or that was made
            // non-public, must lose its old documents too.
            $deleteResult = statsIndexDeleteSpeeches($idChunk, $parliamentCode);
            if (empty($deleteResult['success'])) {
                logger('warn', "Could not clear previous statistics for chunk: " . ($deleteResult['error'] ?? 'unknown'));
            }

            if (!empty($docs)) {
                $batchResults = processBatchWithProperStatistics($client, $parliamentCode, $docs, false);
                $wordsIndexed += $batchResults['words'];
                $statisticsUpdated += $batchResults['stats'];
            }
        } catch (Exception $e) {
            logger('error', "Failed to fetch documents from OpenSearch: " . $e->getMessage());
            throw new Exception("OpenSearch mget operation failed: " . $e->getMessage());
        }
        
        unset($response, $docs, $idChunk);
        
        updateProgress([
            'processedMediaItems' => min(count($mediaIds), $processedChunks * $mgetBatchSize),
            'words_indexed' => $wordsIndexed,
            'statistics_updated' => $statisticsUpdated,
            'statusDetails' => "Processing specific media items: chunk $processedChunks/$totalChunks"
        ]);
    }
    
    updateProgress([
        'processedMediaItems' => count($mediaIds),
        'words_indexed' => $wordsIndexed,
        'statistics_updated' => $statisticsUpdated,
        'statusDetails' => 'Completed processing specific media items'
    ]);
}

function getStatisticsMainIndexName($parliamentCode) {
    return getParliamentIndexName($parliamentCode);
}

function speechSourceHasTextContent($source) {
    if (isset($source['attributes']['textContentsCount']) && (int) $source['attributes']['textContentsCount'] > 0) {
        return true;
    }
    
    return !empty($source['attributes']['textContents']);
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
function processBatchWithProperStatistics($client, $parliamentCode, $hits, $deleteFirst = false) {
    $speechNodes = [];

    foreach ($hits as $hit) {
        $speechData = $hit['_source'];
        $speechData['id'] = $hit['_id'];
        $speechNodes[] = $speechData;
    }

    unset($hits);

    if (empty($speechNodes)) {
        return ['words' => 0, 'stats' => 0];
    }

    // One bulk request and one stem lookup for the whole batch. This used to be
    // one bulk request per speech, plus an index-exists round-trip per speech.
    try {
        $result = indexSpeechStatisticsBatch($speechNodes, $parliamentCode, ['deleteFirst' => $deleteFirst]);
    } catch (Exception $e) {
        logger('error', "Exception processing batch of " . count($speechNodes) . " speeches: " . $e->getMessage());
        return ['words' => 0, 'stats' => 0];
    }

    if (empty($result['success'])) {
        logger('error', "Statistics processing failed for batch: " . ($result['error'] ?? 'Unknown error'));
        return ['words' => 0, 'stats' => 0];
    }

    return ['words' => $result['documents'] ?? 0, 'stats' => $result['documents'] ?? 0];
}



/**
 * Create the statistics index, tuned for bulk indexing.
 *
 * The mapping itself lives in modules/statistics-index/index-admin.php -- this
 * used to carry a second, divergent copy of it.
 */
function setupStatisticsIndices($client, $parliamentCode, $isFullRebuild = false) {
    $result = createStatisticsIndexing($parliamentCode, $isFullRebuild, true);

    if (empty($result['success'])) {
        throw new Exception('Could not create statistics index: ' . ($result['error'] ?? 'unknown error'));
    }

    logger('info', "Statistics index ready: " . getStatisticsIndexName($parliamentCode));
}

/**
 * Restore serving settings after a bulk rebuild.
 */
function restoreOptimalIndexSettings($client, $parliamentCode) {
    $result = statsIndexRestoreSettings($parliamentCode);

    if (empty($result['success'])) {
        logger('warn', "Could not restore settings: " . ($result['error'] ?? 'unknown error'));
        return;
    }

    try {
        $client->indices()->refresh(['index' => getStatisticsIndexName($parliamentCode)]);
    } catch (Exception $e) {
        logger('warn', "Could not refresh index: " . $e->getMessage());
    }

    logger('info', "Restored serving settings for: " . getStatisticsIndexName($parliamentCode));
}