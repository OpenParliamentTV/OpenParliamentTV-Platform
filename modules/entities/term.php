<?php
/**
 * Term node builder.
 *
 * Plain data, no envelope (D2): termGetNode() returns the JSON:API node or
 * null when there is no such term, and throws OptvInvalidIdException for an
 * unusable id / OptvEntityException when the row cannot be assembled. The
 * envelope for `getItem&itemType=term` is applied by termGetByID() in
 * api/v1/modules/term.php.
 *
 * Thumbnail URIs go through imageCacheUrl() here, inside the builder, so a
 * caller taking the plain node still gets proxied images.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../images/image-cache.php");
require_once(__DIR__ . "/../../api/v1/utilities.php"); // getApiDatabaseConnection()

/**
 * @param string $id TermID (a Wikidata id).
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array|null The node, or null when no such term exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function termGetNode($id, $db = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing term id.");
    }
    if (!preg_match("/(Q|P)\d+/i", $id)) {
        throw new OptvInvalidIdException("Invalid term id.");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection();
        if (!is_object($db)) {
            throw new OptvEntityException("Platform database unavailable.");
        }
    }

    $item = $db->getRow("SELECT * FROM ?n WHERE TermID=?s", $config["platform"]["sql"]["tbl"]["Term"], $id);
    if (!$item) {
        return null;
    }

    $node = termBuildNode($item, $db);
    if (!$node) {
        throw new OptvEntityException("Could not build term node for {$id}.");
    }

    return $node;
}

function termBuildNode($item = false, $db = false) {
    global $config;

    if (!is_array($item) || !$db || !is_object($db)) {
        return false;
    }

    try {
        // Safely decode JSON fields with fallback to empty arrays
        $labelAlternative = json_decode($item["TermLabelAlternative"], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $labelAlternative = [];
        }

        $additionalInfo = json_decode($item["TermAdditionalInformation"], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $additionalInfo = [];
        }

        return [
            "type" => "term",
            "id" => $item["TermID"],
            "attributes" => [
                "type" => $item["TermType"],
                "label" => $item["TermLabel"],
                "labelAlternative" => $labelAlternative,
                "abstract" => $item["TermAbstract"],
                "thumbnailURI" => imageCacheUrl("term", $item["TermID"], $item["TermThumbnailURI"]),
                "thumbnailCreator" => $item["TermThumbnailCreator"],
                "thumbnailLicense" => $item["TermThumbnailLicense"],
                "websiteURI" => $item["TermWebsiteURI"],
                "embedURI" => $item["TermEmbedURI"],
                "additionalInformation" => $additionalInfo,
                "lastChanged" => $item["TermLastChanged"]
            ],
            "links" => [
                "self" => $config["dir"]["api"]."/term/".$item["TermID"]
            ],
            "relationships" => [
                "media" => [
                    "links" => [
                        "self" => $config["dir"]["api"]."/search/media?termID=".$item["TermID"]
                    ]
                ]
            ]
        ];

    } catch (Exception $e) {
        error_log("Error in termBuildNode: " . $e->getMessage());
        return false;
    }
}
