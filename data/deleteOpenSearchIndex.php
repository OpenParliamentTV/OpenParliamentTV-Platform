<?php
session_start();
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

    /*
     * TODO: REMOVE IF OTHER WORKS
     *
     * $hosts = ["https://@localhost:9200"];
    $ESClient = Elasticsearch\ClientBuilder::create()
        ->setHosts($hosts)
        ->setBasicAuthentication("admin", "admin")
        ->setSSLVerification(realpath(__DIR__ . "/../../opensearch-root-ssl.pem"))
        ->build();
    */
    require_once(__DIR__.'/../config.php');

    $ESClientBuilder = Elasticsearch\ClientBuilder::create();

    if ($config["ES"]["hosts"]) {
        $ESClientBuilder->setHosts($config["ES"]["hosts"]);
    }
    if ($config["ES"]["BasicAuthentication"]["user"]) {
        $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"],$config["ES"]["BasicAuthentication"]["passwd"]);
    }
    if ($config["ES"]["SSL"]["pem"]) {
        $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
    }

    $ESClient = $ESClientBuilder->build();

    $response = $ESClient->indices()->delete(array("index" => "openparliamenttv_de"));
    echo '<pre>';
    print_r($response);
    echo '</pre>';

}
?>