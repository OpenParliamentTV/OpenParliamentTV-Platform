<?php
/**
 * Error signalling for modules/entities (D2).
 *
 * A node builder returns the node, or `null` when the entity does not exist —
 * "not found" is an ordinary answer, not a failure. It throws only when it
 * cannot answer at all.
 *
 * Each exception carries a coarse `reason` because the API wrappers map the
 * same failure onto different envelopes per entity (a malformed media id is a
 * parse error, an unknown parliament in the same id is an invalid-id error, and
 * a dead platform connection reads differently from a dead parliament one).
 * Those envelopes are frozen by the byte-identical rule, so the reason is what
 * lets one wrapper reproduce them without re-parsing the id itself.
 */

/** The id is malformed, or names a parliament this instance does not have. */
class OptvInvalidIdException extends InvalidArgumentException
{
    /** 'parse' = unusable/unknown-type id · 'parliament' = no such parliament. */
    private $reason;

    public function __construct($message = "", $reason = "parse")
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public function getReason()
    {
        return $this->reason;
    }
}

/** The entity exists but could not be assembled (bad stored JSON, broken relation). */
class OptvEntityException extends RuntimeException
{
    /** '' generic · 'platform' / 'parliament' = that database is unreachable. */
    private $reason;

    public function __construct($message = "", $reason = "")
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public function getReason()
    {
        return $this->reason;
    }
}

/**
 * The entity exists but the current session may not see it. Deliberately not a
 * subclass of OptvEntityException: a caller that treats "unavailable" as 404
 * (transcript, export, IIIF) must be able to catch this without also
 * swallowing a database outage.
 */
class OptvEntityAccessException extends RuntimeException
{
}
