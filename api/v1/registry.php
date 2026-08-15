<?php
/**
 * Shared dispatch registry for the v1 API.
 *
 * One table, two consumers: the internal apiV1() facade (api/v1/api.php) and
 * the HTTP routes (routes/api.php). auth() reads the same `access` metadata, so
 * an action's handler and its public/admin status cannot drift apart.
 *
 * Shape:
 *
 *   '<action>' => [
 *       'access'    => 'public' | 'admin',   // 'public' = reachable without a session
 *       'file'      => 'modules/x.php',      // required before dispatch (optional)
 *       'before'    => callable(array $r): array,          // request rewrite (optional)
 *       'itemTypes' => ['<itemType>' => ['file' => …, 'fn' => callable]],
 *       'any'       => ['file' => …, 'fn' => callable],    // instead of itemTypes:
 *                                                          // this action ignores itemType
 *       'invalidItemType' => 'detail message',             // custom 422 detail (optional)
 *   ]
 *
 * Every 'fn' has the signature `function (array $request, $db, $dbp)` and
 * returns exactly what apiV1() returns — i.e. it applies its own envelope via
 * apiResponse(). A few deliberately never return: transcript and entityDump
 * write their body and exit, and the two IIIF entries return a bare Manifest
 * after setting their own Content-Type, all of which bypass the JSON encoder in
 * api/v1/index.php. That asymmetry is pre-existing and frozen by the
 * byte-identical rule, which is why the callables return rather than the
 * registry describing a uniform result type.
 *
 * Paths in 'file' are relative to api/v1/.
 */

require_once(__DIR__ . "/utilities.php");

/**
 * Shared tail of the getItemsFromDB action: every itemType computes a raw
 * ['total','data'] array (or a ready-made error envelope) and this turns it
 * into the response.
 */
function apiRegistryItemsFromDbResponse(array $r, $result)
{
    // A getItemsFromDB function may return a ready-made API error response
    // (e.g. parliament DB unreachable) — pass it through instead of wrapping
    // it as an empty success.
    if (is_array($result) && (($result["meta"]["requestStatus"] ?? null) === "error")) {
        return apiResponse($result);
    }

    if ($result) {
        return apiResponse(
            apiSuccessResponse(
                $r["id"] !== "all" ? ($result["data"][0] ?? null) : $result["data"],
                ["requestStatus" => "success"],
                null,
                null,
                $r["id"] === "all" ? $result["total"] : null
            )
        );
    }

    return apiResponse(
        apiErrorDatabaseError()
    );
}

/** The action table. Built once per request and memoised. */
function apiRegistry()
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    // --- small builders, so the 100+ entries stay readable -------------------

    /** apiResponse(fn($request)) */
    $req = function ($file, $fn) {
        return ['file' => $file, 'fn' => function ($r, $db, $dbp) use ($fn) { return apiResponse($fn($r)); }];
    };
    /** apiResponse(fn($request["id"])) */
    $byId = function ($file, $fn) {
        return ['file' => $file, 'fn' => function ($r, $db, $dbp) use ($fn) { return apiResponse($fn($r["id"])); }];
    };
    /** apiResponse(fn($request, $db)) */
    $reqDb = function ($file, $fn) {
        return ['file' => $file, 'fn' => function ($r, $db, $dbp) use ($fn) { return apiResponse($fn($r, $db)); }];
    };
    /** apiResponse(fn($request, $db, $dbp)) */
    $reqDbp = function ($file, $fn) {
        return ['file' => $file, 'fn' => function ($r, $db, $dbp) use ($fn) { return apiResponse($fn($r, $db, $dbp)); }];
    };
    /** getItemsFromDB entries: the standard six-argument call. */
    $items = function ($file, $fn, $extra = null) {
        return ['file' => $file, 'fn' => function ($r, $db, $dbp) use ($fn, $extra) {
            $args = [$r["id"], $r["limit"], $r["offset"], $r["search"], $r["sort"], $r["order"]];
            if ($extra) {
                $args = array_merge($args, $extra($r));
            }
            return apiRegistryItemsFromDbResponse($r, $fn(...$args));
        }];
    };

    $registry = [

        // =====================================================================
        // Public actions — reachable without a session
        // =====================================================================

        'getItem' => ['access' => 'public', 'itemTypes' => [
            'organisation'    => $byId('modules/organisation.php', 'organisationGetByID'),
            'document'        => $byId('modules/document.php', 'documentGetByID'),
            'term'            => $byId('modules/term.php', 'termGetByID'),
            'person'          => $byId('modules/person.php', 'personGetByID'),
            'session'         => $byId('modules/session.php', 'sessionGetByID'),
            'agendaItem'      => $byId('modules/agenda-item.php', 'agendaItemGetByID'),
            'electoralPeriod' => $byId('modules/electoral-period.php', 'electoralPeriodGetByID'),
            // media is the one getItem that takes the caller's connections.
            'media' => ['file' => 'modules/media.php', 'fn' => function ($r, $db, $dbp) {
                return apiResponse(mediaGetByID($r["id"], $db, $dbp));
            }],
        ]],

        'transcript' => ['access' => 'public', 'file' => 'modules/transcript.php', 'itemTypes' => [
            // Serves text/vtt directly and exits (bypasses the JSON encoder).
            'vtt' => ['fn' => function ($r, $db, $dbp) {
                transcriptServeVTT($r["id"] ?? "", $r["type"] ?? null, $r["lang"] ?? null);
                return null; // unreachable; transcriptServeVTT exits
            }],
        ]],

        // Bulk entity export for NEL pipelines. Serves the dump directly and
        // exits (bypasses the JSON envelope + encoder), because the body must
        // stay byte-identical to the pre-API /data/entity-dump response those
        // pipelines already parse. itemType is the entity selector, not a
        // dispatch key — any value is valid here.
        'entityDump' => ['access' => 'public', 'file' => 'modules/entity-dump.php',
            'any' => ['fn' => function ($r, $db, $dbp) {
                entityDumpServe($r, $db);
                return null; // unreachable; entityDumpServe exits
            }],
        ],

        'iiif' => ['access' => 'public', 'file' => 'modules/iiif.php', 'itemTypes' => [
            'collection' => ['fn' => function ($r, $db, $dbp) {
                $collection = iiifGenerateCollection($r["parliament"] ?? "", $r["electoralPeriod"] ?? null);
                if ($collection === null) {
                    return apiResponse(apiErrorInvalidParameter("parliament"));
                }
                header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
                header('Access-Control-Allow-Origin: *');
                return $collection;
            }],
            'search' => ['fn' => function ($r, $db, $dbp) {
                $collection = iiifGenerateSearchCollection($r);
                if ($collection === null) {
                    return apiResponse(apiErrorNotFound("media"));
                }
                header('Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"');
                header('Access-Control-Allow-Origin: *');
                return $collection;
            }],
        ]],

        'status' => ['access' => 'public', 'file' => 'modules/status.php', 'itemTypes' => [
            'all' => $req(null, 'getStatus'),
        ]],

        'search' => ['access' => 'public', 'itemTypes' => [
            'people'           => $req('modules/person.php', 'personSearch'),
            'organisations'    => $req('modules/organisation.php', 'organisationSearch'),
            'documents'        => $req('modules/document.php', 'documentSearch'),
            'terms'            => $req('modules/term.php', 'termSearch'),
            'media'            => $req('modules/media.php', 'mediaSearch'),
            'electoralPeriods' => $req('modules/electoral-period.php', 'electoralPeriodSearch'),
        ]],

        'autocomplete' => ['access' => 'public', 'file' => 'modules/autocomplete.php', 'itemTypes' => [
            'text'       => ['fn' => function ($r, $db, $dbp) { return apiResponse(fulltextAutocomplete($r["q"])); }],
            'agendaItem' => ['fn' => function ($r, $db, $dbp) { return apiResponse(agendaItemAutocomplete($r["q"])); }],
            'entities'   => ['fn' => function ($r, $db, $dbp) { return apiResponse(entitiesAutocomplete($r["q"])); }],
        ]],

        'user' => ['access' => 'public', 'file' => 'modules/user.php', 'itemTypes' => [
            'login'                  => $req(null, 'userLogin'),
            'register'               => $req(null, 'userRegister'),
            'logout'                 => ['fn' => function ($r, $db, $dbp) { return apiResponse(userLogout()); }],
            'password-reset'         => $req(null, 'userPasswordReset'),
            'password-reset-request' => $req(null, 'userPasswordResetRequest'),
            'confirm-registration'   => $req(null, 'userConfirmRegistration'),
        ]],

        // Notifications & alerts: the action passes the central gate; each
        // module enforces "logged-in" (and admin where needed) itself, so
        // anonymous callers get an auth error from the module, not from here.
        'alert' => ['access' => 'public', 'file' => 'modules/alert.php', 'itemTypes' => [
            'list'   => $req(null, 'alertList'),
            'get'    => $req(null, 'alertGet'),
            'create' => $req(null, 'alertCreate'),
            'update' => $req(null, 'alertUpdate'),
            'delete' => $req(null, 'alertDelete'),
            'status' => $req(null, 'alertStatus'),
        ]],

        // Playlists: same arrangement as alerts — the action passes the central
        // gate and the module enforces the feature flag plus "logged-in" itself.
        // `resolve` is deliberately reachable anonymously; it checks the share
        // flag on the row rather than the session.
        'playlist' => ['access' => 'public', 'file' => 'modules/playlist.php', 'itemTypes' => [
            'list'          => $req(null, 'playlistList'),
            'get'           => $req(null, 'playlistGet'),
            'resolve'       => $req(null, 'playlistResolve'),
            'create'        => $req(null, 'playlistCreate'),
            'update'        => $req(null, 'playlistUpdate'),
            'delete'        => $req(null, 'playlistDelete'),
            'itemAdd'       => $req(null, 'playlistItemAdd'),
            'itemRemove'    => $req(null, 'playlistItemRemove'),
            'itemsReorder'  => $req(null, 'playlistItemsReorder'),
            'convert'       => $req(null, 'playlistConvert'),
            'createAlert'   => $req(null, 'playlistCreateAlert'),
        ]],

        'systemMessage' => ['access' => 'public', 'file' => 'modules/system-message.php', 'itemTypes' => [
            'list'   => $req(null, 'systemMessageList'),
            'create' => $req(null, 'systemMessageCreate'),
        ]],

        'notification' => ['access' => 'public', 'file' => 'modules/notification.php', 'itemTypes' => [
            'list'        => $req(null, 'notificationList'),
            'unreadCount' => $req(null, 'notificationUnreadCount'),
            'markRead'    => $req(null, 'notificationMarkRead'),
            'markUnread'  => $req(null, 'notificationMarkUnread'),
            'markAllRead' => $req(null, 'notificationMarkAllRead'),
            'delete'      => $req(null, 'notificationDelete'),
            'preferences' => $req(null, 'notificationPreferences'),
            'unsubscribe' => $req(null, 'notificationUnsubscribe'),
            'runMatch'    => $req(null, 'notificationRunMatch'),
        ]],

        'lang' => ['access' => 'public', 'itemTypes' => [
            'set' => ['fn' => function ($r, $db, $dbp) {
                global $acceptLang;
                if (!empty($r["lang"]) && isset($acceptLang) && array_key_exists($r["lang"], $acceptLang)) {
                    $_SESSION["lang"] = $r["lang"];
                    return apiResponse(
                        apiSuccessResponse(
                            $r["lang"],
                            ["requestStatus" => "success", "message" => "Language has been set to " . $r["lang"]]
                        )
                    );
                }
                return apiResponse(apiErrorInvalidParameter("lang", "Invalid language code"));
            }],
        ]],

        // =====================================================================
        // Admin actions — auth() lets these through only for a user whose
        // UserRole is "admin".
        // =====================================================================

        'addItem' => ['access' => 'admin', 'itemTypes' => [
            'media'            => $reqDbp('modules/media.php', 'mediaAdd'),
            'conflict'         => $reqDb('modules/conflict.php', 'conflictAdd'),
            'entitySuggestion' => $reqDb('modules/entity-suggestion.php', 'entitySuggestionAdd'),
            'person'           => $reqDbp('modules/person.php', 'personAdd'),
            'organisation'     => $reqDbp('modules/organisation.php', 'organisationAdd'),
            'document'         => $reqDbp('modules/document.php', 'documentAdd'),
            'term'             => $reqDbp('modules/term.php', 'termAdd'),
            'apiKey'           => $reqDb('modules/api-key.php', 'apiKeyAdd'),
            'entityRemap'      => $reqDb('modules/entity-remap.php', 'entityRemapAdd'),
        ]],

        'changeItem' => ['access' => 'admin', 'itemTypes' => [
            'organisation'    => $req('modules/organisation.php', 'organisationChange'),
            'document'        => $req('modules/document.php', 'documentChange'),
            'term'            => $req('modules/term.php', 'termChange'),
            'person'          => $req('modules/person.php', 'personChange'),
            'media'           => $req('modules/media.php', 'mediaChange'),
            'session'         => $req('modules/session.php', 'sessionChange'),
            'agendaItem'      => $req('modules/agenda-item.php', 'agendaItemChange'),
            'electoralPeriod' => $req('modules/electoral-period.php', 'electoralPeriodChange'),
            'user'            => $req('modules/user.php', 'userChange'),
            'apiKey'          => $reqDb('modules/api-key.php', 'apiKeyChange'),
            'entityRemap'     => $reqDb('modules/entity-remap.php', 'entityRemapChange'),
            'conflict'        => $reqDb('modules/conflict.php', 'conflictChange'),
        ]],

        'deleteItem' => ['access' => 'admin', 'invalidItemType' => 'Invalid itemType for deleteItem action.', 'itemTypes' => [
            'entitySuggestion' => ['file' => 'modules/entity-suggestion.php', 'fn' => function ($r, $db, $dbp) {
                return apiResponse(entitySuggestionDelete($r["EntitysuggestionID"] ?? null, $db));
            }],
            'apiKey'      => $reqDb('modules/api-key.php', 'apiKeyDelete'),
            'entityRemap' => $reqDb('modules/entity-remap.php', 'entityRemapDelete'),
            'conflict'    => $reqDb('modules/conflict.php', 'conflictDelete'),
        ]],

        'getItemsFromDB' => [
            'access' => 'admin',
            // Defaults for the common optional parameters, applied before dispatch.
            'before' => function (array $r) {
                return array_merge([
                    "limit"  => 10,
                    "offset" => 0,
                    "sort"   => false,
                    "order"  => false,
                    "search" => false,
                    "id"     => "all",
                ], $r);
            },
            'itemTypes' => [
                'person'          => $items('modules/person.php', 'personGetItemsFromDB'),
                'organisation'    => $items('modules/organisation.php', 'organisationGetItemsFromDB'),
                'document'        => $items('modules/document.php', 'documentGetItemsFromDB'),
                'term'            => $items('modules/term.php', 'termGetItemsFromDB'),
                'electoralPeriod' => $items('modules/electoral-period.php', 'electoralPeriodGetItemsFromDB'),
                'user'            => $items('modules/user.php', 'userGetItemsFromDB'),
                'apiKey'          => $items('modules/api-key.php', 'apiKeyGetItemsFromDB'),
                'entityRemap'     => $items('modules/entity-remap.php', 'entityRemapGetItemsFromDB'),
                'session'         => $items('modules/session.php', 'sessionGetItemsFromDB',
                    function ($r) { return [false, $r["electoralPeriodID"] ?? null]; }),
                'agendaItem'      => $items('modules/agenda-item.php', 'agendaItemGetItemsFromDB',
                    function ($r) { return [false, $r["electoralPeriodID"] ?? null, $r["sessionID"] ?? null]; }),
                'conflict'        => $items('modules/conflict.php', 'conflictGetItemsFromDB',
                    function ($r) { return [$r["status"] ?? "open", $r["type"] ?? false, $r["getStats"] ?? false]; }),
                'entitySuggestion' => $items('modules/entity-suggestion.php', 'entitySuggestionGetItemsFromDB',
                    function ($r) { return [$r["idType"] ?? "external"]; }),
            ],
        ],

        'index' => ['access' => 'admin', 'file' => 'modules/search-index.php', 'itemTypes' => [
            'update'              => $req(null, 'searchIndexUpdate'),
            'full-update'         => $req(null, 'searchIndexTriggerFullUpdate'),
            'delete'              => $req(null, 'searchIndexDelete'),
            'status'              => $req(null, 'searchIndexGetStatus'),
            'statistics-update'   => $req(null, 'searchIndexTriggerStatisticsUpdate'),
            'statistics-status'   => $req(null, 'searchIndexGetStatisticsStatus'),
            'optimize'            => $req(null, 'searchIndexOptimize'),
            'optimization-status' => $req(null, 'searchIndexGetOptimizationStatus'),
        ]],

        'import' => ['access' => 'admin', 'file' => 'modules/import.php', 'invalidItemType' => 'Invalid itemType for import action.', 'itemTypes' => [
            'reimport-sessions' => $reqDb(null, 'reimportSessions'),
            'run'               => $req(null, 'importRunCronUpdater'),
            'status'            => $req(null, 'importGetCronUpdaterStatus'),
        ]],

        'externalData' => ['access' => 'admin', 'file' => 'modules/external-data.php', 'invalidItemType' => 'Invalid itemType for externalData action.', 'itemTypes' => [
            'get-info'        => $req(null, 'externalDataGetInfo'),
            'update-entities' => $req(null, 'externalDataUpdateEntities'),
            'full-update'     => $req(null, 'externalDataTriggerFullUpdate'),
            'status'          => $req(null, 'externalDataGetFullUpdateStatus'),
        ]],

        'cleanup' => ['access' => 'admin', 'itemTypes' => [
            'entitySuggestions' => ['file' => 'modules/entity-suggestion.php', 'fn' => function ($r, $db, $dbp) {
                return apiResponse(entitySuggestionCleanup($db));
            }],
            'conflicts' => ['file' => 'modules/conflict.php', 'fn' => function ($r, $db, $dbp) {
                return apiResponse(conflictCleanup($db));
            }],
        ]],

        'migration' => ['access' => 'admin', 'file' => 'modules/migration.php', 'itemTypes' => [
            'status' => $req(null, 'migrationGetStatus'),
            'run'    => $req(null, 'migrationRun'),
        ]],

        'system' => ['access' => 'admin', 'file' => 'modules/system-status.php', 'itemTypes' => [
            'status'       => $req(null, 'systemStatusGet'),
            'clear-caches' => $req(null, 'systemClearCaches'),
            'storage-scan' => $req(null, 'systemStorageScan'),
        ]],

        // Public: /statistics is a public page, and content/pages/api/page.php has
        // always documented these as open endpoints. Corpus-wide responses are
        // served from the warmed cache (modules/statistics/cache.php), so opening
        // them up does not expose a way to make the cluster do expensive work on
        // demand; the standard per-IP rate limit applies as to any public action.
        'statistics' => ['access' => 'public', 'file' => 'modules/statistics.php', 'itemTypes' => [
            'general'              => $req(null, 'statisticsGetGeneral'),
            'entity'               => $req(null, 'statisticsGetEntity'),
            'word-trends'          => $req(null, 'statisticsGetWordTrends'),
            'entity-counts'        => $req(null, 'statisticsGetEntityCounts'),
            'significant-words'    => $req(null, 'statisticsGetSignificantWords'),
            'significant-entities' => $req(null, 'statisticsGetSignificantEntities'),
        ]],
    ];

    return $registry;
}

/** Actions the old auth.php whitelist let through without a session. */
function apiRegistryIsPublicAction($action)
{
    $registry = apiRegistry();
    return isset($registry[$action]) && $registry[$action]['access'] === 'public';
}
