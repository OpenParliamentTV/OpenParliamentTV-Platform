<?php defined('OPTV') or die(); ?>
<?php
/**
 * Adapter: one media-annotation node onto the shared entity card.
 *
 * The media page's relationships list is the one surface that comes at the card
 * from an annotation rather than from a database row or an API response, so it
 * needs the translation this file does — which context line to show, which count
 * the "found" badge means, and the source-document button. Everything below the
 * translation is content/components/entity-item.php.
 *
 * Kept at this path rather than folded into the caller: an instance override of
 * content/components/entity-preview-small.php resolves by path, and a rename
 * would silently detach it.
 *
 * @var array $relationshipItem A media annotation node (modules/entities/media.php).
 * @var int   $tmpCount         Optional occurrence count for the "found" badge.
 * @var array $annotation       Optional annotation the count came from.
 */

require_once(__DIR__ . '/../../modules/utilities/security.php');
require_once(__DIR__ . '/../../modules/utilities/entities.php');
require_once(__DIR__ . '/../../modules/entities/preview.php');

$entityItem = entityPreviewNodeFromEntity($relationshipItem);

if ($entityItem !== null) {
    $entityItemOptions = [];

    // The role this entity played in the speech, when it is one worth naming.
    // The structural contexts are excluded: they either describe who was
    // speaking, or say "recognised in the text", which the panel already groups
    // by and would otherwise repeat on every card.
    $previewContext = $relationshipItem["attributes"]["context"] ?? null;
    if ($previewContext !== null
        && !in_array($previewContext, [
            "NER",
            "main-speaker-faction",
            "vice-president-faction",
            "speaker-faction",
            "proceedingsReference",
        ], true)) {
        $entityItemOptions["contextLabel"] = translateContextValue($previewContext);
    }

    // How often NER found this entity in the transcript. Only meaningful on the
    // NER panel, where every card in the list was counted the same way.
    if (!empty($tmpCount)
        && ($annotation["attributes"]["context"] ?? null) === "NER") {
        $entityItemOptions["count"] = $tmpCount;
        $entityItemOptions["countLabel"] = L::found();
    }

    // Official documents show their subtype instead of an avatar, and carry a
    // button straight to the PDF at the source.
    if ($entityItem["type"] === "document") {
        $subType = $relationshipItem["attributes"]["additionalInformation"]["subType"] ?? null;
        if (!empty($subType)) {
            $entityItemOptions["typeLabel"] = $subType;
        }

        $creator = $relationshipItem["attributes"]["additionalInformation"]["creator"][0] ?? null;
        if (!empty($creator)) {
            $entityItem["secondaryLabel"] = L::by() . ': ' . $creator;
        }

        if (($relationshipItem["attributes"]["type"] ?? null) === "officialDocument") {
            $entityItemOptions["showThumbnail"] = false;

            if (!empty($relationshipItem["attributes"]["sourceURI"])) {
                $entityItemOptions["buttonHtml"] = '<a class="entityButton" href="'
                    . hAttr($relationshipItem["attributes"]["sourceURI"])
                    . '" target="_blank"><span class="btn btn-sm icon-file-pdf"></span></a>';
            }
        }
    }

    include(__DIR__ . '/entity-item.php');
}
