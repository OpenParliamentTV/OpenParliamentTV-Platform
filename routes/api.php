<?php defined('OPTV') or die();
/**
 * API Route Definitions
 *
 * Mirrors routes/web.php, but for /api/v1. Each route maps a path onto the
 * `action` / `itemType` (plus any extracted parameters) that the shared registry
 * in api/v1/registry.php dispatches on.
 *
 * Two properties of this table are load-bearing:
 *
 * 1. **Query-string parameters win.** `?itemType=x` overrides the path-derived
 *    value, because api/v1/index.php merges path params at the lowest
 *    precedence. This is what lets `?type=` select the entityDump slice.
 *
 * 2. **The generic routes are lenient about trailing segments.** The
 *    `[/{rest:.+}]` suffixes mean `/person/Q77195/junk` serves the person
 *    rather than 404ing. The IIIF, entityDump and transcript routes are strict.
 *
 * Method is not part of the routing decision — the API dispatches on
 * parameters — so index.php dispatches every request as GET.
 */

use FastRoute\RouteCollector;

/** Actions addressed as `/{action}/{itemType}`; everything else is a getItem. */
const API_ROUTE_ACTIONS = 'search|autocomplete|statistics|user|index|status|lang|import|externalData|alert|notification|playlist|systemMessage|migration';

return function (RouteCollector $r) {

    // Every route yields a parameter array; index.php merges it under $_GET.
    $map = function (string $path, array $params) use ($r) {
        $r->addRoute('GET', $path, $params);
    };

    // ---- IIIF (anchored in the original rules) ----
    $map('/iiif/search', ['action' => 'iiif', 'itemType' => 'search']);
    $map('/iiif/collection/{parliament}', ['action' => 'iiif', 'itemType' => 'collection']);
    $map('/iiif/collection/{parliament}/{electoralPeriod}', ['action' => 'iiif', 'itemType' => 'collection']);

    // ---- Bulk entity export (anchored). The path segment is the selector; a
    // legacy ?type= still wins because query params take precedence. ----
    $map('/entityDump', ['action' => 'entityDump', 'itemType' => 'all']);
    $map('/entityDump/{itemType}', ['action' => 'entityDump']);

    // ---- WebVTT transcript for a media item (anchored) ----
    $map('/media/{id}/transcript.vtt', ['action' => 'transcript', 'itemType' => 'vtt']);

    // ---- Statistics entity paths (unanchored; the 4-segment form wins) ----
    $map('/statistics/entity/{entityType}/{entityID}[/{rest:.+}]', ['action' => 'statistics', 'itemType' => 'entity']);
    $map('/statistics/entity/{entityType}', ['action' => 'statistics', 'itemType' => 'entity']);

    // ---- `status` with no itemType defaulted to "all" ----
    $map('/status', ['action' => 'status', 'itemType' => 'all']);

    // ---- The named actions: /{action}/{itemType} ----
    $map('/{action:' . API_ROUTE_ACTIONS . '}/{itemType}[/{rest:.+}]', []);

    // ---- Everything else with two segments is a getItem ----
    $map('/{itemType}/{id}[/{rest:.+}]', ['action' => 'getItem']);

    // ---- A single segment is a bare action (and thus has no itemType, which
    // is what produced the "missing parameter itemType" error) ----
    $map('/{action}', []);
};
