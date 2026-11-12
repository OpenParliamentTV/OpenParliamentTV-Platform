<?php

$config["dir"]["api"] = $config["dir"]["root"]."/api/v1";

/**
 * Entity types and their subtypes
 */
$config["entityTypes"] = [
    "person" => [
        "memberOfParliament",
        "person"
    ],
    "organisation" => [
        "party",
        "faction",
        "government",
        "company",
        "ngo",
        "otherOrganisation"
    ],
    "document" => [
        "officialDocument",
        "legalDocument",
        "otherDocument"
    ],
    "term" => [
        "otherTerm"
    ]
];

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
        "fields",
        "fragDenStaatID", 
        "getAllResults",
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
        // Not a search filter: the player's playlist token. It is allow-listed so
        // it survives $paramStr/$isResult (index.php) and the media links built
        // from them; processRequestParameters() has no default case, so it never
        // reaches the query.
        "playlist",
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
        "limit",
        "name",
        "organisationID",
        "originID",
        "page",
        "party",
        "partyID",
        "type"
    ],
    "organisation" => [
        "filterable",
        "limit",
        "name",
        "page",
        "type"
    ],
    "document" => [
        "label",
        "limit",
        "page",
        "procedureID",
        "type",
        "wikidataID"
    ],
    "term" => [
        "label",
        "limit",
        "page",
        "type",
        "wikidataID"
    ],
    "electoralPeriod" => [
        "limit",
        "page",
        "parliament"
    ]
];

/**
 * Stopwords excluded from word-frequency statistics, per language.
 *
 * The key is the transcript language (modules/statistics-index/text-processing.php
 * resolves it per speech, falling back to the parliament's configured
 * statistics language). A language with no list here simply has no stopwords.
 *
 * Two things shape the German list:
 *
 *  - It carries the short function words ("die", "und", "der", ...). The word
 *    filter has ONE minimum-length rule, three characters, so anything shorter
 *    is out and everything else must be listed explicitly. The list previously
 *    had almost no three-letter entries because isStopword() auto-dropped
 *    anything under four BYTES -- a rule that also silently discarded real
 *    three-character words and treated umlauts inconsistently.
 *  - It does NOT carry comment vocabulary ("beifall", "zuruf") or faction names
 *    ("cdu/csu", "grünen", "bündnis"). Those were only ever listed to mask
 *    interjections being counted as the speaker's words; interjections and
 *    chair text are now excluded at the source (modules/statistics-index/segments.php),
 *    so listing them here would only erase their genuine uses in real speech.
 */
$config["statistics"]["stopwords"]["de"] = [
    "aber", "alle", "allem", "allen", "aller", "alles", "als", "also", "ander", "andere",
    "anderem", "anderen", "anderer", "anderes", "anderm", "andern", "anderr", "anders", "antrag", "auch",
    "auf", "aus", "ausschuss", "bei", "beiden", "beim", "beispiel", "bereich", "bereits", "beschluss",
    "bin", "bis", "bist", "bitte", "brauchen", "breg", "bundesminister", "bundesministerin", "bundesregierung", "bundesrepublik",
    "bundestag", "dabei", "dafür", "damen", "damit", "dank", "dann", "darauf", "darf", "darum",
    "darüber", "das", "dass", "dasselbe", "davon", "dazu", "daß", "debatte", "dein", "deine",
    "deinem", "deinen", "deiner", "deines", "dem", "demselben", "den", "denn", "denselben", "der",
    "derer", "derselbe", "derselben", "des", "deshalb", "desselben", "dessen", "deswegen", "deutlich", "deutsche",
    "deutschen", "deutschland", "dich", "die", "dies", "diese", "dieselbe", "dieselben", "diesem", "diesen",
    "dieser", "dieses", "dir", "doch", "dort", "drucksache", "durch", "eben", "eigentlich", "ein",
    "eine", "einem", "einen", "einer", "eines", "einfach", "einig", "einige", "einigem", "einigen",
    "einiger", "einiges", "einmal", "ende", "endlich", "erst", "etwa", "etwas", "euch", "euer",
    "eure", "eurem", "euren", "eurer", "eures", "finde", "folge", "frage", "fraktion", "fraktionslosen",
    "frau", "für", "ganz", "geben", "geehrte", "geehrter", "gegen", "gehen", "geht", "gehört",
    "gemacht", "genau", "gerade", "gesagt", "gesetzentwurf", "gewesen", "gibt", "glaube", "gremium", "große",
    "gute", "hab", "habe", "haben", "hat", "hatte", "hatten", "heißt", "herr", "herren",
    "heute", "hier", "hin", "hinter", "ich", "ihm", "ihn", "ihnen", "ihr", "ihre",
    "ihrem", "ihren", "ihrer", "ihres", "immer", "indem", "ins", "insbesondere", "ist", "jahr",
    "jahren", "jede", "jedem", "jeden", "jeder", "jedes", "jene", "jenem", "jenen", "jener",
    "jenes", "jetzt", "kann", "kanzler", "kanzlerin", "kein", "keine", "keinem", "keinen", "keiner",
    "keines", "klar", "kollege", "kollegen", "kollegin", "kolleginnen", "kommen", "kommission", "kommt", "können",
    "könnte", "lassen", "letzten", "liebe", "machen", "macht", "mal", "man", "manche", "manchem", "manchen",
    "mancher", "manches", "mark", "mehr", "mein", "meine", "meinem", "meinen", "meiner", "meines",
    "mich", "minister", "ministerin", "mir", "mit", "muss", "musste", "möchte", "müssen", "nach",
    "natürlich", "nicht", "nichts", "noch", "nun", "nur", "nämlich", "oder", "ohne", "plenum",
    "prozent", "präs", "präsident", "präsidentin", "recht", "redner", "rednerin", "richtig", "sage", "sagen",
    "sagt", "sagte", "schluss", "schon", "sehr", "sein", "seine", "seinem", "seinen", "seiner",
    "seines", "seit", "selbst", "sich", "sie", "sind", "sitzung", "sogar", "solche", "solchem",
    "solchen", "solcher", "solches", "soll", "sollen", "sollte", "sollten", "sondern", "sonst", "sowie",
    "staatssekretär", "staatssekretärin", "stehen", "steht", "stelle", "stellen", "stimmt", "thema", "und", "union",
    "uns", "unse", "unsem", "unsen", "unser", "unsere", "unserem", "unserer", "unter", "viel",
    "viele", "vielen", "vielleicht", "vizepräsident", "vizepräsidentin", "vom", "von", "vor", "vorlage", "war",
    "waren", "warum", "was", "weg", "weil", "weiter", "welche", "welchem", "welchen", "welcher",
    "welches", "wenn", "werde", "werden", "weshalb", "wichtig", "wie", "wieder", "wieso", "will",
    "wir", "wird", "wirklich", "wissen", "wollen", "wollte", "worden", "wort", "wurde", "wurden",
    "während", "wäre", "würde", "würden", "zum", "zur", "zwar", "zwei", "zwischen", "über",
    "überhaupt"
];

?>