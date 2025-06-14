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
        "termID",
        "fields"
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