<?php
/**
 * Electoral period node builder.
 *
 * Plain data, no envelope (D2): electoralPeriodGetNode() returns the JSON:API
 * node or null when there is no such electoral period, and throws
 * OptvInvalidIdException for an unusable id / OptvEntityException when the
 * parliament database is unreachable. The envelope for
 * `getItem&itemType=electoralPeriod` is applied by electoralPeriodGetByID() in
 * api/v1/modules/electoral-period.php.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php"); // optvParseStringId()
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()

/**
 * @param string $id ElectoralPeriodID, e.g. "DE-020".
 * @param SafeMySQL|false $dbp Parliament connection; opened here when not supplied.
 * @return array|null The node, or null when no such electoral period exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function electoralPeriodGetNode($id, $dbp = false)
{
    global $config;

    $idInfo = optvParseStringId($id);
    if (!$idInfo || $idInfo["type"] !== "electoralPeriod") {
        throw new OptvInvalidIdException("Could not parse electoral period id.");
    }

    $parliament = $idInfo["parliament"];
    if (!isset($config["parliament"][$parliament])) {
        throw new OptvInvalidIdException("Unknown parliament in electoral period id {$id}.", "parliament");
    }

    $parliamentLabel = $config["parliament"][$parliament]["label"];

    if (!is_object($dbp)) {
        try {
            $dbp = optvDbConnection('parliament', $parliament);
        } catch (OptvDbException $e) {
            throw new OptvEntityException("Parliament database unavailable.", "parliament");
        }
    }

    $item = $dbp->getRow("SELECT * FROM ?n WHERE ElectoralPeriodID=?s",
        $config["parliament"][$parliament]["sql"]["tbl"]["ElectoralPeriod"],
        $id
    );

    if (!$item) {
        return null;
    }

    // Get associated sessions
    $sessionItems = $dbp->getAll("SELECT sess.*, COUNT(ai.AgendaItemID) AS AgendaItemCount
        FROM ?n AS sess
        LEFT JOIN ?n as ai
            ON ai.AgendaItemSessionID=sess.SessionID
        WHERE SessionElectoralPeriodID=?s
        GROUP BY sess.SessionID",
        $config["parliament"][$parliament]["sql"]["tbl"]["Session"],
        $config["parliament"][$parliament]["sql"]["tbl"]["AgendaItem"],
        $id
    );

    $data = [
        "type" => "electoralPeriod",
        "id" => $item["ElectoralPeriodID"],
        "attributes" => [
            "number" => (int)$item["ElectoralPeriodNumber"],
            "dateStart" => $item["ElectoralPeriodDateStart"],
            "dateEnd" => $item["ElectoralPeriodDateEnd"],
            "parliament" => $parliament,
            "parliamentLabel" => $parliamentLabel
        ],
        "links" => [
            "self" => $config["dir"]["api"]."/electoralPeriod/".$item["ElectoralPeriodID"]
        ],
        "relationships" => [
            "media" => [
                "links" => [
                    "self" => $config["dir"]["api"]."/search/media?electoralPeriodID=".$item["ElectoralPeriodID"]
                ]
            ],
            "sessions" => [
                "data" => [],
                "links" => [
                    "self" => $config["dir"]["api"]."/search/session?electoralPeriodID=".$item["ElectoralPeriodID"]
                ]
            ]
        ]
    ];

    // Add session data. The extra "data" wrapper per entry is frozen payload shape.
    foreach ($sessionItems as $sessionItem) {
        $data["relationships"]["sessions"]["data"][] = [
            "data" => [
                "type" => "session",
                "id" => $sessionItem["SessionID"],
                "attributes" => [
                    "dateStart" => $sessionItem["SessionDateStart"],
                    "dateEnd" => $sessionItem["SessionDateEnd"],
                    "number" => $sessionItem["SessionNumber"],
                    "agendaItemCount" => $sessionItem["AgendaItemCount"]
                ],
                "links" => [
                    "self" => $config["dir"]["api"]."/session/".$sessionItem["SessionID"]
                ]
            ]
        ];
    }

    return $data;
}
