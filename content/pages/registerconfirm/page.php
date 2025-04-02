<?php include_once(__DIR__ . '/../../header.php'); ?>
<?php include_once(__DIR__ . '/../../../modules/user-management/registerconfirm.backend.sql.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<div class="alert alert-info" role="alert"><?= L::registerConfirmMailAddress; ?></div>

			<?php

			$response = registerConfirm($_REQUEST["id"],$_REQUEST["c"]);
			print_r($response); //TODO Output

			?>


		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="content/pages/registerconfirm/client/registerconfirm.functions.js"></script>