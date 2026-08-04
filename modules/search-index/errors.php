<?php
/**
 * Error signalling for modules/search-index (D2): the module returns plain data
 * and throws on failure; the JSON:API envelope is applied only by the thin
 * wrappers in api/v1/modules/search-index.php.
 *
 * Two cases, because the wrappers map them to different HTTP statuses:
 * a caller that asked for something impossible (422) versus infrastructure that
 * did not do its job (500).
 */

/** The caller's arguments are wrong — unknown parliament, missing id, bad type. */
class SearchIndexInvalidInputException extends InvalidArgumentException
{
}

/**
 * OpenSearch, the filesystem or a spawned process failed. $errorCode carries the
 * machine-readable code the API response should report (e.g. "BULK_API_EXCEPTION"),
 * $context any extra fields for the error payload.
 */
class SearchIndexException extends RuntimeException
{
    private $errorCode;
    private $context;

    public function __construct($message, $errorCode = 'SEARCH_INDEX_FAILURE', array $context = [], $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getContext()
    {
        return $this->context;
    }
}
