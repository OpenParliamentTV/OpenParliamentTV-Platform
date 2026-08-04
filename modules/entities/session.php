<?php
/**
 * Session node builder.
 *
 * Plain data, no envelope (D2): sessionGetNode() returns the JSON:API node or
 * null when there is no such session, and throws OptvInvalidIdException for an
 * unusable id / OptvEntityException when the parliament database is
 * unreachable. The envelope for `getItem&itemType=session` is applied by
 * sessionGetByID() in api/v1/modules/session.php.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvParseStringId()
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()

/**
 * @param string $id SessionID, e.g. "DE-0200172".
 * @param SafeMySQL|false $dbp Parliament connection; opened here when not supplied.
 * @return array|null The node, or null when no such session exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function sessionGetNode($id, $dbp = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing session id.");
    }

    $idInfo = optvParseStringId($id);
    if (!$idInfo || $idInfo["type"] !== "session") {
        throw new OptvInvalidIdException("Could not parse session id {$id}.");
    }

    $parliament = $idInfo["parliament"];
    if (!$parliament || !isset($config["parliament"][$parliament])) {
        throw new OptvInvalidIdException("Unknown parliament in session id {$id}.", "parliament");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    if (!is_object($dbp)) {
        try {
            $dbp = optvDbConnection('parliament', $parliament);
        } catch (OptvDbException $e) {
            throw new OptvEntityException("Parliament database unavailable.", "parliament");
        }
    }

    $item = $dbp->getRow("SELECT
                        sess.*,
                        ep.*
                      FROM ?n AS sess
                      LEFT JOIN ?n as ep
                      ON
                        sess.SessionElectoralPeriodID=ep.ElectoralPeriodID
                      WHERE sess.SessionID=?s",
                        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
                        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
                        $id);

    if (!$item) {
        return null;
    }

    $agendaItems = $dbp->getAll("SELECT * FROM ?n WHERE AgendaItemSessionID=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
        $id // Use the full session ID string
    );

    $sessionData = [
        "type" => "session",
        "id" => $id,
        "attributes" => [
            "number" => (int)$item["SessionNumber"],
            "dateStart" => $item["SessionDateStart"],
            "dateEnd" => $item["SessionDateEnd"],
            "parliament" => $parliament,
            "parliamentLabel" => $parliamentLabel
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/session/".$id
        ],
        "relationships" => [
            "media" => [
                "links" => [
                    "self" => $config["dir"]["api"]."/search/media?sessionID=".$id
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

    if ($agendaItems) {
        $sessionData["relationships"]["agendaItems"] = [
            "data" => array_map(function($agendaItem) use ($config, $parliament) {
                // Harmonize AgendaItemID construction with agenda-item.php (use "-")
                $fullAgendaItemID = $parliament . "-" . $agendaItem["AgendaItemID"];
                return [
                    "type" => "agendaItem",
                    "id" => $agendaItem["AgendaItemID"],
                    "attributes" => [
                        "officialTitle" => $agendaItem["AgendaItemOfficialTitle"],
                        "title" => $agendaItem["AgendaItemTitle"],
                        "order" => $agendaItem["AgendaItemOrder"]
                    ],
                    "links" => [
                        "self" => $config["dir"]["api"]."/agendaItem/".$fullAgendaItemID
                    ]
                ];
            }, $agendaItems),
            "links" => [
                "self" => $config["dir"]["api"]."/search/agendaItem?sessionID=".$id
            ]
        ];
    }

    return $sessionData;
}
