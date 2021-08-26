<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {


include_once(__DIR__ . '/../../header.php'); 
require_once(__DIR__."/../../../modules/media/include.media.php");
$flatDataArray = flattenEntityJSON($apiResult["data"][0]);
?>
<main id="content">
    <?php include_once('content.player.php'); ?>
</main>
<div id="shareQuoteModal" class="modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo L::shareQuote; ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <label>1. <?php echo L::selectTheme; ?>:</label>
                <div class="form-row row-cols-2">
                    <div class="col">
                        <div class="card sharePreview active" data-theme="l">
                            <img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                            <div class="antialiased text-break cardMeta">
                                <div class="overflow-hidden select-none cardTitleWrapper">
                                    <div class="cardTitle text-truncate"><?= $speechTitleShort ?> | <?php echo L::brand ?></div>
                                    <div class="overflow-hidden text-break text-truncate whitespace-no-wrap select-none cardDescription"><?= L::speech.' '.L::onTheSubject.' '.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].' '.L::by.' '.$mainSpeaker['attributes']['label'].' ('.$speech["attributes"]["parliamentLabel"].', '.$formattedDate.')' ?></div>
                                </div>
                                <div class="overflow-hidden text-truncate text-nowrap cardWebsite">de.openparliament.tv</div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card sharePreview" data-theme="d">
                            <img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                            <div class="antialiased text-break cardMeta">
                                <div class="overflow-hidden select-none cardTitleWrapper">
                                    <div class="cardTitle text-truncate"><?= $speechTitleShort ?> | <?php echo L::brand ?></div>
                                    <div class="overflow-hidden text-break text-truncate whitespace-no-wrap select-none cardDescription"><?= L::speech.' '.L::onTheSubject.' '.$speech["relationships"]["agendaItem"]["data"]['attributes']["title"].' '.L::by.' '.$mainSpeaker['attributes']['label'].' ('.$speech["attributes"]["parliamentLabel"].', '.$formattedDate.')' ?></div>
                                </div>
                                <div class="overflow-hidden text-truncate text-nowrap cardWebsite">de.openparliament.tv</div>
                            </div>
                        </div>
                    </div>
                </div>
                <small class="d-block mt-2 text-muted"><?php echo L::shareQuoteMessageTheme; ?></small>
                <div class="form-group mt-3">
                    <label for="shareURL">2. <?php echo L::shareQuoteMessageURL; ?>:</label>
                    <textarea id="shareURL" class="form-control" type="text" name="shareURL" rows=3></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal"><?php echo L::close; ?></button>
            </div>
        </div>
    </div>
</div>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/FrameTrail.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/share-this.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/shareQuote.js"></script>
<script type="text/javascript">
	var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;
    var currentMediaID = '<?= $speech['id'] ?>';
</script>

<?php
}
?>