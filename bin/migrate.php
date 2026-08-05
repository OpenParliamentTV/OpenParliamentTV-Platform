<?php
require_once(__DIR__ . "/../bootstrap-cli.php");

/**
 * Schema migration runner.
 *
 * Applies the numbered SQL files in migrations/ to the platform database and to
 * every configured parliament database, recording what has run in a
 * `_migrations` table inside each database. That table is the point: until it
 * existed the files were applied by hand, which is why every one of them still
 * has to be safe to re-run.
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

const MIGRATE_TABLE = '_migrations';

$opts = getopt("", ["apply", "status", "scope:", "parliament:", "help"]);

if (isset($opts["help"])) {
    fwrite(STDOUT, "Usage: php bin/migrate.php [--apply] [--status] [--scope=platform|parliament|all] [--parliament=XX]\n");
    exit(0);
}

$apply = isset($opts["apply"]);
$statusOnly = isset($opts["status"]);
$scope = $opts["scope"] ?? "all";
$onlyParliament = $opts["parliament"] ?? null;

if (!in_array($scope, ["platform", "parliament", "all"], true)) {
    fwrite(STDERR, "error: --scope must be platform, parliament or all\n");
    exit(2);
}
if ($onlyParliament !== null) {
    $scope = "parliament";
}

/**
 * The databases to visit, as [label, scope, config-block].
 * A parliament whose migrations directory is empty is still visited, so its
 * `_migrations` table is created and `--status` can report on it.
 */
function migrateTargets($scope, $onlyParliament)
{
    global $config;

    $targets = [];

    if ($scope === "platform" || $scope === "all") {
        $targets[] = [
            "label" => "platform (" . $config["platform"]["sql"]["db"] . ")",
            "dir"   => __DIR__ . "/../migrations/platform",
            "access" => $config["platform"]["sql"]["access"],
            "db"    => $config["platform"]["sql"]["db"],
        ];
    }

    if ($scope === "parliament" || $scope === "all") {
        foreach ($config["parliament"] as $code => $parliament) {
            if ($onlyParliament !== null && $code !== $onlyParliament) {
                continue;
            }
            $targets[] = [
                "label" => "parliament " . $code . " (" . $parliament["sql"]["db"] . ")",
                "dir"   => __DIR__ . "/../migrations/parliament",
                "access" => $parliament["sql"]["access"],
                "db"    => $parliament["sql"]["db"],
            ];
        }
    }

    return $targets;
}

/** Open a raw mysqli connection: migrations are multi-statement files. */
function migrateConnect(array $access, $db)
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($access["host"], $access["user"], $access["passwd"], $db);
    $conn->set_charset("utf8mb4");
    return $conn;
}

function migrateEnsureTable(mysqli $conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `" . MIGRATE_TABLE . "` (
            `Filename` varchar(191) NOT NULL,
            `AppliedAt` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`Filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function migrateApplied(mysqli $conn)
{
    $applied = [];
    $res = $conn->query("SELECT `Filename`, `AppliedAt` FROM `" . MIGRATE_TABLE . "` ORDER BY `Filename`");
    while ($row = $res->fetch_assoc()) {
        $applied[$row["Filename"]] = $row["AppliedAt"];
    }
    return $applied;
}

/** Migration files in a directory, sorted by name (= application order). */
function migrateFiles($dir)
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . "/*.sql") ?: [];
    sort($files, SORT_STRING);
    return $files;
}

/**
 * Run one file. Migrations are multi-statement, so this uses multi_query and
 * drains every result set — the first error aborts, and the file is only
 * recorded as applied when all of it succeeded.
 */
function migrateRunFile(mysqli $conn, $path)
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("could not read " . basename($path));
    }

    if (!$conn->multi_query($sql)) {
        throw new RuntimeException($conn->error);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno) {
            throw new RuntimeException($conn->error);
        }
    } while ($conn->more_results() && $conn->next_result());

    // next_result() returns false on the final statement *and* on error.
    if ($conn->errno) {
        throw new RuntimeException($conn->error);
    }
}

// ---------------------------------------------------------------------------

$targets = migrateTargets($scope, $onlyParliament);
if (empty($targets)) {
    fwrite(STDERR, "error: no matching database (unknown --parliament?)\n");
    exit(2);
}

$totalPending = 0;
$totalApplied = 0;
$failed = 0;

foreach ($targets as $target) {
    fwrite(STDOUT, "\n" . $target["label"] . "\n");

    try {
        $conn = migrateConnect($target["access"], $target["db"]);
    } catch (Throwable $e) {
        fwrite(STDERR, "  connection failed: " . $e->getMessage() . "\n");
        $failed++;
        continue;
    }

    try {
        migrateEnsureTable($conn);
        $applied = migrateApplied($conn);
    } catch (Throwable $e) {
        fwrite(STDERR, "  could not read " . MIGRATE_TABLE . ": " . $e->getMessage() . "\n");
        $failed++;
        $conn->close();
        continue;
    }

    $files = migrateFiles($target["dir"]);

    if ($statusOnly) {
        if (empty($files)) {
            fwrite(STDOUT, "  (no migrations in " . basename(dirname($target["dir"])) . "/" . basename($target["dir"]) . ")\n");
        }
        foreach ($files as $file) {
            $name = basename($file);
            fwrite(STDOUT, isset($applied[$name])
                ? sprintf("  applied  %-44s %s\n", $name, $applied[$name])
                : sprintf("  PENDING  %s\n", $name));
        }
        $conn->close();
        continue;
    }

    $pending = array_values(array_filter($files, function ($f) use ($applied) {
        return !isset($applied[basename($f)]);
    }));

    if (empty($pending)) {
        fwrite(STDOUT, "  up to date (" . count($files) . " migration(s) already applied)\n");
        $conn->close();
        continue;
    }

    $totalPending += count($pending);

    foreach ($pending as $file) {
        $name = basename($file);

        if (!$apply) {
            fwrite(STDOUT, "  pending  " . $name . "\n");
            continue;
        }

        try {
            migrateRunFile($conn, $file);
            $stmt = $conn->prepare("INSERT INTO `" . MIGRATE_TABLE . "` (`Filename`) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();
            fwrite(STDOUT, "  applied  " . $name . "\n");
            $totalApplied++;
        } catch (Throwable $e) {
            fwrite(STDERR, "  FAILED   " . $name . ": " . $e->getMessage() . "\n");
            fwrite(STDERR, "  stopping this database; later migrations were not applied.\n");
            $failed++;
            break;
        }
    }

    $conn->close();
}

if ($statusOnly) {
    exit(0);
}

fwrite(STDOUT, "\n");
if ($failed) {
    fwrite(STDOUT, "finished with " . $failed . " failure(s)\n");
    exit(1);
}
if (!$apply) {
    fwrite(STDOUT, $totalPending
        ? $totalPending . " migration(s) pending. Re-run with --apply.\n"
        : "nothing pending.\n");
    exit(0);
}
fwrite(STDOUT, "applied " . $totalApplied . " migration(s).\n");
exit(0);
