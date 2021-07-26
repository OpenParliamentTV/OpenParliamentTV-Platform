<?php 
include_once(__DIR__ . '/../../header.php'); 
require_once(__DIR__."/../../../modules/media/functions.php");
require_once(__DIR__."/../../../modules/media/include.media.php");
require_once(__DIR__."/../../../modules/utilities/functions.entities.php");
$flatDataArray = flattenEntityJSON($apiResult["data"]);
?>
<main id="content">
    <?php include_once('content.player.php'); ?>
</main>
<div id="shareQuoteModal" class="modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Quote</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="shareURL">URL</label>
                    <textarea id="shareURL" class="form-control" type="text" name="shareURL"aria-describedby="shareURLhelp"></textarea>
                    <small id="shareURLhelp" class="form-text text-muted">Copy this URL to link directly to your quote.</small>
                </div>
                <label>Preview</label>
                <div class="row row-cols-1 row-cols-sm-2">
                    <img id="sharePreviewLight" class="col img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                    <img id="sharePreviewDark" class="col img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/share-image.php">
                </div>

                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary">Save changes</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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