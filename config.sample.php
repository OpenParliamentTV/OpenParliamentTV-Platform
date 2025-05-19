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
 * E-Mail addresses of the platform
 */
$config["mail"]["from"] = "noreply@openparliament.tv";
$config["mail"]["replyto"] = "noreply@openparliament.tv";

/**
 * Executabels
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
$config["parliament"]["DE"]["ES"]["index"] = "de"; // openparliamenttv_THIS

/**
 * Configuration for the ElasticSearch or OpenSearch server
 */
$config["ES"]["hosts"] = []; // E.g. ["https://@localhost:9200"]
$config["ES"]["BasicAuthentication"]["user"] = "";
$config["ES"]["BasicAuthentication"]["passwd"] = "";
$config["ES"]["SSL"]["pem"] = ""; // E.g. realpath(__DIR__."/../opensearch-root-ssl.pem");

/**
 * Allowed parameters for the search endpoint
 */
$config["allowedSearchParams"] = [
    "media" => [
        "abgeordnetenwatchID", 
        "agendaItemID", 
        "agendaItemTitle",
        "aligned", 
        "context", 
        "dateFrom", 
        "dateTo", 
        "documentID", 
        "electoralPeriod", 
        "electoralPeriodID", 
        "faction", 
        "factionID", 
        "fragDenStaatID", 
        "id", 
        "includeAll", 
        "limit",
        "numberOfTexts", 
        "organisation", 
        "organisationID", 
        "page",
        "parliament", 
        "party", 
        "partyID", 
        "person", 
        "personID", 
        "personOriginID", 
        "procedureID", 
        "public", 
        "q",
        "sessionID", 
        "sessionNumber", 
        "sort", 
        "termID"
    ],
    "person" => [
        "abgeordnetenwatchID",
        "degree", 
        "faction", 
        "factionID", 
        "fragDenStaatID",
        "gender", 
        "name", 
        "organisationID", 
        "originID", 
        "party", 
        "partyID", 
        "type"
    ],
    "organisation" => [
        "name",
        "type"
    ],
    "document" => [
        "label", 
        "type",
        "wikidataID"
    ],
    "term" => [
        "label",
        "type",
        "wikidataID"
    ]
];

/**
 * List of stopwords to exclude from term frequency statistics
 */
$config["excludedStopwords"] = [
    "die", "der", "und", "in", "den", "das", "ist", "zu", "von", "mit", "sich", "des", "auf", "für", "im", "dem", 
    "nicht", "ein", "eine", "als", "auch", "es", "an", "werden", "aus", "er", "hat", "dass", "sie", "nach", "wird", 
    "bei", "einer", "um", "am", "sind", "noch", "wie", "einem", "über", "einen", "so", "zum", "war", "haben", "oder", 
    "aber", "vor", "zur", "bis", "mehr", "durch", "man", "sein", "wurde", "sei", "wurden", "während", "hatte", 
    "kann", "gegen", "vom", "können", "schon", "wenn", "habe", "seine", "mark", "ihre", "dann", "unter", "wir", "soll", 
    "ich", "eines", "es", "jahr", "zwei", "jahren", "diese", "dieser", "wieder", "keine", "um", "und", "muss", "jahr", 
    "zwei", "dabei", "beim", "wurde", "sowie", "nur", "ber", "dabei", "viele", "zwischen", "immer", "einmal", "etwa", 
    "alle", "beiden", "dafür", "sollte", "seit", "wurden", "wurde", "etwas", "sagte", "sagt", "gibt", "folge", "große", 
    "insbesondere", "ganz", "müssen", "selbst", "nun", "beifall", "cdu", "csu", "fdp", "grünen", "spd", "afd", "fdp", 
    "linke", "fraktion", "fr", "sehr", "90", "abgeordneten", "kollegen", "bündnis", "dank", "uns", "hier", "b", "was", 
    "gr", "jetzt", "vielen", "herr", "damit", "kolleginnen", "frau", "denn", "liebe", "diesem", "ja", "da", "linken", 
    "ihnen", "dr", "meine", "dieses", "weil", "abg", "geht", "doch", "sagen", "sondern", "dazu", "diesen", "gerade", 
    "mich", "präs", "mal", "kommen", "unsere", "also", "viel", "breg", "deshalb", "mir", "anderen", "will"
];

?>