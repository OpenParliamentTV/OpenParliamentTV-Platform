<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

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
                    <h2><?= L::manageUsers; ?>: <?= htmlspecialchars($userData["UserName"]); ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <form id="userForm" class="needs-validation" novalidate>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($userData["UserID"]); ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="UserName" class="form-label"><?= L::name; ?></label>
                                        <input type="text" class="form-control" id="UserName" name="UserName" 
                                               value="<?= htmlspecialchars($userData["UserName"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="UserMail" class="form-label"><?= L::mailAddress; ?></label>
                                        <input type="email" class="form-control" id="UserMail" 
                                               value="<?= htmlspecialchars($userData["UserMail"]); ?>" readonly>
                                    </div>
                                </div>

                                <?php if ($isAdmin || $isOwnProfile): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label mb-0"><?= L::password; ?></label>
                                            <button type="button" class="btn btn-outline-primary rounded-pill btn-sm" id="togglePassword">
                                                <span class="icon-pencil"></span><?= L::changePassword; ?>
                                            </button>
                                        </div>
                                        <div id="passwordFields" style="display: none;">
                                            <div class="input-group mb-2">
                                                <input type="password" class="form-control" id="UserPassword" name="UserPassword" 
                                                       minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral; ?> <?= L::password; ?>">
                                                <button class="btn btn-outline-primary" type="button" id="showPassword">
                                                    <i class="icon-eye"></i>
                                                </button>
                                            </div>
                                            <div class="input-group mb-2">
                                                <input type="password" class="form-control" id="UserPasswordConfirm" name="UserPasswordConfirm" 
                                                       minlength="8" autocomplete="new-password" placeholder="<?= L::newNeutral; ?> <?= L::passwordConfirm; ?>">
                                                <button class="btn btn-outline-primary" type="button" id="showPasswordConfirm">
                                                    <i class="icon-eye"></i>
                                                </button>
                                            </div>
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <div class="text-danger small" id="passwordStrengthText"></div>
                                            <div class="text-danger small" id="passwordMatchText"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($isAdmin): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="UserRole" class="form-label"><?= L::role; ?></label>
                                        <select class="form-select" id="UserRole" name="UserRole">
                                            <option value="user" <?= $userData["UserRole"] === "user" ? "selected" : ""; ?>><?= L::roleUser; ?></option>
                                            <option value="admin" <?= $userData["UserRole"] === "admin" ? "selected" : ""; ?>><?= L::roleAdmin; ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="UserActive" name="UserActive" 
                                                   <?= $userData["UserActive"] ? "checked" : ""; ?>>
                                            <label class="form-check-label" for="UserActive"><?= L::active; ?></label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="UserBlocked" name="UserBlocked" 
                                                   <?= $userData["UserBlocked"] ? "checked" : ""; ?>>
                                            <label class="form-check-label" for="UserBlocked"><?= L::blocked; ?></label>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label"><?= L::lastLogin; ?></label>
                                        <input type="text" class="form-control" value="<?= $userData["UserLastLogin"]; ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><?= L::registerDate; ?></label>
                                        <input type="text" class="form-control" value="<?= $userData["UserRegisterDate"]; ?>" readonly>
                                    </div>
                                </div>

                                <!-- Message container -->
                                <div id="formMessage" class="alert mb-3" style="display: none;"></div>

                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary rounded-pill" id="saveButton" disabled><span class="icon-ok"></span> <?= L::save; ?></button>
                                        <button type="button" class="btn btn-primary rounded-pill" id="cancelButton" disabled><span class="icon-cancel"></span> <?= L::cancel; ?></button>
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
    const showPasswordBtn = document.getElementById('showPassword');
    const passwordInput = document.getElementById('UserPassword');
    const passwordConfirmInput = document.getElementById('UserPasswordConfirm');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordStrengthText = document.getElementById('passwordStrengthText');
    const passwordMatchText = document.getElementById('passwordMatchText');
    
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
        togglePasswordBtn.innerHTML = isVisible ? '<span class="icon-pencil"></span><?= L::changePassword; ?>' : '<span class="icon-cancel"></span><?= L::cancel; ?>';
        
        if (isVisible) {
            // Clear password fields when hiding
            passwordInput.value = '';
            passwordConfirmInput.value = '';
            passwordStrength.style.width = '0%';
            passwordStrengthText.textContent = '';
            passwordMatchText.textContent = '';
            checkFormChanges();
        }
    });
    
    // Toggle password visibility
    showPasswordBtn.addEventListener('click', function() {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        showPasswordBtn.querySelector('i').className = type === 'password' ? 'icon-eye' : 'icon-eye-off';
    });

    document.getElementById('showPasswordConfirm').addEventListener('click', function() {
        const type = passwordConfirmInput.type === 'password' ? 'text' : 'password';
        passwordConfirmInput.type = type;
        this.querySelector('i').className = type === 'password' ? 'icon-eye' : 'icon-eye-off';
    });
    
    // Check password strength
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];
        
        if (password.length >= 8) strength += 20;
        if (password.match(/[a-z]/)) strength += 20;
        if (password.match(/[A-Z]/)) strength += 20;
        if (password.match(/[0-9]/)) strength += 20;
        if (password.match(/[^\w]/)) strength += 20;
        
        // Set progress bar color based on strength
        if (strength <= 20) {
            passwordStrength.className = 'progress-bar bg-danger';
        } else if (strength <= 40) {
            passwordStrength.className = 'progress-bar bg-warning';
        } else if (strength <= 60) {
            passwordStrength.className = 'progress-bar bg-info';
        } else if (strength <= 80) {
            passwordStrength.className = 'progress-bar bg-primary';
        } else {
            passwordStrength.className = 'progress-bar bg-success';
        }
        
        passwordStrength.style.width = strength + '%';
        
        // Set feedback text
        if (password.length < 8) feedback.push(localizedLabels.messagePasswordTooShort);
        if (!password.match(/[a-z]/)) feedback.push(localizedLabels.messagePasswordNoLowercase);
        if (!password.match(/[A-Z]/)) feedback.push(localizedLabels.messagePasswordNoUppercase);
        if (!password.match(/[0-9]/)) feedback.push(localizedLabels.messagePasswordNoNumber);
        if (!password.match(/[^\w]/)) feedback.push(localizedLabels.messagePasswordNoSpecial);
        
        passwordStrengthText.textContent = feedback.join(', ');
        return strength === 100;
    }
    
    // Add password validation listeners
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
        checkFormChanges();
    });
    
    passwordConfirmInput.addEventListener('input', function() {
        checkPasswordMatch();
        checkFormChanges();
    });

    // Check password match
    function checkPasswordMatch() {
        const match = passwordInput.value === passwordConfirmInput.value;
        
        if (passwordConfirmInput.value && !match) {
            passwordMatchText.textContent = localizedLabels.messagePasswordNotIdentical;
        } else {
            passwordMatchText.textContent = '';
        }
        
        return match;
    }
    
    // Function to check if form has changed
    function checkFormChanges() {
        const currentState = {
            UserName: form.UserName.value,
            UserRole: form.UserRole.value,
            UserActive: form.UserActive.checked,
            UserBlocked: form.UserBlocked.checked,
            UserPassword: passwordInput.value
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
        togglePasswordBtn.innerHTML = '<span class="icon-pencil"></span><?= L::changePassword; ?>';
        passwordStrength.style.width = '0%';
        passwordStrengthText.textContent = '';
        passwordMatchText.textContent = '';
        checkFormChanges();
    });
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Clear previous message
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'none';
        formMessage.textContent = '';
        formMessage.className = 'alert mb-3';
        
        // Validate password if it's being changed
        if (passwordFields.style.display !== 'none') {
            if (!checkPasswordStrength(passwordInput.value)) {
                formMessage.textContent = '<?= L::messagePasswordTooWeak; ?>';
                formMessage.className = 'alert alert-danger mb-3';
                formMessage.style.display = 'block';
                form.classList.add('was-validated');
                return;
            }
        }
        
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
        } else {
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
                        formMessage.textContent = '<?= L::messageEditSuccess; ?>';
                        formMessage.className = 'alert alert-success mb-3';
                        formMessage.style.display = 'block';
                        
                        // Update initial state to match current form state
                        initialFormState = {
                            UserName: form.UserName.value,
                            UserRole: form.UserRole.value,
                            UserActive: form.UserActive.checked,
                            UserBlocked: form.UserBlocked.checked,
                            UserPassword: passwordInput.value
                        };
                        
                        // Hide password fields and reset them
                        passwordFields.style.display = 'none';
                        togglePasswordBtn.innerHTML = '<span class="icon-pencil"></span><?= L::changePassword; ?>';
                        passwordInput.value = '';
                        passwordConfirmInput.value = '';
                        passwordStrength.style.width = '0%';
                        passwordStrengthText.textContent = '';
                        passwordMatchText.textContent = '';
                        
                        // Disable buttons since form is now in sync
                        saveButton.disabled = true;
                        cancelButton.disabled = true;
                        
                        // Remove validation styling on success
                        form.classList.remove('was-validated');
                    } else {
                        // Show error message
                        formMessage.textContent = response.errors[0].detail;
                        formMessage.className = 'alert alert-danger mb-3';
                        formMessage.style.display = 'block';
                        
                        // Show validation styling on error
                        form.classList.add('was-validated');
                        
                        // Re-enable buttons
                        saveButton.disabled = false;
                        cancelButton.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    formMessage.textContent = '<?= L::messageEditError; ?>: ' + error;
                    formMessage.className = 'alert alert-danger mb-3';
                    formMessage.style.display = 'block';
                    
                    // Show validation styling on error
                    form.classList.add('was-validated');
                    
                    // Re-enable buttons
                    saveButton.disabled = false;
                    cancelButton.disabled = false;
                }
            });
        }
    });
});
</script>

<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>