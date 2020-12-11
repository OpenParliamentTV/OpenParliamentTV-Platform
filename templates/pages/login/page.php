
<?php include_once(__DIR__.'/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::login; ?></h2>
		</div>
	</div>
	<form class="needs-validation row g-3">

			<div class="col-sm-6">
				<label for="username" class="form-label">E-Mail</label>
				<input type="text" class="form-control" id="username" placeholder="" value="" required="" name="user">
				<div class="invalid-feedback">
					E-Mail is required
				</div>
			</div>

			<div class="col-sm-6">
				<label for="password" class="form-label">Last name</label>
				<input type="password" class="form-control" id="password" placeholder="" value="" required="" name="password">
				<div class="invalid-feedback">
					Valid last name is required.
				</div>
			</div>
			<div class="col-12">
				<button class="w-100 btn btn-primary" type="submit">Login</button>
			</div>

	</form>
</main>
<script type="text/javascript" src="client/js/jquery-3.4.1.min.js"></script>
<script type="text/javascript" src="client/js/jquery-ui.min.js"></script>
<script type="text/javascript" src="client/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="client/js/generic.js"></script>