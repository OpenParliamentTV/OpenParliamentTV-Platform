<?php include_once(__DIR__ . '/../structure/header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::login; ?></h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12">
			<form>
				<div class="form-group">
					<label for="mail">E-mail</label>
					<input type="email" class="form-control" id="mail" name="mail">
				</div>
				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" class="form-control" id="password" name="password">
				</div>
				<button type="submit" class="btn btn-primary">Login</button>
			</form>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../structure/footer.php'); ?>