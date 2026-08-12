<?php
require_once(__DIR__ . "/../bootstrap-cli.php");
require_once(__DIR__ . "/../modules/entities/media.php");
require_once(__DIR__ . "/../modules/statistics-index/aggregation.php");

/**
 * Statistics index reference check.
 *
 * Recomputes what the statistics index SHOULD contain for a set of speeches --
 * straight from the media nodes in the parliament database -- and diffs that
 * against what it actually contains.
 *
 * The comparison deliberately crosses the database -> index boundary rather than
 * re-reading the search index the indexer itself reads, so it catches a stale or
 * half-written statistics index, not just a disagreement about the word rules.
 *
 *   php bin/verify-statistics.php --session=DE-0210010
 *   php bin/verify-statistics.php --session=DE-0210010,DE-0210011 --verbose
 *   php bin/verify-statistics.php --media-ids=DE-0210010006,DE-0210010007
 *   php bin/verify-statistics.php --parliament=DE --limit=200
 *
 * Exit codes: 0 everything matches, 1 mismatches found, 2 bad usage.
 */

$opts = getopt("", ["parliament:", "session:", "media-ids:", "limit:", "verbose", "help"]);

if (isset($opts["help"])) {
    fwrite(STDOUT, "Usage: php bin/verify-statistics.php [--parliament=XX] "
        . "[--session=ID[,ID...]] [--media-ids=ID[,ID...]] [--limit=N] [--verbose]\n");
    exit(0);
}

$parliament = resolveParliamentKey($opts["parliament"] ?? null);
$verbose    = isset($opts["verbose"]);
$limit      = isset($opts["limit"]) ? (int) $opts["limit"] : 0;

$sessionIds = isset($opts["session"]) ? array_filter(explode(",", $opts["session"])) : [];
$mediaIds   = isset($opts["media-ids"]) ? array_filter(explode(",", $opts["media-ids"])) : [];

if (empty($sessionIds) && empty($mediaIds) && $limit <= 0) {
    fwrite(STDERR, "error: give --session, --media-ids or --limit\n");
    exit(2);
}

try {
    $dbp = optvDbConnection("parliament", $parliament);
    $db  = optvDbConnection("platform");
} catch (OptvDbException $e) {
    fwrite(STDERR, "error: database unavailable: " . $e->getMessage() . "\n");
    exit(2);
}

$mediaTable      = $config["parliament"][$parliament]["sql"]["tbl"]["Media"];
$agendaItemTable = $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"];

// Resolve the set of media ids to check.
if (empty($mediaIds)) {
    if (!empty($sessionIds)) {
        // Media hangs off an agenda item, which is what carries the session.
        $mediaIds = $dbp->getCol(
            "SELECT m.MediaID FROM ?n AS m
                JOIN ?n AS ai ON ai.AgendaItemID = m.MediaAgendaItemID
                WHERE ai.AgendaItemSessionID IN (?a)
                ORDER BY m.MediaID",
            $mediaTable,
            $agendaItemTable,
            $sessionIds
        );
    } else {
        $mediaIds = $dbp->getCol(
            "SELECT MediaID FROM ?n ORDER BY MediaID LIMIT ?i",
            $mediaTable,
            $limit
        );
    }
} elseif ($limit > 0) {
    $mediaIds = array_slice($mediaIds, 0, $limit);
}

if (empty($mediaIds)) {
    fwrite(STDERR, "error: no media found for the given selection\n");
    exit(2);
}

$indexName = getStatisticsIndexName($parliament);
$client    = getApiOpenSearchClient();

if (is_array($client) && isset($client["errors"])) {
    fwrite(STDERR, "error: OpenSearch unavailable\n");
    exit(2);
}

fwrite(STDOUT, "parliament : {$parliament}\n");
fwrite(STDOUT, "index      : {$indexName}\n");
fwrite(STDOUT, "media      : " . count($mediaIds) . "\n\n");

$checked = 0;
$matched = 0;
$mismatches = [];
$skipped = 0;

foreach (array_chunk($mediaIds, 50) as $chunk) {
    // Expected: build from the database, exactly as the indexer would.
    $nodes = [];
    foreach ($chunk as $mediaId) {
        try {
            $node = mediaGetNode($mediaId, $db, $dbp);
        } catch (Exception $e) {
            // A non-public speech is not indexed at all; nothing to verify.
            $skipped++;
            continue;
        }
        if ($node === null) {
            $skipped++;
            continue;
        }
        $node["id"] = $mediaId;
        $nodes[] = $node;
    }

    if (empty($nodes)) {
        continue;
    }

    $expectedDocs = statsIndexBuildBatchDocs($nodes, $parliament);

    $expected = [];
    foreach ($expectedDocs as $entry) {
        $expected[$entry["doc"]["speech_id"]][$entry["_id"]] = $entry["doc"];
    }

    // Actual: everything the index holds for these speeches.
    $actual = [];
    $from = 0;
    do {
        $response = $client->search([
            "index" => $indexName,
            "body"  => [
                "size"  => 1000,
                "from"  => $from,
                "query" => ["terms" => ["speech_id" => array_column($nodes, "id")]],
            ],
        ]);
        foreach ($response["hits"]["hits"] as $hit) {
            $actual[$hit["_source"]["speech_id"]][$hit["_id"]] = $hit["_source"];
        }
        $from += 1000;
    } while ($from < ($response["hits"]["total"]["value"] ?? 0) && $from < 10000);

    foreach ($nodes as $node) {
        $checked++;
        $id  = $node["id"];
        $exp = $expected[$id] ?? [];
        $act = $actual[$id] ?? [];

        $problems = statsVerifyDiff($exp, $act);

        if (empty($problems)) {
            $matched++;
            continue;
        }

        $mismatches[$id] = $problems;

        fwrite(STDOUT, "MISMATCH {$id}\n");
        foreach (array_slice($problems, 0, $verbose ? 100 : 5) as $problem) {
            fwrite(STDOUT, "    {$problem}\n");
        }
        if (!$verbose && count($problems) > 5) {
            fwrite(STDOUT, "    ... " . (count($problems) - 5) . " more (use --verbose)\n");
        }
    }
}

/**
 * Compare expected and actual documents for one speech.
 *
 * @return array Human-readable problem descriptions; empty when they agree.
 */
function statsVerifyDiff(array $expected, array $actual)
{
    $problems = [];

    foreach ($expected as $docId => $doc) {
        if (!isset($actual[$docId])) {
            $problems[] = "missing: " . statsVerifyLabel($doc);
            continue;
        }

        foreach (["word", "stem", "count", "token_count", "counted_word_count",
                  "procedural", "faction_id", "speaker_id", "session_id",
                  "agenda_item_id", "electoral_period_id", "date_string"] as $field) {
            if (!array_key_exists($field, $doc)) {
                continue;
            }
            $want = $doc[$field];
            $have = $actual[$docId][$field] ?? null;
            if ($want != $have) {
                $problems[] = statsVerifyLabel($doc) . ": {$field} expected "
                    . var_export($want, true) . ", got " . var_export($have, true);
            }
        }
    }

    foreach ($actual as $docId => $doc) {
        if (!isset($expected[$docId])) {
            $problems[] = "stale: " . statsVerifyLabel($doc);
        }
    }

    return $problems;
}

/** Short identifier for a document, for the report. */
function statsVerifyLabel(array $doc)
{
    $scope = !empty($doc["procedural"]) ? "procedural" : "main";

    if (($doc["aggregation_type"] ?? "") === "speech_totals") {
        return "totals[{$scope}]";
    }

    return "word[{$scope}] '" . ($doc["word"] ?? "?") . "'";
}

fwrite(STDOUT, "\n");
fwrite(STDOUT, "checked   : {$checked}\n");
fwrite(STDOUT, "matched   : {$matched}\n");
fwrite(STDOUT, "mismatched: " . count($mismatches) . "\n");
if ($skipped > 0) {
    fwrite(STDOUT, "skipped   : {$skipped} (not retrievable / not public)\n");
}

if (!empty($mismatches)) {
    fwrite(STDOUT, "\nFAIL\n");
    exit(1);
}

fwrite(STDOUT, "\nOK\n");
exit(0);
