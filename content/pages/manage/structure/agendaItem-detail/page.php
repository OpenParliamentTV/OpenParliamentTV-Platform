<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {
    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");
} else {
    // Get agenda item data
    $agendaItemData = $apiResult["data"];

    // Get ID parts
    $idParts = getInfosFromStringID($agendaItemData["id"]);
    
    include_once(__DIR__ . '/../../../../header.php'); ?>

<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail Agenda Item</h2>
                    <div class="card mb-3">
						<div class="card-body">
    
                        </div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-th-list"></span> <?= L::data; ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="data" role="tabpanel" aria-labelledby="data-tab">
                            <form id="agendaItemForm" class="needs-validation" novalidate>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($agendaItemData["id"]); ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="AgendaItemTitle" class="form-label"><?= L::title; ?></label>
                                        <input type="text" class="form-control" id="AgendaItemTitle" name="AgendaItemTitle" 
                                               value="<?= htmlspecialchars($agendaItemData["AgendaItemTitle"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table mt-3 mt-md-0 mb-2 mb-md-0" style="border: 1px solid var(--border-color);">
                                            <tr>
                                                <td>ID:</td>
                                                <td><?= htmlspecialchars($agendaItemData["id"]); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= L::session; ?>:</td>
                                                <td><?= htmlspecialchars($agendaItemData["SessionNumber"]); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= L::electoralPeriod; ?>:</td>
                                                <td><?= htmlspecialchars($agendaItemData["ElectoralPeriodNumber"]); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="AgendaItemOfficialTitle" class="form-label">Official Title</label>
                                        <input type="text" class="form-control" id="AgendaItemOfficialTitle" name="AgendaItemOfficialTitle" 
                                               value="<?= htmlspecialchars($agendaItemData["AgendaItemOfficialTitle"]); ?>" required>
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="AgendaItemOrder" class="form-label"><?= L::order; ?></label>
                                        <input type="number" class="form-control" id="AgendaItemOrder" name="AgendaItemOrder" 
                                               value="<?= htmlspecialchars($agendaItemData["AgendaItemOrder"]); ?>">
                                        <div class="invalid-feedback">
                                            <?= L::messageErrorFieldRequired; ?>
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
                                                <button type="submit" class="btn btn-primary rounded-pill w-100" id="saveButton" disabled><span class="icon-ok"></span> <?= L::save; ?></button>
                                            </div>
                                            <div class="col-6 col-sm-auto">
                                                <button type="button" class="btn btn-primary rounded-pill w-100" id="cancelButton" disabled><span class="icon-cancel"></span> <?= L::cancel; ?></button>
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
    const form = document.getElementById('agendaItemForm');
    const saveButton = document.getElementById('saveButton');
    const cancelButton = document.getElementById('cancelButton');
    
    // Store initial form state
    let initialFormState = {
        AgendaItemTitle: form.AgendaItemTitle.value,
        AgendaItemOfficialTitle: form.AgendaItemOfficialTitle.value,
        AgendaItemOrder: form.AgendaItemOrder.value
    };
    
    // Function to check if form has changed
    function checkFormChanges() {
        const currentState = {
            AgendaItemTitle: form.AgendaItemTitle.value,
            AgendaItemOfficialTitle: form.AgendaItemOfficialTitle.value,
            AgendaItemOrder: form.AgendaItemOrder.value
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
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Clear previous messages
        const formMessage = document.getElementById('formMessage');
        formMessage.style.display = 'none';
        formMessage.textContent = '';
        formMessage.className = 'alert mb-3';
        
        // Validate form
        if (!form.checkValidity()) {
            event.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        
        // Get form data
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => data[key] = value);
        
        // Debug log
        console.log('Form submission data:', data);

        // Disable buttons during submission
        saveButton.disabled = true;
        cancelButton.disabled = true;

        $.ajax({
            url: '<?= $config["dir"]["root"] ?>/api/v1/',
            method: 'POST',
            data: {
                action: 'changeItem',
                itemType: 'agendaItem',
                ...data
            },
            success: function(response) {
                if (response.meta.requestStatus === 'success') {
                    // Show success message
                    formMessage.className = 'alert alert-success mb-3';
                    formMessage.textContent = response.data.message || 'Agenda item updated successfully';
                    formMessage.style.display = 'block';
                    
                    // Update initial form state
                    initialFormState = {
                        AgendaItemTitle: form.AgendaItemTitle.value,
                        AgendaItemOfficialTitle: form.AgendaItemOfficialTitle.value,
                        AgendaItemOrder: form.AgendaItemOrder.value
                    };
                    
                    // Disable buttons
                    saveButton.disabled = true;
                    cancelButton.disabled = true;
                } else {
                    // Show error message
                    formMessage.className = 'alert alert-danger mb-3';
                    formMessage.textContent = response.errors[0].detail;
                    formMessage.style.display = 'block';
                    
                    // Mark invalid fields
                    if (response.errors[0].meta && response.errors[0].meta.domSelector) {
                        const invalidField = form.querySelector(response.errors[0].meta.domSelector);
                        if (invalidField) {
                            invalidField.classList.add('is-invalid');
                            const feedback = invalidField.nextElementSibling;
                            if (feedback && feedback.classList.contains('invalid-feedback')) {
                                feedback.textContent = response.errors[0].detail;
                            }
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                // Show error message
                formMessage.textContent = '<?= L::messageEditError; ?>: ' + error;
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