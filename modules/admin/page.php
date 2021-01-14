<?php include_once(__DIR__ . '/../../structure/header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
		    <h2><?php echo L::imprint; ?></h2>
			<?php
			 switch ($_REQUEST["t"]) {
				 case "showConflicts":
					 include_once(__DIR__."/page.showConflicts.php");
				 break;
				 default:
					 include_once(__DIR__."/page.default.php");
				 break;
			 }
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../structure/footer.php'); ?>
<script type="application/javascript">
	$("tbody tr.clickable").on("click", function() {
		$($(this).data("target")+" .first pre").load("./server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictidentifier"));
		$($(this).data("target")+" .second pre").load("./server/ajaxServer.php?a=getMedia&v="+$(this).data("conflictrival"));
		$($(this).data("target")+" .third pre").load("./server/ajaxServer.php?a=getMediaDiffs&v1="+$(this).data("conflictidentifier")+"&v2="+$(this).data("conflictrival"));
	})
</script>