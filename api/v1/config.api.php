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
        "type",
        "filterable"
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
    "90/die", "aber", "abgeordneten", "alle", "allem", "allen", "aller", "alles", "also", "ander",
    "andere", "anderem", "anderen", "anderer", "anderes", "anderm", "andern", "anderr", "anders", "antrag",
    "auch", "ausschuss", "beiden", "beifall", "beim", "beispiel", "bereich", "bereits", "beschluss", "bist",
    "bitte", "brauchen", "breg", "bundesminister", "bundesministerin", "bundesregierung", "bundesrepublik", "bundestag", "bündnis", "bündnisses",
    "cdu/csu", "cdu/csu-fraktion", "dabei", "dafür", "damen", "damit", "dank", "dann", "darauf", "darf",
    "darum", "darüber", "dass", "dasselbe", "davon", "dazu", "daß", "debatte", "dein", "deine",
    "deinem", "deinen", "deiner", "deines", "demselben", "denn", "denselben", "derer", "derselbe", "derselben",
    "deshalb", "desselben", "dessen", "deswegen", "deutlich", "deutsche", "deutschen", "deutschland", "dich", "dies",
    "diese", "dieselbe", "dieselben", "diesem", "diesen", "dieser", "dieses", "doch", "dort", "drucksache",
    "durch", "eben", "eigentlich", "eine", "einem", "einen", "einer", "eines", "einfach", "einig",
    "einige", "einigem", "einigen", "einiger", "einiges", "einmal", "ende", "endlich", "erst", "etwa",
    "etwas", "euch", "euer", "eure", "eurem", "euren", "eurer", "eures", "finde", "folge",
    "frage", "fraktion", "fraktionslos", "fraktionslosen", "frau", "für", "ganz", "geben", "geehrte", "geehrter",
    "gegen", "gehen", "geht", "gehört", "gemacht", "genau", "gerade", "gesagt", "gesetzentwurf", "gewesen",
    "gibt", "glaube", "gremium", "große", "grünen", "gute", "habe", "haben", "hatte", "hatten",
    "heißt", "herr", "herren", "heute", "hier", "hinter", "ihnen", "ihre", "ihrem", "ihren",
    "ihrer", "ihres", "immer", "indem", "insbesondere", "jahr", "jahren", "jede", "jedem", "jeden",
    "jeder", "jedes", "jene", "jenem", "jenen", "jener", "jenes", "jetzt", "kann", "kanzler",
    "kanzlerin", "kein", "keine", "keinem", "keinen", "keiner", "keines", "klar", "kollege", "kollegen",
    "kollegin", "kolleginnen", "kommen", "kommission", "kommt", "können", "könnte", "lassen", "letzten", "liebe",
    "linke", "linken", "machen", "macht", "manche", "manchem", "manchen", "mancher", "manches", "mark",
    "mehr", "mein", "meine", "meinem", "meinen", "meiner", "meines", "mich", "minister", "ministerin",
    "muss", "musste", "möchte", "müssen", "nach", "natürlich", "nicht", "nichts", "noch", "nämlich",
    "oder", "ohne", "plenum", "prozent", "präs", "präsident", "präsidentin", "recht", "redner", "rednerin",
    "richtig", "sage", "sagen", "sagt", "sagte", "schluss", "schon", "sehr", "sein", "seine",
    "seinem", "seinen", "seiner", "seines", "seit", "selbst", "sich", "sind", "sitzung", "sogar",
    "solche", "solchem", "solchen", "solcher", "solches", "soll", "sollen", "sollte", "sollten", "sondern",
    "sonst", "sowie", "spd-fraktion", "staatssekretär", "staatssekretärin", "stehen", "steht", "stelle", "stellen", "stimmt",
    "thema", "union", "unse", "unsem", "unsen", "unser", "unsere", "unserem", "unserer", "unter",
    "viel", "viele", "vielen", "vielleicht", "vizepräsident", "vizepräsidentin", "vorlage", "waren", "warum", "weil",
    "weiter", "wenn", "werden", "weshalb", "wichtig", "wieder", "wieso", "will", "wird", "wirklich",
    "wissen", "wollen", "worden", "wort", "wurde", "wurden", "während", "wäre", "würde", "zuruf",
    "zurufe", "zwei", "zwischen", "über", "überhaupt"
];

?>