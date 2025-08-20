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
    "90/die", "aber", "abgeordneten", "alle", "alles", "also", "anderen", "antrag", "auch", "ausschuss",
    "beiden", "beifall", "beim", "beschluss", "brauchen", "breg", "bundesminister", "bundesministerin", "bundesregierung", "bundesrepublik",
    "bundestag", "bündnis", "bündnisses", "cdu/csu", "dabei", "dafür", "damen", "damit", "dank", "dann",
    "darauf", "dass", "dazu", "denn", "deshalb", "deswegen", "deutsche", "deutschen", "deutschland", "diese",
    "diesem", "diesen", "dieser", "dieses", "doch", "drucksache", "durch", "eigentlich", "eine", "einem",
    "einen", "einer", "eines", "einmal", "etwa", "etwas", "folge", "frage", "fraktion", "fraktionslosen",
    "frau", "für", "ganz", "geehrte", "gegen", "geht", "genau", "gerade", "gesagt", "gesetzentwurf",
    "gibt", "gremium", "große", "grünen", "habe", "haben", "hatte", "herr", "herren", "heute",
    "hier", "ihnen", "ihre", "ihrer", "immer", "insbesondere", "jahr", "jahren", "jetzt", "kann",
    "kanzler", "kanzlerin", "kein", "keine", "kollege", "kollegen", "kolleginnen", "kommen", "kommission", "können",
    "lassen", "letzten", "liebe", "linke", "linken", "machen", "mark", "mehr", "meine", "mich",
    "minister", "ministerin", "muss", "möchte", "müssen", "nach", "natürlich", "nicht", "nichts", "noch",
    "oder", "ohne", "plenum", "prozent", "präs", "präsident", "präsidentin",
    "sagen", "sagt", "sagte", "schon", "sehr", "sein", "seine", "seit", "selbst", "sich",
    "sind", "sitzung", "soll", "sollte", "sondern", "sowie", "staatssekretär", "staatssekretärin", "union", "unsere",
    "unserer", "unter", "viel", "viele", "vielen", "vizepräsident", "vizepräsidentin", "vorlage", "weil", "wenn",
    "werden", "wieder", "will", "wird", "wirklich", "wollen", "wort", "wurde", "wurden", "während", "wäre",
    "zwei", "zwischen", "über", "thema", "richtig", "zuruf", "darüber", "stellen", "worden", "beispiel", "andere", "unserem", 
    "dort", "kollegin", "debatte", "einfach", "endlich", "ihren", "ihrem", "eben", "weiter", "kommt", "recht", "klar", 
    "nämlich", "spd-fraktion", "fraktionslos", "bitte", "ende", "schluss", "redner", "rednerin", "wichtig"
];

?>