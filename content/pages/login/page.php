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
				<button type="button" class="button-logout btn btn-primary btn-sm"><?= L::logout; ?></button>

			<?php

			} else {

			?>
				<h2 class="mb-3"><?= L::login; ?></h2>
				<form id="login-form">
					<div class="form-floating mb-3">
						<input type="email" class="form-control" id="login-mail" name="mail" placeholder="<?= L::mailAddress; ?>">
						<label for="login-mail"><?= L::mailAddress; ?></label>
					</div>
					<div class="form-floating mb-3">
						<input type="password" class="form-control" id="login-password" name="password" placeholder="<?= L::password; ?>">
						<label for="login-password"><?= L::password; ?></label>
					</div>
					<button type="submit" class="btn btn-primary btn-sm"><?= L::login; ?></button>
					<div id="login-response" class="alert mt-3"></div>
				</form>
				<a href="passwordReset" target="_self"><?= L::passwordForgotQuestion; ?></a>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/login/client/login.functions.js?v=<?= $config["version"] ?>"></script>