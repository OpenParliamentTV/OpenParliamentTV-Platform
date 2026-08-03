<?php
/**
 * Manifest-driven rename executor (development tool).
 *
 * Reads docs/rename-manifest.json and applies one phase's renames to the core
 * tree: `git mv` for files and directories, then rewrites every reference to
 * them across the repository. Function renames are word-boundary token
 * replacements.
 *
 * Doing this by hand across ~80 files is how a rename silently drops a
 * reference; driving it from the manifest keeps the rename and its call sites
 * in lockstep, and makes the resulting diff correspond 1:1 to manifest entries.
 *
 * Usage:
 *   php bin/apply-renames.php --phase=4.3a            # dry run: report only
 *   php bin/apply-renames.php --phase=4.3a --apply    # do it
 *   php bin/apply-renames.php --phase=4.4a --apply --functions
 *
 * Options:
 *   --phase=X     Manifest phase to apply (required)
 *   --apply       Actually write; without it nothing is modified
 *   --functions   Apply the "functions" section instead of "files"
 *   --manifest=P  Manifest path (default docs/rename-manifest.json)
 */

require_once(__DIR__ . "/../bootstrap-cli.php");

$opts = getopt("", ["phase:", "apply", "functions", "manifest::"]);

if (!isset($opts["phase"])) {
    fwrite(STDERR, "--phase is required (e.g. --phase=4.3a)\n");
    exit(1);
}
$phase        = $opts["phase"];
$apply        = isset($opts["apply"]);
$doFunctions  = isset($opts["functions"]);
$manifestPath = $opts["manifest"] ?? __DIR__ . "/../docs/rename-manifest.json";
$root         = realpath(__DIR__ . "/..");

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "Could not parse manifest: {$manifestPath}\n");
    exit(1);
}

/**
 * Every file whose contents may reference a renamed path or function.
 * Excludes the manifest itself, vendor code, recorded fixtures and runtime state.
 */
function renameScanFiles($root) {
    $out = [];
    // docs/ holds the rename manifest itself: rewriting it would destroy the
    // record this tool is driven by, and would corrupt --check for everyone else.
    $skipDirs = ['/.git/', '/vendor/', '/var/', '/_planning/', '/node_modules/',
                 '/tests/fixtures/', '/modules/pdf-viewer/', '/docs/'];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $path = $f->getPathname();
        $rel  = str_replace($root, '', $path);
        foreach ($skipDirs as $skip) {
            if (strpos($rel, $skip) !== false) {
                continue 2;
            }
        }
        if (preg_match('/\.(php|js|css|json|md|yaml|yml|htaccess)$/i', $path)
            || basename($path) === '.htaccess') {
            $out[] = $path;
        }
    }
    return $out;
}

$section = $doFunctions ? 'functions' : 'files';
$entries = array_values(array_filter(
    $manifest[$section] ?? [],
    function ($e) use ($phase) { return ($e['phase'] ?? '') === $phase; }
));

if (!$entries) {
    fwrite(STDERR, "No '{$section}' entries for phase {$phase}\n");
    exit(1);
}

$edits = 0;
$moves = 0;

if ($doFunctions) {
    // ---- function renames: word-boundary token replacement -----------------
    $scan = renameScanFiles($root);
    printf("scanning %d files\n\n", count($scan));
    foreach ($scan as $path) {
        $src = @file_get_contents($path);
        if ($src === false) {
            continue;
        }
        $new = $src;
        foreach ($entries as $e) {
            $new = preg_replace('/\b' . preg_quote($e['old'], '/') . '\b/', $e['new'], $new);
        }
        if ($new !== $src) {
            $edits++;
            printf("  %s edit %s\n", $apply ? '[x]' : '[ ]', str_replace($root . '/', '', $path));
            if ($apply) {
                file_put_contents($path, $new);
            }
        }
    }
} else {
    // ---- file/directory renames --------------------------------------------
    // Longest paths first, so nested entries move before their parent directory.
    usort($entries, function ($a, $b) { return strlen($b['old']) - strlen($a['old']); });

    foreach ($entries as $e) {
        $from = $root . '/' . $e['old'];
        $to   = $root . '/' . $e['new'];
        if (!file_exists($from)) {
            printf("  --  skip %s (not present)\n", $e['old']);
            continue;
        }
        $moves++;
        printf("  %s move %s -> %s\n", $apply ? '[x]' : '[ ]', $e['old'], $e['new']);
        if ($apply) {
            $parent = dirname($to);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            // git mv keeps history; fall back to rename() for untracked files.
            exec('git -C ' . escapeshellarg($root) . ' mv '
                 . escapeshellarg($e['old']) . ' ' . escapeshellarg($e['new']) . ' 2>&1',
                 $o, $rc);
            if ($rc !== 0) {
                rename($from, $to);
            }
        }
    }

    // Rescan AFTER the moves: the pre-move list still holds the old paths, and
    // writing to a path that no longer exists recreates it as an empty file.
    $scan = renameScanFiles($root);

    // Rewrite references.
    //
    // Never replace on a bare basename unless that basename is distinctive.
    // `functions.php` is the basename of eight different modules, so a
    // basename-level replace would rewrite every unrelated module's require and
    // silently break the build. Instead each rename produces a list of
    // replacement pairs ordered most-specific first, and a basename pair is only
    // added when the name cannot collide.
    $replacements = [];
    foreach ($entries as $e) {
        $oldBase = basename($e['old']);
        $newBase = basename($e['new']);
        $pairs   = [];

        // 1. The full repo-relative path.
        $pairs[] = [$e['old'], $e['new']];

        // 2. The last two path segments — how most relative requires are written
        //    ("mail/mailer.php", "statistics-index/indexer.php").
        $oldTail = implode('/', array_slice(explode('/', $e['old']), -2));
        $newTail = implode('/', array_slice(explode('/', $e['new']), -2));
        if ($oldTail !== $e['old'] && $oldTail !== $newTail) {
            $pairs[] = [$oldTail, $newTail];
        }

        // 3. The bare basename — the dangerous one, allowed only when it cannot
        //    match anything but a filename.
        //
        //    A DIRECTORY basename is never eligible. Names like "electoralPeriod"
        //    or "agendaItem" are also API itemType values, query parameters and
        //    payload keys — all frozen. Replacing one as a bare token rewrites
        //    the frozen API surface (this really happened: an .htaccess rewrite
        //    started emitting `electoral-period=$2`, and the whole API 5xx'd).
        //
        //    A file basename qualifies only if it carries an extension, is not a
        //    name many modules share, and is unique among the entries applied.
        $isDirectory = is_dir($to) || (!file_exists($to) && strpos($oldBase, '.') === false);
        $ambiguous = ['functions.php', 'config.php', 'index.php', 'page.php',
                      'bootstrap.php', 'cache.php', 'templates', 'client'];
        $sameBaseElsewhere = 0;
        foreach ($entries as $other) {
            if (basename($other['old']) === $oldBase) {
                $sameBaseElsewhere++;
            }
        }
        if ($oldBase !== $newBase
            && !$isDirectory
            && strpos($oldBase, '.') !== false
            && !in_array($oldBase, $ambiguous, true)
            && $sameBaseElsewhere === 1) {
            $pairs[] = [$oldBase, $newBase];
        }

        $replacements[] = $pairs;
    }

    foreach ($scan as $path) {
        $src = @file_get_contents($path);
        if ($src === false) {
            continue;
        }
        $new = $src;
        foreach ($replacements as $pairs) {
            foreach ($pairs as [$from, $to]) {
                $new = str_replace($from, $to, $new);
            }
        }
        if ($new !== $src) {
            $edits++;
            printf("  %s edit %s\n", $apply ? '[x]' : '[ ]', str_replace($root . '/', '', $path));
            if ($apply) {
                file_put_contents($path, $new);
            }
        }
    }
}

printf("\nphase %s (%s): %d move(s), %d file(s) with reference edits%s\n",
    $phase, $section, $moves, $edits, $apply ? '' : '  [DRY RUN — pass --apply]');
