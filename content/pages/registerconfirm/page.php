<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<div class="alert alert-info" role="alert"><?= L::registerConfirmMailAddress(); ?></div>

			<?php
			// Call the API endpoint
			$apiResult = apiV1([
				"action" => "user",
				"itemType" => "confirm-registration",
				"ConfirmationCode" => $_REQUEST["c"] ?? ""
			]);

			if ($apiResult["meta"]["requestStatus"] === "success") {
				echo '<div class="alert alert-success" role="alert">' . safeHtml($apiResult["data"]["message"]) . '</div>';
			} else {
				echo '<div class="alert alert-danger" role="alert">' . safeHtml($apiResult["errors"][0]["detail"]) . '</div>';
			}
			?>

		</div>
	</div>
</main>
<script type="text/javascript" src="content/pages/registerconfirm/client/registerconfirm.functions.js"></script>