<?php
require_once(__DIR__ . '/../../modules/utilities/security.php');
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

$secondaryLabel = isset($entity["attributes"]["labelAlternative"][0]) ? $entity["attributes"]["labelAlternative"][0] : '';
if ($entity["type"] == "person" && isset($entity["attributes"]["faction"]["label"])) {
    $secondaryLabel = $entity["attributes"]["faction"]["label"];
}

?>
<div class="entityPreview col" data-type="<?= hAttr($entity["type"]) ?>">
    <div class="entityContainer partyIndicator" data-faction="<?= hAttr(isset($entity["attributes"]["faction"]["id"]) ? $entity["attributes"]["faction"]["id"] : '') ?>">
        <a href="<?= $config["dir"]["root"].'/'.hAttr($entity["type"]).'/'.hAttr($entity["id"]) ?>">
            <?php if (isset($entity["attributes"]["type"]) && $entity["attributes"]["type"] != "officialDocument") { ?>
                <div class="thumbnailContainer">
                    <div class="rounded-circle">
                        <?php if ($entity["attributes"]["thumbnailURI"]) { ?>
                            <img src="<?= hAttr($entity["attributes"]["thumbnailURI"]) ?>" alt="..."/>
                        <?php } else { ?>
                            <span class="icon-type-<?= hAttr($entity["type"]) ?>" style="position: absolute;top: 48%;left: 50%;font-size: 28px;transform: translateX(-50%) translateY(-50%);"></span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <div>
                <?php if ($entity["type"] == "document" && isset($relationshipItem["attributes"]["additionalInformation"]["subType"])) { ?>
                    <div class="entityType"><?= h($relationshipItem["attributes"]["additionalInformation"]["subType"]) ?></div>
                <?php } ?>
                <div class="entityTitle"><?= h($entity["attributes"]["label"]) ?></div>
                <div class="break-lines truncate-lines"><?= h($secondaryLabel) ?></div>
                <?php if ($entity["type"] == "document" && isset($relationshipItem["attributes"]["additionalInformation"]["creator"][0])) { ?>
                    <div class="text-truncate"><?= L::by() ?>: <?= h($relationshipItem["attributes"]["additionalInformation"]["creator"][0]) ?></div>
                <?php } ?>
                <?php if ($contextLabelIdentifier) { ?>
                    <div><span class="icon-megaphone"></span><?= L('context'.$contextLabelIdentifier) ?></div>
                <?php } ?>
                <?php if ($countFound > 0 && isset($annotation) && isset($annotation["attributes"]["context"]) && $annotation["attributes"]["context"] == "NER") { ?>
                    <div><?= L::found() ?>: <span class="badge rounded-pill"><?= h($countFound) ?></span></div>
                <?php } ?>
            </div>
        </a>
        <?php if ($entity["type"] == "document" && isset($entity["attributes"]["type"]) && $entity["attributes"]["type"] == "officialDocument") { ?>
            <a class="entityButton" href="<?= hAttr($relationshipItem["attributes"]["sourceURI"]) ?>" target="_blank"><span class="btn btn-sm icon-file-pdf"></span></a>
        <?php } ?>
    </div>
</div>