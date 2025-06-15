<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {
    $electoralPeriodData = $apiResult["data"];
    include_once(__DIR__ . '/../../../../header.php'); ?>

<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail Electoral Period</h2>
                    <div class="card mb-3">
						<div class="card-body"></div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-th-list"></span> <?= L::data(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="data" role="tabpanel" aria-labelledby="data-tab">
                            <form id="electoralPeriodForm" class="needs-validation" novalidate>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($electoralPeriodData["ElectoralPeriodID"]); ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ElectoralPeriodNumber" class="form-label"><?= L::electoralPeriod(); ?></label>
                                        <input type="number" class="form-control" id="ElectoralPeriodNumber" name="ElectoralPeriodNumber" 
                                               value="<?= htmlspecialchars($electoralPeriodData["ElectoralPeriodNumber"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired(); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table mt-3 mt-md-0 mb-2 mb-md-0" style="border: 1px solid var(--border-color);">
                                            <tr>
                                                <td>ID:</td>
                                                <td><?= htmlspecialchars($electoralPeriodData["ElectoralPeriodID"]); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= L::parliament(); ?>:</td>
                                                <td><?= htmlspecialchars($electoralPeriodData["ParliamentLabel"]); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="ElectoralPeriodDateStart" class="form-label"><?= L::dateStart(); ?></label>
                                        <input type="date" class="form-control" id="ElectoralPeriodDateStart" name="ElectoralPeriodDateStart" 
                                               value="<?= htmlspecialchars($electoralPeriodData["ElectoralPeriodDateStart"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired(); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="ElectoralPeriodDateEnd" class="form-label"><?= L::dateEnd(); ?></label>
                                        <input type="date" class="form-control" id="ElectoralPeriodDateEnd" name="ElectoralPeriodDateEnd" 
                                               value="<?= htmlspecialchars($electoralPeriodData["ElectoralPeriodDateEnd"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired(); ?>
                                        </div>
                                    </div>
                                </div>

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
    const form = document.getElementById('electoralPeriodForm');
    const saveButton = document.getElementById('saveButton');
    const cancelButton = document.getElementById('cancelButton');
    
    // Store initial form state
    let initialFormState = {
        ElectoralPeriodNumber: form.ElectoralPeriodNumber.value,
        ElectoralPeriodDateStart: form.ElectoralPeriodDateStart.value,
        ElectoralPeriodDateEnd: form.ElectoralPeriodDateEnd.value
    };
    
    // Function to check if form has changed
    function checkFormChanges() {
        const currentState = {
            ElectoralPeriodNumber: form.ElectoralPeriodNumber.value,
            ElectoralPeriodDateStart: form.ElectoralPeriodDateStart.value,
            ElectoralPeriodDateEnd: form.ElectoralPeriodDateEnd.value
        };
        
        const hasChanges = Object.keys(initialFormState).some(key => {
            return initialFormState[key] !== currentState[key];
        });
        
        saveButton.disabled = !hasChanges;
        cancelButton.disabled = !hasChanges;
    }
    
    // Add change event listeners to all form inputs
    form.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', function() {
            // Clear validation state for this field
            this.classList.remove('is-invalid');
            const feedback = this.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = '';
            }
            checkFormChanges();
        });
        input.addEventListener('input', function() {
            // Clear validation state for this field
            this.classList.remove('is-invalid');
            const feedback = this.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = '';
            }
            checkFormChanges();
        });
    });
    
    // Handle form reset
    cancelButton.addEventListener('click', function() {
        form.reset();
        // Clear all validation states
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        // Clear form message
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'none';
        formMessage.textContent = '';
        formMessage.className = 'alert mb-3';
        checkFormChanges();
    });
    
    // Form submission
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

        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Disable buttons during submission
        saveButton.disabled = true;
        cancelButton.disabled = true;

        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'electoralPeriod',
                ...data
            },
            success: function(response) {
                // Clear previous messages
                const formMessage = document.getElementById('formMessage');
                formMessage.style.display = 'none';
                formMessage.textContent = '';
                formMessage.className = 'alert mb-3';
                
                // Clear all validation states
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');

                if (response.meta.requestStatus === 'success') {
                    // Show success message
                    formMessage.textContent = '<?= L::messageEditSuccess(); ?>';
                    formMessage.className = 'alert alert-success mb-3';
                    formMessage.style.display = 'block';
                    
                    // Update initial state to match current form state
                    initialFormState = {
                        ElectoralPeriodNumber: form.ElectoralPeriodNumber.value,
                        ElectoralPeriodDateStart: form.ElectoralPeriodDateStart.value,
                        ElectoralPeriodDateEnd: form.ElectoralPeriodDateEnd.value
                    };
                    
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
                            formMessage.classList.remove('alert-success');
                            formMessage.classList.add('alert-danger');
                            formMessage.style.display = 'block';
                            formMessage.textContent = error.detail;
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