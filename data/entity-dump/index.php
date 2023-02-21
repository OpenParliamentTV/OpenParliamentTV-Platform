<?php
error_reporting(0);

require_once(__DIR__.'/function.entityDump.php');


if (!$_REQUEST["type"]) {
    echo "To get a specific type, define type = 'person', 'term', 'organisation', 'document' or 'all'. <br>
          To just get items, having a wikidata-id as id, use the parameter 'wiki'=true. in this case you can also add 'wikikeys'=true to get an object with wikidata ids as keys.";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(getEntityDump($_REQUEST),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



?>