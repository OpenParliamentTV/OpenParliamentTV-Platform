<?php
/**
 * Error signalling for modules/entities (D2).
 *
 * A node builder returns the node, or `null` when the entity does not exist —
 * "not found" is an ordinary answer, not a failure. It throws only when it
 * cannot answer at all.
 */

/** The id is malformed, or names a parliament this instance does not have. */
class OptvInvalidIdException extends InvalidArgumentException
{
}

/** The entity exists but could not be assembled (bad stored JSON, broken relation). */
class OptvEntityException extends RuntimeException
{
}
