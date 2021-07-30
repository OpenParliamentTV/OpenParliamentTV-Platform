<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::logout; ?></h2>
            <?php

            require_once(__DIR__."/../../../modules/user-management/logout.backend.php");

            logout();

            ?>
            Erfolgreich ausgeloggt.
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>