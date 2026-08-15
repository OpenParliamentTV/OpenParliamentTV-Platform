<?php
/**
 * Flat preview nodes for the shared entity card.
 *
 * The entity counterpart of mediaQueueNode() (media.php): a deliberately small,
 * flat projection carrying exactly what content/components/entity-item.php and
 * content/client/js/entity-item.js draw, and nothing else. That is what lets a
 * media annotation, a statistics row, a search suggestion and a relationship all
 * arrive at the same card.
 *
 * ```
 * [ 'type', 'id', 'subtype', 'label', 'secondaryLabel', 'thumbnailURI',
 *   'faction' => ['id' => …, 'label' => …] | null, 'href' ]
 * ```
 *
 * Everything a single surface needs on top of that — a count, a score, a context
 * line, where the card links to instead — arrives through the renderer's options
 * bag rather than through more node fields.
 *
 * The batch resolver is why this file exists at all. The read path it replaces
 * called a full {entity}GetByID() per id, each with its own sub-queries for party
 * and faction, so a statistics panel of twenty rows across three types cost
 * upwards of a hundred round trips. Here it is one query per type plus one for
 * the factions those people belong to.
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../images/image-cache.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()

/** Entity types that resolve to a preview node, mapped onto their table + column prefix. */
const OPTV_PREVIEW_ENTITY_TYPES = [
    'person'       => ['table' => 'Person',       'prefix' => 'Person'],
    'organisation' => ['table' => 'Organisation', 'prefix' => 'Organisation'],
    'document'     => ['table' => 'Document',     'prefix' => 'Document'],
    'term'         => ['table' => 'Term',         'prefix' => 'Term'],
];

/**
 * Preview nodes for a set of entity references.
 *
 * References that do not resolve are simply absent from the result — a deleted
 * entity still named by an old annotation must not become a card pointing at a
 * 404, and the caller can tell the difference by key.
 *
 * @param array $refs Either ['type' => [id, id, …]] or a flat list of
 *                    ['type' => …, 'id' => …] pairs.
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array [ "<type>|<id>" => node ], only for references that resolved.
 */
function entityBuildPreviewNodes(array $refs, $db = false)
{
    global $config;

    $idsByType = entityPreviewNormalizeRefs($refs);
    if (empty($idsByType)) {
        return [];
    }

    if (!is_object($db)) {
        try {
            $db = optvDbConnection('platform');
        } catch (OptvDbException $e) {
            return [];
        }
    }

    $rowsByType = [];
    $factionIDs = [];

    foreach ($idsByType as $type => $ids) {
        $table = $config["platform"]["sql"]["tbl"][OPTV_PREVIEW_ENTITY_TYPES[$type]['table']];

        try {
            $rows = $db->getAll(
                "SELECT * FROM ?n WHERE ?n IN (?a)",
                $table,
                OPTV_PREVIEW_ENTITY_TYPES[$type]['prefix'] . 'ID',
                array_values($ids)
            );
        } catch (Exception $e) {
            error_log('entityBuildPreviewNodes: ' . $type . ': ' . $e->getMessage());
            continue;
        }

        $rowsByType[$type] = $rows;

        if ($type === 'person') {
            foreach ($rows as $row) {
                if (!empty($row['PersonFactionOrganisationID'])) {
                    $factionIDs[$row['PersonFactionOrganisationID']] = true;
                }
            }
        }
    }

    // One lookup for every faction the batch's people belong to, rather than one
    // per person as the {entity}GetByID() path does.
    $factions = entityPreviewFactionLabels(array_keys($factionIDs), $db);

    $nodes = [];
    foreach ($rowsByType as $type => $rows) {
        foreach ($rows as $row) {
            $node = entityBuildPreviewNode($row, $type, $factions);
            if ($node !== null) {
                $nodes[$type . '|' . $node['id']] = $node;
            }
        }
    }

    return $nodes;
}

/**
 * One preview node from a platform entity row.
 *
 * @param array  $row      A row of person/organisation/document/term.
 * @param string $type     The entity type the row came from.
 * @param array  $factions id => label, for resolving a person's faction.
 * @return array|null Null when the row carries no usable id.
 */
function entityBuildPreviewNode(array $row, $type, array $factions = [])
{
    global $config;

    if (!isset(OPTV_PREVIEW_ENTITY_TYPES[$type])) {
        return null;
    }

    $prefix = OPTV_PREVIEW_ENTITY_TYPES[$type]['prefix'];
    $id = $row[$prefix . 'ID'] ?? null;
    if (empty($id)) {
        return null;
    }

    $root = isset($config["dir"]["root"]) ? rtrim($config["dir"]["root"], '/') : '';

    $node = [
        'type'           => $type,
        'id'             => (string) $id,
        'subtype'        => $row[$prefix . 'Type'] ?? null,
        'label'          => $row[$prefix . 'Label'] ?? (string) $id,
        'secondaryLabel' => '',
        'thumbnailURI'   => imageCacheUrl($type, $id, $row[$prefix . 'ThumbnailURI'] ?? null),
        'faction'        => null,
        'href'           => $root . '/' . $type . '/' . rawurlencode((string) $id),
    ];

    // A person's faction says more about them than an alternative spelling does,
    // and it is what colours the card — the same rule the media relationships
    // list has always applied.
    if ($type === 'person' && !empty($row['PersonFactionOrganisationID'])) {
        $factionID = $row['PersonFactionOrganisationID'];
        $node['faction'] = [
            'id'    => $factionID,
            'label' => $factions[$factionID] ?? $factionID,
        ];
        $node['secondaryLabel'] = $node['faction']['label'];
    } elseif ($type === 'organisation' && in_array($node['subtype'], ['faction', 'party'], true)) {
        // A faction IS its own colour source, so the card can be tinted the same
        // way a person's is.
        $node['faction'] = ['id' => $node['id'], 'label' => $node['label']];
    }

    if ($node['secondaryLabel'] === '') {
        $alternatives = json_decode($row[$prefix . 'LabelAlternative'] ?? '', true);
        if (is_array($alternatives) && isset($alternatives[0]) && is_string($alternatives[0])) {
            $node['secondaryLabel'] = $alternatives[0];
        }
    }

    if ($type === 'document') {
        $node['sourceURI'] = $row['DocumentSourceURI'] ?? null;
    }

    return $node;
}

/**
 * A preview node built from an already-loaded JSON:API entity node.
 *
 * For callers holding a node rather than a row — the media relationships list
 * and the page-level entity previews. Both node shapes are accepted, because the
 * platform genuinely has two: media annotations flatten the faction onto
 * `attributes.faction`, while {entity}GetNode() keeps it under
 * `relationships.faction.data`.
 *
 * @param array $entity A JSON:API entity or annotation node.
 * @return array|null Null when the node carries no type/id.
 */
function entityPreviewNodeFromEntity(array $entity)
{
    global $config;

    $type = $entity['type'] ?? null;
    $id   = $entity['id'] ?? null;
    if (empty($type) || empty($id) || !isset(OPTV_PREVIEW_ENTITY_TYPES[$type])) {
        return null;
    }

    $attributes = $entity['attributes'] ?? [];
    $root = isset($config["dir"]["root"]) ? rtrim($config["dir"]["root"], '/') : '';

    $faction = null;
    if (isset($attributes['faction']['id'])) {
        $faction = [
            'id'    => $attributes['faction']['id'],
            'label' => $attributes['faction']['label'] ?? $attributes['faction']['id'],
        ];
    } elseif (isset($entity['relationships']['faction']['data']['id'])) {
        $factionData = $entity['relationships']['faction']['data'];
        $faction = [
            'id'    => $factionData['id'],
            'label' => $factionData['attributes']['label'] ?? $factionData['id'],
        ];
    } elseif ($type === 'organisation'
        && in_array($attributes['type'] ?? null, ['faction', 'party'], true)) {
        $faction = ['id' => $id, 'label' => $attributes['label'] ?? $id];
    }

    $secondary = $faction && $type === 'person' ? $faction['label'] : '';
    if ($secondary === '' && isset($attributes['labelAlternative'][0])
        && is_string($attributes['labelAlternative'][0])) {
        $secondary = $attributes['labelAlternative'][0];
    }

    $node = [
        'type'           => $type,
        'id'             => (string) $id,
        'subtype'        => $attributes['type'] ?? null,
        'label'          => $attributes['label'] ?? (string) $id,
        'secondaryLabel' => $secondary,
        'thumbnailURI'   => $attributes['thumbnailURI'] ?? null,
        'faction'        => $faction,
        'href'           => $root . '/' . $type . '/' . rawurlencode((string) $id),
    ];

    if ($type === 'document') {
        $node['sourceURI'] = $attributes['sourceURI'] ?? null;
    }

    return $node;
}

/**
 * Accept either reference shape and reduce it to unique ids per known type.
 *
 * @return array type => list of ids.
 */
function entityPreviewNormalizeRefs(array $refs)
{
    $idsByType = [];

    foreach ($refs as $key => $value) {
        if (is_string($key) && is_array($value)) {
            // ['person' => [id, id, …]]
            $type = $key;
            $ids  = $value;
        } elseif (is_array($value) && isset($value['type'], $value['id'])) {
            // [ ['type' => …, 'id' => …], … ]
            $type = $value['type'];
            $ids  = [$value['id']];
        } else {
            continue;
        }

        if (!isset(OPTV_PREVIEW_ENTITY_TYPES[$type])) {
            continue;
        }

        foreach ($ids as $id) {
            if ($id !== null && $id !== '') {
                $idsByType[$type][(string) $id] = (string) $id;
            }
        }
    }

    return $idsByType;
}

/**
 * Labels for a set of organisation ids.
 *
 * @return array id => label.
 */
function entityPreviewFactionLabels(array $ids, $db)
{
    global $config;

    if (empty($ids)) {
        return [];
    }

    try {
        $rows = $db->getAll(
            "SELECT OrganisationID, OrganisationLabel FROM ?n WHERE OrganisationID IN (?a)",
            $config["platform"]["sql"]["tbl"]["Organisation"],
            array_values($ids)
        );
    } catch (Exception $e) {
        error_log('entityPreviewFactionLabels: ' . $e->getMessage());
        return [];
    }

    $labels = [];
    foreach ($rows as $row) {
        $labels[$row['OrganisationID']] = $row['OrganisationLabel'];
    }

    return $labels;
}
