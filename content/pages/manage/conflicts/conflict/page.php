<?php

exit;

//TODO

include_once(__DIR__ . '/../../../../header.php');

require_once(__DIR__."/../../../../../modules/utilities/functions.conflicts.php");


/*
$("tbody tr.clickable").on("click", function() {
	$($(this).data("target")+" .first pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictidentifier"));
	$($(this).data("target")+" .second pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictrival"));
	$($(this).data("target")+" .third pre").load("<?= $config["dir"]["root"] ?>/server/ajaxServer.php?a=getMediaDiffs&v1="+$(this).data("conflictidentifier")+"&v2="+$(this).data("conflictrival"));
})
*/

?>
<main class="container-fluid subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Manage Detail Conflict</h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<?php

			$conflict = getConflicts($_REQUEST["id"]);
			//print_r($conflict);

			if (!$conflict) {
				echo "Conflict not found";
			} else {
				$conflict = $conflict[0];
				switch($conflict["ConflictEntity"]) {

					case "Media":
						include_once(__DIR__."/content.media.php");
					break;

					case "Party":
					break;
					default:
					break;

				}
			}

			?>

		</div>
	</div>
</main>

<?php include_once(__DIR__ . '/../../../footer.php'); ?>
<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/manage/conflicts/conflict/client/style.css?v=<?= $config["version"] ?>" media="all" />