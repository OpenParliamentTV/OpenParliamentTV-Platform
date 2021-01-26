<?php

$config["dir"]["root"] = "";

$config["allow"]["register"] = true;
$config["allow"]["login"] = true;
$config["salt"] = "gMu2bs)v7ZN!677hH";

$config["mode"] = "dev";



//DB - Platform config
$config["platform"]["sql"]["access"]["host"] = "localhost";
$config["platform"]["sql"]["access"]["user"] = "root";
$config["platform"]["sql"]["access"]["passwd"] = "";
$config["platform"]["sql"]["db"] = "openparliamenttv";

$config["platform"]["sql"]["tbl"]["Party"] = "party";
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
$config["parliament"]["bt"]["sql"]["tbl"]["MediaAnnotation"] = "mediaannotation";
$config["parliament"]["bt"]["sql"]["tbl"]["MediaContent"] = "mediacontent";
$config["parliament"]["bt"]["sql"]["tbl"]["MediaPerson"] = "mediaperson";


?>