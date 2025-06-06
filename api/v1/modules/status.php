<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");
require_once (__DIR__."/../../../modules/search/functions.php");

// Helper function to format date strings to ISO 8601 (ATOM)
function formatDateToISO8601($dateString = null) {
    if (empty($dateString)) {
        return null;
    }
    try {
        // Attempt to create a DateTimeImmutable object.
        // This constructor is quite flexible with input formats.
        $date = new DateTimeImmutable($dateString);
        return $date->format(DateTime::ATOM); // e.g., 2024-05-21T10:30:00+00:00
    } catch (Exception $e) {
        // If parsing fails, log the error and return null to ensure consistent output.
        error_log("formatDateToISO8601: Failed to parse date string '{$dateString}': " . $e->getMessage());
        return null;
    }
}

function getLocalRepoStatus($parliamentCode) {
    global $config;
    $repoPath = __DIR__."/../../../data/repos/".$parliamentCode;
    $processedPath = $repoPath."/processed/";
    $status = [
        "lastUpdated" => null,
        "numberOfSessions" => 0
    ];

    if (is_dir($repoPath . "/.git")) {
        $gitLogOutput = shell_exec($config["bin"]["git"] . ' -C "' . $repoPath . '" log -1 --format=%cI');
        if ($gitLogOutput) {
            $status["lastUpdated"] = formatDateToISO8601(trim($gitLogOutput));
        }

        if (is_dir($processedPath)) {
            $files = scandir($processedPath);
            $sessionFiles = [];
            foreach ($files as $file) {
                if (preg_match('/^[0-9]+-session\.json$/', $file)) {
                    $sessionFiles[] = $file;
                }
            }
            $status["numberOfSessions"] = count($sessionFiles);
        }
    }
    return $status;
}

function getRemoteRepoDetails($repoUrl) {
    // Define cache settings
    $cacheDir = __DIR__ . '/../cache/';
    $cacheLifetime = 600; // 10 minutes in seconds

    // Default response structure
    $defaultDetails = [
        "lastUpdated" => null,
        "numberOfSessions" => 0
    ];

    if (empty($repoUrl) || !preg_match("~github\.com/([^/]+)/([^/.]+)(\.git)?~", $repoUrl, $matches)) {
        return $defaultDetails;
    }
    
    $cacheKey = md5($repoUrl) . '.json';
    $cacheFile = $cacheDir . $cacheKey;

    // Create cache directory if it doesn't exist
    if (!is_dir($cacheDir)) {
        // Use @ to suppress warnings if the directory already exists due to a race condition.
        @mkdir($cacheDir, 0775, true);
    }

    // Try to get data from cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
        $cachedData = @file_get_contents($cacheFile);
        if ($cachedData !== false) {
            $decodedData = json_decode($cachedData, true);
            // Ensure the cached data is a valid array, otherwise proceed to fetch.
            if (is_array($decodedData)) {
                return $decodedData;
            }
        }
    }

    // If cache is invalid or missing, fetch from GitHub API
    $details = $defaultDetails;

    $owner = $matches[1];
    $repo = $matches[2];

    $commitsApiUrl = "https://api.github.com/repos/{$owner}/{$repo}/commits";
    $commitsResponse = makeHttpRequest($commitsApiUrl, ['http' => ['user_agent' => 'OpenParliamentTV-Platform-Status']]);
    if ($commitsResponse !== false) {
        $commitsData = json_decode($commitsResponse, true);
        if (is_array($commitsData) && !empty($commitsData)) {
            $commitDate = $commitsData[0]['commit']['committer']['date'] ?? $commitsData[0]['commit']['author']['date'] ?? null;
            $details["lastUpdated"] = formatDateToISO8601($commitDate);
        }
    }

    $contentsApiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents/processed";
    $contentsResponse = makeHttpRequest($contentsApiUrl, ['http' => ['user_agent' => 'OpenParliamentTV-Platform-Status']]);

    if ($contentsResponse !== false) {
        $contentsData = json_decode($contentsResponse, true);
        if (is_array($contentsData)) {
            $sessionFiles = [];
            foreach ($contentsData as $item) {
                if ($item['type'] === 'file' && preg_match('/^[0-9]+-session\.json$/', $item['name'])) {
                    $sessionFiles[] = $item['name'];
                }
            }
            $details["numberOfSessions"] = count($sessionFiles);
        }
    }

    // Save the fresh data to the cache file only if we got a valid response
    if ($details["lastUpdated"] !== null) { // A successful commit fetch is a good indicator
        @file_put_contents($cacheFile, json_encode($details));
    }
    
    return $details;
}

function getDatabaseStatus($parliamentCode) {
    global $config;
    $status = [
        "lastUpdated" => null,
        "lastSpeechDate" => null,
        "numberOfSessions" => 0,
        "numberOfSpeeches" => 0
    ];

    if (!isset($config["parliament"][$parliamentCode]["sql"])) {
        return $status;
    }

    $dbp = getApiDatabaseConnection('parliament', $parliamentCode);

    if (!($dbp instanceof SafeMySQL)) {
        // error_log("Status API - getDatabaseStatus for $parliamentCode: Failed to connect to database. Error: " . print_r($dbp, true));
        return $status;
    }

    try {
        $mediaTable = $config["parliament"][$parliamentCode]["sql"]["tbl"]["Media"];
        $agendaItemTable = $config["parliament"][$parliamentCode]["sql"]["tbl"]["AgendaItem"];
        // error_log("Status API - getDatabaseStatus for $parliamentCode: Attempting to query tables: $mediaTable, $agendaItemTable");

        $latestModifiedMedia = $dbp->getRow(
            "SELECT m.MediaLastChanged ".
            "FROM ?n AS m ".
            "ORDER BY m.MediaLastChanged DESC LIMIT 1",
            $mediaTable
        );
        // error_log("Status API - getDatabaseStatus for $parliamentCode - Query latestModifiedMedia result: " . print_r($latestModifiedMedia, true));

        if ($latestModifiedMedia && !empty($latestModifiedMedia["MediaLastChanged"])) {
            $status["lastUpdated"] = formatDateToISO8601($latestModifiedMedia["MediaLastChanged"]);
        }

        $latestSpeechDateMedia = $dbp->getRow(
            "SELECT m.MediaDateStart ".
            "FROM ?n AS m ".
            "ORDER BY m.MediaDateStart DESC LIMIT 1",
            $mediaTable
        );
        // error_log("Status API - getDatabaseStatus for $parliamentCode - Query latestSpeechDateMedia result: " . print_r($latestSpeechDateMedia, true));

        if ($latestSpeechDateMedia && !empty($latestSpeechDateMedia["MediaDateStart"])) {
            $status["lastSpeechDate"] = formatDateToISO8601($latestSpeechDateMedia["MediaDateStart"]);
        }

        $totalSpeeches = $dbp->getOne("SELECT COUNT(*) FROM ?n", $mediaTable);
        // error_log("Status API - getDatabaseStatus for $parliamentCode - Query totalSpeeches result: " . $totalSpeeches);
        if ($totalSpeeches) {
            $status["numberOfSpeeches"] = (int)$totalSpeeches;
        }

        $totalSessions = $dbp->getOne(
            "SELECT COUNT(DISTINCT ai.AgendaItemSessionID) ".
            "FROM ?n AS m JOIN ?n AS ai ON m.MediaAgendaItemID = ai.AgendaItemID",
            $mediaTable, $agendaItemTable
        );
        // error_log("Status API - getDatabaseStatus for $parliamentCode - Query totalSessions result: " . $totalSessions);
        if ($totalSessions) {
            $status["numberOfSessions"] = (int)$totalSessions;
        }
        
    } catch (Exception $e) {
        error_log("Status API - getDatabaseStatus for $parliamentCode - DB EXCEPTION: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
    }

    return $status;
}

function getIndexStatus($parliamentCode) {
    global $config, $ESClient;
    $status = [
        "lastUpdated" => null,
        "lastSpeechDate" => null,
        "numberOfSpeeches" => 0
    ];

    // Initialize ESClient for getIndexCount
    if (!isset($ESClient) || !$ESClient) { 
        $ESClient = getApiOpenSearchClient(); 
    }
    if (is_object($ESClient) && !isset($ESClient->errors)) { 
        $status["numberOfSpeeches"] = getIndexCount(); 
    } 

    // Call 1: For lastUpdated (sort by changed-desc)
    $apiRequestParamsLastChanged = [
        "action" => "search",
        "itemType" => "media",
        "parliament" => $parliamentCode,
        "sort" => "changed-desc", // Sort by last changed
        "limit" => 1,
        "offset" => 0
    ];
    $searchResultLastChanged = apiV1($apiRequestParamsLastChanged, false, false);

    if (isset($searchResultLastChanged["meta"]["requestStatus"]) && $searchResultLastChanged["meta"]["requestStatus"] === "success") {
        $status["lastUpdated"] = formatDateToISO8601($searchResultLastChanged["data"][0]["attributes"]["lastChanged"]);
    }

    // Call 2: For lastSpeechDate (sort by date-desc)
    $apiRequestParamsLastSpeechDate = [
        "action" => "search",
        "itemType" => "media",
        "parliament" => $parliamentCode,
        "sort" => "date-desc", // Sort by speech date
        "limit" => 1,
        "offset" => 0
    ];
    $searchResultLastSpeechDate = apiV1($apiRequestParamsLastSpeechDate, false, false);

    if (isset($searchResultLastSpeechDate["meta"]["requestStatus"]) && $searchResultLastSpeechDate["meta"]["requestStatus"] === "success") {
        $status["lastSpeechDate"] = formatDateToISO8601($searchResultLastSpeechDate["data"][0]["attributes"]["dateStart"]);
    }

    return $status;
}

function getStatus($request = []) {
    global $config;

    $statusOutput = [
        "type" => "status",
        "id" => "all",
        "attributes" => [
            "parliaments" => []
        ]
    ];

    foreach ($config["parliament"] as $parliamentCode => $parliamentDetails) {
        $localRepoStatus = getLocalRepoStatus($parliamentCode);
        $remoteRepoData = getRemoteRepoDetails($parliamentDetails["git"]["repository"] ?? null);
        $dbStatus = getDatabaseStatus($parliamentCode);
        $indexStatus = getIndexStatus($parliamentCode);

        $parliamentData = [
            "code" => $parliamentCode,
            "label" => $parliamentDetails["label"],
            "repository" => [
                "location" => $parliamentDetails["git"]["repository"] ?? null,
                "remote" => $remoteRepoData,
                "local" => $localRepoStatus
            ],
            "database" => $dbStatus,
            "index" => $indexStatus
        ];
        
        $statusOutput["attributes"]["parliaments"][] = $parliamentData;
    }

    return createApiSuccessResponse($statusOutput["attributes"]);
}

?> 