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


?>