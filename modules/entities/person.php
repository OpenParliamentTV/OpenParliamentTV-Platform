<?php
/**
 * Person node builder.
 *
 * Plain data, no envelope (D2): personGetNode() returns the JSON:API node or
 * null when there is no such person, and throws OptvInvalidIdException for an
 * unusable id / OptvEntityException when the row cannot be assembled. The
 * envelope for `getItem&itemType=person` is applied by personGetByID() in
 * api/v1/modules/person.php.
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
 * @param string $id PersonID (a Wikidata id).
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array|null The node, or null when no such person exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function personGetNode($id, $db = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing person id.");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            throw new OptvEntityException("Platform database unavailable.");
        }
    }

    $item = $db->getRow("SELECT * FROM ?n WHERE PersonID=?s", $config["platform"]["sql"]["tbl"]["Person"], $id);
    if (!$item) {
        return null;
    }

    $node = personBuildNode($item, $db);
    if (!$node) {
        throw new OptvEntityException("Could not build person node for {$id}.");
    }

    return $node;
}

function personBuildNode($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "person";
        $return["id"] = $item["PersonID"];
        $return["attributes"]["type"] = $item["PersonType"];
        $return["attributes"]["label"] = $item["PersonLabel"];
        $return["attributes"]["labelAlternative"] = json_decode($item["PersonLabelAlternative"],true);
        $return["attributes"]["firstName"] = $item["PersonFirstName"];
        $return["attributes"]["lastName"] = $item["PersonLastName"];
        $return["attributes"]["degree"] = $item["PersonDegree"];
        $return["attributes"]["birthDate"] = $item["PersonBirthDate"];
        $return["attributes"]["gender"] = $item["PersonGender"];
        $return["attributes"]["abstract"] = $item["PersonAbstract"];
        $return["attributes"]["thumbnailURI"] = imageCacheUrl("person", $item["PersonID"], $item["PersonThumbnailURI"]);
        $return["attributes"]["thumbnailCreator"] = $item["PersonThumbnailCreator"] ? htmlentities($item["PersonThumbnailCreator"]) : null;
        $return["attributes"]["thumbnailLicense"] = $item["PersonThumbnailLicense"];
        $return["attributes"]["embedURI"] = $item["PersonEmbedURI"];
        $return["attributes"]["websiteURI"] = $item["PersonWebsiteURI"];
        $return["attributes"]["originID"] = $item["PersonOriginID"];
        $return["attributes"]["socialMediaIDs"] = json_decode($item["PersonSocialMediaIDs"],true);
        $return["attributes"]["additionalInformation"] = json_decode($item["PersonAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["PersonLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];

        if ($item["PersonPartyOrganisationID"]) {

            $itemParty = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonPartyOrganisationID"]);

            if ($itemParty) {

                $return["relationships"]["party"]["data"]["type"] = "organisation";
                $return["relationships"]["party"]["data"]["id"] = $itemParty["OrganisationID"];
                $return["relationships"]["party"]["data"]["attributes"]["label"] = $itemParty["OrganisationLabel"];
                $return["relationships"]["party"]["data"]["attributes"]["labelAlternative"] = json_decode($itemParty["OrganisationLabelAlternative"],true);
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailURI"] = imageCacheUrl("organisation", $itemParty["OrganisationID"], $itemParty["OrganisationThumbnailURI"]);
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailCreator"] = $itemParty["OrganisationThumbnailCreator"];
                $return["relationships"]["party"]["data"]["attributes"]["thumbnailLicense"] = $itemParty["OrganisationThumbnailLicense"];
                $return["relationships"]["party"]["data"]["attributes"]["websiteURI"] = $itemParty["OrganisationWebsiteURI"];
                $return["relationships"]["party"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["party"]["data"]["id"];

            } else {

                $return["relationships"]["party"] = array();

            }

        } else {

            $return["relationships"]["party"] = array();

        }

        if ($item["PersonFactionOrganisationID"]) {

            $itemFaction = $db->getRow("SELECT * FROM ".$config["platform"]["sql"]["tbl"]["Organisation"]." WHERE OrganisationID=?s",$item["PersonFactionOrganisationID"]);

            if ($itemFaction) {

                $return["relationships"]["faction"]["data"]["type"] = "organisation";
                $return["relationships"]["faction"]["data"]["id"] = $itemFaction["OrganisationID"];
                $return["relationships"]["faction"]["data"]["attributes"]["label"] = $itemFaction["OrganisationLabel"];
                $return["relationships"]["faction"]["data"]["attributes"]["labelAlternative"] = json_decode($itemFaction["OrganisationLabelAlternative"],true);
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailURI"] = imageCacheUrl("organisation", $itemFaction["OrganisationID"], $itemFaction["OrganisationThumbnailURI"]);
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailCreator"] = $itemFaction["OrganisationThumbnailCreator"];
                $return["relationships"]["faction"]["data"]["attributes"]["thumbnailLicense"] = $itemFaction["OrganisationThumbnailLicense"];
                $return["relationships"]["faction"]["data"]["attributes"]["websiteURI"] = $itemFaction["OrganisationWebsiteURI"];
                $return["relationships"]["faction"]["links"]["self"] = $config["dir"]["api"]."/"."organisation/".$return["relationships"]["faction"]["data"]["id"];

            } else {

                $return["relationships"]["faction"] = array();

            }

        } else {

            $return["relationships"]["faction"] = array();

        }

        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?personID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;


}
