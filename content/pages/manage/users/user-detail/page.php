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
                                        <label for="UserPassword" class="form-label"><?= L::newNeutral.' '.L::password; ?></label>
                                        <input type="password" class="form-control" id="UserPassword" name="UserPassword">
                                        <div class="form-text"><?= L::messagePasswordTooWeak; ?></div>
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

                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary"><?= L::save; ?></button>
                                        <a href="<?= $config["dir"]["root"]; ?>/manage/users" class="btn btn-secondary"><?= L::cancel; ?></a>
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
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        } else {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                if (key === 'UserActive' || key === 'UserBlocked') {
                    data[key] = value === 'on';
                } else {
                    data[key] = value;
                }
            });

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
                        window.location.href = '<?= $config["dir"]["root"] ?>/manage/users';
                    } else {
                        alert(response.errors[0].detail);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        }
        
        form.classList.add('was-validated');
    });
});
</script>

<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>