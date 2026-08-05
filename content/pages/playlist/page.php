<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<?php
    require_once(__DIR__ . '/../../../modules/utilities/security.php');

    // $playlist and $items come from pagePlaylist() — the resolved queue, already
    // access-checked. $playlist["owner"] is the caller's relationship to it, and
    // is what gates every editing affordance below.
    $isOwner = !empty($playlist["owner"]);
    $shareUrl = $config["dir"]["root"] . '/playlist/' . $playlist["token"];
    $firstItem = $items[0] ?? null;
    $playUrl = $firstItem
        ? $config["dir"]["root"] . '/media/' . rawurlencode($firstItem["id"]) . '?playlist=' . rawurlencode($playlist["token"]) . '&playresults=1'
        : null;
?>
<main id="content" class="container subpage playlistPage">

    <div class="playlistHeader">
        <h1 id="playlistTitle" class="playlistTitle"><?= h($playlist["title"]) ?></h1>
        <?php if (!empty($playlist["description"])): ?>
            <p class="playlistDescription"><?= h($playlist["description"]) ?></p>
        <?php endif; ?>
        <p class="playlistMeta text-muted">
            <?= h(count($items)) ?> <?= L::speeches(); ?>
        </p>

        <div class="playlistActions">
            <?php if ($playUrl): ?>
                <a href="<?= hAttr($playUrl) ?>" class="btn btn-primary rounded-pill" id="playlistPlayAll">
                    <?= L::playlistPlayAll(); ?><span class="icon-play-1 ms-1"></span>
                </a>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" id="playlistRenameButton">
                    <span class="icon-pencil"></span> <?= L::edit(); ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="playlistDeleteButton">
                    <span class="icon-trash"></span> <?= L::delete(); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($isOwner): ?>
            <div class="playlistShare mt-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="playlistSharedSwitch"
                           <?= !empty($playlist["shared"]) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="playlistSharedSwitch"><?= L::playlistShare(); ?></label>
                </div>
                <div id="playlistShareLink" class="input-group input-group-sm mt-2 <?= empty($playlist["shared"]) ? 'd-none' : '' ?>">
                    <input type="text" class="form-control" id="playlistShareUrl" readonly value="<?= hAttr($shareUrl) ?>">
                    <button class="btn btn-outline-secondary" type="button" id="playlistCopyButton"><?= L::apiKeyCopy(); ?></button>
                </div>
                <small class="d-block mt-1 text-muted"><?= L::playlistShareHint(); ?></small>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
        <p class="playlistEmpty mt-4"><?= L::playlistEmpty(); ?></p>
    <?php else: ?>
        <ol class="playlistItems mt-4" id="playlistItems">
            <?php foreach ($items as $index => $item):
                $attributes = $item["attributes"];
                $itemUrl = $config["dir"]["root"] . '/media/' . rawurlencode($item["id"]) . '?playlist=' . rawurlencode($playlist["token"]);
            ?>
                <li class="playlistItem" data-item-id="<?= hAttr($item["playlistItemID"] ?? '') ?>" data-speech-id="<?= hAttr($item["id"]) ?>">
                    <span class="playlistItemFactionBar partyIndicator" data-faction="<?= hAttr($attributes["factionID"] ?? '') ?>"></span>
                    <span class="playlistItemPosition"><?= h($index + 1) ?></span>
                    <a class="playlistItemLink" href="<?= hAttr($itemUrl) ?>">
                        <span class="playlistItemMeta">
                            <span class="playlistItemSpeaker"><?= h($attributes["speaker"] ?? '') ?></span>
                            <?php if (!empty($attributes["faction"])): ?>
                                <span class="partyIndicator partyIndicatorInline" data-faction="<?= hAttr($attributes["factionID"]) ?>"><?= h($attributes["faction"]) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="playlistItemTitle"><?= h($attributes["title"] ?? '') ?></span>
                        <span class="playlistItemFooter text-muted">
                            <?= h(!empty($attributes["dateStart"]) ? date("d.m.Y", strtotime($attributes["dateStart"])) : '') ?>
                            <?php if (!empty($item["segments"])): ?>
                                <span class="playlistItemSegments"><?= h(count($item["segments"])) ?> <?= L::playlistSegment(); ?></span>
                            <?php else: ?>
                                <span class="playlistItemSegments"><?= L::playlistWholeSpeech(); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?php if ($isOwner): ?>
                        <span class="playlistItemActions">
                            <button type="button" class="btn btn-sm playlistItemUp" title="<?= hAttr(L::playlistMoveUp()) ?>"><span class="icon-up-open-big"></span></button>
                            <button type="button" class="btn btn-sm playlistItemDown" title="<?= hAttr(L::playlistMoveDown()) ?>"><span class="icon-down-open-big"></span></button>
                            <button type="button" class="btn btn-sm playlistItemRemove" title="<?= hAttr(L::delete()) ?>"><span class="icon-trash"></span></button>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

</main>
<script type="text/javascript">
    var playlistPageData = <?= json_encode([
        "id" => $playlist["id"] ?? null,
        "token" => $playlist["token"],
        "owner" => $isOwner,
    ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/playlist/client/playlist-page.js?v=<?= $config["version"] ?>"></script>
