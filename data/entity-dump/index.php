<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../modules/utilities/security.php');
applySecurityHeaders();

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once(__DIR__.'/function.entityDump.php');


if (!$_REQUEST["type"]) {
    echo "To get a specific type, define type = 'person', 'term', 'organisation', 'document' or 'all'. <br>
          To get all types but you want to exclude a specific type, add 'exclude_document', 'exclude_organisation', 'exclude_term' or 'exclude_person' additionaly to the 'type=all' parameter<br><br>
          To just get items, having a wikidata-id as id, use the parameter 'wiki'=true. in this case you can also add 'wikikeys'=true to get an object with wikidata ids as keys.<br><br>";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(getEntityDump($_REQUEST),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



?>