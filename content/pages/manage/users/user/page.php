<?php include_once(__DIR__ . '/../../../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Manage Detail User</h2>
			<div class="card mb-3">
				<div class="card-body">

					<?php


					if (!$_REQUEST["id"]) {

						echo "No UserID was given.";  // TODO i18n

					} elseif (($_SESSION["userdata"]["id"] != $_REQUEST["id"]) && ($_SESSION["userdata"]["role"] != "admin")) {

						echo "You are not allowed to edit this User.";  // TODO i18n

					} else {


						require_once(__DIR__."/../../../../../modules/user-management/users.backend.php");

						$user = getUsers($_REQUEST["id"]);

						if ($user["success"] != "true") {

							echo "There was an error getting userinformation."; // TODO i18n
							print_r($user);

						} else {
							$user = $user["return"];
							echo '
								<form id="useredit-form">
								<input type="hidden" name="id" value="'.$user["UserID"].'">
								<input type="hidden" name="a" value="userChange">
								<div class="form-group">
									<label for="useredit-name">Name</label>
									<input type="text" class="form-control" id="useredit-name" name="name" value="'.$user["UserName"].'">
								</div>
								<div class="form-group">
									<label for="useredit-mail">E-mail</label>
									<input type="email" class="form-control" id="useredit-mail" name="mail" value="'.$user["UserMail"].'">
								</div>
								<div class="form-group">
									<label for="useredit-password">New Password</label>
									<input type="password" class="form-control" id="useredit-password" name="password">
								</div>
								<div class="form-group">
									<label for="useredit-password-check">Retype new Password</label>
									<input type="password" class="form-control" id="useredit-password" name="password">
								</div>';
							if ($_SESSION["userdata"]["role"] == "admin") {
								echo '<div class="form-group">
									<label for="useredit-role">Role</label>
									<select class="form-control" id="useredit-role" name="active">
										<option class="form-control" value="1" ' . (($user["UserRole"] == "user") ? " selected" : "") . '>User</option>
										<option class="form-control" value="0" ' . ((!$user["UserRole"] == "admin") ? " selected" : "") . '>Administrator</option>
									</select>
								</div>
								<div class="form-group">
									<label for="useredit-mail">Activated</label>
									<select class="form-control" id="useredit-active" name="active">
										<option class="form-control" value="1" ' . (($user["UserActive"]) ? " selected" : "") . '>User is activated</option>
										<option class="form-control" value="0" ' . ((!$user["UserActive"]) ? " selected" : "") . '>User is not activated</option>
									</select>
								</div>
								<div class="form-group">
									<label for="useredit-mail">Blocked</label>
									<select class="form-control" id="useredit-blocked" name="blocked">
										<option class="form-control" value="1" ' . (($user["UserBlocked"]) ? " selected" : "") . '>User is blocked</option>
										<option class="form-control" value="0" ' . ((!$user["UserBlocked"]) ? " selected" : "") . '>User is not blocked</option>
									</select>
								</div>';
							}
							echo '	<button type="submit" class="btn btn-primary btn-sm">Save changes</button>
							</form>
							';
						}
					}

					?>

				</div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../../../footer.php'); ?>