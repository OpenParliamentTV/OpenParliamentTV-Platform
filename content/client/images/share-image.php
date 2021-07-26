<?php
header("Content-type: image/png;");
require_once(__DIR__."/../../../modules/image-quote/functions.php");

renderImageQuote($_REQUEST['theme'], $_REQUEST['text']);

?>