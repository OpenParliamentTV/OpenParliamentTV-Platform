<?php
require_once (__DIR__."/../../../config.php");
require_once (__DIR__."/../../../modules/utilities/functions.php");
require_once (__DIR__."/../../../modules/utilities/safemysql.class.php");
require_once (__DIR__."/../../../api/v1/utilities.php");

/**
 * Wikidata id corrections applied to NER annotations at import time.
 *
 * Named-entity recognition regularly returns the *party* id where the speech
 * means the parliamentary *group* (Q49766 "Bündnis 90/Die Grünen" instead of
 * Q1007353, its Bundestag faction), or a regional/derived entity instead of the
 * one the platform tracks. Each row rewrites one such source id to the id the
 * platform actually holds, before the annotation is written.
 *
 * Rows are parliament-scoped: a party→faction mapping is only correct for the
 * parliament whose factions it names. `EntityRemapParliament = ''` means the
 * row applies everywhere; a parliament-specific row wins over a global one for
 * the same source id.
 *
 * entityRemapGetMap() is the import-time read path. The CRUD functions below are
 * routed from api/v1/api.php as itemType "entityRemap" on the private admin
 * actions getItemsFromDB | addItem | changeItem | deleteItem; those actions are
 * absent from the apiV1 whitelist in modules/utilities/auth.php, so the HTTP
 * entry point already restricts them to admins, and entityRemapRequireAdmin()
 * additionally guards internal apiV1() callers, which bypass that gate.
 */

/** Guard for internal apiV1() callers, which do not pass the HTTP-level auth gate. */
function entityRemapRequireAdmin()
{
    $isAdmin = isset($_SESSION["userdata"]["role"]) && $_SESSION["userdata"]["role"] === "admin";
    if (!$isAdmin) {
        return apiErrorResponse(403, 1, "messageAuthNotPermittedTitle", "messageAuthNotPermittedDetail");
    }
    return true;
}

/** The configured table name, with the schema default as fallback. */
function entityRemapTable()
{
    global $config;
    return $config["platform"]["sql"]["tbl"]["EntityRemap"] ?? "entity_remap";
}

/**
 * Normalize a parliament scope: "" (every parliament) or a configured key.
 * Returns false when the value names a parliament this instance does not have —
 * a typo there would silently produce a row that never applies.
 */
function entityRemapParseParliament($value)
{
    global $config;

    $parliament = trim((string) ($value ?? ""));
    if ($parliament === "" || $parliament === "*") {
        return "";
    }
    if (!isset($config["parliament"][$parliament])) {
        return false;
    }
    return $parliament;
}

/**
 * @param string $parliament Parliament key (e.g. "DE").
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array Map of source Wikidata id => target Wikidata id. Empty when the
 *               table is missing or unreachable — a remap is a correction, so
 *               losing it must degrade the import, not abort it.
 */
function entityRemapGetMap($parliament, $db = false)
{
    global $config;

    if (!$db) {
        $db = getApiDatabaseConnection('platform');
        if (!($db instanceof SafeMySQL)) {
            return [];
        }
    }

    try {
        // Global rows first, parliament-specific rows after, so the more
        // specific mapping overwrites the general one while building the map.
        $rows = $db->getAll(
            "SELECT EntityRemapSourceID, EntityRemapTargetID FROM ?n
             WHERE EntityRemapParliament = '' OR EntityRemapParliament = ?s
             ORDER BY EntityRemapParliament ASC",
            $config["platform"]["sql"]["tbl"]["EntityRemap"] ?? "entity_remap",
            (string) $parliament
        );
    } catch (Exception $e) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $map[$row["EntityRemapSourceID"]] = $row["EntityRemapTargetID"];
    }

    return $map;
}

/**
 * List rows for the admin table. Mirrors apiKeyGetItemsFromDB()'s contract,
 * which bootstrap-table's responseHandler depends on: total at the root, raw
 * rows in data.
 */
function entityRemapGetItemsFromDB($id = "all", $limit = 25, $offset = 0, $search = false, $sort = false, $order = false)
{
    $admin = entityRemapRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $db = getApiDatabaseConnection('platform');
    if (!is_object($db)) {
        return $db;
    }

    $tbl = entityRemapTable();

    $where = ($id === "all" || $id === null || $id === "")
        ? "1"
        : $db->parse("EntityRemapID = ?i", $id);

    if (!empty($search)) {
        $where .= $db->parse(
            " AND (LOWER(EntityRemapSourceID) LIKE LOWER(?s) OR LOWER(EntityRemapTargetID) LIKE LOWER(?s)
                   OR LOWER(EntityRemapDescription) LIKE LOWER(?s) OR LOWER(EntityRemapParliament) LIKE LOWER(?s))",
            "%".$search."%", "%".$search."%", "%".$search."%", "%".$search."%"
        );
    }

    // Allowlist maps the client-supplied sort field onto a literal column name.
    $sortable = [
        "EntityRemapParliament"  => "EntityRemapParliament",
        "EntityRemapSourceID"    => "EntityRemapSourceID",
        "EntityRemapTargetID"    => "EntityRemapTargetID",
        "EntityRemapDescription" => "EntityRemapDescription",
        "EntityRemapLastChanged" => "EntityRemapLastChanged",
    ];

    $orderLimit = (!empty($sort) && isset($sortable[$sort]))
        ? " ORDER BY " . $sortable[$sort] . " " . ((strtolower((string) $order) === "desc") ? "DESC" : "ASC")
        : " ORDER BY EntityRemapParliament ASC, EntityRemapTargetID ASC, EntityRemapSourceID ASC";

    if ($limit != 0) {
        $orderLimit .= $db->parse(" LIMIT ?i, ?i", $offset, $limit);
    }

    // Count under the same filter as the rows, so paging stays correct while searching.
    $total = $db->getOne("SELECT COUNT(EntityRemapID) FROM ?n WHERE ?p", $tbl, $where);
    $rows = $db->getAll("SELECT * FROM ?n WHERE ?p", $tbl, $where . $orderLimit);

    foreach ($rows as &$row) {
        $row["EntityRemapID"] = (int) $row["EntityRemapID"];
    }
    unset($row);

    return [
        "meta" => ["requestStatus" => "success"],
        "total" => $total,
        "data" => $rows
    ];
}

/**
 * Validate + normalize the writable fields shared by add and change.
 * Returns the column array, or an error envelope.
 */
function entityRemapParseFields($parameter, $requireAll)
{
    $update = [];

    if ($requireAll || isset($parameter["EntityRemapParliament"])) {
        $parliament = entityRemapParseParliament($parameter["EntityRemapParliament"] ?? "");
        if ($parliament === false) {
            return apiErrorInvalidParameter("EntityRemapParliament");
        }
        $update["EntityRemapParliament"] = $parliament;
    }

    foreach (["EntityRemapSourceID", "EntityRemapTargetID"] as $field) {
        if (!$requireAll && !isset($parameter[$field])) {
            continue;
        }
        $value = strtoupper(trim((string) ($parameter[$field] ?? "")));
        if ($value === "") {
            return apiErrorMissingParameter($field);
        }
        // Both ends address Wikidata entities; anything else would produce an
        // annotation type the entity dump can never resolve.
        if (!optvValidateWikidataId($value)) {
            return apiErrorInvalidParameter($field);
        }
        $update[$field] = $value;
    }

    if (isset($update["EntityRemapSourceID"], $update["EntityRemapTargetID"])
        && $update["EntityRemapSourceID"] === $update["EntityRemapTargetID"]) {
        return apiErrorInvalidParameter("EntityRemapTargetID");
    }

    if ($requireAll || isset($parameter["EntityRemapDescription"])) {
        $update["EntityRemapDescription"] = mb_substr(trim((string) ($parameter["EntityRemapDescription"] ?? "")), 0, 255);
    }

    return $update;
}

function entityRemapAdd($parameter = [], $db = false)
{
    $admin = entityRemapRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $fields = entityRemapParseFields($parameter, true);
    if (isset($fields["meta"])) { return $fields; }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    $tbl = entityRemapTable();

    // (parliament, source) is UNIQUE — report the clash rather than letting the
    // driver throw, so the admin sees which row already claims the source id.
    $existing = $db->getOne(
        "SELECT EntityRemapID FROM ?n WHERE EntityRemapParliament = ?s AND EntityRemapSourceID = ?s",
        $tbl, $fields["EntityRemapParliament"], $fields["EntityRemapSourceID"]
    );
    if ($existing) {
        return apiErrorResponse(
            409,
            "ENTITY_REMAP_DUPLICATE",
            "messageErrorInvalidParameter",
            "messageErrorInvalidParameter",
            ["param" => "EntityRemapSourceID"],
            null,
            ["existingID" => (int) $existing]
        );
    }

    $db->query("INSERT INTO ?n SET ?u", $tbl, $fields);

    return apiSuccessResponse([
        "type" => "entityRemap",
        "id" => (int) $db->insertId(),
        "attributes" => $fields,
    ]);
}

function entityRemapChange($parameter = [], $db = false)
{
    $admin = entityRemapRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $id = isset($parameter["id"]) ? (int) $parameter["id"] : 0;
    if ($id < 1) {
        return apiErrorMissingParameter("id");
    }

    $update = entityRemapParseFields($parameter, false);
    if (isset($update["meta"])) { return $update; }
    if (empty($update)) {
        return apiErrorMissingParameter();
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    $tbl = entityRemapTable();

    $row = $db->getRow("SELECT * FROM ?n WHERE EntityRemapID = ?i", $tbl, $id);
    if (!$row) {
        return apiErrorNotFound("entityRemap");
    }

    // Same UNIQUE guard as add, against the merged post-update values.
    $parliament = $update["EntityRemapParliament"] ?? $row["EntityRemapParliament"];
    $source     = $update["EntityRemapSourceID"] ?? $row["EntityRemapSourceID"];
    $target     = $update["EntityRemapTargetID"] ?? $row["EntityRemapTargetID"];

    if ($source === $target) {
        return apiErrorInvalidParameter("EntityRemapTargetID");
    }

    $clash = $db->getOne(
        "SELECT EntityRemapID FROM ?n
         WHERE EntityRemapParliament = ?s AND EntityRemapSourceID = ?s AND EntityRemapID <> ?i",
        $tbl, $parliament, $source, $id
    );
    if ($clash) {
        return apiErrorResponse(
            409,
            "ENTITY_REMAP_DUPLICATE",
            "messageErrorInvalidParameter",
            "messageErrorInvalidParameter",
            ["param" => "EntityRemapSourceID"],
            null,
            ["existingID" => (int) $clash]
        );
    }

    $db->query("UPDATE ?n SET ?u WHERE EntityRemapID = ?i", $tbl, $update, $id);

    return apiSuccessResponse(["type" => "entityRemap", "id" => $id]);
}

function entityRemapDelete($parameter = [], $db = false)
{
    $admin = entityRemapRequireAdmin();
    if (is_array($admin)) { return $admin; }

    $id = isset($parameter["id"]) ? (int) $parameter["id"] : 0;
    if ($id < 1) {
        return apiErrorMissingParameter("id");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) { return $db; }
    }

    $tbl = entityRemapTable();

    if (!$db->getOne("SELECT EntityRemapID FROM ?n WHERE EntityRemapID = ?i", $tbl, $id)) {
        return apiErrorNotFound("entityRemap");
    }

    $db->query("DELETE FROM ?n WHERE EntityRemapID = ?i", $tbl, $id);

    return apiSuccessResponse(["type" => "entityRemap", "id" => $id]);
}
?>
