<?php

require_once (__DIR__."/../../../config.php");
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
        // Use the original autocomplete function which already returns the right format
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

function agendaItemAutocomplete($query) {
    if (!isset($query)) {
        return createApiErrorMissingParameter('q');
    }

    if (strlen($query) <= 2) {
        return createApiErrorInvalidLength('q', 3);
    }

    try {
        $autocompleteResult = searchAgendaItemAutocomplete($query);
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