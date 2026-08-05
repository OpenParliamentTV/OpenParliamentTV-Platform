<?php
require_once(__DIR__ . "/../bootstrap-cli.php");

/**
 * Naming convention checker.
 *
 * CONTRIBUTING.md states one rule set for file, directory and function names.
 * Documentation alone does not hold — this makes the rules executable, so a
 * violation fails a command rather than waiting to be noticed in review.
 *
 *   php bin/check-naming.php            # report violations
 *   php bin/check-naming.php --verbose  # also list what was scanned
 *
 * Exit codes: 0 clean, 1 violations found.
 *
 * Deliberately narrow: it checks the rules that are mechanically decidable and
 * stays silent on the rest. A checker that cries wolf gets switched off.
 */

$opts = getopt("", ["verbose", "help"]);

if (isset($opts["help"])) {
    fwrite(STDOUT, "Usage: php bin/check-naming.php [--verbose]\n");
    exit(0);
}

$verbose = isset($opts["verbose"]);
$root = realpath(__DIR__ . "/..");

/**
 * Paths never scanned: third-party code, runtime state, and the instance
 * customization trees (which mirror core names and are not ours to rename).
 */
const NAMING_SKIP_DIRS = [
    "vendor", ".git", ".claude", "var", "node_modules", "custom", "_planning",
    "content/client/js/vendor", "content/client/css/vendor",
    "modules/pdf-viewer",
];

/**
 * Directories whose file names are governed by a different, documented rule and
 * are therefore not kebab-case-checked:
 *
 *  - migrations/  — migrations/README.md mandates NNNN_snake_case_description.sql,
 *                   and the zero-padded numeric prefix is load-bearing for order.
 *  - db/          — dump file names mirror the database names, which are frozen.
 */
const NAMING_SQL_NAME_DIRS = ["migrations", "db"];

/**
 * Files exempt from the file-name rules — bundled third-party code and the
 * frozen names listed in CONTRIBUTING.md's "Frozen surface".
 */
const NAMING_EXEMPT_FILES = [
    "modules/utilities/safemysql.class.php",
    "modules/i18n/i18n.php",
];

/**
 * File-name patterns that are exempt: frozen config names, the uppercase doc
 * files every repo carries, and minified third-party assets.
 */
const NAMING_EXEMPT_PATTERNS = [
    '/^config(-[A-Za-z0-9]+)?\.php$/',   // frozen: config.php, config.sample.php, config-DE.php
    '/^config\.sample\.php$/',
    '/^[A-Z][A-Za-z_.]*\.md$/',          // README.md, CONTRIBUTING.md, CLAUDE.local.md
    '/\.min\.(js|css)$/',                // vendored minified assets
];

/**
 * Pre-existing file-name violations. `modules/utilities/functions.php` is the
 * one file that still carries the banned name; it has 42 require sites across
 * 40 files, and CONTRIBUTING.md requires a rename to ship as its own pure
 * commit, so it cannot ride along with feature work.
 */
const NAMING_KNOWN_DEBT_FILES = [
    "modules/utilities/functions.php",
];

/**
 * Pre-existing violations, recorded so the checker can be green today and block
 * anything *new*. Each entry is "<path>::<function>". Shrinking this list is
 * ordinary cleanup; growing it needs a reason.
 */
const NAMING_KNOWN_DEBT = [
    "bin/additional-data.php::_ads_get_progress_data",
    "bin/additional-data.php::_ads_save_progress_data",
    "bin/additional-data.php::_ads_resolve_parliament",
    "bin/additional-data.php::_ads_process_official_documents",
    "bin/additional-data.php::_ads_record_startup_error",
    "api/v1/modules/external-data.php::_externalData_resolve_parliament",
    "api/v1/modules/external-data.php::_externalData_build_ads_url",
    "api/v1/modules/external-data.php::_optv_get_string_from_data",
];

/**
 * The module-prefix convention (CONTRIBUTING.md's table) is deliberately NOT
 * checked here. It has too many legitimate shapes to encode without a large
 * allowlist that would drift out of date and flag correct code — and a checker
 * that is wrong more often than the code gets switched off. Prefixes stay a
 * review matter; this file covers only what is mechanically decidable.
 */

$violations = [];
$scanned = 0;

/** Record one violation. */
function namingReport(&$violations, $path, $rule, $detail) {
    $violations[] = ["path" => $path, "rule" => $rule, "detail" => $detail];
}

/** Whether a repo-relative path sits inside a skipped directory. */
function namingIsSkipped($relative) {
    foreach (NAMING_SKIP_DIRS as $skip) {
        if ($relative === $skip || strpos($relative, $skip . "/") === 0) {
            return true;
        }
    }
    return false;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($current) use ($root) {
            $relative = substr($current->getPathname(), strlen($root) + 1);
            return !namingIsSkipped($relative);
        }
    )
);

foreach ($iterator as $file) {
    $relative = substr($file->getPathname(), strlen($root) + 1);
    $name = $file->getFilename();
    $extension = strtolower($file->getExtension());

    if (!in_array($extension, ["php", "js", "css", "json", "sql", "md"], true)) {
        continue;
    }
    if (in_array($relative, NAMING_EXEMPT_FILES, true)) {
        continue;
    }
    foreach (NAMING_EXEMPT_PATTERNS as $pattern) {
        if (preg_match($pattern, $name)) {
            continue 2;
        }
    }

    $scanned++;
    if ($verbose) {
        fwrite(STDOUT, "  scan  $relative\n");
    }

    // --- file names ---------------------------------------------------------

    if ($name === "functions.php" && !in_array($relative, NAMING_KNOWN_DEBT_FILES, true)) {
        namingReport($violations, $relative, "no-functions-php",
            "name the file after the capability it provides (mailer.php, git-sync.php, image-cache.php)");
    }

    $base = substr($name, 0, strrpos($name, ".") ?: strlen($name));
    // Strip a trailing .{lang} qualifier, which is a permitted second dot.
    $base = preg_replace('/\.[a-z]{2}$/', '', $base);

    if (strpos($base, ".") !== false) {
        namingReport($violations, $relative, "no-dots-in-base-name",
            "'$base' — only the extension and a .{lang}.php override suffix may add a dot");
    }

    if ($base !== "" && $base !== strtolower($base) && !preg_match('/^[a-z]+[A-Z]/', $base)) {
        namingReport($violations, $relative, "lowercase-file-names", "'$name' is not all-lowercase");
    }

    // Migration and dump names follow their own documented scheme.
    $sqlNamed = false;
    foreach (NAMING_SQL_NAME_DIRS as $dir) {
        if (strpos($relative, $dir . "/") === 0) {
            $sqlNamed = true;
            break;
        }
    }

    if (!$sqlNamed && preg_match('/[_ ]/', $base)) {
        namingReport($violations, $relative, "kebab-case-file-names",
            "'$base' uses underscores or spaces; use kebab-case");
    }

    // --- PHP function names -------------------------------------------------

    if ($extension !== "php") {
        continue;
    }

    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        continue;
    }

    // Top-level function declarations only; methods are indented inside classes.
    if (!preg_match_all('/^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $source, $matches)) {
        continue;
    }

    foreach ($matches[1] as $function) {
        if (in_array($relative . "::" . $function, NAMING_KNOWN_DEBT, true)) {
            continue;
        }

        if (strpos($function, "_") !== false) {
            namingReport($violations, $relative, "no-snake-case",
                "function $function() — PHP identifiers are camelCase");
        }
    }
}

// --- output -----------------------------------------------------------------

if (empty($violations)) {
    fwrite(STDOUT, "naming: clean ($scanned files checked)\n");
    exit(0);
}

$byRule = [];
foreach ($violations as $violation) {
    $byRule[$violation["rule"]][] = $violation;
}

fwrite(STDOUT, "\nnaming: " . count($violations) . " violation(s) in $scanned files\n");
foreach ($byRule as $rule => $entries) {
    fwrite(STDOUT, "\n  [$rule]\n");
    foreach ($entries as $entry) {
        fwrite(STDOUT, "    " . $entry["path"] . "\n        " . $entry["detail"] . "\n");
    }
}
fwrite(STDOUT, "\nSee CONTRIBUTING.md for the rules.\n");

exit(1);
