<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../api/v1/utilities.php");
require_once (__DIR__."/../../../modules/database/migrations.php");

/**
 * Envelope wrappers over the migration engine (modules/database/migrations.php),
 * so an operator can see and apply pending schema migrations from
 * /manage/settings instead of needing shell access.
 *
 * Admin-only: the registry marks the whole `migration` action `admin`, which is
 * what the HTTP entry point gates on. migrationRequireAdmin() additionally
 * covers internal apiV1() callers, which bypass that gate.
 */

/** Internal apiV1() callers bypass the HTTP auth gate; this closes that path. */
function migrationRequireAdmin()
{
    $isAdmin = isset($_SESSION["userdata"]["role"]) && $_SESSION["userdata"]["role"] === "admin";
    if ($isAdmin || optvIsCli()) {
        return true;
    }
    return apiErrorResponse(
        403,
        1,
        "messageAuthNotPermittedTitle",
        "messageAuthNotPermittedDetail"
    );
}

/**
 * What has been applied and what is pending, per database.
 *
 * `pendingTotal` is the "does this instance need a migration?" answer the
 * settings panel keys its badge on.
 */
function migrationGetStatus($api_request)
{
    $admin = migrationRequireAdmin();
    if ($admin !== true) {
        return $admin;
    }

    try {
        $status = optvDbMigrationStatus();
    } catch (Exception $e) {
        return apiErrorDatabaseError($e->getMessage());
    }

    $unreachable = 0;
    foreach ($status as $entry) {
        if ($entry["error"] !== null) {
            $unreachable++;
        }
    }

    return apiSuccessResponse(
        ["databases" => $status],
        [
            "requestStatus" => "success",
            "pendingTotal"  => optvDbMigrationPendingCount($status),
            "unreachable"   => $unreachable,
        ]
    );
}

/**
 * Apply every pending migration. Safe to press twice: an already-applied file
 * is skipped, and the files themselves are re-runnable anyway.
 */
function migrationRun($api_request)
{
    $admin = migrationRequireAdmin();
    if ($admin !== true) {
        return $admin;
    }

    try {
        $results = optvDbMigrationApply();
    } catch (Exception $e) {
        return apiErrorDatabaseError($e->getMessage());
    }

    $applied = 0;
    $failed  = 0;
    foreach ($results as $entry) {
        $applied += count($entry["applied"]);
        if ($entry["failed"] !== null || $entry["error"] !== null) {
            $failed++;
        }
    }

    return apiSuccessResponse(
        ["databases" => $results],
        [
            "requestStatus" => "success",
            "appliedTotal"  => $applied,
            "failed"        => $failed,
        ]
    );
}
