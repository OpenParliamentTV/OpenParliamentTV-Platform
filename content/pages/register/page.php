<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<!--<div class="alert alert-info" role="alert"></div>-->
			<form id="register-form">
				<div class="form-group">
					<label for="register-name"><?php echo L::name; ?></label>
					<input type="text" class="form-control" id="register-name" name="name">
				</div>
				<div class="form-group">
					<label for="register-mail"><?php echo L::mailAddress; ?></label>
					<input type="email" class="form-control" id="register-mail" name="mail">
				</div>
				<div class="form-group">
					<label for="register-password"><?php echo L::password; ?></label>
					<input type="password" class="form-control" id="register-password" name="password">
				</div>
				<div class="form-group">
					<label for="register-passwordCheck"><?php echo L::password; ?></label>
					<input type="password" class="form-control" id="register-passwordCheck" name="passwordCheck">
				</div>
				<button type="submit" class="btn btn-primary btn-sm"><?php echo L::registerNewAccount; ?></button>
			</form>
			<div id="register-response"></div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript" src="content/pages/register/client/register.functions.js"></script>