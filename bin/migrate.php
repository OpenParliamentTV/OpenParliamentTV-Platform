<?php
require_once(__DIR__ . "/../bootstrap-cli.php");
require_once(__DIR__ . "/../modules/database/migrations.php");

/**
 * Schema migration runner (CLI front end).
 *
 * The engine lives in modules/database/migrations.php and is shared with the
 * panel under /manage/settings, so both report and apply exactly the same
 * thing. This file is argument parsing and output.
 *
 * Default is a dry run; --apply executes.
 *
 *   php bin/migrate.php                          # report what is pending
 *   php bin/migrate.php --apply                  # apply it
 *   php bin/migrate.php --apply --scope=platform # platform database only
 *   php bin/migrate.php --apply --parliament=DE  # one parliament database
 *   php bin/migrate.php --status                 # what has been applied, per database
 *
 * Exit codes: 0 success (or nothing pending), 1 a migration failed, 2 bad usage.
 */

$opts = getopt("", ["apply", "status", "scope:", "parliament:", "help"]);

if (isset($opts["help"])) {
    fwrite(STDOUT, "Usage: php bin/migrate.php [--apply] [--status] [--scope=platform|parliament|all] [--parliament=XX]\n");
    exit(0);
}

$apply          = isset($opts["apply"]);
$statusOnly     = isset($opts["status"]);
$scope          = $opts["scope"] ?? "all";
$onlyParliament = $opts["parliament"] ?? null;

if (!in_array($scope, ["platform", "parliament", "all"], true)) {
    fwrite(STDERR, "error: --scope must be platform, parliament or all\n");
    exit(2);
}
if ($onlyParliament !== null) {
    $scope = "parliament";
}

if (empty(optvDbMigrationTargets($scope, $onlyParliament))) {
    fwrite(STDERR, "error: no matching database (unknown --parliament?)\n");
    exit(2);
}

// ---------------------------------------------------------------- report modes
if ($statusOnly || !$apply) {
    $status = optvDbMigrationStatus($scope, $onlyParliament);
    $failed = 0;

    foreach ($status as $entry) {
        fwrite(STDOUT, "\n" . $entry["label"] . " (" . $entry["db"] . ")\n");

        if ($entry["error"] !== null) {
            fwrite(STDERR, "  unreachable: " . $entry["error"] . "\n");
            $failed++;
            continue;
        }
        if (empty($entry["applied"]) && empty($entry["pending"])) {
            fwrite(STDOUT, "  (no migrations for this database)\n");
            continue;
        }
        if ($statusOnly) {
            foreach ($entry["applied"] as $a) {
                fwrite(STDOUT, sprintf("  applied  %-44s %s\n", $a["file"], $a["appliedAt"]));
            }
        } elseif (empty($entry["pending"])) {
            fwrite(STDOUT, "  up to date (" . count($entry["applied"]) . " migration(s) already applied)\n");
        }
        foreach ($entry["pending"] as $file) {
            fwrite(STDOUT, "  " . ($statusOnly ? "PENDING  " : "pending  ") . $file . "\n");
        }
    }

    $pending = optvDbMigrationPendingCount($status);
    fwrite(STDOUT, "\n");
    if ($failed) {
        fwrite(STDOUT, $failed . " database(s) unreachable\n");
        exit(1);
    }
    if (!$statusOnly) {
        fwrite(STDOUT, $pending
            ? $pending . " migration(s) pending. Re-run with --apply.\n"
            : "nothing pending.\n");
    }
    exit(0);
}

// ----------------------------------------------------------------- apply mode
$results = optvDbMigrationApply($scope, $onlyParliament);
$applied = 0;
$failed  = 0;

foreach ($results as $entry) {
    fwrite(STDOUT, "\n" . $entry["label"] . " (" . $entry["db"] . ")\n");

    foreach ($entry["applied"] as $file) {
        fwrite(STDOUT, "  applied  " . $file . "\n");
        $applied++;
    }
    if ($entry["failed"] !== null) {
        fwrite(STDERR, "  FAILED   " . $entry["failed"] . ": " . $entry["error"] . "\n");
        fwrite(STDERR, "  stopping this database; later migrations were not applied.\n");
        $failed++;
    } elseif ($entry["error"] !== null) {
        fwrite(STDERR, "  unreachable: " . $entry["error"] . "\n");
        $failed++;
    } elseif (empty($entry["applied"])) {
        fwrite(STDOUT, "  up to date\n");
    }
}

fwrite(STDOUT, "\n");
if ($failed) {
    fwrite(STDOUT, "applied " . $applied . " migration(s), " . $failed . " failure(s)\n");
    exit(1);
}
fwrite(STDOUT, "applied " . $applied . " migration(s).\n");
exit(0);
