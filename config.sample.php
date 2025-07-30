<?php

/**
 * Version Number 
 * (mostly used for refreshing cached resources of previous version)
 * Example: "1.5"
 * String
 */
$config["version"] = "";

/**
 * The URI to your Open Parliament TV instance
 * Example: https://de.openparliament.tv
 */
$config["dir"]["root"] = "";

/**
 * Display NER results in UI
 * true or false
 */
$config["display"]["ner"] = true;

/**
 * Speeches per page
 * Number
 */
$config["display"]["speechesPerPage"] = 40;

/**
 * Allow user to register
 * true or false
 */
$config["allow"]["register"] = true;


/**
 * Allow registered user to login
 * true or false
 */
$config["allow"]["login"] = true;


/**
 * Allow public access to all none administrative data and functions
 * true or false
 */
$config["allow"]["publicAccess"] = true;


/**
 * A complicated string to salt the user account password hashes
 */
$config["salt"] = "";

/**
 * Available languages of the platform (translation files can be found at /lang/*.json
 */

$acceptLang = array(
    "de"=>array(
        "short"=>"de",
        "name"=>"Deutsch",
        "icon"=>""
    ),
    "en"=>array(
        "short"=>"en",
        "name"=>"English",
        "icon"=>""
    )
);

/**
 * for debugging and developing
 * Example: "dev" or "production"
 */
$config["mode"] = "production";


/**
 * Mail Settings
 */
$config["mail"]["from"]["email"] = "noreply@openparliament.tv";
$config["mail"]["from"]["name"] = "Open Parliament TV";
$config["mail"]["replyto"] = "noreply@openparliament.tv";

// SMTP Configuration (optional - leave empty to use PHP mail())
$config["mail"]["smtp"]["enabled"] = false;
$config["mail"]["smtp"]["host"] = ""; // E.g. "smtp.gmail.com"
$config["mail"]["smtp"]["port"] = 587; // 587 for TLS, 465 for SSL, 25 for no encryption
$config["mail"]["smtp"]["username"] = "";
$config["mail"]["smtp"]["password"] = "";
$config["mail"]["smtp"]["encryption"] = "tls"; // "tls", "ssl", or "" for no encryption

// Development Mode: When $config["mode"] is "dev", emails are saved to files instead of being sent
$config["mail"]["dev"]["file_path"] = ""; // E.g. realpath(__DIR__."/logs/mail") - leave empty for default (logs/mail)

/**
 * Executables
 */
$config["bin"]["git"] = "git";
$config["bin"]["php"] = "php";

/**
 * Additional Data Service of OPTV
 */
$config["ads"]["api"]["uri"] = "";
$config["ads"]["api"]["key"] = "";

/**
 * Platform Database (mariadb/MySQL) information
 */
$config["platform"]["sql"]["access"]["host"] = "";
$config["platform"]["sql"]["access"]["user"] = "";
$config["platform"]["sql"]["access"]["passwd"] = "";
$config["platform"]["sql"]["db"] = "";

$config["platform"]["sql"]["tbl"]["Organisation"] = "organisation";
$config["platform"]["sql"]["tbl"]["Term"] = "term";
$config["platform"]["sql"]["tbl"]["Document"] = "document";
$config["platform"]["sql"]["tbl"]["Person"] = "person";
$config["platform"]["sql"]["tbl"]["Auth"] = "auth";
$config["platform"]["sql"]["tbl"]["Conflict"] = "conflict";
$config["platform"]["sql"]["tbl"]["Entitysuggestion"] = "entitysuggestion";
$config["platform"]["sql"]["tbl"]["User"] = "user";


/**
 * Parliament Database information
 * This is an example for a parliament called "DE" (as it can be seen by the key ["DE"] at the object $config["parliament"])
 * There also can be multiple parliaments available at the platform by duplicating the configuration under different keys
 */
$config["parliament"]["DE"]["label"] = ""; //E.g. "Deutscher Bundestag"
$config["parliament"]["DE"]["sql"]["access"]["host"] = "";
$config["parliament"]["DE"]["sql"]["access"]["user"] = "";
$config["parliament"]["DE"]["sql"]["access"]["passwd"] = "";
$config["parliament"]["DE"]["sql"]["db"] = "";

$config["parliament"]["DE"]["sql"]["tbl"]["AgendaItem"] = "agendaitem";
$config["parliament"]["DE"]["sql"]["tbl"]["ElectoralPeriod"] = "electoralperiod";
$config["parliament"]["DE"]["sql"]["tbl"]["Session"] = "session";
$config["parliament"]["DE"]["sql"]["tbl"]["Media"] = "media";
$config["parliament"]["DE"]["sql"]["tbl"]["Annotation"] = "annotation";
$config["parliament"]["DE"]["sql"]["tbl"]["Text"] = "text";
$config["parliament"]["DE"]["git"]["repository"] = ""; //e.g. https://github.com/OpenParliamentTV/OpenParliamentTV-Data-DE.git
$config["parliament"]["DE"]["OpenSearch"]["index"] = "de"; // openparliamenttv_THIS

/**
 * Configuration for the OpenSearch server
 */
$config["OpenSearch"]["hosts"] = []; // E.g. ["https://@localhost:9200"]
$config["OpenSearch"]["BasicAuthentication"]["user"] = "";
$config["OpenSearch"]["BasicAuthentication"]["passwd"] = "";
$config["OpenSearch"]["SSL"]["pem"] = ""; // E.g. realpath(__DIR__."/../opensearch-root-ssl.pem");

// Include API config
require_once(__DIR__."/api/v1/config.api.php");

?>