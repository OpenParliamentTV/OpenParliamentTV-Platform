<?php
/**
 * Mapping and analysis settings for the main search index
 * (openparliamenttv_<parliament>), passed verbatim to indices()->create().
 *
 * Index-time only: an existing index is never re-mapped from here, so changing
 * anything below takes effect on the next full rebuild
 * (?action=index&itemType=full-update), not on an incremental update.
 */

/** @return array The `body` for an OpenSearch index-creation call. */
function searchIndexMappingBody() {
    $data = array();

    $data["mappings"] = array("properties" => array(
        "attributes" => array("properties" => array(
            "textContents" => array("properties" => array(
                "textHTML" => array(
                    "type" => "text",
                    "analyzer" => "html_analyzer",
                    "search_analyzer" => "standard",
                    "fielddata" => true,
                    "fields" => array(
                        "keyword" => array(
                            "type" => "keyword",
                            "ignore_above" => 256
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
                    ),
                    "attributes" => array("properties" => array(
                        "title" => array(
                            "type" => "text",
                            "analyzer" => "agenda_item_analyzer",
                            "search_analyzer" => "standard",
                            "fields" => array(
                                "keyword" => array(
                                    "type" => "keyword",
                                    "ignore_above" => 256
                                )
                            )
                        ),
                        "officialTitle" => array(
                            "type" => "text",
                            "analyzer" => "agenda_item_analyzer",
                            "search_analyzer" => "standard",
                            "fields" => array(
                                "keyword" => array(
                                    "type" => "keyword",
                                    "ignore_above" => 256
                                )
                            )
                        )
                    ))
                ))
            )),
            // Replicates the dynamic mapping of existing indices (text with
            // .keyword subfield) so the media procedureID filter — a term
            // query on ...procedureIDs.id.keyword — also works on fresh indices.
            "documents" => array("properties" => array(
                "data" => array("properties" => array(
                    "attributes" => array("properties" => array(
                        "additionalInformation" => array("properties" => array(
                            "procedureIDs" => array("properties" => array(
                                "id" => array(
                                    "type" => "text",
                                    "fields" => array(
                                        "keyword" => array(
                                            "type" => "keyword",
                                            "ignore_above" => 256
                                        )
                                    )
                                )
                            ))
                        ))
                    ))
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
                    "attributes" => array("properties" => array(
                        "context" => array(
                            "type" => "keyword"
                        )
                    )),
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
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),
                "html_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["lowercase", "custom_synonyms"]
                ),

                "agenda_item_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "filter" => ["lowercase", "custom_stemmer", "custom_synonyms"]
                ),

            ),

            "char_filter" => array(
                "custom_html_strip" => array(
                    "type" => "pattern_replace",
                    "pattern" => "<\w+\s[^>]+></\w+>", 
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
