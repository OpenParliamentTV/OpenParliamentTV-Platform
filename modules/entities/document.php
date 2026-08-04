<?php
/**
 * Document node builder.
 *
 * Plain data, no envelope (D2): documentGetNode() returns the JSON:API node or
 * null when there is no such document, and throws OptvInvalidIdException for an
 * unusable id / OptvEntityException when the row cannot be assembled. The
 * envelope for `getItem&itemType=document` is applied by documentGetByID() in
 * api/v1/modules/document.php.
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
 * @param int|string $id DocumentID (the platform-internal integer id).
 * @param SafeMySQL|false $db Platform connection; opened here when not supplied.
 * @return array|null The node, or null when no such document exists.
 * @throws OptvInvalidIdException|OptvEntityException
 */
function documentGetNode($id, $db = false)
{
    global $config;

    if (!$id) {
        throw new OptvInvalidIdException("Missing document id.");
    }

    if (!is_object($db)) {
        $db = getApiDatabaseConnection('platform');
        if (!is_object($db)) {
            throw new OptvEntityException("Platform database unavailable.");
        }
    }

    $item = $db->getRow("SELECT * FROM ?n WHERE DocumentID=?i", $config["platform"]["sql"]["tbl"]["Document"], (int) $id);
    if (!$item) {
        return null;
    }

    $node = documentBuildNode($item, $db);
    if (!$node) {
        throw new OptvEntityException("Could not build document node for {$id}.");
    }

    return $node;
}

function documentBuildNode($item = false, $db = false) {

    global $config;

    if ((is_array($item)) && $db) {

        $return["type"] = "document";
        $return["id"] = $item["DocumentID"];
        $return["attributes"]["type"] = $item["DocumentType"];
        $return["attributes"]["wikidataID"] = $item["DocumentWikidataID"];
        $return["attributes"]["label"] = str_replace(array("\r","\n"), " ", $item["DocumentLabel"]);
        //$return["attributes"]["labelAlternative"] = str_replace(array("\r","\n"), " ", $item["DocumentLabelAlternative"]);
        $return["attributes"]["labelAlternative"] = json_decode($item["DocumentLabelAlternative"],true);
        $return["attributes"]["abstract"] = $item["DocumentAbstract"];
        $return["attributes"]["thumbnailURI"] = imageCacheUrl("document", $item["DocumentID"], $item["DocumentThumbnailURI"]);
        $return["attributes"]["thumbnailCreator"] = $item["DocumentThumbnailCreator"];
        $return["attributes"]["thumbnailLicense"] = $item["DocumentThumbnailLicense"];
        $return["attributes"]["sourceURI"] = $item["DocumentSourceURI"];
        $return["attributes"]["embedURI"] = $item["DocumentEmbedURI"];
        $return["attributes"]["additionalInformation"] = json_decode($item["DocumentAdditionalInformation"],true);
        $return["attributes"]["lastChanged"] = $item["DocumentLastChanged"];
        $return["links"]["self"] = $config["dir"]["api"]."/".$return["type"]."/".$return["id"];
        $return["relationships"]["media"]["links"]["self"] = $config["dir"]["api"]."/search/media?documentID=".$return["id"];

    } else {

        $return = false;

    }

    return $return;
}
