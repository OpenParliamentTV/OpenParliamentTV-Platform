<?php
class SPARQLQueryDispatcher
{
    private $endpointUrl;

    public function __construct(string $endpointUrl)
    {
        $this->endpointUrl = $endpointUrl;
    }

    public function query(string $sparqlQuery): array
    {

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/sparql-results+json',
                    'User-Agent: WDQS-example PHP/' . PHP_VERSION, // TODO adjust this; see https://w.wiki/CX6
                ],
            ],
        ];
        $context = stream_context_create($opts);

        $url = $this->endpointUrl . '?query=' . urlencode($sparqlQuery);
        $response = file_get_contents($url, false, $context);
        return json_decode($response, true);
    }
}

function mb_ucfirst($string, $encoding = 'UTF-8'){
    $strlen = mb_strlen($string, $encoding);
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, $strlen - 1, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}

function getFormatedEntry ($id = false) {
    if (!$id) {
        return false;
    }

    $endpointUrl = 'https://query.wikidata.org/sparql';
    $sparqlQueryString = <<<HERE

SELECT DISTINCT ?person ?personLabel ?altLabel ?affiliation ?abstract ?dateOfBirth ?dateOfDeath ?abgeordnetenwatchID ?thumbnailURI ?party ?gender ?websiteURI ?instagram ?facebook ?twitter WITH {
        SELECT ?person WHERE {
            VALUES ?person { wd:$id } .
            ?person ?p ?o
        } LIMIT 10 } AS %i
    WHERE {
        INCLUDE %i
        OPTIONAL { ?person wdt:P1416 ?affiliation. }
        OPTIONAL { ?person skos:altLabel ?altLabel. FILTER (lang(?altLabel) = "de") }
        OPTIONAL { ?person wdt:P569 ?dateOfBirth. }
        OPTIONAL { ?person wdt:P570 ?dateOfDeath. }
        OPTIONAL { ?person wdt:P5355 ?abgeordnetenwatchID. }
        OPTIONAL {
            ?person wdt:P18 ?image_.
            BIND(REPLACE(wikibase:decodeUri(STR(?image_)), "http://commons.wikimedia.org/wiki/Special:FilePath/", "") AS ?imageFileName_)
            BIND(REPLACE(?imageFileName_, " ", "_") AS ?imageFileNameSafe_)
            BIND(MD5(?imageFileNameSafe_) AS ?imageFileNameHash_)
            BIND(CONCAT("https://upload.wikimedia.org/wikipedia/commons/thumb/", SUBSTR(?imageFileNameHash_, 1 , 1 ), "/", SUBSTR(?imageFileNameHash_, 1 , 2 ), "/", ?imageFileNameSafe_, "/300px-", ?imageFileNameSafe_) AS ?thumbnailURI)
        }
        OPTIONAL { ?person p:P102 ?partyStatement_. ?partyStatement_ ps:P102 ?party.  OPTIONAL {?party wdt:P576 ?partyEndDate_.} }
        FILTER('1949-01-01'^^xsd:dateTime <= ?partyEndDate_ || !BOUND(?partyEndDate_)).
        OPTIONAL {
            ?person wdt:P21 ?gender_. ?gender_ rdfs:label ?genderLabel_.
            FILTER(lang(?genderLabel_) = "en").
        }
        BIND(IF(BOUND(?genderLabel_ ), ?genderLabel_, "unknown") AS ?gender).
        OPTIONAL { ?person wdt:P856 ?websiteURI. }
        OPTIONAL { ?person wdt:P2003 ?instagram. }
        OPTIONAL { ?person wdt:P2013 ?facebook. }
        OPTIONAL { ?person wdt:P2002 ?twitter. }
        SERVICE wikibase:label { bd:serviceParam wikibase:language "de". ?person rdfs:label ?personLabel. ?person schema:description ?abstract. }
        }
LIMIT 10
HERE;

    $queryDispatcher = new SPARQLQueryDispatcher($endpointUrl);
    $queryResult = $queryDispatcher->query($sparqlQueryString);

    $findings = array();
    foreach ($queryResult["results"]["bindings"] as $finding) {
        $newPerson = array();
        foreach ($finding as $label=>$value) {
            switch ($label) {
                case "dateOfBirth":
                    $tmpVal= preg_split("~T~",$value["value"]);
                    array_pop($tmpVal);
                    $newPerson["birthDate"]=$tmpVal;
                    break;
                case "personLabel":
                    $newPerson["label"]=$value["value"];
                    break;
                case "abgeordnetenwatchID":
                    $newPerson["additionalInformation"][$label]=$value["value"];
                    break;
                case "party":
                    $newPerson["partyID"] = array_pop(preg_split("~\/~",$finding["party"]["value"]));
                    break;
                case "twitter":
                case "instagram":
                case "facebook":
                    $newPerson["socialMediaIDs"][] = array("label"=>mb_ucfirst($label),"id"=>$value["value"]);
                    break;
                default:
                    $newPerson[$label]=$value["value"];
                    break;

                //TODO: factionID
                //TODO: thumbnailCreator
                //TODO: thumbnailLicense
                //TODO: type
            }

        }
        $newPerson["id"] = array_pop(preg_split("~\/~",$finding["person"]["value"]));

        if (!$newPerson["additionalInformation"]) {
            $newPerson["additionalInformation"] = array();
        }

        $findings[$newPerson["id"]] = (is_array($findings[$newPerson["id"]]) ? array_merge_recursive($findings[$newPerson["id"]], $newPerson) : $newPerson);
        $findings[$newPerson["id"]] = array_uniquify($findings[$newPerson["id"]]);

    }
    return $findings;

}

function array_uniquify($array) {
    $return = array();
    foreach ($array as $k=>$v) {
        if (is_array($v)) {
            $return[$k] = array_uniquify($v);
        } else {
            if (!in_array($v,$return)) {
                $return[$k]=$v;
            }
        }
    }
    return $return;
}

function searchPersonAtWikidata($name) {
    if ($name) {

        $url = "https://www.wikidata.org/w/api.php?action=wbsearchentities&search=".urlencode($name)."&format=json&errorformat=plaintext&language=de&uselang=de&type=item";

        $wbsearch = json_decode(file_get_contents($url),true);

        if ($wbsearch["search"][0]["id"]) {

            $findings = getFormatedEntry($wbsearch["search"][0]["id"]);
            return $findings;

        }

    }
}


?>
