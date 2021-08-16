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

				<div class="alert alert-info">Angemeldet als:<br><b><?=$_SESSION["userdata"]["name"]?></b><br><?=$_SESSION["userdata"]["mail"]?></div>
				<button type="button" class="button-logout btn btn-primary btn-sm"><?php echo L::logout; ?></button>

			<?php

			} else {

			?>
				<h2 class="mb-3"><?php echo L::login; ?></h2>
				<form id="login-form">
					<div class="form-group">
						<label for="login-mail"><?php echo L::mailAddress; ?></label>
						<input type="email" class="form-control" id="login-mail" name="mail">
					</div>
					<div class="form-group">
						<label for="login-password"><?php echo L::password; ?></label>
						<input type="password" class="form-control" id="login-password" name="password">
					</div>
					<button type="submit" class="btn btn-primary btn-sm"><?php echo L::login; ?></button>
					<div id="login-response" class="alert mt-3"></div>
				</form>
				<a href="passwordReset" target="_self"><?php echo L::passwordForgotQuestion; ?></a>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/login/client/login.functions.js"></script>