<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<div class="alert alert-info" role="alert">
			  Password reset.
				// TODO i18n
			</div>
			<?php
			if ($_REQUEST["mail"]) {
				include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');

				$response = passwordResetMail($_REQUEST["mail"]);

				print_r($response); //TODO Output

				if ($response["success"] != "true") {

					echo "There was an error with your password reset.";

				} else {

					echo "The mail with the password reset link has been sent.<br>
				Please check your mail account.<br>
				In case you didn't receive the mail, make sure you also take a look at the spam folder.<br><br>";


				}


				// TODO i18n


			} elseif ($_REQUEST["id"]) {

				include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');
				include_once(__DIR__ . '/../../../modules/utilities/functions.php');

				if (strlen($_REQUEST["c"]) < 10) {

					echo "There was an error with your reset code. Please check it or try it again."; // TODO i18n

				} elseif (!$_REQUEST["password"]) {

					$response = passwordResetCheckCode($_REQUEST["id"], $_REQUEST["c"]);


					if ($response["success"] == "true") {


						?>
						<form id="resetpassword-form" method="post">
							<input type="hidden" name="a" value="passwordReset">
							<input type="hidden" name="c" value="<?= $response["UserPasswordReset"] ?>">
							<input type="hidden" name="id" value="<?= $response["UserID"] ?>">

							<div class="form-group">
								<label for="login-password">Password</label>
								<input type="password" class="form-control" id="login-password" name="password">
							</div>
							<div class="form-group">
								<label for="login-password-check">Password</label>
								<input type="password" class="form-control" id="login-password" name="password-check">
							</div>
							<button type="submit" class="btn btn-primary btn-sm">Change Password</button>
						</form>
					<?php
					} else {

						//TODO Output & i18n
						print_r($response);

					}

				} elseif ($_REQUEST["password"] && ($_REQUEST["password"] != $_REQUEST["password-check"])) {

					echo "Password and Password-Check were not the same."; //TODO Output & i18n

				} elseif ($_REQUEST["password"] && (!passwordStrength($_REQUEST["password"]))) {

					echo "Password is too weak."; //TODO Output & i18n

				} elseif ($_REQUEST["password"] && ($_REQUEST["password"] == $_REQUEST["password-check"])) {

					include_once(__DIR__ . '/../../../modules/user-management/passwordreset.backend.sql.php');

					$response = passwordResetCheckCode($_REQUEST["id"], $_REQUEST["c"]);

					if ($response["success"] == "true") {


						$resetResponse = passwordResetChangePassword($_REQUEST["id"], $_REQUEST["c"], $_REQUEST["password"], $_REQUEST["password-check"]);

						if ($resetResponse["success"] == "true") {

							echo "Your password has been changed."; //TODO Output & i18n

						} else {

							echo "There was an error. Please try again."; //TODO Output & i18n
							print_r($resetResponse);

						}


					} else {

						echo "Resetcode was not correct for the given user".
						print_r($response); //TODO Output & i18n

					}


				}

			} else {
				// TODO i18n
			?>
				<form id="resetpassword-mail-form" method="post">
					<input type="hidden" name="a" value="passwordReset">
					<div class="form-group">
						<label for="login-mail">E-mail</label>
						<input type="email" class="form-control" id="resetpassword-mail" name="mail">
					<button type="submit" class="btn btn-primary btn-sm">Send reset link</button>
				</form>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/passwordreset/client/passwordreset.functions.js"></script>