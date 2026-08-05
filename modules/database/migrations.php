<?php
/**
 * Schema migration engine.
 *
 * Plain data and exceptions (D2): reports what is pending and applies it,
 * knowing nothing about HTTP or the CLI. Two consumers — bin/migrate.php and
 * api/v1/modules/migration.php (the panel under /manage/settings) — so an
 * operator without shell access can still see and run migrations.
 *
 * Applied files are recorded in a `_migrations` table inside each database, so
 * the record travels with the database rather than sitting in the deployment.
 * Every migration must nonetheless stay re-runnable: instances that predate
 * this runner had files applied by hand, and their first run re-applies them.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");

const OPTV_MIGRATION_TABLE = '_migrations';

/**
 * The databases migrations apply to: the platform database, plus every
 * configured parliament.
 *
 * @param string $scope 'platform' | 'parliament' | 'all'
 * @param string|null $onlyParliament Restrict to one parliament key.
 * @return array List of ['key','label','dir','access','db'].
 */
function optvDbMigrationTargets($scope = 'all', $onlyParliament = null)
{
    global $config;

    $targets = [];
    $base = __DIR__ . "/../../migrations";

    if ($scope === 'platform' || $scope === 'all') {
        $targets[] = [
            'key'    => 'platform',
            'label'  => 'platform',
            'dir'    => $base . '/platform',
            'access' => $config["platform"]["sql"]["access"],
            'db'     => $config["platform"]["sql"]["db"],
        ];
    }

    if ($scope === 'parliament' || $scope === 'all') {
        foreach ($config["parliament"] as $code => $parliament) {
            if ($onlyParliament !== null && $code !== $onlyParliament) {
                continue;
            }
            $targets[] = [
                'key'    => $code,
                'label'  => 'parliament ' . $code,
                'dir'    => $base . '/parliament',
                'access' => $parliament["sql"]["access"],
                'db'     => $parliament["sql"]["db"],
            ];
        }
    }

    return $targets;
}

/**
 * Migrations are multi-statement files, which SafeMySQL does not run, so this
 * opens a raw mysqli connection.
 *
 * @throws OptvDbException
 */
function optvDbMigrationConnect(array $target)
{
    $previous = mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(
            $target['access']['host'],
            $target['access']['user'],
            $target['access']['passwd'],
            $target['db']
        );
        $conn->set_charset('utf8mb4');
        return $conn;
    } catch (Throwable $e) {
        throw new OptvDbException($e->getMessage(), 'unavailable', $target['key'], $e);
    } finally {
        mysqli_report($previous);
    }
}

/** Migration files for a target, sorted by name (= application order). */
function optvDbMigrationFiles(array $target)
{
    if (!is_dir($target['dir'])) {
        return [];
    }
    $files = glob($target['dir'] . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    return array_map('basename', $files);
}

function optvDbMigrationEnsureTable(mysqli $conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `" . OPTV_MIGRATION_TABLE . "` (
            `Filename` varchar(191) NOT NULL,
            `AppliedAt` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`Filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** @return array Filename => AppliedAt */
function optvDbMigrationApplied(mysqli $conn)
{
    $applied = [];
    $res = $conn->query("SELECT `Filename`, `AppliedAt` FROM `" . OPTV_MIGRATION_TABLE . "` ORDER BY `Filename`");
    while ($row = $res->fetch_assoc()) {
        $applied[$row['Filename']] = $row['AppliedAt'];
    }
    return $applied;
}

/**
 * Status for every target: which files exist, which have run, which are pending.
 *
 * An unreachable database is reported as an `error` on that target rather than
 * throwing, so one dead parliament does not hide the state of the others.
 *
 * @return array List of ['key','label','db','applied','pending','error'].
 */
function optvDbMigrationStatus($scope = 'all', $onlyParliament = null)
{
    $out = [];

    foreach (optvDbMigrationTargets($scope, $onlyParliament) as $target) {
        $entry = [
            'key'     => $target['key'],
            'label'   => $target['label'],
            'db'      => $target['db'],
            'applied' => [],
            'pending' => [],
            'error'   => null,
        ];

        try {
            $conn = optvDbMigrationConnect($target);
            optvDbMigrationEnsureTable($conn);
            $applied = optvDbMigrationApplied($conn);
            $conn->close();
        } catch (Throwable $e) {
            $entry['error'] = $e->getMessage();
            $out[] = $entry;
            continue;
        }

        foreach (optvDbMigrationFiles($target) as $file) {
            if (isset($applied[$file])) {
                $entry['applied'][] = ['file' => $file, 'appliedAt' => $applied[$file]];
            } else {
                $entry['pending'][] = $file;
            }
        }

        $out[] = $entry;
    }

    return $out;
}

/** Total pending files across all targets — the "is a migration necessary?" answer. */
function optvDbMigrationPendingCount(array $status)
{
    $n = 0;
    foreach ($status as $entry) {
        $n += count($entry['pending']);
    }
    return $n;
}

/**
 * Run one file, draining every result set so an error in a later statement is
 * not swallowed. Throws on the first failure; the caller records nothing.
 *
 * @throws OptvDbException
 */
function optvDbMigrationRunFile(mysqli $conn, $path)
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new OptvDbException("Could not read " . basename($path), 'unavailable');
    }

    if (!$conn->multi_query($sql)) {
        throw new OptvDbException($conn->error, 'unavailable');
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno) {
            throw new OptvDbException($conn->error, 'unavailable');
        }
    } while ($conn->more_results() && $conn->next_result());

    // next_result() returns false both on the last statement and on error.
    if ($conn->errno) {
        throw new OptvDbException($conn->error, 'unavailable');
    }
}

/**
 * Apply every pending migration, in order, to every target in scope.
 *
 * A file is recorded only once all of its statements succeeded. The first
 * failure stops that database (later files are left pending) but the remaining
 * databases are still attempted.
 *
 * @return array List of ['key','label','db','applied','failed','error'].
 */
function optvDbMigrationApply($scope = 'all', $onlyParliament = null)
{
    $out = [];

    foreach (optvDbMigrationTargets($scope, $onlyParliament) as $target) {
        $entry = [
            'key'     => $target['key'],
            'label'   => $target['label'],
            'db'      => $target['db'],
            'applied' => [],
            'failed'  => null,
            'error'   => null,
        ];

        try {
            $conn = optvDbMigrationConnect($target);
            optvDbMigrationEnsureTable($conn);
            $applied = optvDbMigrationApplied($conn);
        } catch (Throwable $e) {
            $entry['error'] = $e->getMessage();
            $out[] = $entry;
            continue;
        }

        foreach (optvDbMigrationFiles($target) as $file) {
            if (isset($applied[$file])) {
                continue;
            }
            try {
                optvDbMigrationRunFile($conn, $target['dir'] . '/' . $file);
                $stmt = $conn->prepare("INSERT INTO `" . OPTV_MIGRATION_TABLE . "` (`Filename`) VALUES (?)");
                $stmt->bind_param('s', $file);
                $stmt->execute();
                $stmt->close();
                $entry['applied'][] = $file;
            } catch (Throwable $e) {
                $entry['failed'] = $file;
                $entry['error'] = $e->getMessage();
                break;
            }
        }

        $conn->close();
        $out[] = $entry;
    }

    return $out;
}
