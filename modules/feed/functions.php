<?php
/**
 * Public RSS / Atom feed generation.
 *
 * Feeds are stateless, public and read-only. Speech feeds reuse the existing
 * OpenSearch-backed media search (mediaSearch() -> searchSpeeches()); the
 * sessions feed queries the parliament DB directly (sessionGetItemsFromDB()).
 *
 * Non-public media are excluded automatically because we never set
 * "includeAll" on the search request (see modules/search/functions.php).
 */

require_once(__DIR__ . "/../../api/v1/api.php");
require_once(__DIR__ . "/../../api/v1/modules/media.php");
require_once(__DIR__ . "/../../api/v1/modules/session.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/functions.entities.php");
require_once(__DIR__ . "/../i18n/language.php");

/** Number of items per feed. */
const FEED_ITEM_LIMIT = 50;

/** Escape a string for safe inclusion in XML text/attribute content. */
function feedXml($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_XML1, "UTF-8");
}

/** Map of entity feed type => media search filter parameter. */
function feedEntityFilterMap() {
    return [
        "person"       => "personID",
        "organisation" => "organisationID",
        "term"         => "termID",
        "document"     => "documentID",
        "session"      => "sessionID",
        "agendaItem"   => "agendaItemID",
    ];
}

/**
 * Absolute base URL for the instance. Uses $config["dir"]["root"] when it is a
 * full URI, otherwise reconstructs scheme + host from the current request so
 * feed links/GUIDs are always absolute.
 */
function feedBaseUrl() {
    global $config;
    $root = isset($config["dir"]["root"]) ? rtrim($config["dir"]["root"], "/") : "";
    if ($root === "" || stripos($root, "http") !== 0) {
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host   = $_SERVER["HTTP_HOST"] ?? "localhost";
        $root   = $scheme . "://" . $host . $root;
    }
    return $root;
}

/**
 * Main entry point. Returns the rendered XML feed as a string.
 */
function generateFeed($feedType, $params, $format = "rss") {
    $meta = getFeedMeta($feedType, $params);

    if ($feedType === "sessions") {
        $items = fetchRecentSessions($params);
    } else {
        $items = fetchFeedSpeeches($feedType, $params);
    }

    $template = ($format === "atom") ? "atom" : "rss";
    return renderFeedTemplate($template, $meta, $items);
}

/**
 * Renders one of the XML templates with $meta and $items in scope.
 */
function renderFeedTemplate($template, $meta, $items) {
    ob_start();
    include(__DIR__ . "/templates/" . $template . ".php");
    return ob_get_clean();
}

/**
 * Build channel/feed metadata (title, link, description, self URL, language).
 */
function getFeedMeta($feedType, $params) {
    global $config, $lang;

    $baseUrl  = feedBaseUrl();
    $language = isset($lang) && $lang ? $lang : LanguageManager::getInstance()->getCurrentLang();
    $brand    = L::brand();
    $id       = $params["id"] ?? null;

    // Defaults (global media feed).
    $title       = $brand . " — " . L::feedNewSpeeches();
    $description = L::feedNewSpeechesDescription();
    $link        = $baseUrl . "/search";
    $selfUrl     = $baseUrl . "/feed/media";

    switch ($feedType) {
        case "sessions":
            $title       = $brand . " — " . L::feedNewSessions();
            $description = L::feedNewSessionsDescription();
            $link        = $baseUrl;
            $selfUrl     = $baseUrl . "/feed/sessions";
            break;

        case "person":
        case "organisation":
        case "session":
        case "agendaItem":
        case "term":
        case "document":
            $label   = $id ? feedEntityLabel($feedType, $id) : "";
            $link    = $baseUrl . "/" . $feedType . "/" . rawurlencode($id);
            $selfUrl = $baseUrl . "/feed/" . $feedType . "/" . rawurlencode($id);
            if (in_array($feedType, ["term", "document", "agendaItem"], true)) {
                $title       = $brand . " — " . L::feedSpeechesOn() . " " . $label;
                $description = L::feedSpeechesOn() . " " . $label;
            } elseif ($feedType === "session") {
                $title       = $brand . " — " . $label;
                $description = L::feedNewSpeechesDescription();
            } else {
                $title       = $brand . " — " . L::feedSpeechesBy() . " " . $label;
                $description = L::feedSpeechesBy() . " " . $label;
            }
            break;

        case "search":
            $queryStr    = feedSearchQueryString($params);
            $summary     = feedSearchSummary($params);
            $title       = $brand . " — " . L::search() . ($summary ? ": " . $summary : "");
            $description = $title;
            $link        = $baseUrl . "/search" . $queryStr;
            $selfUrl     = $baseUrl . "/feed/search" . $queryStr;
            break;

        case "media":
        default:
            // defaults already set
            break;
    }

    return [
        "title"          => $title,
        "description"    => $description,
        "link"           => $link,
        "selfUrl"        => $selfUrl,
        "language"       => $language,
        "lastBuildDate"  => date(DATE_RSS),
        "lastBuildAtom"  => date(DATE_ATOM),
        "imageUrl"       => $baseUrl . "/content/client/images/optv-mark.png",
        "imageLink"      => $baseUrl,
        "xslUrl"         => $baseUrl . "/content/client/feed.xsl",
        "brand"          => $brand,
    ];
}

/**
 * Resolve a human-readable label for an entity feed via the existing API.
 */
function feedEntityLabel($feedType, $id) {
    $item = apiV1(["action" => "getItem", "itemType" => $feedType, "id" => $id]);
    if (!is_array($item) || ($item["meta"]["requestStatus"] ?? "") === "error") {
        return $id;
    }
    $attr = $item["data"]["attributes"] ?? [];
    if (!empty($attr["label"]))  return $attr["label"];
    if (!empty($attr["title"]))  return $attr["title"];
    if ($feedType === "session" && isset($attr["number"])) {
        return L::session() . " " . $attr["number"];
    }
    return $id;
}

/**
 * Fetch the most recent media items for a speech feed and map them to feed items.
 *
 * Calls mediaSearch() directly with a controlled parameter array (rather than
 * apiV1(), which force-merges $_GET) so the entity feed's ?id= does not leak
 * into the media search as a media-ID filter.
 */
function fetchFeedSpeeches($feedType, $params) {
    $request = [];

    if ($feedType === "search") {
        $request = filterAllowedSearchParams($params, "media");
        // Drop anything that would override our feed presentation.
        unset($request["limit"], $request["page"], $request["sort"],
              $request["includeAll"], $request["public"], $request["getAllResults"], $request["fields"]);
    } else {
        $map = feedEntityFilterMap();
        if (isset($map[$feedType]) && !empty($params["id"])) {
            $request[$map[$feedType]] = $params["id"];
        }
        // "media" (global) => no filters
    }

    $request["action"]   = "search";
    $request["itemType"] = "media";
    $request["sort"]     = "date-desc";
    $request["limit"]    = FEED_ITEM_LIMIT;
    $request["page"]     = 1;

    $result = mediaSearch($request);

    $items   = [];
    $baseUrl = feedBaseUrl();
    if (!isset($result["data"]) || !is_array($result["data"])) {
        return $items;
    }
    foreach ($result["data"] as $d) {
        $items[] = mapMediaItemToFeed($d, $baseUrl);
    }
    return $items;
}

/**
 * Map one media search result item to a normalized feed item.
 */
function mapMediaItemToFeed($d, $baseUrl) {
    $attr  = $d["attributes"] ?? [];
    $rel   = $d["relationships"] ?? [];
    $annos = $d["annotations"]["data"] ?? [];

    $mainSpeaker = getMainSpeakerFromPeopleArray($annos, $rel["people"]["data"] ?? []);
    $mainFaction = getMainFactionFromOrganisationsArray($annos, $rel["organisations"]["data"] ?? []);

    $speakerName = $mainSpeaker ? ($mainSpeaker["attributes"]["label"] ?? "") : "";
    $factionName = $mainFaction ? ($mainFaction["attributes"]["label"] ?? "") : "";
    $agendaTitle = $rel["agendaItem"]["data"]["attributes"]["title"] ?? "";
    $sessionNo   = $rel["session"]["data"]["attributes"]["number"] ?? null;
    $epNo        = $rel["electoralPeriod"]["data"]["attributes"]["number"] ?? null;
    $parliament  = $attr["parliamentLabel"] ?? "";
    $dateStart   = $attr["dateStart"] ?? null;

    // Title: "Speaker (Faction) — Agenda item"
    $titleParts = $speakerName;
    if ($factionName) {
        $titleParts .= " (" . $factionName . ")";
    }
    if ($agendaTitle) {
        $titleParts = $titleParts ? $titleParts . " — " . $agendaTitle : $agendaTitle;
    }
    if ($titleParts === "") {
        $titleParts = $d["id"] ?? L::speeches();
    }

    // Description: session/EP context + transcript excerpt.
    $context = [];
    if ($sessionNo !== null) $context[] = L::session() . " " . $sessionNo;
    if ($epNo !== null)      $context[] = $epNo . ". " . L::electoralPeriod();
    if ($parliament)         $context[] = $parliament;
    $contextStr = implode(", ", $context);

    $excerpt = buildExcerpt($attr["textContents"] ?? []);
    $description = $excerpt ? ($contextStr ? $contextStr . " — " . $excerpt : $excerpt) : $contextStr;

    $url = $baseUrl . "/media/" . rawurlencode($d["id"] ?? "");

    return [
        "title"       => $titleParts,
        "link"        => $url,
        "guid"        => $url,
        "pubDateRss"  => $dateStart ? date(DATE_RSS, strtotime($dateStart)) : date(DATE_RSS),
        "pubDateAtom" => $dateStart ? date(DATE_ATOM, strtotime($dateStart)) : date(DATE_ATOM),
        "author"      => $speakerName,
        "category"    => $factionName,
        "description" => $description,
    ];
}

/**
 * Build a plain-text transcript excerpt (~300 chars) from textContents.
 */
function buildExcerpt($textContents, $maxLen = 300) {
    if (!is_array($textContents) || empty($textContents)) {
        return "";
    }
    $text = "";
    foreach ($textContents as $tc) {
        if (!empty($tc["textHTML"])) {
            $text .= " " . $tc["textHTML"];
        }
    }
    $text = trim(preg_replace('/\s+/', " ", strip_tags($text)));
    if ($text === "") {
        return "";
    }
    if (function_exists("mb_strlen") && mb_strlen($text) > $maxLen) {
        return rtrim(mb_substr($text, 0, $maxLen)) . "…";
    } elseif (strlen($text) > $maxLen) {
        return rtrim(substr($text, 0, $maxLen)) . "…";
    }
    return $text;
}

/**
 * Fetch the most recent sessions across all configured parliaments.
 */
function fetchRecentSessions($params) {
    $result = sessionGetItemsFromDB("all", FEED_ITEM_LIMIT, 0, false, "SessionDateStart", "DESC");

    $items   = [];
    $baseUrl = feedBaseUrl();
    if (!isset($result["data"]) || !is_array($result["data"])) {
        return $items;
    }
    foreach ($result["data"] as $s) {
        $sessionNo  = $s["SessionNumber"] ?? "";
        $epNo       = $s["ElectoralPeriodNumber"] ?? "";
        $parliament = $s["ParliamentLabel"] ?? "";
        $dateStart  = $s["SessionDateStart"] ?? null;

        $titleParts = [L::session() . " " . $sessionNo];
        if ($epNo !== "") $titleParts[] = $epNo . ". " . L::electoralPeriod();
        if ($parliament)  $titleParts[] = $parliament;

        $url = $baseUrl . "/session/" . rawurlencode($s["SessionID"] ?? "");

        $items[] = [
            "title"       => implode(" — ", $titleParts),
            "link"        => $url,
            "guid"        => $url,
            "pubDateRss"  => $dateStart ? date(DATE_RSS, strtotime($dateStart)) : date(DATE_RSS),
            "pubDateAtom" => $dateStart ? date(DATE_ATOM, strtotime($dateStart)) : date(DATE_ATOM),
            "author"      => "",
            "category"    => "",
            "description" => $parliament,
        ];
    }
    return $items;
}

/**
 * Build the query string (allowed media search params) for a search feed's
 * self/page links. Returns "" or "?key=value&...".
 */
function feedSearchQueryString($params) {
    $allowed = filterAllowedSearchParams($params, "media");
    unset($allowed["limit"], $allowed["page"], $allowed["sort"], $allowed["format"],
          $allowed["feedType"], $allowed["a"], $allowed["includeAll"], $allowed["public"]);
    if (empty($allowed)) {
        return "";
    }
    return "?" . preg_replace('/%5B\d+%5D=/i', '%5B%5D=', http_build_query($allowed));
}

/**
 * Short human-readable summary of the active search filters for the feed title.
 */
function feedSearchSummary($params) {
    $parts = [];
    if (!empty($params["q"])) {
        $parts[] = $params["q"];
    }
    $map = feedEntityFilterMap();
    foreach (["personID" => "person", "organisationID" => "organisation", "termID" => "term", "documentID" => "document"] as $key => $type) {
        if (!empty($params[$key])) {
            $val = is_array($params[$key]) ? $params[$key][0] : $params[$key];
            $parts[] = feedEntityLabel($type, $val);
        }
    }
    return implode(", ", $parts);
}
