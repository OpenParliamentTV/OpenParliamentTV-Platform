<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?= L::registerNewAccount; ?></h2>
			<form id="register-form" class="needs-validation" novalidate>
				<div class="form-floating mb-3">
					<input type="text" class="form-control" id="register-name" name="name" placeholder="<?= L::name; ?>" required>
					<label for="register-name"><?= L::name; ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<div class="form-floating mb-3">
					<input type="email" class="form-control" id="register-mail" name="mail" placeholder="<?= L::mailAddress; ?>" required>
					<label for="register-mail"><?= L::mailAddress; ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<div class="mb-3">
					<div class="input-group">
						<input type="password" class="form-control" id="register-password" name="password" 
							   minlength="8" autocomplete="new-password" placeholder="<?= L::password; ?>" required>
						<button class="btn btn-outline-primary" type="button" id="showPassword">
							<i class="icon-eye"></i>
						</button>
					</div>
					<div class="invalid-feedback"></div>
				</div>
				<div class="mb-3">
					<div class="input-group">
						<input type="password" class="form-control" id="register-passwordCheck" name="passwordCheck" 
							   minlength="8" autocomplete="new-password" placeholder="<?= L::passwordConfirm; ?>" required>
						<button class="btn btn-outline-primary" type="button" id="showPasswordConfirm">
							<i class="icon-eye"></i>
						</button>
					</div>
					<div class="invalid-feedback"></div>
				</div>
				<div class="progress mb-2" style="height: 5px;">
					<div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
				</div>
				<div class="text-danger small" id="passwordStrengthText"></div>
				<div class="text-danger small" id="passwordMatchText"></div>
				<button type="submit" class="w-100 btn btn-primary rounded-pill mt-3"><?= L::registerNewAccount; ?></button>
			</form>
			<div id="register-response" class="alert mt-3" style="display: none;"></div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript">
$(function() {
    // Initialize password fields
    const passwordFields = initPasswordFields({
        passwordFieldId: 'register-password',
        confirmFieldId: 'register-passwordCheck'
    });

    // Reset form validation state
    function resetValidation() {
        $("#register-form .is-invalid").removeClass("is-invalid");
        $("#register-form .invalid-feedback").empty();
    }

    $("#register-form").on('submit', function(e) {
        e.preventDefault();
        resetValidation();
        
        const formData = {
            UserName: $("#register-name").val(),
            UserMail: $("#register-mail").val(),
            UserPassword: $("#register-password").val()
        };

        // Check if passwords match
        if (!passwordFields.checkPasswordMatch()) {
            $("#register-passwordCheck")
                .addClass("is-invalid")
                .siblings(".invalid-feedback")
                .text("<?= L::messagePasswordNotIdentical; ?>");
            return;
        }

        // Check password strength
        if (!passwordFields.checkPasswordStrength()) {
            $("#register-password")
                .addClass("is-invalid")
                .siblings(".invalid-feedback")
                .text("<?= L::messagePasswordTooWeak; ?>");
            return;
        }

        $.ajax({
            url: config["dir"]["root"] + "/api/v1/user/register",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
            success: function(response) {
                if (response.meta.requestStatus === "success") {
                    $("#register-response")
                        .removeClass("alert-danger")
                        .addClass("alert-success")
                        .show()
                        .text(response.data.message);
                    
                    // Clear form
                    $("#register-form")[0].reset();
                } else {
                    // Handle validation errors
                    response.errors.forEach(function(error) {
                        if (error.meta && error.meta.domSelector) {
                            const $field = $(error.meta.domSelector);
                            $field.addClass("is-invalid");
                            $field.siblings(".invalid-feedback").html(error.detail);
                        } else {
                            // Show general error in response div
                            $("#register-response")
                                .removeClass("alert-success")
                                .addClass("alert-danger")
                                .show()
                                .html(error.detail);
                        }
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = "There was an error while registering. Please try again.";
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]) {
                    errorMessage = xhr.responseJSON.errors[0].detail;
                }
                $("#register-response")
                    .removeClass("alert-success")
                    .addClass("alert-danger")
                    .show()
                    .html(errorMessage);
            }
        });
    });
});
</script>