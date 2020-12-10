<script type="text/javascript" src="client/FrameTrail.min.js"></script>
<script type="text/javascript">
    var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;

    var prevResultURL = <?php if ($prevResult) { 
            echo "'?id=".$prevResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
    var nextResultURL = <?php if ($nextResult) { 
            echo "'?id=".$nextResult["_source"]["meta"]["id"].$paramStr."'.replace(/\s/g, '+')";
        } else {
            echo 'null';
        } ?>;
</script>
<?php include_once(__DIR__.'/../../header.php'); ?>
<main id="content">
	<?php include_once('content.player.php'); ?>
</main>
<?php include_once(__DIR__.'/../../footer.php'); ?>
<script type="text/javascript" src="client/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="client/js/generic.js"></script>
<script type="text/javascript" src="client/js/player.js"></script>