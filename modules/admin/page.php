<?php include_once(__DIR__ . '/../../structure/header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
		    <h2><?php /*echo L::imprint;*/ ?>Admin</h2>
			<?php
			 switch ($_REQUEST["t"]) {
				 case "showConflicts":
					 include_once(__DIR__."/page.showConflicts.php");
				 break;
				 case "mediaAdd":
					 include_once(__DIR__."/page.mediaAdd.php");
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
