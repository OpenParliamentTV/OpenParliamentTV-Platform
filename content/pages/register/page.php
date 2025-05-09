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
				<div class="form-floating mb-3">
					<input type="password" class="form-control" id="register-password" name="password" placeholder="<?= L::password; ?>" required>
					<label for="register-password"><?= L::password; ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<div class="form-floating mb-3">
					<input type="password" class="form-control" id="register-passwordCheck" name="passwordCheck" placeholder="<?= L::passwordConfirm; ?>" required>
					<label for="register-passwordCheck"><?= L::passwordConfirm; ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<button type="submit" class="w-100 btn btn-primary rounded-pill"><?= L::registerNewAccount; ?></button>
			</form>
			<div id="register-response" class="alert mt-3" style="display: none;"></div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>
<script type="text/javascript">
$(function() {
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
        if ($("#register-password").val() !== $("#register-passwordCheck").val()) {
            $("#register-passwordCheck")
                .addClass("is-invalid")
                .siblings(".invalid-feedback")
                .text("<?= L::messagePasswordNotIdentical; ?>");
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