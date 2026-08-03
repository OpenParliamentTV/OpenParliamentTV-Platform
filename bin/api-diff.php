<?php
/**
 * API regression harness.
 *
 * Fetches every URL in a corpus file, normalizes the volatile parts of the
 * response, and compares the result against committed fixtures. The refactoring
 * work has one hard invariant — external API responses stay byte-identical —
 * and this is what enforces it. Run it before and after every API-touching
 * commit, including pure renames.
 *
 * Usage:
 *   php bin/api-diff.php --update            # (re)record fixtures from the current code
 *   php bin/api-diff.php                     # compare current responses against fixtures
 *   php bin/api-diff.php --selftest          # fetch everything twice, report unstable fields
 *   php bin/api-diff.php --filter=iiif       # only entries whose path contains "iiif"
 *   php bin/api-diff.php --verbose           # show the full diff for each mismatch
 *
 * Options:
 *   --base=URL       Base URL (default: $config["dir"]["root"])
 *   --corpus=PATH    Corpus file (default: tests/fixtures/api-corpus.txt)
 *   --out=PATH       Fixture directory (default: tests/fixtures/api)
 *   --session=SID    PHPSESSID to send for entries flagged @admin
 *
 * Corpus format — one entry per line, `#` starts a comment:
 *   /api/v1/person/Q1909731              a plain GET
 *   @admin /api/v1/statistics/general    sent with the --session cookie
 *
 * Exit code is 0 when every entry matches, 1 otherwise, so it can gate CI.
 */

require_once(__DIR__ . "/../bootstrap-cli.php");

$opts = getopt("", ["update", "selftest", "verbose", "base::", "corpus::", "out::", "session::", "filter::"]);

$doUpdate   = isset($opts["update"]);
$doSelftest = isset($opts["selftest"]);
$verbose    = isset($opts["verbose"]);
$baseUrl    = rtrim($opts["base"] ?? $config["dir"]["root"], "/");
$corpusPath = $opts["corpus"] ?? __DIR__ . "/../tests/fixtures/api-corpus.txt";
$fixtureDir = rtrim($opts["out"] ?? __DIR__ . "/../tests/fixtures/api", "/");
$sessionId  = $opts["session"] ?? "";
$filter     = $opts["filter"] ?? "";

if (!is_file($corpusPath)) {
    fwrite(STDERR, "Corpus file not found: {$corpusPath}\n");
    exit(1);
}

/**
 * JSON keys whose values change between two identical requests. Matched on the
 * key name at any depth; the value is replaced with a stable placeholder so a
 * real regression still shows up while clock//cluster noise does not.
 */
const API_DIFF_VOLATILE_KEYS = [
    'lastBuildDate', 'generatedAt', 'timestamp', 'startTime', 'endTime',
    'lastActivityTime', 'took', 'elapsed', 'duration', 'durationMs',
    'cacheAge', 'cachedAt', 'requestTime', 'serverTime',
    // Live gauges in action=system&itemType=status: disk, heap and CPU move
    // between any two requests. The structural shape around them (which
    // sections exist, the reported paths) stays pinned, which is what a
    // refactor could actually break.
    'freeBytes', 'usedBytes', 'usedPercent', 'availableBytes', 'uptimeSeconds',
    'fsAvailableBytes', 'heapUsedBytes', 'heapUsedPercent', 'cpuPercent',
];

/**
 * Regexes applied to raw (non-JSON) bodies — VTT, XML exports, HTML.
 * Each pair is [pattern, replacement].
 */
const API_DIFF_VOLATILE_PATTERNS = [
    // RFC-822 / ISO-8601 timestamps emitted at render time
    ['/<lastBuildDate>[^<]*<\/lastBuildDate>/', '<lastBuildDate>NORMALIZED</lastBuildDate>'],
    ['/<updated>[^<]*<\/updated>/', '<updated>NORMALIZED</updated>'],
    // Akoma Ntoso / TEI carry a generation date in their metadata blocks
    ['/(<FRBRdate[^>]*\bdate=")[^"]*(")/', '$1NORMALIZED$2'],
    ['/(<date[^>]*\bwhen=")[^"]*(")/', '$1NORMALIZED$2'],
];

/** Response headers worth pinning. Everything else is transport noise. */
const API_DIFF_HEADERS = ['content-type', 'x-robots-tag', 'location'];

/**
 * Recursively replace volatile values so two runs of unchanged code compare equal.
 */
function apiDiffNormalizeJson($node) {
    if (is_array($node)) {
        $out = [];
        foreach ($node as $k => $v) {
            $out[$k] = (is_string($k) && in_array($k, API_DIFF_VOLATILE_KEYS, true) && !is_array($v))
                ? '<<NORMALIZED>>'
                : apiDiffNormalizeJson($v);
        }
        return $out;
    }
    return $node;
}

/**
 * Turn a raw response into the canonical text stored in a fixture: the status
 * line, the pinned headers, then the normalized body (pretty-printed when JSON,
 * so a one-field change produces a one-line diff).
 */
function apiDiffCanonicalize($status, array $headers, $body) {
    $out = "HTTP {$status}\n";
    foreach (API_DIFF_HEADERS as $h) {
        if (isset($headers[$h])) {
            $out .= $h . ": " . $headers[$h] . "\n";
        }
    }
    $out .= "\n";

    $decoded = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) {
        $out .= json_encode(
            apiDiffNormalizeJson($decoded),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return $out . "\n";
    }

    // Binary payloads (images) are pinned by size + hash, not content, so the
    // fixture stays readable and small.
    if (isset($headers['content-type']) && strpos($headers['content-type'], 'image/') === 0) {
        return $out . "<<BINARY " . strlen($body) . " bytes sha256=" . hash('sha256', $body) . ">>\n";
    }

    foreach (API_DIFF_VOLATILE_PATTERNS as [$pattern, $replacement]) {
        $body = preg_replace($pattern, $replacement, $body);
    }
    return $out . $body;
}

/**
 * Fetch one URL, retrying transient infrastructure failures.
 *
 * A local Apache/PHP-FPM can saturate when the whole corpus is swept back to
 * back, producing connection resets and 5xx that have nothing to do with the
 * API's content. Those are retried, and if they persist the caller reports them
 * as INFRA rather than as a content regression — a harness that cannot tell
 * "the server hiccuped" from "the response changed" is one people stop trusting.
 *
 * @return array [status, headers(lowercased), body, isInfraFailure]
 */
function apiDiffFetchWithRetry($url, $cookie = "", $attempts = 3) {
    for ($i = 1; $i <= $attempts; $i++) {
        [$status, $headers, $body] = apiDiffFetch($url, $cookie);
        $infra = ($status === 0 || $status >= 500);
        if (!$infra) {
            return [$status, $headers, $body, false];
        }
        if ($i < $attempts) {
            usleep(250000 * $i); // back off a little before retrying
        }
    }
    return [$status, $headers, $body, true];
}

/** Fetch one URL; returns [status, headers(lowercased), body]. */
function apiDiffFetch($url, $cookie = "") {
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,   // local dev uses a self-signed cert
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false,   // redirects are part of what we pin
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_COOKIE         => $cookie,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    $body   = curl_exec($ch);
    $errNo  = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo !== 0) {
        return [0, [], "CURL ERROR {$errNo}"];
    }
    return [$status, $headers, (string) $body];
}

/**
 * Stable, filesystem-safe fixture name for a corpus entry.
 *
 * The admin flag is part of the key: the same path requested with and without a
 * session is two different assertions (403 vs 200), and they must not collide.
 */
function apiDiffSlug($path, $admin = false) {
    $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $path), '-');
    $slug = strtolower(substr($slug, 0, 80));
    $key  = ($admin ? 'admin ' : '') . $path;
    // Suffix with a hash so entries that differ only past the truncation point
    // (or only in punctuation, or only in the admin flag) never collide.
    return ($admin ? 'admin-' : '') . $slug . '-' . substr(sha1($key), 0, 8);
}

// --- Parse the corpus -------------------------------------------------------
$entries = [];
foreach (file($corpusPath, FILE_IGNORE_NEW_LINES) as $lineNo => $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $admin = false;
    if (strpos($line, '@admin') === 0) {
        $admin = true;
        $line = trim(substr($line, 6));
    }
    if ($filter !== '' && strpos($line, $filter) === false) {
        continue;
    }
    $entries[] = ['path' => $line, 'admin' => $admin, 'slug' => apiDiffSlug($line, $admin)];
}

if (!$entries) {
    fwrite(STDERR, "No corpus entries matched.\n");
    exit(1);
}

if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0775, true) && !is_dir($fixtureDir)) {
    fwrite(STDERR, "Could not create fixture directory: {$fixtureDir}\n");
    exit(1);
}

if (($doUpdate || !$doSelftest) && $sessionId === '') {
    $hasAdmin = false;
    foreach ($entries as $e) { $hasAdmin = $hasAdmin || $e['admin']; }
    if ($hasAdmin) {
        fwrite(STDERR, "warning: corpus has @admin entries but no --session was given; "
                     . "those will record their anonymous (403) response.\n");
    }
}

// --- Run --------------------------------------------------------------------
$pass = $fail = $infra = 0;
$failed = [];
$infraFailed = [];

foreach ($entries as $e) {
    $url    = $baseUrl . $e['path'];
    $cookie = $e['admin'] && $sessionId !== '' ? "PHPSESSID={$sessionId}" : "";

    [$status, $headers, $body, $isInfra] = apiDiffFetchWithRetry($url, $cookie);

    if ($isInfra) {
        // Server-side failure that survived retries — not a content regression.
        $infra++;
        $infraFailed[] = $e['path'];
        printf("  INFRA    %s  (HTTP %d after retries — re-run before believing this)\n", $e['path'], $status);
        continue;
    }

    $current = apiDiffCanonicalize($status, $headers, $body);

    if ($doSelftest) {
        // Second fetch of the same URL: anything that differs is volatile and
        // needs a normalization rule before the fixture can be trusted.
        [$s2, $h2, $b2] = apiDiffFetch($url, $cookie);
        $second = apiDiffCanonicalize($s2, $h2, $b2);
        if ($current === $second) {
            $pass++;
            printf("  stable   %s\n", $e['path']);
        } else {
            $fail++;
            $failed[] = $e['path'];
            printf("  UNSTABLE %s\n", $e['path']);
            if ($verbose) {
                foreach (apiDiffTextDiff($current, $second) as $d) { echo "      $d\n"; }
            }
        }
        continue;
    }

    $fixture = $fixtureDir . '/' . $e['slug'] . '.txt';

    if ($doUpdate) {
        file_put_contents($fixture, $current);
        $pass++;
        printf("  recorded %s  (HTTP %d, %d bytes)\n", $e['path'], $status, strlen($current));
        continue;
    }

    if (!is_file($fixture)) {
        $fail++;
        $failed[] = $e['path'];
        printf("  MISSING  %s  (no fixture — run --update)\n", $e['path']);
        continue;
    }

    $expected = file_get_contents($fixture);
    if ($expected === $current) {
        $pass++;
        printf("  ok       %s\n", $e['path']);
    } else {
        $fail++;
        $failed[] = $e['path'];
        printf("  FAIL     %s\n", $e['path']);
        $diff = apiDiffTextDiff($expected, $current);
        foreach (array_slice($diff, 0, $verbose ? 200 : 12) as $d) { echo "      $d\n"; }
        if (!$verbose && count($diff) > 12) {
            printf("      … %d more differing lines (--verbose for all)\n", count($diff) - 12);
        }
    }
}

/** Minimal line diff — enough to point at the changed field. */
function apiDiffTextDiff($expected, $actual) {
    $a = explode("\n", $expected);
    $b = explode("\n", $actual);
    $out = [];
    $max = max(count($a), count($b));
    for ($i = 0; $i < $max; $i++) {
        $la = $a[$i] ?? '<missing>';
        $lb = $b[$i] ?? '<missing>';
        if ($la !== $lb) {
            $out[] = sprintf("line %d:", $i + 1);
            $out[] = "  - " . substr($la, 0, 200);
            $out[] = "  + " . substr($lb, 0, 200);
        }
    }
    return $out;
}

echo "\n";
if ($doSelftest) {
    printf("selftest: %d stable, %d unstable\n", $pass, $fail);
} elseif ($doUpdate) {
    printf("recorded %d fixture(s) into %s\n", $pass, $fixtureDir);
} else {
    printf("%d passed, %d failed, %d infra (of %d)\n", $pass, $fail, $infra, count($entries));
}
if ($failed) {
    echo "\nContent mismatches:\n";
    foreach ($failed as $f) { echo "  {$f}\n"; }
}
if ($infraFailed) {
    echo "\nInfrastructure failures (server did not answer — re-run):\n";
    foreach ($infraFailed as $f) { echo "  {$f}\n"; }
}

// Infra failures still fail the run: they mean the sweep was inconclusive, not
// that the API is fine.
exit((($fail > 0 && !$doUpdate) || $infra > 0) ? 1 : 0);
