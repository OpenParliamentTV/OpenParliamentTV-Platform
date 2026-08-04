<?php
/**
 * Agenda item node builder.
 *
 * Plain data, no envelope (D2): agendaItemGetNode() returns the JSON:API node
 * or null when there is no such agenda item, and throws OptvInvalidIdException
 * for an unusable id / OptvEntityException when the parliament database is
 * unreachable. The envelope for `getItem&itemType=agendaItem` is applied by
 * agendaItemGetByID() in api/v1/modules/agenda-item.php.
 *
 * Note the id asymmetry, which is frozen payload shape: the node's own `id` and
 * its self link carry the "<PARL>-<n>" form, while the row is looked up by the
 * numeric part alone.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvParseStringId()
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../../api/v1/utilities.php"); // getApiDatabaseConnection()

/**
 * @param string $id AgendaItemID, e.g. "DE-4711".
 * @param SafeMySQL|false $dbp Parliament connection; opened here when not supplied.
 * @return array|null The node, or null when no such agenda item exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function agendaItemGetNode($id, $dbp = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing agenda item id.");
    }

    $idInfo = optvParseStringId($id);
    if (!$idInfo) {
        throw new OptvInvalidIdException("Could not parse agenda item id {$id}.");
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        throw new OptvInvalidIdException("Unknown parliament in agenda item id {$id}.", "parliament");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    if (!is_object($dbp)) {
        $dbp = getApiDatabaseConnection('parliament', $parliament);
        if (!is_object($dbp)) {
            throw new OptvEntityException("Parliament database unavailable.", "parliament");
        }
    }

    $item = $dbp->getRow("SELECT ai.*,
                               sess.*,
                               ep.*
                        FROM ?n AS ai
                        LEFT JOIN ?n AS sess
                            ON ai.AgendaItemSessionID=sess.SessionID
                        LEFT JOIN ?n AS ep
                            ON sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                        WHERE AgendaItemID=?i",
                        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
                        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                        $idInfo["id_part"]);

    if (!$item) {
        return null;
    }

    return [
        "type" => "agendaItem",
        "id" => $parliament."-".$item["AgendaItemID"], // Preserve special ID format
        "attributes" => [
            "officialTitle" => $item["AgendaItemOfficialTitle"],
            "title" => $item["AgendaItemTitle"],
            "order" => (int)$item["AgendaItemOrder"],
            "parliament" => $parliament,
            "parliamentLabel" => $parliamentLabel
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/agendaItem/".$parliament."-".$item["AgendaItemID"]
        ],
        "relationships" => [
            "media" => [
                "links" => [
                    "self" => $config["dir"]["api"]."/search/media?agendaItemID=".$parliament."-".$item["AgendaItemID"]
                ]
            ],
            "session" => [
                "data" => [
                    "type" => "session",
                    "id" => $item["SessionID"],
                    "attributes" => [
                        "number" => $item["SessionNumber"],
                        "dateStart" => $item["SessionDateStart"],
                        "dateEnd" => $item["SessionDateEnd"]
                    ]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/session/".$item["SessionID"]
                ]
            ],
            "electoralPeriod" => [
                "data" => [
                    "type" => "electoralPeriod",
                    "id" => $item["ElectoralPeriodID"],
                    "attributes" => [
                        "number" => $item["ElectoralPeriodNumber"],
                        "dateStart" => $item["ElectoralPeriodDateStart"],
                        "dateEnd" => $item["ElectoralPeriodDateEnd"]
                    ]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"]
                ]
            ]
        ]
    ];
}
