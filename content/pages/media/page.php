<?php include_once(__DIR__ . '/../../header.php'); ?>
<main id="content">
    <?php include_once('content.player.php'); ?>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/FrameTrail.min.js"></script>
<script type="text/javascript">
	var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;

	var prevResultURL = <?php if ($prevResult) { 
            echo "'media/".$prevResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    var nextResultURL = <?php if ($nextResult) { 
            echo "'media/".$nextResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
</script>