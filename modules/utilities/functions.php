<?php

function array_diff_assoc_recursive($array1, $array2)
{
	foreach($array1 as $key => $value){

		if(is_array($value)){
			if(!isset($array2[$key]))
			{
				$difference[$key] = $value;
			}
			elseif(!is_array($array2[$key]))
			{
				$difference[$key] = $value;
			}
			else
			{
				$new_diff = array_diff_assoc_recursive($value, $array2[$key]);
				if($new_diff != FALSE)
				{
					$difference[$key] = $new_diff;
				}
			}
		}
		elseif((!isset($array2[$key]) || $array2[$key] != $value) && !($array2[$key]===null && $value===null))
		{
			$difference[$key] = $value;
		}
	}
	return !isset($difference) ? 0 : $difference;
}

function arrayRecursiveDiff($aArray1, $aArray2) {
	$aReturn = array();

	foreach ($aArray1 as $mKey => $mValue) {
		if (array_key_exists($mKey, $aArray2)) {
			if (is_array($mValue)) {
				$aRecursiveDiff = arrayRecursiveDiff($mValue, $aArray2[$mKey]);
				if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
			} else {
				if ($mValue != $aArray2[$mKey]) {
					$aReturn[$mKey] = $mValue;
				}
			}
		} else {
			$aReturn[$mKey] = $mValue;
		}
	}
	return $aReturn;
}

// Polyfill for PHP 4 - PHP 7, safe to utilize with PHP 8
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}


/**
 * @param string $password
 * @return bool
 * Checks if the given password is weak (returns false) or strong (Uppercase, lowercase, number, specialChar and >8 character)
 */
function passwordStrength($password = "") {

	$uppercase = preg_match('@[A-Z]@', $password);
	$lowercase = preg_match('@[a-z]@', $password);
	$number    = preg_match('@[0-9]@', $password);
	$specialChars = preg_match('@[^\w]@', $password);

	if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {

		return false; // Password is weak.

	} else {

		return true; // Password is strong

	}

}

/**
 *
 * eg.:
 *      DE-BY-0190023123
 *          returns
 *      array(
 *          "parliament" => "DE-BY",
 *          "type" => "media"
 *          "id_full" => "DE-BY-0190023123"
 *          "id_part" => "0190023123",
 *          "electoralPeriodNumber" => "019",
 *          "sessionNumber" => "0023",
 *          "mediaNumber" => "123"
 *      );
 *
 * -----
 *
 *      DE-BY-0190023
 *          returns
 *      array(
 *          "parliament" => "DE-BY",
 *          "type" => "session"
 *          "id_full" => "DE-BY-0190023"
 *          "id_part" => "0190023",
 *          "electoralPeriodNumber" => "019",
 *          "sessionNumber" => "0023"
 *      );
 * @param string $stringID
 * @return string
 */
function getInfosFromStringID($stringID = "") {


    if ($stringID == "") {

        return false;

    } else {

        $stringsplit = explode("-",$stringID);

        $id = array_pop($stringsplit);

        $length = strlen($id);

        if ($length >= 8) {

            $return["type"] = "media";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);
            $return["sessionNumber"] = substr($id,3, 4);
            $return["mediaNumber"] = substr($id,7);

        } elseif ($length == 7) {

            $return["type"] = "session";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);
            $return["sessionNumber"] = substr($id,3, 4);

        } elseif ($length == 3) {

            $return["type"] = "electoralPeriod";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);

        } else {

            $return["type"] = "unknown"; // Maybe AgendaItem
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;

        }

        if (is_array($stringsplit)) {

            $parliament = implode("-",$stringsplit);

        }

        $return["parliament"] = $parliament;

        return $return;
    }

}

/*
 * Replaces special characters in a string with their "non-special" counterpart.
 *
 * Useful for friendly URLs.
 *
 * @access public
 * @param string
 * @return string
 */
function convertAccentsAndSpecialToNormal($string) {
    $table = array(
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Ă'=>'A', 'Ā'=>'A', 'Ą'=>'A', 'Æ'=>'A', 'Ǽ'=>'A',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ă'=>'a', 'ā'=>'a', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',

        'Þ'=>'B', 'þ'=>'b', 'ß'=>'s',

        'Ç'=>'C', 'Č'=>'C', 'Ć'=>'C', 'Ĉ'=>'C', 'Ċ'=>'C',
        'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',

        'Đ'=>'Dj', 'Ď'=>'D', 'Đ'=>'D',
        'đ'=>'dj', 'ď'=>'d',

        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ĕ'=>'E', 'Ē'=>'E', 'Ę'=>'E', 'Ė'=>'E',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'ę'=>'e', 'ė'=>'e',

        'Ĝ'=>'G', 'Ğ'=>'G', 'Ǧ'=>'G', 'Ġ'=>'G', 'Ģ'=>'G',
        'ĝ'=>'g', 'ğ'=>'g', 'ǧ'=>'g', 'ġ'=>'g', 'ģ'=>'g',

        'Ĥ'=>'H', 'Ħ'=>'H',
        'ĥ'=>'h', 'ħ'=>'h',

        'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'Ĩ'=>'I', 'Ī'=>'I', 'Ĭ'=>'I', 'Į'=>'I',
        'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',

        'Ĵ'=>'J',
        'ĵ'=>'j',

        'Ķ'=>'K',
        'ķ'=>'k', 'ĸ'=>'k',

        'Ĺ'=>'L', 'Ļ'=>'L', 'Ľ'=>'L', 'Ŀ'=>'L', 'Ł'=>'L',
        'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',

        'Ñ'=>'N', 'Ń'=>'N', 'Ň'=>'N', 'Ņ'=>'N', 'Ŋ'=>'N',
        'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',

        'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ō'=>'O', 'Ŏ'=>'O', 'Ő'=>'O', 'Œ'=>'O',
        'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ō'=>'o', 'ŏ'=>'o', 'ő'=>'o', 'œ'=>'o', 'ð'=>'o',

        'Ŕ'=>'R', 'Ř'=>'R',
        'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',

        'Š'=>'S', 'Ŝ'=>'S', 'Ś'=>'S', 'Ş'=>'S',
        'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',

        'Ŧ'=>'T', 'Ţ'=>'T', 'Ť'=>'T',
        'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',

        'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ũ'=>'U', 'Ū'=>'U', 'Ŭ'=>'U', 'Ů'=>'U', 'Ű'=>'U', 'Ų'=>'U',
        'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ũ'=>'u', 'ū'=>'u', 'ŭ'=>'u', 'ů'=>'u', 'ű'=>'u', 'ų'=>'u',

        'Ŵ'=>'W', 'Ẁ'=>'W', 'Ẃ'=>'W', 'Ẅ'=>'W',
        'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',

        'Ý'=>'Y', 'Ÿ'=>'Y', 'Ŷ'=>'Y',
        'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',

        'Ž'=>'Z', 'Ź'=>'Z', 'Ż'=>'Z', 'Ž'=>'Z',
        'ž'=>'z', 'ź'=>'z', 'ż'=>'z', 'ž'=>'z'
    );

    $string = strtr($string, $table);
    // Currency symbols: £¤¥€  - we dont bother with them for now
    $string = preg_replace("/[^\x9\xA\xD\x20-\x7F]/u", "", $string);

    return $string;
}


function annotationRawSortByMainSpeaker($array = array()){
    usort($array, function($a, $b) {
        $a_val = 0;
        $b_val = 0;

        if(isset($a['AnnotationContext'])) {
            if($a['AnnotationContext'] === 'main-speaker') {
                $a_val = 2;
            } elseif($a['AnnotationContext'] === 'president' || $a['AnnotationContext'] === 'vice-president') {
                $a_val = 1;
            }
        }

        if(isset($b['AnnotationContext'])) {
            if($b['AnnotationContext'] === 'main-speaker') {
                $b_val = 2;
            } elseif(($b['AnnotationContext'] === 'vice-president') || ($b['AnnotationContext'] === 'president')) {
                $b_val = 1;
            }
        }

        if($a_val === $b_val) {
            if($a['AnnotationType'] === 'person' && $b['AnnotationType'] === 'person') {
                $a_id = isset($a['id']) ? $a['id'] : '';
                $b_id = isset($b['id']) ? $b['id'] : '';
                return strcmp($a_id, $b_id);
            } elseif($a['AnnotationType'] === 'person') {
                return -1;
            } elseif($b['AnnotationType'] === 'person') {
                return 1;
            } else {
                return 0;
            }
        } else {
            return $b_val - $a_val;
        }
    });
    return $array;
}
/*
function annotationSortByMainspeaker($array = array()){
    usort($array, function($a, $b) {
        $a_val = 0;
        $b_val = 0;

        if(isset($a['attributes']['context'])) {
            if($a['attributes']['context'] === 'main-speaker') {
                $a_val = 2;
            } elseif($a['attributes']['context'] === 'president' || $a['attributes']['context'] === 'vice-president') {
                $a_val = 1;
            }
        }

        if(isset($b['attributes']['context'])) {
            if($b['attributes']['context'] === 'main-speaker') {
                $b_val = 2;
            } elseif(($b['attributes']['context'] === 'vice-president') || ($b['attributes']['context'] === 'vice-president')) {
                $b_val = 1;
            }
        }

        if($a_val === $b_val) {
            if($a['type'] === 'person' && $b['type'] === 'person') {
                return strcmp($a['id'], $b['id']);
            } elseif($a['type'] === 'person') {
                return -1;
            } elseif($b['type'] === 'person') {
                return 1;
            } else {
                return 0;
            }
        } else {
            return $b_val - $a_val;
        }
    });
    return $array;
}
*/

function getURLParameterFromArray($array = array()) {

    $return = array();

    foreach ($array as $k=>$tmpparam) {
        if (is_array($tmpparam)) {
            foreach ($tmpparam as $tmpitem) {
                $return[] = urlencode($k)."[]=".urlencode($tmpitem);
            }
        } else {
            $return[] = urlencode($k)."=".urlencode($tmpparam);
        }
    }

    return implode("&",$return);

}

/*
 * Check if current file is executed by a CLI or a browser
 */

function is_cli()
{
    if (defined('STDIN')) {
        return true;
    }

    if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
        return true;
    }

    return false;
}


function isJson($string) {
    $decoded = json_decode($string);
    if ( !is_object($decoded) && !is_array($decoded) ) {
        return false;
    }
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Resolve a PHP CLI binary for spawning background jobs from the web context.
 * Bare "php" in config often fails under Apache's limited PATH.
 */
function resolvePhpCliBinary($configuredPath = '') {
    $configuredPath = trim((string) $configuredPath);

    if ($configuredPath !== '' && (strpos($configuredPath, '/') !== false || strpos($configuredPath, '\\') !== false) && is_file($configuredPath)) {
        return $configuredPath;
    }

    if (defined('PHP_BINDIR')) {
        $bindirPhp = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
        if (is_file($bindirPhp)) {
            return $bindirPhp;
        }
    }

    if (defined('PHP_BINARY') && PHP_BINARY !== '' && stripos(PHP_BINARY, 'fpm') === false && is_file(PHP_BINARY)) {
        return PHP_BINARY;
    }

    return $configuredPath !== '' ? $configuredPath : 'php';
}

function isPhpFunctionDisabled($functionName) {
    if (!function_exists($functionName)) {
        return true;
    }

    $disabled = ini_get('disable_functions');
    if ($disabled === false || $disabled === '') {
        return false;
    }

    return in_array($functionName, array_map('trim', explode(',', $disabled)), true);
}

function executeAsyncShellCommand($cmd = null, $logFile = null) {

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        //pclose(popen("start /B " . $cmd, "r"));
        pclose(popen('start /B cmd /C "'.$cmd.' >NUL 2>NUL"', 'r'));
    } else {
        if ($logFile) {
            exec($cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &');
        } else {
            exec("$cmd > /dev/null 2>&1 &");
        }
    }
}

/**
 * Filters an array to only include keys that are in the allowed list.
 * Can be used in two modes:
 * 1. Direct key filtering (like array_intersect_key)
 * 2. Case-sensitive key matching (like array_filter with in_array)
 *
 * @param array $data The input array to filter
 * @param string $type The type of search (e.g. "media", "person", "organisation", "document", "term")
 * @param bool $caseSensitive Whether to perform case-sensitive matching (default: true)
 * @return array Filtered array containing only allowed fields
 */
function filterAllowedSearchParams($data, $type, $caseSensitive = true) {

    global $config;

    $allowedParams = $config["allowedSearchParams"];

    // Only allow includeAll and public for admin users
    // TODO: check if this is the correct way to do this
    if (isset($_SESSION["userdata"]["role"]) && $_SESSION["userdata"]["role"] != "admin") {
        $allowedParams[$type] = array_diff($allowedParams[$type], ["includeAll", "public"]);
    }

    if (!isset($allowedParams[$type])) {
        return array();
    }
    if ($caseSensitive) {
        return array_filter($data, function($key) use ($allowedParams, $type) {
            return in_array($key, $allowedParams[$type]);
        }, ARRAY_FILTER_USE_KEY);
    }
    return array_intersect_key($data, array_flip($allowedParams[$type]));
}

/**
 * Makes an external HTTP GET request.
 *
 * @param string $url The URL to request.
 * @param array $options Context options for stream_context_create (e.g., user_agent, timeout).
 *                       Default user_agent is 'OpenParliamentTV-Platform-HTTPClient'.
 *                       Default timeout is 5 seconds.
 * @return string|false The response body as a string, or false on failure.
 */
function makeHttpRequest($url, $options = []) {
    $defaultOptions = [
        'http' => [
            'user_agent' => 'OpenParliamentTV-Platform-HTTPClient',
            'timeout' => 5,
            'method' => 'GET' // Default to GET
        ]
    ];

    // Merge provided options with defaults, allowing user_agent and timeout to be overridden
    if (isset($options['http']['user_agent'])) {
        $defaultOptions['http']['user_agent'] = $options['http']['user_agent'];
    }
    if (isset($options['http']['timeout'])) {
        $defaultOptions['http']['timeout'] = $options['http']['timeout'];
    }
    // Allow other http options to be passed through
    $contextOptions = array_replace_recursive($defaultOptions, $options);

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        // You might want to log an error here
        // error_log("HTTP request to $url failed. Error: " . ($http_response_header[0] ?? 'Unknown error'));
    }

    return $response;
}

/**
 * Validates a Wikidata ID.
 * A Wikidata ID starts with 'Q' or 'P' followed by digits.
 * @param string $id The ID to validate.
 * @return bool True if valid, false otherwise.
 */
function validateWikidataID($id) {
    if (empty($id)) {
        return false;
    }
    // Matches Q<digits> or P<digits>
    return (bool) preg_match('/^(Q|P)\d+$/i', $id);
}

// --- START: Generic Progress File Helper Functions ---

/**
 * Ensures the directory for the given progress file path exists.
 *
 * @param string $progressFilePath Full path to the progress file.
 * @return bool True if directory exists or was created, false otherwise.
 */
function ensureProgressDirectoryExists($progressFilePath) {
    $progressFileDir = dirname($progressFilePath);
    if (!is_dir($progressFileDir)) {
        // Attempt to create the directory recursively with 0775 permissions
        if (!mkdir($progressFileDir, 0775, true) && !is_dir($progressFileDir)) { // Check !is_dir again in case of race condition
            error_log("Failed to create progress directory: " . $progressFileDir);
            return false;
        }
    }
    return true;
}

/**
 * Initializes or resets a progress file with the given initial data.
 * It's expected that $initialData includes common fields like:
 * processName, statusDetails, totalItems (or equivalent), processedItems (or equivalent).
 * Mandatory fields added by this function: status="running", startTime, endTime=null, errors=[].
 *
 * @param string $progressFilePath Full path to the progress file.
 * @param array $initialData Associative array of initial progress data.
 * @return bool True on success, false on failure.
 */
function initBaseProgressFile($progressFilePath, array $initialData) {
    if (!ensureProgressDirectoryExists($progressFilePath)) {
        error_log("Cannot initialize progress file, directory creation failed for: " . $progressFilePath);
        return false;
    }

    $baseData = [
        "status" => "running", // Initial status is always running
        "startTime" => date('c'), // ISO8601 format
        "endTime" => null,
        "errors" => [],
        "lastActivityTime" => date('c')
    ];

    // Ensure initialData provides essential keys or set defaults if not present
    $initialData['processName'] = $initialData['processName'] ?? 'UnnamedProcess';
    $initialData['statusDetails'] = $initialData['statusDetails'] ?? 'Initializing...';


    $progressData = array_merge($initialData, $baseData);

    if (file_put_contents($progressFilePath, json_encode($progressData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        error_log("Failed to write initial progress data to: " . $progressFilePath);
        return false;
    }
    return true;
}

/**
 * Atomically applies a mutation to an existing progress file under an exclusive lock.
 *
 * The file is opened once and an exclusive flock is held across the whole
 * read-modify-write, so concurrent writers (e.g. a cron run and an API-triggered run)
 * cannot interleave and scramble counters. The $mutator receives the decoded progress
 * array and returns the array to persist (or null to abort the write). A corrupt or
 * empty file is recovered in place while the lock is held.
 *
 * @param string $progressFilePath Full path to the progress file.
 * @param callable $mutator function(array $currentProgress): ?array
 * @return bool True on success, false on failure or if the file is not initialized.
 */
function _atomicUpdateProgressFile($progressFilePath, callable $mutator) {
    if (!file_exists($progressFilePath)) {
        error_log("Cannot update progress file, it does not exist: " . $progressFilePath . ". Initialize it first.");
        return false;
    }

    $fp = @fopen($progressFilePath, 'c+');
    if ($fp === false) {
        error_log("Failed to open progress file for update: " . $progressFilePath);
        return false;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            error_log("Failed to acquire exclusive lock on progress file: " . $progressFilePath);
            return false;
        }

        $currentProgress = json_decode(stream_get_contents($fp), true);
        if (!is_array($currentProgress)) {
            error_log("Progress file content is not valid JSON or empty, recovering in place: " . $progressFilePath);
            $currentProgress = [
                'processName' => 'UnknownProcessOnCorrupt',
                'statusDetails' => 'File was corrupt, re-initialized during update.',
                'status' => 'running',
                'startTime' => date('c'),
                'endTime' => null,
                'errors' => [],
                'lastActivityTime' => date('c'),
            ];
        }

        $newProgress = $mutator($currentProgress);
        if (!is_array($newProgress)) {
            return false; // mutator chose not to write
        }

        rewind($fp);
        ftruncate($fp, 0);
        $written = fwrite($fp, json_encode($newProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        if ($written === false) {
            error_log("Failed to write updated progress data to: " . $progressFilePath);
            return false;
        }
        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Updates the progress file with new data (merged), atomically.
 * Always updates 'lastActivityTime'.
 *
 * @param string $progressFilePath Full path to the progress file.
 * @param array $updateData Associative array of data to merge into the progress file.
 * @return bool True on success, false on failure to read/write or if file not init.
 */
function updateBaseProgressFile($progressFilePath, array $updateData) {
    return _atomicUpdateProgressFile($progressFilePath, function ($currentProgress) use ($updateData) {
        $updateData["lastActivityTime"] = date('c'); // Always update last activity time
        return array_merge($currentProgress, $updateData);
    });
}

/**
 * Logs an error to the progress file.
 *
 * @param string $progressFilePath Full path to the progress file.
 * @param string $errorMessage The error message.
 * @param string|null $itemId Optional ID of the item being processed when error occurred.
 * @param mixed|null $errorDetail Optional additional details about the error.
 * @return bool True on success, false on failure.
 */
function logErrorToBaseProgressFile($progressFilePath, $errorMessage, $itemId = null, $errorDetail = null) {
    return _atomicUpdateProgressFile($progressFilePath, function ($currentProgress) use ($errorMessage, $itemId, $errorDetail) {
        if (!isset($currentProgress["errors"]) || !is_array($currentProgress["errors"])) {
            $currentProgress["errors"] = [];
        }

        $errorEntry = [
            "timestamp" => date('c'),
            "message" => $errorMessage
        ];
        if ($itemId !== null) {
            $errorEntry["itemId"] = $itemId;
        }
        if ($errorDetail !== null) {
            $errorEntry["detail"] = $errorDetail;
        }
        $currentProgress["errors"][] = $errorEntry;
        $currentProgress["lastError"] = end($currentProgress["errors"]);
        $currentProgress["lastActivityTime"] = date('c');

        return $currentProgress;
    });
}

/**
 * Finalizes the progress file, setting status and end time.
 *
 * @param string $progressFilePath Full path to the progress file.
 * @param string $finalStatus Typically "completed_successfully", "error_final", "partially_completed_with_errors".
 * @param string|null $finalStatusDetails Optional final status detail message.
 * @return bool True on success, false on failure.
 */
function finalizeBaseProgressFile($progressFilePath, $finalStatus, $finalStatusDetails = null) {
    return _atomicUpdateProgressFile($progressFilePath, function ($currentProgress) use ($finalStatus, $finalStatusDetails) {
        $currentProgress["status"] = $finalStatus;
        if ($finalStatusDetails !== null) {
            $currentProgress["statusDetails"] = $finalStatusDetails;
        } else {
            $currentProgress["statusDetails"] = ucfirst(str_replace("_", " ", $finalStatus));
        }
        $currentProgress["endTime"] = date('c');
        $currentProgress["lastActivityTime"] = date('c');

        if ($finalStatus === "completed_successfully" && empty($currentProgress["errors"])) {
            // For a clean success, ensure the errors array is present and empty.
            $currentProgress["errors"] = [];
        }

        return $currentProgress;
    });
}

// --- END: Generic Progress File Helper Functions ---

/**
 * Formats time duration or time ago with multilingual support
 * 
 * @param array $options Configuration array
 * @param int|string|DateTime $options['input'] - Seconds (for duration) or Date/DateTime (for time ago)
 * @param string $options['mode'] - 'duration' or 'ago' (default: 'duration')
 * @param bool $options['short'] - Show only 2 largest units (default: false)
 * @param bool $options['showAgo'] - Add "ago" suffix for ago mode (default: true)
 * @param array $options['labels'] - Override labels (optional, uses global $lang by default)
 * @param string $options['lang'] - Language code (optional, uses global $lang by default)
 * @return string Formatted time string
 */
function getTimeDistanceString($options = []) {
    $defaults = [
        'input' => 0,
        'mode' => 'duration',
        'short' => false,
        'showAgo' => true,
        'labels' => null,
        'lang' => null
    ];
    
    $config = array_merge($defaults, $options);
    
    // Get current language from global or default to 'en'
    global $lang;
    $currentLang = $config['lang'] ?? ($lang ?? 'en');
    
    // Helper function to get plural/singular form using L:: constants
    $getTimeLabel = function($value, $singularKey, $pluralKey) {
        // Try to get the label using L:: constants if class exists
        if (class_exists('L')) {
            try {
                if ($value == 1) {
                    if (defined('L::' . $singularKey)) {
                        return constant('L::' . $singularKey);
                    }
                } else {
                    if (defined('L::' . $pluralKey)) {
                        return constant('L::' . $pluralKey);
                    }
                }
            } catch (Exception $e) {
                // Continue to fallback
            }
        }
        
        // Fallback to English
        $fallback = [
            'timeDay' => 'day', 'timeDays' => 'days',
            'timeHour' => 'hour', 'timeHours' => 'hours',
            'timeMinute' => 'minute', 'timeMinutes' => 'minutes',
            'timeSecond' => 'second', 'timeSeconds' => 'seconds'
        ];
        return $value == 1 ? $fallback[$singularKey] : $fallback[$pluralKey];
    };
    
    $seconds = 0;
    
    if ($config['mode'] === 'ago') {
        // Calculate seconds from date to now
        $inputDate = null;
        
        if ($config['input'] instanceof DateTime) {
            $inputDate = $config['input'];
        } elseif (is_string($config['input'])) {
            $inputDate = new DateTime($config['input']);
        } elseif (is_numeric($config['input'])) {
            // Assume it's a timestamp
            $inputDate = new DateTime('@' . $config['input']);
        } else {
            // Invalid input for ago mode
            return '';
        }
        
        $now = new DateTime();
        $seconds = (int)($now->getTimestamp() - $inputDate->getTimestamp());
        
        // Handle future dates
        if ($seconds < 0) {
            $seconds = abs($seconds);
        }
    } else {
        // Duration mode - input is already in seconds
        $seconds = (int)$config['input'];
    }
    
    // Handle zero or negative values
    if ($seconds <= 0) {
        if ($config['mode'] === 'ago') {
            // Try to get timeAgo using L:: constant
            if (class_exists('L')) {
                try {
                    if (defined('L::timeAgo')) {
                        return constant('L::timeAgo');
                    }
                } catch (Exception $e) {
                    // Continue to fallback
                }
            }
            
            return 'ago';
        }
        return '0 ' . $getTimeLabel(0, 'timeSecond', 'timeSeconds');
    }
    
    // Calculate time units
    $days = (int)($seconds / 86400);
    $hours = (int)(($seconds % 86400) / 3600);
    $minutes = (int)(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;
    
    // Build parts array
    $parts = [];
    
    if ($days > 0) {
        $parts[] = $days . ' ' . $getTimeLabel($days, 'timeDay', 'timeDays');
    }
    
    if ($hours > 0) {
        $parts[] = $hours . ' ' . $getTimeLabel($hours, 'timeHour', 'timeHours');
    }
    
    if ($minutes > 0) {
        $parts[] = $minutes . ' ' . $getTimeLabel($minutes, 'timeMinute', 'timeMinutes');
    }
    
    if ($remainingSeconds > 0) {
        $parts[] = $remainingSeconds . ' ' . $getTimeLabel($remainingSeconds, 'timeSecond', 'timeSeconds');
    }
    
    // Handle edge case where all units are 0 (shouldn't happen with our logic, but safety)
    if (empty($parts)) {
        $parts[] = '0 ' . $getTimeLabel(0, 'timeSecond', 'timeSeconds');
    }
    
    // Apply short mode (only 2 largest units)
    if ($config['short'] && count($parts) > 2) {
        $parts = array_slice($parts, 0, 2);
    }
    
    // Join parts
    $result = implode(' ', $parts);
    
    // Add "ago" suffix for ago mode
    if ($config['mode'] === 'ago' && $config['showAgo']) {
        // Try to get timeAgo using L:: constant
        $timeAgo = 'ago';
        if (class_exists('L')) {
            try {
                if (defined('L::timeAgo')) {
                    $timeAgo = constant('L::timeAgo');
                }
            } catch (Exception $e) {
                // Continue to fallback
            }
        }
        
        // Different languages have different word orders
        // German: "vor 2 Stunden" (ago at beginning)
        // English: "2 hours ago" (ago at end)
        // French: "il y a 2 heures" (ago at beginning)
        // Turkish: "2 saat önce" (ago at end)
        
        if ($currentLang === 'de' || $currentLang === 'fr') {
            // German and French: "vor/il y a" + time
            $result = $timeAgo . ' ' . $result;
        } else {
            // English, Turkish, and others: time + "ago/önce"
            $result = $result . ' ' . $timeAgo;
        }
    }
    
    return $result;
}

/**
 * @param $file string realpath to file to include
 * @param $include bool include or return the path
 *
 * This function checks if there is a custom file at /custom/ at the given sub-path of $file and includes (or returns) this instead of the given generic file.
 * If there is a custom language file, it gets preferred.
 *
 * e.g.: $file = /www/content/footer.php
 * if $_SESSION["lang"] is "en" and there is /www/custom/content/footer.en.php it gets included.
 * if not, but /www/custom/content/footer.php is available, it gets included.
 * if this file is also not available, the original file located at /www/content/footer.php gets included.
 *
 */

function include_custom($file, $include = true) {

    $pathinfo = pathinfo($file);
    $selfpath = pathinfo(realpath(__DIR__."/../../index.php"));

    $custompath = str_replace($selfpath["dirname"], $selfpath["dirname"]."/custom/", $pathinfo["dirname"]);

    // Resolve the active language the same way L:: does (request → session → Accept-Language → cookie → fallback),
    // not just $_SESSION["lang"], which is only set after an explicit ?lang= switch. Otherwise the localized
    // template (e.g. home.en.php) is skipped while L:: already renders in that language, mixing languages on the page.
    $lang = isset($_SESSION["lang"]) ? $_SESSION["lang"] : null;
    if (class_exists("LanguageManager")) {
        $currentLang = LanguageManager::getInstance()->getCurrentLang();
        if (!empty($currentLang)) {
            $lang = $currentLang;
        }
    }

    if ($lang !== null && file_exists($custompath."/".$pathinfo["filename"].".".$lang.".".$pathinfo["extension"])) {

        $path = realpath($custompath."/".$pathinfo["filename"].".".$lang.".".$pathinfo["extension"]);

    } elseif (file_exists($custompath."/".$pathinfo["basename"])) {

        $path = realpath($custompath."/".$pathinfo["basename"]);

    } else {

        $path = $file;

    }

    if ($include) {

        include_once($path);

    } else {

        return $path;

    }


}

?>