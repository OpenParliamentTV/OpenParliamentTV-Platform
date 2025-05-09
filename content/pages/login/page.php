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
				<button type="button" class="w-100 button-logout btn btn-primary rounded-pill"><?= L::logout; ?></button>

			<?php

			} else {

			?>
				<h2 class="mb-3"><?= L::login; ?></h2>
				<form id="login-form" class="needs-validation mb-3" novalidate>
					<div class="form-floating mb-3">
						<input type="email" class="form-control" id="login-mail" name="mail" placeholder="<?= L::mailAddress; ?>" required>
						<label for="login-mail"><?= L::mailAddress; ?></label>
						<div class="invalid-feedback"></div>
					</div>
					<div class="form-floating mb-3">
						<input type="password" class="form-control" id="login-password" name="password" placeholder="<?= L::password; ?>" required>
						<label for="login-password"><?= L::password; ?></label>
						<div class="invalid-feedback"></div>
					</div>
					<button type="submit" class="w-100 btn btn-primary rounded-pill"><?= L::login; ?></button>
					<div id="login-response" class="alert mt-3" style="display: none;"></div>
				</form>
				<a href="password-reset" target="_self"><?= L::passwordForgotQuestion; ?></a>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script>
$(function() {
    // Reset form validation state
    function resetValidation() {
        $("#login-form .is-invalid").removeClass("is-invalid");
        $("#login-form .invalid-feedback").empty();
    }

    $("#login-form").on('submit', function(e) {
        e.preventDefault();
        resetValidation();
        
        const formData = {
            UserMail: $("#login-mail").val(),
            UserPassword: $("#login-password").val()
        };

        $.ajax({
            url: config["dir"]["root"] + "/api/v1/user/login",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
            success: function(response) {
                if (response.meta.requestStatus === "success") {
                    $("#login-response")
                        .removeClass("alert-danger")
                        .addClass("alert-success")
                        .show()
                        .text(response.data.message);
                    
                    // Reload page after successful login
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    // Handle validation errors
                    response.errors.forEach(function(error) {
                        if (error.meta && error.meta.domSelector) {
                            const $field = $(error.meta.domSelector);
                            $field.addClass("is-invalid");
                            $field.siblings(".invalid-feedback").html(error.detail);
                        } else {
                            // Show general error in response div
                            $("#login-response")
                                .removeClass("alert-success")
                                .addClass("alert-danger")
                                .show()
                                .html(error.detail);
                        }
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = "There was an error while logging in. Please try again.";
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]) {
                    errorMessage = xhr.responseJSON.errors[0].detail;
                }
                $("#login-response")
                    .removeClass("alert-success")
                    .addClass("alert-danger")
                    .show()
                    .html(errorMessage);
            }
        });
    });

    $(".button-logout").click(function() {
        $.ajax({
            url: config["dir"]["root"] + "/api/v1/user/logout",
            method: "POST",
            contentType: "application/json",
            success: function(response) {
                if (response.meta.requestStatus === "success") {
                    location.reload();
                } else {
                    console.log("Error during logout:", response.errors[0].detail);
                }
            },
            error: function() {
                console.log("There was an error while logging out. Please try again.");
            }
        });
    });
});
</script>