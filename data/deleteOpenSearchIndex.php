<?php

include_once(__DIR__ . '/../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "elasticSearch", "deleteIndex");

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];


} else {


    error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

    set_time_limit(0);
    ini_set('memory_limit', '500M');
    date_default_timezone_set('CET');

    require __DIR__ . '/../vendor/autoload.php';

    $hosts = ["https://@localhost:9200"];
    $ESClient = Elasticsearch\ClientBuilder::create()
        ->setHosts($hosts)
        ->setBasicAuthentication("admin", "admin")
        ->setSSLVerification(realpath(__DIR__ . "/../../opensearch-root-ssl.pem"))
        ->build();

    $response = $ESClient->indices()->delete(array("index" => "openparliamenttv_de"));
    echo '<pre>';
    print_r($response);
    echo '</pre>';

}
?>