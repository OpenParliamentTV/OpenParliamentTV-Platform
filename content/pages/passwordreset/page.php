<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?= L::resetPassword; ?></h2>
			<?php
			if ($_REQUEST["mail"]) {
				// Show success message for password reset request
				echo '<div class="alert alert-success">'.L::messagePasswordResetMailSent.'</div>';
			} elseif ($_REQUEST["id"]) {
				if (strlen($_REQUEST["c"]) < 10) {
					echo '<div class="alert alert-danger">'.L::messagePasswordResetCodeIncorrect.'</div>';
				} else {
					?>
					<form id="resetpassword-form" class="needs-validation" novalidate>
						<input type="hidden" name="UserID" value="<?= $_REQUEST["id"] ?>">
						<input type="hidden" name="ResetCode" value="<?= $_REQUEST["c"] ?>">
						
						<div class="mb-3">
							<div class="input-group">
								<input type="password" class="form-control" id="reset-password" name="NewPassword" 
									   minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral.' '.L::password; ?>" required>
								<button class="btn btn-outline-primary" type="button" id="showPassword">
									<i class="icon-eye"></i>
								</button>
							</div>
							<div class="invalid-feedback"></div>
						</div>
						<div class="mb-3">
							<div class="input-group">
								<input type="password" class="form-control" id="reset-password-check" name="password-check" 
									   minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral.' '.L::passwordConfirm; ?>" required>
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
						<button type="submit" class="w-100 btn btn-primary rounded-pill mt-3"><?= L::changePassword; ?></button>
						<div id="reset-response" class="alert mt-3" style="display: none;"></div>
					</form>
					<?php
				}
			} else {
			?>
				<form id="resetpassword-mail-form" class="needs-validation" novalidate>
					<div class="form-floating mb-3">
						<input type="email" class="form-control" id="reset-mail" name="UserMail" placeholder="<?= L::mailAddress; ?>" required>
						<label for="reset-mail"><?= L::mailAddress; ?></label>
						<div class="invalid-feedback"></div>
					</div>
					<button type="submit" class="w-100 btn btn-primary rounded-pill"><?= L::resetPassword; ?></button>
					<div id="reset-mail-response" class="alert mt-3" style="display: none;"></div>
				</form>
			<?php
			}
			?>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script>
$(function() {
    // Initialize password fields if the reset form exists
    const passwordFields = initPasswordFields({
        passwordFieldId: 'reset-password',
        confirmFieldId: 'reset-password-check',
        strengthBarId: 'passwordStrength',
        strengthTextId: 'passwordStrengthText',
        matchTextId: 'passwordMatchText',
        showPasswordBtnId: 'showPassword',
        showPasswordConfirmBtnId: 'showPasswordConfirm'
    });

    // Reset form validation state
    function resetValidation(formId) {
        $(formId + " .is-invalid").removeClass("is-invalid");
        $(formId + " .invalid-feedback").empty();
    }

    // Handle password reset request form
    $("#resetpassword-mail-form").on('submit', function(e) {
        e.preventDefault();
        resetValidation("#resetpassword-mail-form");
        
        const formData = {
            UserMail: $("#reset-mail").val()
        };

        $.ajax({
            url: config["dir"]["root"] + "/api/v1/user/password-reset-request",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
            success: function(response) {
                if (response.meta.requestStatus === "success") {
                    $("#reset-mail-response")
                        .removeClass("alert-danger")
                        .addClass("alert-success")
                        .show()
                        .text(response.data.message);
                    
                    // Redirect to show success message
                    window.location.href = window.location.pathname + "?mail=1";
                } else {
                    // Handle validation errors
                    response.errors.forEach(function(error) {
                        if (error.meta && error.meta.domSelector) {
                            const $field = $(error.meta.domSelector);
                            $field.addClass("is-invalid");
                            $field.siblings(".invalid-feedback").html(error.detail);
                        } else {
                            // Show general error in response div
                            $("#reset-mail-response")
                                .removeClass("alert-success")
                                .addClass("alert-danger")
                                .show()
                                .html(error.detail);
                        }
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = "There was an error while processing your request. Please try again.";
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]) {
                    errorMessage = xhr.responseJSON.errors[0].detail;
                }
                $("#reset-mail-response")
                    .removeClass("alert-success")
                    .addClass("alert-danger")
                    .show()
                    .html(errorMessage);
            }
        });
    });

    // Handle password reset form
    $("#resetpassword-form").on('submit', function(e) {
        e.preventDefault();
        resetValidation("#resetpassword-form");
        
        // Check if passwords match
        if (!passwordFields.checkPasswordMatch()) {
            $("#reset-password-check")
                .addClass("is-invalid")
                .siblings(".invalid-feedback")
                .text("<?= L::messagePasswordNotIdentical; ?>");
            return;
        }

        // Check password strength
        if (!passwordFields.checkPasswordStrength()) {
            $("#reset-password")
                .addClass("is-invalid")
                .siblings(".invalid-feedback")
                .text("<?= L::messagePasswordTooWeak; ?>");
            return;
        }

        const formData = {
            UserID: $("input[name='UserID']").val(),
            ResetCode: $("input[name='ResetCode']").val(),
            NewPassword: $("#reset-password").val()
        };

        $.ajax({
            url: config["dir"]["root"] + "/api/v1/user/password-reset",
            method: "POST",
            data: JSON.stringify(formData),
            contentType: "application/json",
            success: function(response) {
                if (response.meta.requestStatus === "success") {
                    $("#reset-response")
                        .removeClass("alert-danger")
                        .addClass("alert-success")
                        .show()
                        .html(response.data.message + '<br><a href="login" class="w-100 btn btn-primary rounded-pill mt-3"><?= L::login; ?></a>');
                    
                    // Clear form
                    $("#resetpassword-form")[0].reset();
                } else {
                    // Handle validation errors
                    response.errors.forEach(function(error) {
                        if (error.meta && error.meta.domSelector) {
                            const $field = $(error.meta.domSelector);
                            $field.addClass("is-invalid");
                            $field.siblings(".invalid-feedback").html(error.detail);
                        } else {
                            // Show general error in response div
                            $("#reset-response")
                                .removeClass("alert-success")
                                .addClass("alert-danger")
                                .show()
                                .html(error.detail);
                        }
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = "There was an error while resetting your password. Please try again.";
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0]) {
                    errorMessage = xhr.responseJSON.errors[0].detail;
                }
                $("#reset-response")
                    .removeClass("alert-success")
                    .addClass("alert-danger")
                    .show()
                    .html(errorMessage);
            }
        });
    });
});
</script>