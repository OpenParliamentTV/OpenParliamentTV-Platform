<?php

$config["dir"]["input"] = "../input";
$config["dir"]["output"] = "../output";
$config["dir"]["opendata"] = "../cache";
$config["dir"]["tmp"] = "../cache";
$config["dir"]["cacheaudio"] = "../cache";

$config["sleep"] = 0;



$config["allow"]["register"] = true;
$config["allow"]["login"] = true;
$config["salt"] = "gMu2bs)v7ZN!677hH";



//DB - Platform config
$config["platform"]["sql"]["access"]["host"] = "localhost";
$config["platform"]["sql"]["access"]["user"] = "root";
$config["platform"]["sql"]["access"]["passwd"] = "";
$config["platform"]["sql"]["db"] = "openparliamenttv";

$config["platform"]["sql"]["tbl"]["Party"] = "party";
$config["platform"]["sql"]["tbl"]["Speaker"] = "speaker";
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
$config["parliament"]["bt"]["sql"]["tbl"]["Speech"] = "speech";
$config["parliament"]["bt"]["sql"]["tbl"]["SpeechAnnotation"] = "speechannotation";
$config["parliament"]["bt"]["sql"]["tbl"]["SpeechContent"] = "speechcontent";


?>