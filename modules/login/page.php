<?php include_once(__DIR__ . '/../../structure/header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::login; ?></h2>
		</div>
	</div>
	<div class="row mt-5">
		<div class="col-12">
			<?php
			if ($_SESSION["login"] == 1) {

			?>

				Logged in as:<br> <?=$_SESSION["userdata"]["name"]?> (<?=$_SESSION["userdata"]["mail"]?>, <?=$_SESSION["userdata"]["role"]?>)<br><br>
				<button type="button" class="button-logout btn btn-primary">Logout</button>

			<?php

			} else {

			?>
				<ul class="nav nav-tabs" id="loginTabs" role="tablist">
					<li class="nav-item">
						<a class="nav-link active" id="login-tab" data-toggle="tab" href="#login" role="tab"
						   aria-controls="login" aria-selected="true">Login</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" id="register-tab" data-toggle="tab" href="#register" role="tab"
						   aria-controls="profile" aria-selected="false">Register</a>
					</li>
				</ul>
				<div class="tab-content pt-5" id="loginTabContent">
					<div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
						<form id="login-form">
							<div class="form-group">
								<label for="login-mail">E-mail</label>
								<input type="email" class="form-control" id="login-mail" name="mail">
							</div>
							<div class="form-group">
								<label for="login-password">Password</label>
								<input type="password" class="form-control" id="login-password" name="password">
							</div>
							<button type="submit" class="btn btn-primary">Login</button>
							<div id="login-response"></div>
						</form>

					</div>
					<div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
						<form id="register-form">
							<div class="form-group">
								<label for="register-name">Name</label>
								<input type="text" class="form-control" id="register-name" name="name">
							</div>
							<div class="form-group">
								<label for="register-mail">E-mail</label>
								<input type="email" class="form-control" id="register-mail" name="mail">
							</div>
							<div class="form-group">
								<label for="register-password">Password</label>
								<input type="password" class="form-control" id="register-password" name="password">
							</div>
							<button type="submit" class="btn btn-primary">Register</button>
						</form>
						<div id="register-response"></div>
					</div>
				</div>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../structure/footer.php'); ?>
<script type="text/javascript" src="modules/login/client/login.functions.js"></script>