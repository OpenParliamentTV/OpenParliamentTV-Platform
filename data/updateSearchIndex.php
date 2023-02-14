<?php
session_start();
//include_once(__DIR__ . '/../modules/utilities/auth.php');



function updateSearchIndex($parliament, $items, $initIndex = false) {
    //print_r($items);

    if (!$parliament || !is_array($items) || count($items) < 1) {
        echo "no!".$parliament.count($items);
        return false;

    }
    require_once(__DIR__ . '/../config.php');
    require_once (__DIR__."/../vendor/autoload.php");
    global $config;

    set_time_limit(0);
    ini_set('memory_limit', '500M');
    date_default_timezone_set('CET');

    $ESClientBuilder = Elasticsearch\ClientBuilder::create();

    if ($config["ES"]["hosts"]) {
        $ESClientBuilder->setHosts($config["ES"]["hosts"]);
    }
    if ($config["ES"]["BasicAuthentication"]["user"]) {
        $ESClientBuilder->setBasicAuthentication($config["ES"]["BasicAuthentication"]["user"],$config["ES"]["BasicAuthentication"]["passwd"]);
    }
    if ($config["ES"]["SSL"]["pem"]) {
        $ESClientBuilder->setSSLVerification($config["ES"]["SSL"]["pem"]);
    }
    $ESClient = $ESClientBuilder->build();

    /**
     * Set Index structure if parameter 3 is true
     */

    if ($initIndex == true) {

        $indexParams = array(
            "index" => "openparliamenttv_" . strtolower($config["parliament"][$parliament]["ES"]["index"]),
            "body" => getSearchIndexParameter()
        );

        try {
            $ESClient->indices()->create($indexParams);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    $return["updated"] = 0;

    foreach ($items as $item) {
        $docParams = array(
            "index" => "openparliamenttv_" . strtolower($config["parliament"][$parliament]["ES"]["index"]),
            "id" => $item["data"]["id"],
            "body" => json_encode($item["data"])
        );
        //print_r($docParams);

        try {
            $result = $ESClient->index($docParams);
            print_r($result);
            $return["updated"]++;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    return $return["updated"];

}


function getSearchIndexParameter()
{
    $data = array();

    $data["mappings"] = array("properties" => array(
        "attributes" => array("properties" => array(
            "textContents" => array("properties" => array(
                "textHTML" => array(
                    "type" => "text",
                    "analyzer" => "html_analyzer",
                    "search_analyzer" => "standard",
                    "fields" => array(
                        "autocomplete" => array(
                            "analyzer" => "autocomplete_html_analyzer",
                            "type" => "text"
                        )
                    )
                )
            ))
        )),
        "relationships" => array("properties" => array(
            "electoralPeriod" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    )
                ))
            )),
            "session" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    )
                ))
            )),
            "agendaItem" => array("properties" => array(
                "data" => array("properties" => array(
                    "id" => array(
                        "type" => "keyword"
                    )
                ))
            )),
            "people" => array("properties" => array(
                "data" => array(
                    "type" => "nested",
                    "properties" => array(
                        "attributes" => array("properties" => array(
                            "context" => array(
                                "type" => "keyword"
                            )
                        ))
                    ))
            )
            ),
            "organisations" => array("properties" => array(
                "data" => array(
                    "type" => "nested",
                    "properties" => array(
                        "attributes" => array("properties" => array(
                            "context" => array(
                                "type" => "keyword"
                            )
                        ))
                    ))
            )
            )),
        ),
        "annotations" => array("properties" => array(
            "data" => array(
                "type" => "nested",
                "properties" => array(
                    "context" => array(
                        "type" => "keyword"
                    ),
                    "id" => array(
                        "type" => "keyword"
                    )
                )
            )
        ))
    ));

    $data["settings"] = array(
        "index" => array("max_ngram_diff" => 20),
        "number_of_replicas" => 0,
        "number_of_shards" => 2,
        "analysis" => array(
            "analyzer" => array(
                "default" => array(
                    "type" => "custom",
                    //"tokenizer"=>"nGramTokenizer",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),
                "html_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    //"filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                    "filter" => ["lowercase", "custom_synonyms"]
                ),
                "autocomplete_html_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["custom_stopwords", "lowercase", "custom_synonyms"]
                )
            ),
            "char_filter" => array(
                "custom_html_strip" => array(
                    "type" => "pattern_replace",
                    "pattern" => "<\w+\s[^>]+>|</\w+>",
                    "replacement" => " "
                )
            ),
            "filter" => array(
                "custom_stopwords" => array(
                    "type" => "stop",
                    "ignore_case" => true,
                    "stopwords" => "_german_"
                ),
                "custom_stemmer" => array(
                    "type" => "stemmer",
                    "name" => "light_german"
                ),
                "custom_synonyms" => array(
                    "type" => "synonym_graph",
                    "lenient" => true,
                    "synonyms_path" => "analysis/synonyms.txt"
                )
            )
        )
    );
    return $data;

}




?>