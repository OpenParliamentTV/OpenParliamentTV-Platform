<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?= L::registerNewAccount; ?></h2>
			<form id="register-form">
				<div class="form-group">
					<label for="register-name"><?= L::name; ?></label>
					<input type="text" class="form-control" id="register-name" name="name">
				</div>
				<div class="form-group">
					<label for="register-mail"><?= L::mailAddress; ?></label>
					<input type="email" class="form-control" id="register-mail" name="mail">
				</div>
				<div class="form-group">
					<label for="register-password"><?= L::password; ?></label>
					<input type="password" class="form-control" id="register-password" name="password">
				</div>
				<div class="form-group">
					<label for="register-passwordCheck"><?= L::passwordConfirm; ?></label>
					<input type="password" class="form-control" id="register-passwordCheck" name="passwordCheck">
				</div>
				<button type="submit" class="btn btn-primary btn-sm"><?= L::registerNewAccount; ?></button>
			</form>
			<div id="register-response" class="alert mt-3"></div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="content/pages/register/client/register.functions.js"></script>