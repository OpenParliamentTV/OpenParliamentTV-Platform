<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?= L::logout(); ?></h2>
            <p><?= L::messageLogoutSuccess(); ?></p>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>