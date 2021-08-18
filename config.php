<?php

$config["dir"]["root"] = "";

$config["allow"]["register"] = true;
$config["allow"]["login"] = true;
$config["salt"] = "gMu2bs)v7ZN!677hH";

$config["mode"] = "dev";

$config["mail"]["from"] = "noreply@openparliament.tv";
$config["mail"]["replyto"] = "noreply@openparliament.tv";



//DB - Platform config
$config["platform"]["sql"]["access"]["host"] = "localhost";
$config["platform"]["sql"]["access"]["user"] = "root";
$config["platform"]["sql"]["access"]["passwd"] = "";
$config["platform"]["sql"]["db"] = "openparliamenttv";

$config["platform"]["sql"]["tbl"]["Organisation"] = "organisation";
$config["platform"]["sql"]["tbl"]["Term"] = "term";
$config["platform"]["sql"]["tbl"]["Document"] = "document";
$config["platform"]["sql"]["tbl"]["Person"] = "person";
$config["platform"]["sql"]["tbl"]["Auth"] = "auth";
$config["platform"]["sql"]["tbl"]["Conflict"] = "conflict";
$config["platform"]["sql"]["tbl"]["User"] = "user";


//DB - Parliament config
$config["parliament"]["DE"]["label"] = "Deutscher Bundestag";
$config["parliament"]["DE"]["sql"]["access"]["host"] = "localhost";
$config["parliament"]["DE"]["sql"]["access"]["user"] = "root";
$config["parliament"]["DE"]["sql"]["access"]["passwd"] = "";
$config["parliament"]["DE"]["sql"]["db"] = "openparliamenttv_de";
$config["parliament"]["DE"]["sql"]["tbl"]["AgendaItem"] = "agendaitem";
$config["parliament"]["DE"]["sql"]["tbl"]["ElectoralPeriod"] = "electoralperiod";
$config["parliament"]["DE"]["sql"]["tbl"]["Session"] = "session";
$config["parliament"]["DE"]["sql"]["tbl"]["Media"] = "media";
$config["parliament"]["DE"]["sql"]["tbl"]["Annotation"] = "annotation";
$config["parliament"]["DE"]["sql"]["tbl"]["Text"] = "text";


//DB - Parliament config Brandenburg
$config["parliament"]["DE-BB"]["label"] = "Landtag Brandenburg";
$config["parliament"]["DE-BB"]["sql"]["access"]["host"] = "localhost";
$config["parliament"]["DE-BB"]["sql"]["access"]["user"] = "root";
$config["parliament"]["DE-BB"]["sql"]["access"]["passwd"] = "";
$config["parliament"]["DE-BB"]["sql"]["db"] = "openparliamenttv_de_bb";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["AgendaItem"] = "agendaitem";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["ElectoralPeriod"] = "electoralperiod";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["Session"] = "session";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["Media"] = "media";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["Annotation"] = "annotation";
$config["parliament"]["DE-BB"]["sql"]["tbl"]["Text"] = "text";




//ES Config
$config["ES"]["hosts"] = false;
$config["ES"]["BasicAuthentication"]["user"] = false;
$config["ES"]["BasicAuthentication"]["passwd"] = false;
$config["ES"]["SSL"]["pem"] = false;

/*
if (((array_key_exists("SERVER_NAME", $_SERVER)) && (!preg_match("/openparliament\.tv/", $_SERVER["SERVER_NAME"])))
    || (!array_key_exists("PWD", $_SERVER) && (php_sapi_name() == "cli"))
    || (array_key_exists("PWD", $_SERVER) && (!preg_match("/openparliament\.tv/", $_SERVER["PWD"])))) {

    $config["ES"]["hosts"] = ["https://@localhost:9200"];
    $config["ES"]["BasicAuthentication"]["user"] = "admin";
    $config["ES"]["BasicAuthentication"]["passwd"] = "admin";
    $config["ES"]["SSL"]["pem"] = realpath(__DIR__."/../opensearch-root-ssl.pem");

}
*/
?>