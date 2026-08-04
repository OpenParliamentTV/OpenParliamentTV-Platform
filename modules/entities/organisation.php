<?php
/**
 * Organisation node builder.
 *
 * Plain data, no envelope (D2): organisationGetNode() returns the JSON:API node or
 * null when there is no such organisation, and throws OptvInvalidIdException for an
 * unusable id / OptvEntityException when the row cannot be assembled. The
 * envelope for `getItem&itemType=organisation` is applied by organisationGetByID() in
 * api/v1/modules/organisation.php.
 *
 * Thumbnail URIs go through imageCacheUrl() here, inside the builder, so a
 * caller taking the plain node still gets proxied images.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/functions.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");
require_once(__DIR__ . "/../images/image-cache.php");
require_once(__DIR__ . "/../database/connections.php"); // optvDbConnection()

/**
 * @param string $id OrganisationID (a Wikidata id).
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array|null The node, or null when no such organisation exists.
 * @throws OptvInvalidIdException|OptvEntityException
 *
 * Note the deliberate difference from organisationBuildNode(): the single-item
 * node carries no `order` / `filterable` attributes, the search-result node
 * does. Both are frozen payload shapes, so they are kept apart rather than
 * unified.
 */
function organisationGetNode($id, $db = false)
{
    global $config;

    if (!$id || !preg_match("/(Q|P)\d+/i", $id)) {
        throw new OptvInvalidIdException("Missing or invalid organisation id.");
    }

    if (!is_object($db)) {
        try {
            $db = optvDbConnection('platform');
        } catch (OptvDbException $e) {
            throw new OptvEntityException("Platform database unavailable.");
        }
    }

    $item = $db->getRow("SELECT * FROM ?n WHERE OrganisationID=?s", $config["platform"]["sql"]["tbl"]["Organisation"], $id);
    if (!$item) {
        return null;
    }

    $data = [
        "type" => "organisation",
        "id" => $item["OrganisationID"],
        "attributes" => [
            "type" => $item["OrganisationType"],
            "label" => $item["OrganisationLabel"],
            "labelAlternative" => json_decode($item["OrganisationLabelAlternative"], true),
            "abstract" => $item["OrganisationAbstract"],
            "thumbnailURI" => imageCacheUrl("organisation", $item["OrganisationID"], $item["OrganisationThumbnailURI"]),
            "thumbnailCreator" => $item["OrganisationThumbnailCreator"],
            "thumbnailLicense" => $item["OrganisationThumbnailLicense"],
            "embedURI" => $item["OrganisationEmbedURI"],
            "websiteURI" => $item["OrganisationWebsiteURI"],
            "socialMediaIDs" => json_decode($item["OrganisationSocialMediaIDs"], true),
            "color" => $item["OrganisationColor"],
            "additionalInformation" => json_decode($item["OrganisationAdditionalInformation"], true),
            "lastChanged" => $item["OrganisationLastChanged"]
        ]
    ];

    $data["links"] = [
        "self" => $config["dir"]["api"]."/".$data["type"]."/".$data["id"]
    ];

    $data["relationships"] = [
        "media" => [
            "links" => [
                "self" => $config["dir"]["api"]."/"."search/media?organisationID=".$data["id"]
            ]
        ],
        "people" => [
            "links" => [
                "self" => $config["dir"]["api"]."/"."search/people?organisationID=".$data["id"]
            ]
        ]
    ];

    return $data;
}

function organisationBuildNode($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "organisation";
        $return["id"] = $item["OrganisationID"];
        $return["attributes"]["type"] = $item["OrganisationType"];
        $return["attributes"]["label"] = $item["OrganisationLabel"];
        $return["attributes"]["labelAlternative"] = json_decode($item["OrganisationLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["OrganisationAbstract"];
        $return["attributes"]["thumbnailURI"] = imageCacheUrl("organisation", $item["OrganisationID"], $item["OrganisationThumbnailURI"]);
        $return["attributes"]["thumbnailCreator"] = $item["OrganisationThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["OrganisationThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["OrganisationEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["OrganisationWebsiteURI"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["OrganisationSocialMediaIDs"],true);
        $return["attributes"]["color"] = $item["OrganisationColor"];
        $return["attributes"]["order"] = $item["OrganisationOrder"];
        $return["attributes"]["filterable"] = $item["OrganisationFilterable"];
        $return["attributes"]["additionalInformation"] = json_decode($item["OrganisationAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["OrganisationLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/"."search/media?organisationID=".$return["id"];
        $return["relationships"]["people"]["links"]["self"] = $config["dir"]["api"]."/"."search/people?organisationID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;

}
