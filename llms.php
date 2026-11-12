<?php
/**
 * llms.txt for OpenParliamentTV (https://llmstxt.org): a short Markdown overview
 * that points LLM agents and automated clients to the open-data API instead of
 * the HTML pages. Served via a rewrite, like robots.txt, so the URLs carry the
 * host of the parliament subdomain from config. Exempt from the AI-crawler 403
 * in .htaccess and allowed for those crawlers in robots.txt.
 */

require_once(__DIR__ . '/bootstrap-core.php');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$baseUrl = rtrim($config["dir"]["root"], '/');
?>
# Open Parliament TV

> Video recordings of parliamentary speeches, synchronised with their transcripts and linked to the people, factions, documents and topics involved (identified by Wikidata IDs). Everything shown on this site is available as open data through a public JSON API. If you want the data, please use the API instead of scraping the HTML pages.

## API

- [API documentation](<?= $baseUrl ?>/api): endpoints, parameters and examples
- [OpenAPI specification](<?= $baseUrl ?>/api/openapi.yaml): machine-readable description of every endpoint
- [Search speeches](<?= $baseUrl ?>/api/v1/search/media?q=klima): full-text search over the transcripts, filterable by person, faction, document, topic and date; `/api/v1/search/{people|organisations|documents|terms}` searches the entities
- Single items: any speech, person, organisation, document, term, session, agenda item or electoral period by ID, as `<?= $baseUrl ?>/api/v1/{type}/{id}`
- [Entity dump](<?= $baseUrl ?>/api/v1/entityDump): all people, organisations, documents and terms in one response (ID, label, alternative labels, type)
- Speech formats: `/api/v1/media/{id}/transcript.vtt` (WebVTT), `/api/v1/media/{id}?format=akomantoso` (Akoma Ntoso), `/api/v1/media/{id}?format=parlamint` (ParlaMint TEI), and IIIF manifests (see the OpenAPI specification)

## Usage

- Free to use, no API key needed. Requests are rate-limited per client IP to a few per second; a `429` response means slow down.
- For a higher limit, ask for an API key (see the API documentation) and send it in the `X-API-Key` header. A key raises the limit; it grants no additional access.
- For the complete raw dataset, do not page through the API: all raw speech data is published in the [Open Parliament TV data repositories on GitHub](https://github.com/OpenParliamentTV/).
- Data license: [ODC Open Database License (ODbL) v1.0](https://opendatacommons.org/licenses/odbl/1-0/)
