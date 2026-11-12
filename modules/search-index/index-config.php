<?php
/**
 * Mapping and analysis settings for the main search index
 * (openparliamenttv_<parliament>), passed verbatim to indices()->create().
 *
 * Index-time only: an existing index is never re-mapped from here, so changing
 * anything below takes effect on the next full rebuild
 * (?action=index&itemType=full-update), not on an incremental update.
 */

/**
 * Shortest and longest word beginning / ending stored in textHTML.prefix and
 * textHTML.suffix. A search word with a stem inside this range ("Klima*",
 * "*gesetz") is answered from those subfields; anything outside it falls back to
 * a wildcard query on the main field (see processFuzzyMatch()).
 */
const SEARCH_INDEX_AFFIX_MIN_CHARS = 2;
const SEARCH_INDEX_AFFIX_MAX_CHARS = 19;

/** @return array The `body` for an OpenSearch index-creation call. */
function searchIndexMappingBody() {
    $data = array();

    $data["mappings"] = array("properties" => array(
        // Speech text with interjections and chair segments stripped out, added
        // at write time by searchIndexWriteItems() rather than by mediaGetNode(),
        // so the public media API response is unchanged. significant_text reads
        // this field: run over textHTML it would rank applause formulas and
        // agenda announcements as a scope's most distinctive vocabulary.
        "cleanText" => array(
            "type" => "text"
        ),
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
                        ),
                        // Every word's beginnings (prefix) and endings (suffix),
                        // so "Klima*" and "*gesetz" are a single term lookup
                        // instead of one clause per matching word, which runs
                        // into indices.query.bool.max_clause_count. Counts only
                        // (no positions, no norms): the score then grows with
                        // the number of hits in a speech, regardless of its length.
                        "prefix" => array(
                            "type" => "text",
                            "analyzer" => "html_prefix_analyzer",
                            "search_analyzer" => "affix_search_analyzer",
                            "index_options" => "freqs",
                            "norms" => false
                        ),
                        "suffix" => array(
                            "type" => "text",
                            "analyzer" => "html_suffix_analyzer",
                            "search_analyzer" => "affix_search_analyzer",
                            "index_options" => "freqs",
                            "norms" => false
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

                // html_analyzer's tokens, expanded into their beginnings / endings
                // (reverse + edge n-grams + reverse yields the endings).
                "html_prefix_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["lowercase", "custom_synonyms", "affix_grams"]
                ),
                "html_suffix_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "standard",
                    "char_filter" => ["custom_html_strip"],
                    "filter" => ["lowercase", "custom_synonyms", "reverse", "affix_grams", "reverse"]
                ),
                // The searched stem is looked up as typed, only lowercased.
                "affix_search_analyzer" => array(
                    "type" => "custom",
                    "tokenizer" => "keyword",
                    "filter" => ["lowercase"]
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
                "affix_grams" => array(
                    "type" => "edge_ngram",
                    "min_gram" => SEARCH_INDEX_AFFIX_MIN_CHARS,
                    "max_gram" => SEARCH_INDEX_AFFIX_MAX_CHARS
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
