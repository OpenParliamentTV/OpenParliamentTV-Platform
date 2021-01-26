<?php
include_once(__DIR__ . '/../../../header.php');

include_once(__DIR__."/../../../../modules/import/functions.conflicts.php");


/*
$("tbody tr.clickable").on("click", function() {
	$($(this).data("target")+" .first pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictidentifier"));
	$($(this).data("target")+" .second pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictrival"));
	$($(this).data("target")+" .third pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMediaDiffs&v1="+$(this).data("conflictidentifier")+"&v2="+$(this).data("conflictrival"));
})
*/

?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Manage Detail Conflict</h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<?php

			echo json_encode(getConflicts($_REQUEST["id"]));

			?>

		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../../footer.php'); ?>