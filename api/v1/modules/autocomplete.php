<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/functions.api.php");
require_once (__DIR__."/../../../modules/search/functions.php");

function fulltextAutocomplete($text) {
    if (!isset($text)) {
        return createApiErrorMissingParameter('text');
    }

    if (strlen($text) <= 2) {
        return createApiErrorInvalidLength('text', 3);
    }

    try {
        $autocompleteResult = searchAutocomplete($text);
        return createApiSuccessResponse($autocompleteResult);
    } catch (Exception $e) {
        return createApiErrorResponse(
            500,
            1,
            "messageErrorSearchGenericTitle",
            "messageErrorSearchRequestDetail",
            ["details" => $e->getMessage()]
        );
    }
}
?>