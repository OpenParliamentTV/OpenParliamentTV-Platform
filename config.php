<?php

$config["dir"]["root"] = "";

$config["allow"]["register"] = true;
$config["allow"]["login"] = true;
$config["salt"] = "gMu2bs)v7ZN!677hH";

$config["mode"] = "dev";

$config["mail"]["from"] = "temp@norealmailaddressyet121212.com";
$config["mail"]["replyto"] = "temp@norealmailaddressyet121212.com";



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

$config["parliament"]["bt"]["label"] = "Deutscher Bundestag";
$config["parliament"]["bt"]["sql"]["access"]["host"] = "localhost";
$config["parliament"]["bt"]["sql"]["access"]["user"] = "root";
$config["parliament"]["bt"]["sql"]["access"]["passwd"] = "";
$config["parliament"]["bt"]["sql"]["db"] = "openparliamenttv_bt";

$config["parliament"]["bt"]["sql"]["tbl"]["AgendaItem"] = "agendaitem";
$config["parliament"]["bt"]["sql"]["tbl"]["ElectoralPeriod"] = "electoralperiod";
$config["parliament"]["bt"]["sql"]["tbl"]["Session"] = "session";
$config["parliament"]["bt"]["sql"]["tbl"]["Media"] = "media";
$config["parliament"]["bt"]["sql"]["tbl"]["Annotation"] = "annotation";
$config["parliament"]["bt"]["sql"]["tbl"]["Text"] = "text";


?>