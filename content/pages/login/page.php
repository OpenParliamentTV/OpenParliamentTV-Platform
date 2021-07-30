<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
            <?php
            if ($alertText) {
                echo '<div class="alert alert-info" role="alert">'.$alertText.'</div>';
            }
			if ($_SESSION["login"] == 1) {

			?>

				Logged in as:<br> <?=$_SESSION["userdata"]["name"]?> (<?=$_SESSION["userdata"]["mail"]?>, <?=$_SESSION["userdata"]["role"]?>)<br><br>
				<button type="button" class="button-logout btn btn-primary">Logout</button>

			<?php

			} else {

			?>
				<form id="login-form">
					<div class="form-group">
						<label for="login-mail">E-mail</label>
						<input type="email" class="form-control" id="login-mail" name="mail">
					</div>
					<div class="form-group">
						<label for="login-password">Password</label>
						<input type="password" class="form-control" id="login-password" name="password">
					</div>
					<button type="submit" class="btn btn-primary btn-sm">Login</button>
					<div id="login-response"></div>
				</form>
				<a href="passwordReset" target="_self">Forgot password?</a>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/login/client/login.functions.js"></script>