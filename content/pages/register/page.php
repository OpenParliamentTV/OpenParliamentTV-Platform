<?php include_once(include_custom(realpath(__DIR__ . '/../../header.php'),false)); ?>
<main class="container subpage">
	<div class="row mt-4 justify-content-center">
		<div class="col-11 col-md-8 col-lg-6 col-xl-5">
			<h2 class="mb-3"><?= L::registerNewAccount(); ?></h2>
			<form id="register-form" class="needs-validation" novalidate>
				<div class="form-floating mb-3">
					<input type="text" class="form-control" id="register-name" name="UserName" placeholder="<?= L::name(); ?>" required>
					<label for="register-name"><?= L::name(); ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<div class="form-floating mb-3">
					<input type="email" class="form-control" id="register-mail" name="UserMail" placeholder="<?= L::mailAddress(); ?>" required>
					<label for="register-mail"><?= L::mailAddress(); ?></label>
					<div class="invalid-feedback"></div>
				</div>
				<div class="mb-3">
					<div class="input-group">
						<input type="password" class="form-control" id="register-password" name="UserPassword" 
							   minlength="8" autocomplete="new-password" placeholder="<?= L::password(); ?>" required>
						<button class="btn btn-outline-primary" type="button" id="showPassword">
							<i class="icon-eye"></i>
						</button>
					</div>
					<div class="invalid-feedback"></div>
				</div>
				<div class="mb-3">
					<div class="input-group">
						<input type="password" class="form-control" id="register-password-check" name="UserPasswordConfirm" 
							   minlength="8" autocomplete="new-password" placeholder="<?= L::passwordConfirm(); ?>" required>
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
				<button type="submit" class="w-100 btn btn-primary rounded-pill mt-3"><?= L::registerNewAccount(); ?></button>
			</form>
			<div id="register-response" class="alert mt-3" style="display: none;"></div>
		</div>
	</div>
</main>
<?php include_once (include_custom(realpath(__DIR__ . '/../../footer.php'),false)); ?>
<script type="text/javascript">
$(function() {
    // Initialize password fields
    initPasswordFields({
        passwordFieldId: 'register-password',
        confirmFieldId: 'register-password-check'
    });

    // Reset validation states
    function resetValidation() {
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.invalid-feedback').forEach(el => el.innerHTML = '');
        document.getElementById('register-response').innerHTML = '';
    }

    // Handle form submission
    document.getElementById('register-form').addEventListener('submit', function(e) {
        e.preventDefault();
        resetValidation();

        // Only check password strength if password is being changed
        const password = document.getElementById('register-password').value;
        const passwordConfirm = document.getElementById('register-password-check').value;
        
        if (password && !checkPasswordStrength(password)) {
            document.getElementById('register-password').classList.add('is-invalid');
            document.getElementById('register-password').nextElementSibling.innerHTML = L.messagePasswordTooWeak;
            return;
        }

        if (password && password !== passwordConfirm) {
            document.getElementById('register-password-check').classList.add('is-invalid');
            document.getElementById('register-password-check').nextElementSibling.innerHTML = L.messagePasswordNotIdentical;
            return;
        }

        // Get form data
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => data[key] = value);

        // Make API call
        fetch('<?= $config["dir"]["root"] ?>/api/v1/user/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(response => {
            if (response.meta.requestStatus === 'success') {
                // Show success message
                const successDiv = document.createElement('div');
                successDiv.className = 'alert alert-success';
                successDiv.textContent = response.data.message;
                document.getElementById('register-response').innerHTML = '';
                document.getElementById('register-response').appendChild(successDiv);
                // Redirect to login page after 2 seconds
                setTimeout(() => {
                    window.location.href = '/login';
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
                            }
                        } else {
                            // Show error message in response div if no specific field
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger';
                            errorDiv.textContent = error.detail;
                            document.getElementById('register-response').innerHTML = '';
                            document.getElementById('register-response').appendChild(errorDiv);
                        }
                    });
                } else {
                    // Show general error message
                    document.getElementById('register-response').innerHTML = '<div class="alert alert-danger">' + L.messageErrorGeneric + '</div>';
                }
            }
        })
        .catch(error => {
            document.getElementById('register-response').innerHTML = '<div class="alert alert-danger">' + L.messageErrorGeneric + '</div>';
        });
    });
});
</script>