<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');
include_once(__DIR__ . '/../../../../../modules/utilities/security.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {
    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");
} else {
    $userData = $apiResult["data"];
    $isAdmin = $_SESSION["userdata"]["role"] === "admin";
    $isOwnProfile = $_SESSION["userdata"]["id"] == $_REQUEST["id"];

    include_once(__DIR__ . '/../../../../header.php'); 
?>

<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" role="tab" aria-controls="account" aria-selected="true"><span class="icon-cog"></span> Account</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
                            
                            <form id="userForm" class="needs-validation" novalidate>
                                <input type="hidden" name="id" value="<?= h($userData["UserID"]); ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="UserName" class="form-label"><?= L::name(); ?></label>
                                        <input type="text" class="form-control" id="UserName" name="UserName" 
                                               value="<?= h($userData["UserName"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired(); ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-3 mb-3 mb-md-0">
                                            <label class="form-label mb-0"><?= L::password(); ?></label>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="togglePassword">
                                                <span class="icon-pencil"></span><?= L::changePassword(); ?>
                                            </button>
                                        </div>
                                        <div id="passwordFields" class="mt-3 mb-4 mb-md-0" style="display: none;">
                                            <div class="input-group mb-2">
                                                <input type="password" class="form-control" id="UserPassword" name="UserPassword" 
                                                       minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral(); ?> <?= L::password(); ?>">
                                                <button class="btn btn-outline-primary" type="button" id="showPassword">
                                                    <i class="icon-eye"></i>
                                                </button>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                            <div class="input-group mb-2">
                                                <input type="password" class="form-control" id="UserPasswordConfirm" name="UserPasswordConfirm" 
                                                       minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral(); ?> <?= L::passwordConfirm(); ?>">
                                                <button class="btn btn-outline-primary" type="button" id="showPasswordConfirm">
                                                    <i class="icon-eye"></i>
                                                </button>
                                                <div class="invalid-feedback"></div>
                                            </div>
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <div class="text-danger small" id="passwordStrengthText"></div>
                                            <div class="text-danger small" id="passwordMatchText"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table mb-2 mb-md-0" style="border: 1px solid var(--border-color);">
                                            <tr>
                                                <td><?= L::mailAddress(); ?>:</td>
                                                <td><?= h($userData["UserMail"]); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= L::lastLogin(); ?>:</td>
                                                <td><?= $userData["UserLastLogin"]; ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= L::registerDate(); ?>:</td>
                                                <td><?= $userData["UserRegisterDate"]; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <?php if ($isAdmin): ?>
                                <hr>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="UserRole" class="form-label"><?= L::role(); ?></label>
                                        <select class="form-select" id="UserRole" name="UserRole">
                                            <option value="user" <?= $userData["UserRole"] === "user" ? "selected" : ""; ?>><?= L::roleUser(); ?></option>
                                            <option value="admin" <?= $userData["UserRole"] === "admin" ? "selected" : ""; ?>><?= L::roleAdmin(); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mt-3 mt-md-0">
                                        <label class="form-label">Status</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="UserActive" name="UserActive" 
                                                           <?= $userData["UserActive"] ? "checked" : ""; ?>>
                                                    <label class="form-check-label" for="UserActive"><?= L::active(); ?></label>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="UserBlocked" name="UserBlocked" 
                                                           <?= $userData["UserBlocked"] ? "checked" : ""; ?>>
                                                    <label class="form-check-label" for="UserBlocked"><?= L::blocked(); ?></label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <hr>
                                <!-- Message container -->
                                <div id="formMessage" class="alert mb-3" style="display: none;"></div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-6 col-sm-auto">
                                                <button type="submit" class="btn btn-primary w-100" id="saveButton" disabled><span class="icon-ok"></span> <?= L::save(); ?></button>
                                            </div>
                                            <div class="col-6 col-sm-auto">
                                                <button type="button" class="btn btn-secondary w-100" id="cancelButton" disabled><span class="icon-cancel"></span> <?= L::cancel(); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
$(function() {
    const form = document.getElementById('userForm');
    const saveButton = document.getElementById('saveButton');
    const cancelButton = document.getElementById('cancelButton');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordFields = document.getElementById('passwordFields');
    
    // Initialize password fields
    const passwordValidation = initPasswordFields({
        passwordFieldId: 'UserPassword',
        confirmFieldId: 'UserPasswordConfirm'
    });
    
    // Store initial form state
    const initialFormState = {
        UserName: form.UserName.value,
        UserRole: form.UserRole.value,
        UserActive: form.UserActive.checked,
        UserBlocked: form.UserBlocked.checked,
        UserPassword: ''
    };
    
    // Toggle password fields visibility
    togglePasswordBtn.addEventListener('click', function() {
        const isVisible = passwordFields.style.display !== 'none';
        passwordFields.style.display = isVisible ? 'none' : 'block';
        togglePasswordBtn.innerHTML = isVisible ? '<span class="icon-pencil"></span><?= L::changePassword(); ?>' : '<span class="icon-cancel"></span><?= L::cancel(); ?>';
        
        if (isVisible) {
            // Clear password fields when hiding
            document.getElementById('UserPassword').value = '';
            document.getElementById('UserPasswordConfirm').value = '';
            document.getElementById('passwordStrength').style.width = '0%';
            document.getElementById('passwordStrengthText').textContent = '';
            document.getElementById('passwordMatchText').textContent = '';
            checkFormChanges();
        }
    });
    
    // Function to check if form has changed
    function checkFormChanges() {
        const currentState = {
            UserName: form.UserName.value,
            UserRole: form.UserRole.value,
            UserActive: form.UserActive.checked,
            UserBlocked: form.UserBlocked.checked,
            UserPassword: document.getElementById('UserPassword').value
        };
        
        const hasChanges = Object.keys(initialFormState).some(key => {
            return initialFormState[key] !== currentState[key];
        });
        
        saveButton.disabled = !hasChanges;
        cancelButton.disabled = !hasChanges;
    }
    
    // Add change event listeners to all form inputs
    form.querySelectorAll('input, select').forEach(input => {
        if (input.id !== 'UserPassword' && input.id !== 'UserPasswordConfirm') {
            input.addEventListener('change', checkFormChanges);
            input.addEventListener('input', checkFormChanges);
        }
    });
    
    // Handle form reset
    cancelButton.addEventListener('click', function() {
        form.reset();
        passwordFields.style.display = 'none';
        togglePasswordBtn.innerHTML = '<span class="icon-pencil"></span><?= L::changePassword(); ?>';
        document.getElementById('passwordStrength').style.width = '0%';
        document.getElementById('passwordStrengthText').textContent = '';
        document.getElementById('passwordMatchText').textContent = '';
        checkFormChanges();
    });
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Clear previous messages
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'none';
        formMessage.textContent = '';
        formMessage.className = 'alert mb-3';
        
        // Clear all validation states
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Only validate password if it's being changed
        if (passwordFields.style.display !== 'none') {
            if (!passwordValidation.checkPasswordStrength()) {
                const passwordField = document.getElementById('UserPassword');
                passwordField.classList.add('is-invalid');
                passwordField.closest('.input-group').querySelector('.invalid-feedback').innerHTML = '<?= L::messagePasswordTooWeak(); ?>';
                return;
            }
            
            if (!passwordValidation.checkPasswordMatch()) {
                const confirmField = document.getElementById('UserPasswordConfirm');
                confirmField.classList.add('is-invalid');
                confirmField.closest('.input-group').querySelector('.invalid-feedback').innerHTML = '<?= L::messagePasswordNotIdentical(); ?>';
                return;
            }
        }

        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            if (key === 'UserActive' || key === 'UserBlocked') {
                data[key] = value === 'on';
            } else if (key === 'UserPasswordConfirm') {
                // Skip password confirmation field
                return;
            } else {
                data[key] = value;
            }
        });

        // Disable buttons during submission
        saveButton.disabled = true;
        cancelButton.disabled = true;

        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'user',
                ...data
            },
            success: function(response) {
                if (response.meta.requestStatus === 'success') {
                    // Show success message
                    formMessage.textContent = '<?= L::messageEditSuccess(); ?>';
                    formMessage.className = 'alert alert-success mb-3';
                    formMessage.style.display = 'block';
                    
                    // Update initial state to match current form state
                    initialFormState = {
                        UserName: form.UserName.value,
                        UserRole: form.UserRole.value,
                        UserActive: form.UserActive.checked,
                        UserBlocked: form.UserBlocked.checked,
                        UserPassword: document.getElementById('UserPassword').value
                    };
                    
                    // Hide password fields and reset them
                    passwordFields.style.display = 'none';
                    togglePasswordBtn.innerHTML = '<span class="icon-pencil"></span><?= L::changePassword(); ?>';
                    document.getElementById('UserPassword').value = '';
                    document.getElementById('UserPasswordConfirm').value = '';
                    document.getElementById('passwordStrength').style.width = '0%';
                    document.getElementById('passwordStrengthText').textContent = '';
                    document.getElementById('passwordMatchText').textContent = '';
                    
                    // Disable buttons since form is now in sync
                    saveButton.disabled = true;
                    cancelButton.disabled = true;
                } else {
                    // Handle validation errors
                    response.errors.forEach(function(error) {
                        if (error.meta && error.meta.domSelector) {
                            const $field = $(error.meta.domSelector);
                            $field.addClass('is-invalid');
                            $field.siblings('.invalid-feedback').html(error.detail);
                        } else {
                            // Show general error in response div
                            formMessage
                                .removeClass('alert-success')
                                .addClass('alert-danger')
                                .show()
                                .html(error.detail);
                        }
                    });
                    
                    // Re-enable buttons
                    saveButton.disabled = false;
                    cancelButton.disabled = false;
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                formMessage.textContent = '<?= L::messageEditError(); ?>: ' + error;
                formMessage.className = 'alert alert-danger mb-3';
                formMessage.style.display = 'block';
                
                // Re-enable buttons
                saveButton.disabled = false;
                cancelButton.disabled = false;
            }
        });
    });
});
</script>

<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>