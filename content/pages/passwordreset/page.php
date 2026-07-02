<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?= L::resetPassword(); ?></h2>
			<?php
			if ($_REQUEST["mail"]) {
				// Show success message for password reset request
				echo '<div class="alert alert-success">'.L::messagePasswordResetMailSent().'</div>';
			} elseif (!empty($_REQUEST["c"])) {
				if (strlen($_REQUEST["c"]) < 10) {
					echo '<div class="alert alert-danger">'.L::messagePasswordResetCodeIncorrect().'</div>';
				} else {
					?>
					<form id="resetpassword-form" class="needs-validation" novalidate>
						<input type="hidden" name="ResetCode" value="<?= hAttr($_REQUEST["c"] ?? '') ?>">
						
						<div class="mb-3">
							<div class="input-group">
								<input type="password" class="form-control" id="reset-password" name="NewPassword" 
									   minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral().' '.L::password(); ?>" required>
								<button class="btn btn-outline-primary" type="button" id="showPassword">
									<i class="icon-eye"></i>
								</button>
							</div>
							<div class="invalid-feedback"></div>
						</div>
						<div class="mb-3">
							<div class="input-group">
								<input type="password" class="form-control" id="reset-password-check" name="NewPasswordConfirm" 
									   minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral().' '.L::passwordConfirm(); ?>" required>
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
						<button type="submit" class="w-100 btn btn-primary mt-3"><?= L::changePassword(); ?></button>
						<div id="reset-response" class="alert mt-3" style="display: none;"></div>
					</form>
					<?php
				}
			} else {
			?>
				<form id="resetpassword-mail-form" class="needs-validation" novalidate>
					<div class="form-floating mb-3">
						<input type="email" class="form-control" id="reset-mail" name="UserMail" placeholder="<?= L::mailAddress(); ?>" required>
						<label for="reset-mail"><?= L::mailAddress(); ?></label>
						<div class="invalid-feedback"></div>
					</div>
					<button type="submit" class="w-100 btn btn-primary rounded-pill"><?= L::resetPassword(); ?></button>
					<div id="reset-mail-response" class="alert mt-3" style="display: none;"></div>
				</form>
			<?php
			}
			?>
		</div>
	</div>
</main>
<script>
$(function() {
    // Initialize password fields only if the reset password form exists
    const resetPasswordForm = document.getElementById('resetpassword-form');
    let passwordValidation = null;
    if (resetPasswordForm) {
        passwordValidation = initPasswordFields({
            passwordFieldId: 'reset-password',
            confirmFieldId: 'reset-password-check'
        });
    }

    // Reset validation states
    function resetValidation() {
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.innerHTML = '');
        showResponse('reset-response', null, '');
        showResponse('reset-mail-response', null, '');
    }

    // Show (or clear) a form-level response banner. type: 'success' | 'danger' | null
    function showResponse(elId, type, message) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.className = 'alert mt-3' + (type ? ' alert-' + type : '');
        el.textContent = message || '';
        el.style.display = type ? 'block' : 'none';
    }

    // Handle password reset request form submission
    const resetMailForm = document.getElementById('resetpassword-mail-form');
    if (resetMailForm) {
        resetMailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            resetValidation();

            // Get form data
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => data[key] = value);

            // Make API call
            fetch('<?= $config["dir"]["root"] ?>/api/v1/user/password-reset-request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(response => {
                if (response.meta.requestStatus === 'success') {
                    showResponse('reset-mail-response', 'success', response.data.message);
                    // Hide the request form now that the mail has been sent
                    document.getElementById('resetpassword-mail-form').style.display = 'none';
                } else {
                    // Handle validation errors
                    if (response.errors && response.errors.length > 0) {
                        response.errors.forEach(error => {
                            if (error.meta && error.meta.domSelector) {
                                const element = document.querySelector(error.meta.domSelector);
                                if (element) {
                                    element.classList.add('is-invalid');
                                    // Find the invalid-feedback div within the same input-group or form-floating
                                    const feedbackElement = element.closest('.input-group, .form-floating')?.querySelector('.invalid-feedback');
                                    if (feedbackElement) {
                                        feedbackElement.textContent = error.detail;
                                    }
                                } else {
                                    // Field not present on this page — surface at form level instead of dropping it
                                    showResponse('reset-mail-response', 'danger', error.detail);
                                }
                            } else {
                                // Show error message in the response banner if no specific field
                                showResponse('reset-mail-response', 'danger', error.detail);
                            }
                        });
                    } else {
                        // Show general error message
                        showResponse('reset-mail-response', 'danger', L.messageErrorGeneric);
                    }
                }
            })
            .catch(error => {
                showResponse('reset-mail-response', 'danger', L.messageErrorGeneric);
            });
        });
    }

    // Handle password reset form submission
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            resetValidation();

            if (!passwordValidation.checkPasswordStrength()) {
                const passwordField = document.getElementById('reset-password');
                passwordField.classList.add('is-invalid');
                passwordField.closest('.input-group').querySelector('.invalid-feedback').innerHTML = L.messagePasswordTooWeak;
                return;
            }

            if (!passwordValidation.checkPasswordMatch()) {
                const passwordConfirmField = document.getElementById('reset-password-check');
                passwordConfirmField.classList.add('is-invalid');
                passwordConfirmField.closest('.input-group').querySelector('.invalid-feedback').innerHTML = L.messagePasswordNotIdentical;
                return;
            }

            // Get form data
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => data[key] = value);

            // Make API call
            fetch('/api/v1/user/password-reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(response => {
                if (response.meta.requestStatus === 'success') {
                    showResponse('reset-response', 'success', response.data.message);
                    // Redirect to login page after 2 seconds
                    setTimeout(() => {
                        window.location.href = '<?= $config["dir"]["root"] ?>/login';
                    }, 2000);
                } else {
                    // Handle validation errors
                    if (response.errors && response.errors.length > 0) {
                        response.errors.forEach(error => {
                            if (error.meta && error.meta.domSelector) {
                                const element = document.querySelector(error.meta.domSelector);
                                if (element) {
                                    element.classList.add('is-invalid');
                                    // Find the invalid-feedback div within the same input-group or form-floating
                                    const feedbackElement = element.closest('.input-group, .form-floating')?.querySelector('.invalid-feedback');
                                    if (feedbackElement) {
                                        feedbackElement.textContent = error.detail;
                                    }
                                } else {
                                    // Field not present on this page — surface at form level instead of dropping it
                                    showResponse('reset-response', 'danger', error.detail);
                                }
                            } else {
                                // Show error message in the response banner if no specific field
                                showResponse('reset-response', 'danger', error.detail);
                            }
                        });
                    } else {
                        // Show general error message
                        showResponse('reset-response', 'danger', L.messageErrorGeneric);
                    }
                }
            })
            .catch(error => {
                showResponse('reset-response', 'danger', L.messageErrorGeneric);
            });
        });
    }
});
</script>