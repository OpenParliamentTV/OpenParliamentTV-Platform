<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?php echo L::resetPassword; ?></h2>
			<?php
			if ($_REQUEST["mail"]) {
				include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');

				$response = passwordResetMail($_REQUEST["mail"]);

				//print_r($response);

				if ($response["success"] != "true") {
					echo '<div class="alert alert-danger">'.$response["txt"].'</div>';
				} else {
					echo '<div class="alert alert-success">'.L::messagePasswordResetMailSent.'</div>';
				}


			} elseif ($_REQUEST["id"]) {

				include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');
				include_once(__DIR__ . '/../../../modules/utilities/functions.php');

				if (strlen($_REQUEST["c"]) < 10) {
					
					echo '<div class="alert alert-danger">'.L::messagePasswordResetCodeIncorrect.'</div>';

				} elseif (!$_REQUEST["password"]) {

					$response = passwordResetCheckCode($_REQUEST["id"], $_REQUEST["c"]);


					if ($response["success"] == "true") {


						?>
						<form id="resetpassword-form" method="post">
							<input type="hidden" name="a" value="passwordReset">
							<input type="hidden" name="c" value="<?= $response["UserPasswordReset"] ?>">
							<input type="hidden" name="id" value="<?= $response["UserID"] ?>">

							<div class="form-group">
								<label for="login-password"><?php echo L::newNeutral.' '.L::password; ?></label>
								<input type="password" class="form-control" id="login-password" name="password">
							</div>
							<div class="form-group">
								<label for="login-password-check"><?php echo L::newNeutral.' '.L::passwordConfirm; ?></label>
								<input type="password" class="form-control" id="login-password" name="password-check">
							</div>
							<button type="submit" class="btn btn-primary btn-sm"><?php echo L::changePassword; ?></button>
						</form>
					<?php
					} else {

						echo '<div class="alert alert-danger">'.$response["txt"].'</div>';
						//print_r($response);

					}

				} elseif ($_REQUEST["password"] && ($_REQUEST["password"] != $_REQUEST["password-check"])) {

					echo '<div class="alert alert-danger">'.L::messagePasswordNotIdentical.'</div>';

				} elseif ($_REQUEST["password"] && (!passwordStrength($_REQUEST["password"]))) {

					echo '<div class="alert alert-danger">'.L::messagePasswordTooWeak.'</div>';

				} elseif ($_REQUEST["password"] && ($_REQUEST["password"] == $_REQUEST["password-check"])) {

					include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');

					$response = passwordResetCheckCode($_REQUEST["id"], $_REQUEST["c"]);

					if ($response["success"] == "true") {


						$resetResponse = passwordResetChangePassword($_REQUEST["id"], $_REQUEST["c"], $_REQUEST["password"], $_REQUEST["password-check"]);

						if ($resetResponse["success"] == "true") {

							echo '<div class="alert alert-success">'.L::messagePasswordResetSuccess.'</div>';
							echo '<a href="login" class="btn btn-primary btn-sm">'.L::login.'</a>';

						} else {

							echo '<div class="alert alert-danger">'.L::messageErrorGeneric.'</div>';
							//print_r($resetResponse);

						}


					} else {

						echo '<div class="alert alert-danger">'.L::messagePasswordResetCodeIncorrect.'</div>';
						//print_r($response);

					}


				}

			} else {
			?>
				<form id="resetpassword-mail-form" method="post">
					<input type="hidden" name="a" value="passwordReset">
					<div class="form-group">
						<label for="login-mail"><?php echo L::mailAddress; ?></label>
						<input type="email" class="form-control" id="resetpassword-mail" name="mail">
					</div>
					<button type="submit" class="btn btn-primary btn-sm"><?php echo L::resetPassword; ?></button>
				</form>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/passwordreset/client/passwordreset.functions.js"></script>