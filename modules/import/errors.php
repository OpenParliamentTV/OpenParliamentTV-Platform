<?php
/**
 * Error signalling for modules/import (D2): the module throws, and the thin
 * wrapper in api/v1/modules/media.php turns the throw back into a JSON:API
 * envelope.
 */

/** Anything that stopped an import from completing. */
class ImportException extends RuntimeException
{
}

/**
 * The session data does not describe an importable speech.
 *
 * Carries the envelope fields rather than a message, because the API response
 * for a rejected `addItem&itemType=media` must stay exactly what it was before
 * the extraction: same status, same code, same lang keys, same parameter name.
 */
class ImportValidationException extends ImportException
{
    private $status;
    private $errorCode;
    private $titleKey;
    private $detailKey;
    private $params;
    private $domSelector;

    public function __construct($status, $errorCode, $titleKey, $detailKey, array $params = [], $domSelector = null)
    {
        parent::__construct($detailKey);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->titleKey = $titleKey;
        $this->detailKey = $detailKey;
        $this->params = $params;
        $this->domSelector = $domSelector;
    }

    /** A missing required field, which is most of the validation. */
    public static function missingParameter($parameter)
    {
        return new self(400, 1, "messageErrorParameterMissingTitle", "messageErrorMissingParameter", ["parameter" => $parameter]);
    }

    public function getStatus()      { return $this->status; }
    public function getErrorCode()   { return $this->errorCode; }
    public function getTitleKey()    { return $this->titleKey; }
    public function getDetailKey()   { return $this->detailKey; }
    public function getParams()      { return $this->params; }
    public function getDomSelector() { return $this->domSelector; }

    /** The shape reportConflict() records under "media-validation-error". */
    public function toArray()
    {
        return [
            "status" => $this->status,
            "code" => $this->errorCode,
            "title" => $this->titleKey,
            "detail" => $this->detailKey,
            "params" => $this->params,
        ];
    }
}
