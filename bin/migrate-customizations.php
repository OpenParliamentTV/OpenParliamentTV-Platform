<?php
/**
 * Replay the core rename manifest against an instance's custom/ tree.
 *
 * Instances keep their overrides in a separate custom/ tree that is synced into
 * the platform. An override only applies when its path matches the core file it
 * overrides, so renaming a core file silently detaches every override of it —
 * no error, no warning, the instance just quietly starts serving core content.
 * There is deliberately no runtime fallback to old names, which makes running
 * this a REQUIRED upgrade step.
 *
 * Usage:
 *   php bin/migrate-customizations.php                 # dry-run report (default)
 *   php bin/migrate-customizations.php --apply         # rename + rewrite
 *   php bin/migrate-customizations.php --check         # fail if any old name survives
 *   php bin/migrate-customizations.php --check --core  # same, against the core tree
 *
 * Options:
 *   --path=DIR     Tree to operate on (default: custom/)
 *   --manifest=P   Manifest path (default: docs/rename-manifest.json)
 *
 * --check exits non-zero on any hit. It greps *every* file extension, because a
 * dynamic include or a string-built call is exactly what --apply cannot rewrite
 * and exactly what would otherwise fail silently in production.
 */

require_once(__DIR__ . "/../bootstrap-cli.php");

$opts = getopt("", ["apply", "check", "core", "path::", "manifest::"]);

$doApply      = isset($opts["apply"]);
$doCheck      = isset($opts["check"]);
$coreMode     = isset($opts["core"]);
$root         = realpath(__DIR__ . "/..");
$manifestPath = $opts["manifest"] ?? $root . "/docs/rename-manifest.json";
$target       = $opts["path"] ?? ($coreMode ? $root : $root . "/custom");

$manifest = json_decode(@file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "Could not read manifest: {$manifestPath}\n");
    exit(1);
}
if (!is_dir($target)) {
    echo "No tree at {$target} — nothing to migrate.\n";
    exit(0);
}

$fileRenames = $manifest["files"] ?? [];
$fnRenames   = $manifest["functions"] ?? [];

/**
 * Walk a tree, skipping things that are not instance content.
 *
 * In --check mode every extension is walked (see the note above); otherwise only
 * text files that could carry a path or a call.
 */
function customFiles($dir, $allExtensions = false) {
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    $skip = ['/.git/', '/vendor/', '/var/', '/node_modules/', '/_planning/',
             '/docs/', '/tests/fixtures/', '/modules/pdf-viewer/'];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $p = $f->getPathname();
        foreach ($skip as $s) {
            if (strpos($p, $s) !== false) {
                continue 2;
            }
        }
        if ($allExtensions || preg_match('/\.(php|js|css|json|md|html?|txt)$/i', $p)) {
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * Instance overrides may carry a language suffix: a core `search-examples.php`
 * can be overridden by `search.examples.php`, `search.examples.de.php`, and so
 * on. Renaming the core file has to move every one of those variants.
 */
function langVariants($path) {
    $dir  = dirname($path);
    $base = basename($path);
    if (substr($base, -4) !== '.php') {
        return [$base];
    }
    $stem = substr($base, 0, -4);
    $out  = [$base];
    foreach (glob($dir . '/' . $stem . '.*.php') ?: [] as $hit) {
        $out[] = basename($hit);
    }
    return $out;
}

// ---------------------------------------------------------------- check mode
if ($doCheck) {
    $hits = 0;
    $files = customFiles($target, true);

    // A path is only reported when the file it names is genuinely gone, so a
    // basename that legitimately still exists elsewhere does not raise a
    // false alarm.
    foreach ($files as $p) {
        $rel = str_replace($root . '/', '', $p);

        foreach ($fileRenames as $r) {
            $oldBase = basename($r['old']);
            $newBase = basename($r['new']);
            if ($oldBase === $newBase) {
                continue;
            }
            // A custom file still sitting at the old name.
            if (basename($p) === $oldBase
                || preg_match('/^' . preg_quote(substr($oldBase, 0, -4), '/') . '\.[a-z]{2}\.php$/', basename($p))) {
                printf("  STALE FILE  %s  (core moved to %s)\n", $rel, $r['new']);
                $hits++;
            }
            // A reference to the old path inside a file.
            $src = @file_get_contents($p);
            if ($src !== false && strpos($src, $r['old']) !== false) {
                printf("  STALE REF   %s  -> %s\n", $rel, $r['old']);
                $hits++;
            }
        }

        $src = @file_get_contents($p);
        if ($src === false) {
            continue;
        }
        foreach ($fnRenames as $r) {
            if (preg_match('/\b' . preg_quote($r['old'], '/') . '\b/', $src)) {
                printf("  STALE CALL  %s  -> %s() is now %s()\n", $rel, $r['old'], $r['new']);
                $hits++;
            }
        }
    }

    echo "\n";
    if ($hits === 0) {
        echo "check passed: no pre-rename names found in " . str_replace($root . '/', '', $target) . "\n";
        exit(0);
    }
    printf("check FAILED: %d stale reference(s). Run --apply, then fix any dynamic\n"
         . "include or string-built call by hand — those cannot be rewritten automatically.\n", $hits);
    exit(1);
}

// ------------------------------------------------------- dry-run / apply mode
$renamed = 0;
$edited  = 0;

// 1. Move override files whose core counterpart was renamed, including every
//    .{lang}.php variant.
foreach ($fileRenames as $r) {
    // Overrides mirror the core path under custom/: content/... -> custom/content/...
    $oldRel = preg_replace('#^content/#', '', $r['old']);
    $newRel = preg_replace('#^content/#', '', $r['new']);
    if ($oldRel === $r['old']) {
        continue; // not an overridable content path
    }

    $oldDir = $target . '/content/' . dirname($oldRel);
    if (!is_dir($oldDir)) {
        continue;
    }
    foreach (langVariants($target . '/content/' . $oldRel) as $variant) {
        $from = $oldDir . '/' . $variant;
        if (!is_file($from)) {
            continue;
        }
        // Carry the language suffix across to the new base name.
        $suffix = substr($variant, strlen(basename($oldRel)) - 4);
        $suffix = ($suffix === '.php' || $suffix === '') ? '.php' : $suffix;
        $newBase = substr(basename($newRel), 0, -4) . $suffix;
        $to = $target . '/content/' . dirname($newRel) . '/' . $newBase;

        printf("  %s rename %s -> %s\n", $doApply ? '[x]' : '[ ]',
            str_replace($target . '/', '', $from), str_replace($target . '/', '', $to));
        $renamed++;
        if ($doApply) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0775, true);
            }
            rename($from, $to);
        }
    }
}

// 2. Rewrite renamed paths and renamed function calls inside the tree.
foreach (customFiles($target) as $p) {
    $src = @file_get_contents($p);
    if ($src === false) {
        continue;
    }
    $new = $src;
    foreach ($fileRenames as $r) {
        $new = str_replace($r['old'], $r['new'], $new);
    }
    foreach ($fnRenames as $r) {
        $new = preg_replace('/\b' . preg_quote($r['old'], '/') . '\b/', $r['new'], $new);
    }
    if ($new !== $src) {
        printf("  %s rewrite %s\n", $doApply ? '[x]' : '[ ]', str_replace($target . '/', '', $p));
        $edited++;
        if ($doApply) {
            file_put_contents($p, $new);
        }
    }
}

printf("\n%d file(s) renamed, %d rewritten%s\n",
    $renamed, $edited, $doApply ? '' : '  [DRY RUN — pass --apply]');
if ($doApply) {
    echo "Now run --check to catch anything that could not be rewritten automatically.\n";
}
