<?php
//Entity-specific values

$entity = $relationshipItem;
$countFound = ((isset($tmpCount)) ? $tmpCount : 0);

$contextLabelIdentifier = null;
if (isset($entity["attributes"]["context"]) 
    && $entity["attributes"]["context"] != "NER"
    && $entity["attributes"]["context"] != "main-speaker-faction"
    && $entity["attributes"]["context"] != "vice-president-faction"
    && $entity["attributes"]["context"] != "speaker-faction" 
    && $entity["attributes"]["context"] != "proceedingsReference") {
    $contextLabelIdentifier = lcfirst(implode('', array_map('ucfirst', explode('-', $entity["attributes"]["context"]))));
}

$secondaryLabel = $entity["attributes"]["labelAlternative"][0];
if ($entity["type"] == "person") {
    $secondaryLabel = $entity["attributes"]["faction"]["label"];
}

$entityIcon = "";
if ($entity["type"] == "person") {
    $entityIcon = "icon-torso";
} else if ($entity["type"] == "organisation") {
    $entityIcon = "icon-bank";
} else if ($entity["type"] == "document") {
    $entityIcon = "icon-doc-text";
} else if ($entity["type"] == "term") {
    $entityIcon = "icon-tag-1";
}


?>
<div class="entityPreview col" data-type="<?= $entity["type"]?>">
    <div class="entityContainer partyIndicator" data-faction="<?= $entity["attributes"]["faction"]["id"] ?>">
        <a href="<?= $config["dir"]["root"].'/'.$entity["type"].'/'.$entity["id"] ?>">
            <?php if ($entity["attributes"]["type"] != "officialDocument") { ?>
                <div class="thumbnailContainer">
                    <div class="rounded-circle">
                        <?php if ($entity["attributes"]["thumbnailURI"]) { ?>
                            <img src="<?= $entity["attributes"]["thumbnailURI"] ?>" alt="..."/>
                        <?php } else { ?>
                            <span class="<?= $entityIcon ?>" style="position: absolute;top: 48%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <div>
                <?php if ($entity["type"] == "document" && isset($relationshipItem["attributes"]["additionalInformation"]["subType"])) { ?>
                    <div class="entityType"><?= $relationshipItem["attributes"]["additionalInformation"]["subType"] ?></div>
                <?php } ?>
                <div class="entityTitle"><?= $entity["attributes"]["label"] ?></div>
                <div class="break-lines truncate-lines"><?= $secondaryLabel ?></div>
                <?php if ($entity["type"] == "document" && isset($relationshipItem["attributes"]["additionalInformation"]["creator"][0])) { ?>
                    <div><?= L::by ?>: <?= $relationshipItem["attributes"]["additionalInformation"]["creator"][0] ?></div>
                <?php } ?>
                <?php if ($contextLabelIdentifier) { ?>
                    <div><span class="icon-megaphone"></span><?= L('context'.$contextLabelIdentifier) ?></div>
                <?php } ?>
                <?php if ($countFound > 0) { ?>
                    <div><?= L::found ?>: <span class="badge badge-pill badge-primary"><?= $countFound ?></span></div>
                <?php } ?>
            </div>
        </a>
        <?php if ($entity["type"] == "document" && $entity["attributes"]["type"] == "officialDocument") { ?>
            <a class="entityButton" href="<?= $relationshipItem["attributes"]["sourceURI"] ?>" target="_blank"><span class="btn btn-sm icon-file-pdf"></span></a>
        <?php } ?>
    </div>
</div>