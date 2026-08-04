<?php
/**
 * Error signalling for modules/database (D2).
 *
 * optvDbConnection() returns a connection or throws — there is no "not found"
 * answer for a database handle, so unlike the entity builders it never returns
 * null.
 */

/**
 * A connection could not be established or was asked for incoherently.
 *
 * The reason is what lets getApiDatabaseConnection() reproduce its three
 * distinct frozen envelopes:
 *   'unavailable'        — the server refused or is unreachable   → 503
 *   'unknown-parliament' — no such key in $config["parliament"]   → 422 invalid
 *   'missing-parliament' — a parliament scope with no parliament  → 422 missing
 */
class OptvDbException extends RuntimeException
{
    private $reason;
    private $scope;

    public function __construct($message = "", $reason = "unavailable", $scope = "platform", $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->reason = $reason;
        $this->scope = $scope;
    }

    public function getReason()
    {
        return $this->reason;
    }

    /** 'platform' or 'parliament' — which of the two the caller asked for. */
    public function getScope()
    {
        return $this->scope;
    }
}
