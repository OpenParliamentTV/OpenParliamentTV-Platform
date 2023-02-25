<?php

require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../config.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");

function fulltextAutocomplete($text) {

    global $config;

    require_once (__DIR__."/../../../modules/utilities/functions.php");
    require_once (__DIR__."/../../../modules/search/functions.php");

    try {
        $autocompleteResult = searchAutocomplete($text);
        $return["data"] = $autocompleteResult;
        $return["meta"]["requestStatus"] = "success";
    } catch (Exception $e) {

    }
    
    return $return;

}
?>