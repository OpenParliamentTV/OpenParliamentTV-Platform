<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/admin') ?>
<?php 

    include_once (__DIR__."/../../../../api/v1/api.php");

    require_once(__DIR__ . '/../../../../api/v1/modules/systemMessage.php');
    $systemMessagesResp = systemMessageList([]);
    $systemMessages = ($systemMessagesResp["meta"]["requestStatus"] === "success") ? $systemMessagesResp["data"] : [];

    // Users offered as the optional owner of an API key.
    $apiKeyUsersResp = apiV1(["action" => "getItemsFromDB", "itemType" => "user", "id" => "all", "limit" => 0, "offset" => 0]);
    $apiKeyUsers = ($apiKeyUsersResp["meta"]["requestStatus"] === "success") ? ($apiKeyUsersResp["data"] ?? []) : [];
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" role="tab" aria-controls="status" aria-selected="true"><span class="icon-gauge"></span> Status</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="settings-filterablefactions-tab" data-bs-toggle="tab" data-bs-target="#settings-filterablefactions" role="tab" aria-controls="filterablefactions" aria-selected="true"><span class="icon-filter"></span> <?= L::filterable() ?></a>
                        </li>
                        <?php if (!empty($config["allow"]["notifications"])): ?>
                        <li class="nav-item">
                            <a class="nav-link" id="settings-systemmessages-tab" data-bs-toggle="tab" data-bs-target="#settings-systemmessages" role="tab" aria-controls="systemmessages" aria-selected="false"><span class="icon-megaphone"></span> <?= L::systemMessages(); ?></a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" id="settings-apikeys-tab" data-bs-toggle="tab" data-bs-target="#settings-apikeys" role="tab" aria-controls="apikeys" aria-selected="false"><span class="icon-key"></span> <?= L::apiKeys(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="status" role="tabpanel" aria-labelledby="status-tab">
	<?php
                            $sysParliaments = [];
                            foreach (($config["parliament"] ?? []) as $pCode => $pCfg) {
                                $sysParliaments[$pCode] = ($pCfg["label"] ?? $pCode) ?: $pCode;
                            }
                            ?>
                            <div class="p-3" id="systemStatusPanel">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <h4 class="mb-0"><span class="icon-gauge"></span> System Status</h4>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small" id="sysLastUpdated"></span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="sysRefreshBtn"><span class="icon-arrows-cw"></span> <?= L::refresh() ?></button>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <!-- OpenSearch -->
                                    <div class="col-12">
                                        <div class="card h-100">
                                            <div class="card-header d-flex align-items-center justify-content-between">
                                                <span><span class="icon-search"></span> OpenSearch</span>
                                                <span id="sysOsHealthBadge" class="badge bg-secondary">Status</span>
                                            </div>
                                            <div class="card-body">
                                                <div id="sysOsSummary" class="row row-cols-2 row-cols-md-5 g-2 text-center mb-3"></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-3">
                                                        <thead>
                                                            <tr>
                                                                <th>Nodes</th>
                                                                <th style="min-width:160px;">Heap Usage</th>
                                                                <th>CPU Usage</th>
                                                                <th>Disk Usage</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="sysOsNodes"></tbody>
                                                    </table>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Index</th>
                                                                <th class="text-end">Documents</th>
                                                                <th class="text-end">Deleted</th>
                                                                <th class="text-end">Size</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="sysOsIndices"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="card-footer d-flex align-items-center gap-2 flex-wrap">
                                                <?php foreach ($sysParliaments as $pCode => $pLabel): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm sys-optimize-btn" data-parliament="<?= hAttr($pCode) ?>">
                                                        <span class="icon-magic"></span> <?= L::optimizeIndices() ?> (<?= h($pCode) ?>)
                                                    </button>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn btn-outline btn-sm" id="sysClearCachesBtn"><span class="icon-eraser"></span> <?= L::clearCaches() ?></button>
                                                <span class="text-muted small ms-1" id="sysActionResult"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Databases -->
                                    <div class="col-12 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header"><span class="icon-database"></span> Databases</div>
                                            <div class="card-body">
                                                <div id="sysDbServers" class="mb-3 small"></div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Database</th>
                                                                <th class="text-end">Tables</th>
                                                                <th class="text-end">Size</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="sysDbSchemas"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Storage -->
                                    <div class="col-12 col-xl-6">
                                        <div class="card h-100">
                                            <div class="card-header"><span class="icon-data-volume"></span> Storage</div>
                                            <div class="card-body" id="sysStorageBody"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane bg-white fade show" id="settings-filterablefactions" role="tabpanel" aria-labelledby="settings-filterablefactions-tab">
                            <?php
                            $factions = apiV1(array("action"=>"search", "itemType"=>"organisations", "type"=>"faction" ));
                            if (($factions["meta"]["requestStatus"] =! "success") || (count($factions["data"]) < 1)) {
                                echo "No factions can be found in database";
                            } else {
                                //echo json_encode($factions);
                                echo "<table id='factionFilterable' class='table'>
                                        <thead>
                                        <tr>
                                            <td>Name</td>
                                            <td>ID</td>
                                            <td>".L::filterable()."</td>
                                            <td>Color</td>
                                            <td>Order</td>
                                        </tr>
                                        </thead><tbody>";
                                foreach ($factions["data"] as $faction) {
                                    echo "<tr class='factionRow' data-id='".$faction["id"]."'>
                                                <td>".$faction["attributes"]["label"]."</td>
                                                <td>".$faction["id"]."</td>
                                                <td>
                                                    <div class='form-check form-switch d-flex justify-content-center'>
                                                        <input type='checkbox' class='form-check-input filterable-switch' data-id='".$faction["id"]."' ".($faction["attributes"]["filterable"] ? " checked":"")."></td>
                                                    </div>
                                                <td><input class='factionColor' type='color' data-id='".$faction["id"]."' value='".$faction["attributes"]["color"]."'></td>
                                                <td><i class='icon-shuffle factionOrderHandle' style='cursor: move' data-id='".$faction["id"]."'></i></td>
                                            </tr>";
                                }
                                echo "</tbody></table>";
                            }
                            ?>
                        </div>
                        <?php if (!empty($config["allow"]["notifications"])): ?>
                        <div class="tab-pane bg-white fade show" id="settings-systemmessages" role="tabpanel" aria-labelledby="settings-systemmessages-tab">
                            <div class="p-3">
                                <div class="mb-3">
                                    <h5><?= L::notificationRunMatch(); ?></h5>
                                    <div class="alert alert-info">Run alert matching over the most recent media of a parliament to generate notifications for testing (no full import needed).</div>
                                    <div class="d-flex align-items-end gap-2 flex-wrap">
                                        <div>
                                            <label class="form-label mb-0" for="runMatchParliament">Parliament</label>
                                            <input type="text" class="form-control form-control-sm" id="runMatchParliament" value="DE" style="width:90px;">
                                        </div>
                                        <div>
                                            <label class="form-label mb-0" for="runMatchLast">Last N</label>
                                            <input type="number" class="form-control form-control-sm" id="runMatchLast" value="50" min="1" max="500" style="width:90px;">
                                        </div>
                                        <button type="button" id="runMatchBtn" class="btn btn-sm"><?= L::notificationRunMatch(); ?></button>
                                        <span id="runMatchResult" class="text-muted"></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <h5>New broadcast</h5>
                                    <div class="alert alert-info">Sends an in-app notification to every targeted active user. Optionally also queues an email.</div>
                                    <div class="mb-2">
                                        <label class="form-label mb-0" for="bcTitle">Title</label>
                                        <input type="text" class="form-control form-control-sm" id="bcTitle" maxlength="500">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-0" for="bcBody">Body</label>
                                        <textarea class="form-control form-control-sm" id="bcBody" rows="3"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-0" for="bcLink">Link (optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="bcLink">
                                    </div>
                                    <div class="d-flex align-items-end gap-3 flex-wrap mb-2">
                                        <div>
                                            <label class="form-label mb-0" for="bcTarget">Target</label>
                                            <select class="form-select form-select-sm" id="bcTarget" style="width:160px;">
                                                <option value="">All users</option>
                                                <option value="admin">Admins only</option>
                                                <option value="user">Regular users</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bcSendEmail">
                                            <label class="form-check-label" for="bcSendEmail">Also send email</label>
                                        </div>
                                        <button type="button" id="bcSend" class="btn btn-sm">Send broadcast</button>
                                        <span id="bcResult" class="text-muted"></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="">
                                    <h5>Recent system messages</h5>
                                    <?php if (empty($systemMessages)): ?>
                                        <div class="text-muted">None yet.</div>
                                    <?php else: ?>
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>Type</th><th>Title</th><th>Target</th><th>Email</th><th>Created</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($systemMessages as $m): $a = $m["attributes"]; ?>
                                                <tr>
                                                    <td><?= h($a["messageType"]) ?></td>
                                                    <td><?= h($a["title"]) ?></td>
                                                    <td><?= h($a["targetRole"] ?: "all") ?></td>
                                                    <td><?= $a["sendEmail"] ? "yes" : "no" ?></td>
                                                    <td class="text-muted"><?= h(substr((string)$a["created"], 0, 16)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="tab-pane bg-white fade show" id="settings-apikeys" role="tabpanel" aria-labelledby="settings-apikeys-tab">
                            <div class="p-3">
                                <div class="alert alert-info"><?= L::apiKeyIntro(); ?></div>
                                <form id="apiKeyForm" class="row g-2 align-items-end mb-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label mb-0" for="apiKeyLabel"><?= L::label(); ?></label>
                                        <input type="text" class="form-control form-control-sm" id="apiKeyLabel" name="ApiKeyLabel" maxlength="191" required>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label mb-0" for="apiKeyOwner"><?= L::user(); ?></label>
                                        <select class="form-select form-select-sm" id="apiKeyOwner" name="ApiKeyOwnerUserID">
                                            <option value="">—</option>
                                            <?php foreach ($apiKeyUsers as $apiKeyUser): ?>
                                                <option value="<?= hAttr($apiKeyUser["UserID"]) ?>"><?= h($apiKeyUser["UserName"]) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label mb-0" for="apiKeyRateLimit"><?= L::apiKeyRateLimit(); ?></label>
                                        <input type="number" min="1" class="form-control form-control-sm" id="apiKeyRateLimit" name="ApiKeyRateLimit" placeholder="<?= hAttr(L::apiKeyRateLimitDefault()) ?>">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label mb-0" for="apiKeyExpires"><?= L::apiKeyExpires(); ?></label>
                                        <input type="date" class="form-control form-control-sm" id="apiKeyExpires" name="ApiKeyExpires">
                                    </div>
                                    <div class="col-6 col-md-1">
                                        <button type="submit" class="btn btn-primary btn-sm w-100" id="apiKeyCreateBtn"><span class="icon-plus"></span></button>
                                    </div>
                                </form>
                                <div id="apiKeyFormError" class="text-danger mb-2"></div>
                                <table id="apiKeysTable"></table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="apiKeyRevealModal" tabindex="-1" aria-labelledby="apiKeyRevealModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apiKeyRevealModalLabel"><span class="icon-key"></span> <?= L::apiKeyCreatedTitle(); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= hAttr(L::close()) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning"><span class="icon-attention"></span> <?= L::apiKeyShownOnceWarning(); ?></div>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="apiKeyRevealValue" readonly>
                    <button class="btn btn-primary" type="button" id="apiKeyCopyBtn"><span class="icon-clipboard"></span> <?= L::apiKeyCopy(); ?></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= L::close(); ?></button>
            </div>
        </div>
    </div>
</div>
    <script>

        $(function() {

            $( "#factionFilterable tbody" ).sortable({
                handle: ".factionOrderHandle",
                stop: function(event, ui) {

                    $(".factionRow").each(function(i,e) {

                        const requestData = {
                            action: 'changeItem',
                            itemType: 'organisation',
                            id: $(this).data("id"),
                            OrganisationOrder: i
                        };

                        $.ajax({
                            url: '<?= $config["dir"]["root"] ?>/api/v1/',
                            method: 'POST',
                            data: requestData,
                            success: function(response) {
                                console.log('API Response:', response);
                                if (response && response.meta && response.meta.requestStatus === 'success') {
                                    console.log('Faction order updated successfully');
                                } else {
                                    console.error('Failed to update faction order:', response);
                                    alert('Failed to update faction order: ' + (response.errors ? response.errors[0].detail : 'Unknown error'));
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX error updating faction order:', error);
                                console.error('Response text:', xhr.responseText);
                                alert('Error updating faction order. Please try again.');
                            }
                        });
                    })

                }
            });

            $(document).on("change", ".factionColor", function(e) {
                const requestData = {
                    action: 'changeItem',
                    itemType: 'organisation',
                    id: $(this).data("id"),
                    OrganisationColor: $(this).val()
                };

                $.ajax({
                    url: '<?= $config["dir"]["root"] ?>/api/v1/',
                    method: 'POST',
                    data: requestData,
                    success: function(response) {
                        console.log('API Response:', response);
                        if (response && response.meta && response.meta.requestStatus === 'success') {
                            console.log('Faction color updated successfully');
                        } else {
                            console.error('Failed to update faction color:', response);
                            alert('Failed to update Faction color: ' + (response.errors ? response.errors[0].detail : 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error updating faction color:', error);
                        console.error('Response text:', xhr.responseText);
                        alert('Error updating faction color. Please try again.');
                    }
                });


            });

            $(document).on('change', '.filterable-switch', function(e) {
                const $switch = $(this);
                const factionId = $switch.data('id');
                const newFilterableStatus = ($switch.is(':checked') ? 1 : 0); // What the switch is now set to

                // If making filterable (switch is now ON), show confirmation dialog
                if (newFilterableStatus) {
                    // Create confirmation modal
                    const confirmationHtml = `
                <div class="modal fade" id="filterableConfirmModal" tabindex="-1" aria-labelledby="filterableConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="filterableConfirmModalLabel"><i class="icon-attention"></i> <?= L::filterable() ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p><?= str_replace('{faction}', L::faction(), L::makeFilterable())?></p>
                            </div>
                            <div class="modal-footer">
                                <div class="row w-100">
                                    <div class="col-7 ps-0">
                                        <button type="button" class="btn btn-primary w-100" id="confirmFilterableBtn"><span class="icon-ok"></span> <?= L::makePublic() ?></button>
                                    </div>
                                    <div class="col-5 pe-0">
                                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal"><span class="icon-cancel"></span> <?= L::cancel() ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

                    // Remove existing modal if any
                    $('#filterableConfirmModal').remove();

                    // Add modal to body
                    $('body').append(confirmationHtml);

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('filterableConfirmModal'));
                    modal.show();

                    // Handle confirm button
                    $('#confirmFilterableBtn').off('click').on('click', function() {
                        modal.hide();
                        updateFilterableStatus(factionId, newFilterableStatus, $switch);
                    });

                    // Handle modal close/cancel - revert switch back to OFF
                    $('#filterableConfirmModal').on('hidden.bs.modal', function() {
                        if (!$(this).data('confirmed')) {
                            $switch.prop('checked', 0);
                        }
                        $(this).remove();
                    });

                    // Mark as confirmed when confirm button is clicked
                    $('#confirmFilterableBtn').on('click', function() {
                        $('#filterableConfirmModal').data('confirmed', 1);
                    });

                } else {
                    // Direct update for making item non-public (no confirmation needed)
                    updateFilterableStatus(factionId, newFilterableStatus, $switch);
                }
            });

            function updateFilterableStatus(factionId, filterableStatus, $switch) {
                // Disable switch during update
                $switch.prop('disabled', true);

                const requestData = {
                    action: 'changeItem',
                    itemType: 'organisation',
                    id: factionId,
                    OrganisationFilterable: filterableStatus
                };

                console.log('Sending request:', requestData);

                $.ajax({
                    url: '<?= $config["dir"]["root"] ?>/api/v1/',
                    method: 'POST',
                    data: requestData,
                    success: function(response) {
                        console.log('API Response:', response);
                        if (response && response.meta && response.meta.requestStatus === 'success') {
                            // Success - switch stays in new position
                            console.log('Filterable status updated successfully');
                        } else {
                            // Error - revert switch
                            console.error('Failed to update filterable status:', response);
                            $switch.prop('checked', !publicStatus);
                            alert('Failed to update filterable status: ' + (response.errors ? response.errors[0].detail : 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        // Error - revert switch
                        console.error('AJAX error updating filterable status:', error);
                        console.error('Response text:', xhr.responseText);
                        $switch.prop('checked', !publicStatus);
                        alert('Error updating filterable status. Please try again.');
                    },
                    complete: function() {
                        // Re-enable switch
                        $switch.prop('disabled', false);
                    }
                });
            }


        });

        // System Messages tab: alert matching test run and broadcast sending
        (function () {
            var api = (config.dir.root || "") + "/api/v1";
            var btn = document.getElementById("runMatchBtn");
            var out = document.getElementById("runMatchResult");
            if (btn) {
                btn.addEventListener("click", function () {
                    var parliament = document.getElementById("runMatchParliament").value || "DE";
                    var last = document.getElementById("runMatchLast").value || 50;
                    btn.disabled = true; out.textContent = "…";
                    var body = new URLSearchParams();
                    body.append("parliament", parliament);
                    body.append("last", last);
                    fetch(api + "/notification/runMatch", { method: "POST", credentials: "same-origin", body: body })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            btn.disabled = false;
                            if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
                                out.textContent = "scanned " + res.data.scanned + ", created " + res.data.notificationsCreated + " notification(s)";
                            } else {
                                out.textContent = (res && res.errors && res.errors[0]) ? res.errors[0].detail : "error";
                            }
                        })
                        .catch(function () { btn.disabled = false; out.textContent = "error"; });
                });
            }

            var bcBtn = document.getElementById("bcSend");
            var bcOut = document.getElementById("bcResult");
            if (bcBtn) {
                bcBtn.addEventListener("click", function () {
                    var title = document.getElementById("bcTitle").value.trim();
                    if (!title) { bcOut.textContent = "title required"; return; }
                    bcBtn.disabled = true; bcOut.textContent = "…";
                    var body = new URLSearchParams();
                    body.append("title", title);
                    body.append("body", document.getElementById("bcBody").value);
                    body.append("link", document.getElementById("bcLink").value);
                    body.append("targetRole", document.getElementById("bcTarget").value);
                    body.append("sendEmail", document.getElementById("bcSendEmail").checked);
                    fetch(api + "/systemMessage/create", { method: "POST", credentials: "same-origin", body: body })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            bcBtn.disabled = false;
                            if (res && res.meta && res.meta.requestStatus === "success" && res.data) {
                                bcOut.textContent = "sent to " + res.data.recipients + " user(s)";
                                setTimeout(function () { location.reload(); }, 800);
                            } else {
                                bcOut.textContent = (res && res.errors && res.errors[0]) ? res.errors[0].detail : "error";
                            }
                        })
                        .catch(function () { bcBtn.disabled = false; bcOut.textContent = "error"; });
                });
            }
        })();


        // API Keys tab
        $(function () {

            const apiRoot = '<?= $config["dir"]["root"] ?>/api/v1/';

            // Translations reach JS as JSON, not as raw interpolations: several of them
            // contain apostrophes (e.g. the French strings) that would otherwise break
            // the surrounding string literals.
            const t = <?= json_encode([
                "never" => L::apiKeyNever(),
                "rateLimitDefault" => L::apiKeyRateLimitDefault(),
                "copy" => L::apiKeyCopy(),
                "copied" => L::apiKeyCopied(),
                "confirmDelete" => L::apiKeyConfirmDelete(),
                "delete" => L::delete(),
                "prefix" => L::apiKeyPrefix(),
                "label" => L::label(),
                "user" => L::user(),
                "rateLimit" => L::apiKeyRateLimit(),
                "active" => L::active(),
                "created" => L::apiKeyCreated(),
                "expires" => L::apiKeyExpires(),
                "lastUsed" => L::apiKeyLastUsed(),
            ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;

            // Labels and owner names are admin-supplied free text and are interpolated
            // into cell markup by the formatters below, so they must be escaped.
            function esc(value) {
                return $('<div>').text(value === null || value === undefined ? '' : value).html();
            }

            function errorDetail(response, fallback) {
                return (response && response.errors && response.errors[0] && response.errors[0].detail)
                    ? response.errors[0].detail
                    : fallback;
            }

            const formatters = {
                dateFormatter: function (value) {
                    if (!value) {
                        return '-';
                    }
                    // MySQL DATETIME ("Y-m-d H:i:s") needs the T separator to parse reliably.
                    const parsed = new Date(String(value).replace(' ', 'T'));
                    return isNaN(parsed.getTime()) ? esc(value) : parsed.toLocaleString('de');
                },

                expiresFormatter: function (value) {
                    return value ? formatters.dateFormatter(value) : esc(t.never);
                },

                rateLimitFormatter: function (value) {
                    return (value === null || value === undefined || value === '')
                        ? '<span class="text-muted">' + esc(t.rateLimitDefault) + '</span>'
                        : esc(value);
                },

                prefixFormatter: function (value) {
                    return '<code>' + esc(value) + '</code>';
                },

                textFormatter: function (value) {
                    return value ? esc(value) : '-';
                },

                // The switch is the revoke control: off = revoked, on = active.
                activeFormatter: function (value, row) {
                    return '<div class="form-check form-switch">' +
                        '<input class="form-check-input apikey-active-switch" type="checkbox" ' +
                        'data-apikeyid="' + esc(row.ApiKeyID) + '" ' +
                        (value ? 'checked' : '') + '>' +
                        '</div>';
                },

                operateFormatter: function (value, row) {
                    return '<div class="list-group list-group-horizontal">' +
                        '<a class="list-group-item list-group-item-action apikey-delete" href="#" ' +
                        'title="' + esc(t.delete) + '" data-apikeyid="' + esc(row.ApiKeyID) + '">' +
                        '<span class="icon-trash"></span></a>' +
                        '</div>';
                }
            };

            $('#apiKeysTable').bootstrapTable({
                url: apiRoot + '?action=getItemsFromDB&itemType=apiKey',
                classes: "table table-striped",
                locale: "<?= $lang; ?>",
                search: true,
                searchAlign: "left",
                pagination: true,
                pageSize: 25,
                pageList: [10, 25, 50, 100, 'all'],
                sidePagination: 'server',
                sortName: 'ApiKeyCreated',
                sortOrder: 'desc',
                uniqueId: 'ApiKeyID',
                columns: [
                    {field: 'ApiKeyID', visible: false},
                    {field: 'ApiKeyPrefix', sortable: true, title: t.prefix, formatter: formatters.prefixFormatter},
                    {field: 'ApiKeyLabel', sortable: true, title: t.label, formatter: formatters.textFormatter},
                    {field: 'ApiKeyOwnerName', sortable: true, title: t.user, formatter: formatters.textFormatter},
                    {field: 'ApiKeyRateLimit', sortable: true, title: t.rateLimit, formatter: formatters.rateLimitFormatter},
                    {field: 'ApiKeyActive', sortable: true, title: t.active, formatter: formatters.activeFormatter},
                    {field: 'ApiKeyCreated', sortable: true, title: t.created, formatter: formatters.dateFormatter},
                    {field: 'ApiKeyExpires', sortable: true, title: t.expires, formatter: formatters.expiresFormatter},
                    {field: 'ApiKeyLastUsed', sortable: true, title: t.lastUsed, formatter: formatters.dateFormatter},
                    {field: 'operate', title: '', formatter: formatters.operateFormatter, class: 'minWidthColumn'}
                ],
                queryParams: function (params) {
                    return {
                        limit: params.limit,
                        offset: params.offset,
                        sort: params.sort,
                        order: params.order,
                        search: params.search
                    };
                },
                responseHandler: function (res) {
                    if (!res || !res.data) {
                        console.error('Invalid response format:', res);
                        return {total: 0, rows: []};
                    }
                    return {total: res.total || 0, rows: res.data};
                }
            });

            // Create a key. The raw secret comes back exactly once, in this response —
            // it is not stored, so it has to be surfaced to the admin right here.
            $('#apiKeyForm').on('submit', function (e) {
                e.preventDefault();

                const $btn = $('#apiKeyCreateBtn');
                const $error = $('#apiKeyFormError');
                $error.text('');
                $btn.prop('disabled', true);

                $.ajax({
                    url: apiRoot,
                    method: 'POST',
                    data: {
                        action: 'addItem',
                        itemType: 'apiKey',
                        ApiKeyLabel: $('#apiKeyLabel').val(),
                        ApiKeyOwnerUserID: $('#apiKeyOwner').val(),
                        ApiKeyRateLimit: $('#apiKeyRateLimit').val(),
                        ApiKeyExpires: $('#apiKeyExpires').val()
                    },
                    success: function (response) {
                        $btn.prop('disabled', false);
                        if (response && response.meta && response.meta.requestStatus === 'success' && response.data) {
                            $('#apiKeyForm')[0].reset();
                            $('#apiKeyRevealValue').val(response.data.attributes.key);
                            new bootstrap.Modal(document.getElementById('apiKeyRevealModal')).show();
                            $('#apiKeysTable').bootstrapTable('refresh');
                        } else {
                            $error.text(errorDetail(response, 'Failed to create API key'));
                        }
                    },
                    error: function (xhr, status, error) {
                        $btn.prop('disabled', false);
                        console.error('Error creating API key:', error, xhr.responseText);
                        $error.text(errorDetail(xhr.responseJSON, 'Error creating API key'));
                    }
                });
            });

            $('#apiKeyCopyBtn').on('click', function () {
                const $btn = $(this);
                const value = $('#apiKeyRevealValue').val();
                const done = function () {
                    $btn.html('<span class="icon-ok"></span> ' + esc(t.copied));
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done);
                } else {
                    $('#apiKeyRevealValue').select();
                    document.execCommand('copy');
                    done();
                }
            });

            $('#apiKeyRevealModal').on('hidden.bs.modal', function () {
                // Do not leave the secret sitting in the DOM after the modal closes.
                $('#apiKeyRevealValue').val('');
                $('#apiKeyCopyBtn').html('<span class="icon-clipboard"></span> ' + esc(t.copy));
            });

            $(document).on('change', '.apikey-active-switch', function () {
                const $switch = $(this);
                const isActive = $switch.prop('checked');
                $switch.prop('disabled', true);

                $.ajax({
                    url: apiRoot,
                    method: 'POST',
                    data: {
                        action: 'changeItem',
                        itemType: 'apiKey',
                        id: $switch.data('apikeyid'),
                        ApiKeyActive: isActive ? 1 : 0
                    },
                    success: function (response) {
                        $switch.prop('disabled', false);
                        if (response && response.meta && response.meta.requestStatus === 'success') {
                            $('#apiKeysTable').bootstrapTable('refresh');
                        } else {
                            $switch.prop('checked', !isActive);
                            alert(errorDetail(response, 'Failed to update API key'));
                        }
                    },
                    error: function (xhr, status, error) {
                        $switch.prop('disabled', false).prop('checked', !isActive);
                        console.error('Error updating API key:', error, xhr.responseText);
                        alert(errorDetail(xhr.responseJSON, 'Error updating API key'));
                    }
                });
            });

            $(document).on('click', '.apikey-delete', function (e) {
                e.preventDefault();

                if (!confirm(t.confirmDelete)) {
                    return;
                }

                $.ajax({
                    url: apiRoot,
                    method: 'POST',
                    data: {
                        action: 'deleteItem',
                        itemType: 'apiKey',
                        id: $(this).data('apikeyid')
                    },
                    success: function (response) {
                        if (response && response.meta && response.meta.requestStatus === 'success') {
                            $('#apiKeysTable').bootstrapTable('refresh');
                        } else {
                            alert(errorDetail(response, 'Failed to delete API key'));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Error deleting API key:', error, xhr.responseText);
                        alert(errorDetail(xhr.responseJSON, 'Error deleting API key'));
                    }
                });
            });

        });


    </script>

    <script>
        // System Status tab: OpenSearch cluster / MySQL / storage monitoring,
        // plus the Clear-caches action and the reused Optimize trigger.
        (function () {
            const sysApiRoot = (config.dir.root || "") + '/api/v1/';
            const sysLocale = "<?= $lang; ?>";
            const sysT = <?= json_encode([
                "clearCachesConfirm" => L::clearCachesConfirm(),
                "clearCachesDone"    => L::clearCachesDone(),
            ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;

            let sysPollTimer = null;
            const optimizeRunning = {};

            function escHtml(v) { return $('<div>').text(v === null || v === undefined ? '' : v).html(); }
            function fmtInt(n) { return (n === null || n === undefined || isNaN(n)) ? '–' : Number(n).toLocaleString(sysLocale); }
            function bytesToHuman(b) {
                if (b === null || b === undefined || isNaN(b)) { return '–'; }
                const u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
                let i = 0, n = Number(b);
                while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
                return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
            }
            function humanDuration(sec) {
                sec = Number(sec);
                const d = Math.floor(sec / 86400), h = Math.floor((sec % 86400) / 3600), m = Math.floor((sec % 3600) / 60);
                if (d > 0) { return d + 'd ' + h + 'h'; }
                if (h > 0) { return h + 'h ' + m + 'm'; }
                return m + 'm';
            }
            function barColor(p) { if (p === null || p === undefined) { return 'bg-secondary'; } if (p < 75) { return 'bg-success'; } if (p < 90) { return 'bg-warning'; } return 'bg-danger'; }
            function progressBar(pct, label) {
                const w = (pct === null || pct === undefined) ? 0 : Math.min(100, Math.max(0, pct));
                return '<div class="progress" style="height:18px;min-width:120px;">' +
                    '<div class="progress-bar ' + barColor(pct) + '" role="progressbar" style="width:' + w + '%;">' +
                    escHtml(label !== null && label !== undefined ? label : (pct === null ? '–' : pct + '%')) + '</div></div>';
            }
            function healthBadge(status) {
                const map = { green: 'bg-success', yellow: 'bg-warning', red: 'bg-danger' };
                $('#sysOsHealthBadge').attr('class', 'badge ' + (map[status] || 'bg-secondary'))
                    .text(status ? status.toUpperCase() : '?');
            }
            function summaryTile(label, valueHtml) {
                return '<div class="col"><div class="border rounded py-2 h-100"><div class="fs-5 fw-semibold">' +
                    valueHtml + '</div><div class="text-muted small">' + escHtml(label) + '</div></div></div>';
            }

            function renderOpenSearch(os) {
                if (!os || !os.available) {
                    healthBadge(null);
                    $('#sysOsSummary').html('<div class="col-12 text-danger">' + escHtml((os && os.error) || 'unavailable') + '</div>');
                    $('#sysOsNodes').empty();
                    $('#sysOsIndices').empty();
                    return;
                }
                const c = os.cluster || {};
                healthBadge(c.status);
                $('#sysOsSummary').html(
                    summaryTile("Status", escHtml(c.status || '–')) +
                    summaryTile("Nodes", fmtInt(c.nodes)) +
                    summaryTile("Active Shards", fmtInt(c.activeShards)) +
                    summaryTile("Unassigned", fmtInt(c.unassignedShards)) +
                    summaryTile("Store Size", bytesToHuman(os.totalStoreBytes))
                );
                $('#sysOsNodes').html((os.nodes || []).map(function (n) {
                    const heapLabel = (n.heapUsedPercent === null || n.heapUsedPercent === undefined ? '–' : n.heapUsedPercent + '%') +
                        ' · ' + bytesToHuman(n.heapUsedBytes) + ' / ' + bytesToHuman(n.heapMaxBytes);
                    const fsUsedPct = (n.fsTotalBytes && n.fsAvailableBytes !== null && n.fsAvailableBytes !== undefined)
                        ? Math.round((n.fsTotalBytes - n.fsAvailableBytes) / n.fsTotalBytes * 100) : null;
                    const fsCell = fsUsedPct === null ? '–'
                        : (bytesToHuman(n.fsAvailableBytes) + ' free / ' + bytesToHuman(n.fsTotalBytes));
                    return '<tr><td>' + escHtml(n.name) + '</td><td>' + progressBar(n.heapUsedPercent, heapLabel) + '</td><td>' +
                        (n.cpuPercent === null || n.cpuPercent === undefined ? '–' : n.cpuPercent + '%') + '</td><td>' + fsCell + '</td></tr>';
                }).join('') || '<tr><td colspan="4" class="text-muted">–</td></tr>');
                $('#sysOsIndices').html((os.indices || []).map(function (i) {
                    return '<tr><td>' + escHtml(i.name) + '</td><td class="text-end">' + fmtInt(i.docs) +
                        '</td><td class="text-end">' + fmtInt(i.deleted) + '</td><td class="text-end">' + bytesToHuman(i.sizeBytes) + '</td></tr>';
                }).join('') || '<tr><td colspan="4" class="text-muted">–</td></tr>');
            }

            function renderDatabases(dbs) {
                const servers = (dbs && dbs.servers) || [];
                $('#sysDbServers').html(servers.map(function (s) {
                    return '<div><strong>' + escHtml(s.host || '–') + '</strong> · Version: ' + escHtml(s.version || '–') +
                        ' · Uptime: ' + (s.uptimeSeconds !== null && s.uptimeSeconds !== undefined ? humanDuration(s.uptimeSeconds) : '–') +
                        ' · Connections: ' + fmtInt(s.threadsConnected) + '</div>';
                }).join(''));
                const schemas = (dbs && dbs.schemas) || [];
                $('#sysDbSchemas').html(schemas.map(function (s) {
                    const name = escHtml(s.label) + ' <span class="text-muted">(' + escHtml(s.database) + ')</span>';
                    if (!s.reachable) { return '<tr><td>' + name + '</td><td class="text-end text-danger" colspan="2">unreachable</td></tr>'; }
                    return '<tr><td>' + name + '</td><td class="text-end">' + fmtInt(s.tables) + '</td><td class="text-end">' + bytesToHuman(s.sizeBytes) + '</td></tr>';
                }).join('') || '<tr><td colspan="3" class="text-muted">–</td></tr>');
            }

            function renderStorage(st) {
                if (!st) { $('#sysStorageBody').html(''); return; }
                const fs = st.filesystem || {}, dd = st.dataDir || {}, osfs = st.openSearchFs || {};
                let html = '<div class="mb-2"><div class="d-flex justify-content-between small flex-wrap"><span>Disk Usage <span class="text-muted">' +
                    escHtml(fs.path || '') + '</span></span><span>' + bytesToHuman(fs.usedBytes) + ' used · ' +
                    bytesToHuman(fs.freeBytes) + ' free · ' + bytesToHuman(fs.totalBytes) + ' total</span></div>' +
                    progressBar(fs.usedPercent, (fs.usedPercent === null || fs.usedPercent === undefined ? '–' : fs.usedPercent + '%')) + '</div>';
                html += '<div class="row row-cols-2 g-2 small mt-2">';
                html += '<div class="col"><div class="text-muted">Data Directory</div><div>' + bytesToHuman(dd.sizeBytes) +
                    ' <button type="button" class="btn btn-outline btn-sm px-1 py-0 align-baseline" id="sysRecalcBtn"><?= L::recalculate() ?></button><br>' +
                    (dd.computedAt ? ' <span class="text-muted">(' + escHtml(String(dd.computedAt).replace('T', ' ').slice(0, 16)) + ')</span>' : '') + '</div></div>';
                html += '<div class="col"><div class="text-muted">Databases</div><div>' + bytesToHuman(st.databasesTotalBytes) + '</div></div>';
                html += '<div class="col"><div class="text-muted">OpenSearch</div><div>' + bytesToHuman(st.openSearchTotalBytes) + '</div></div>';
                html += '<div class="col"><div class="text-muted">OpenSearch · free</div><div>' +
                    bytesToHuman(osfs.availableBytes) + ' / ' + bytesToHuman(osfs.totalBytes) + '</div></div>';
                html += '</div>';
                $('#sysStorageBody').html(html);
            }

            function loadSystemStatus() {
                $.ajax({
                    url: sysApiRoot, method: 'POST', data: { action: 'system', itemType: 'status' },
                    success: function (res) {
                        if (res && res.meta && res.meta.requestStatus === 'success' && res.data) {
                            renderOpenSearch(res.data.openSearch);
                            renderDatabases(res.data.databases);
                            renderStorage(res.data.storage);
                            $('#sysLastUpdated').text(new Date().toLocaleTimeString(sysLocale));
                        }
                    }
                });
            }

            $(document).on('click', '#sysClearCachesBtn', function () {
                if (!confirm(sysT.clearCachesConfirm)) { return; }
                const $btn = $(this);
                $btn.prop('disabled', true);
                $('#sysActionResult').text('…');
                $.ajax({
                    url: sysApiRoot, method: 'POST', data: { action: 'system', itemType: 'clear-caches' },
                    success: function (res) {
                        $btn.prop('disabled', false);
                        if (res && res.meta && res.meta.requestStatus === 'success') {
                            const d = res.data || {};
                            const heap = (d.heapBeforePercent !== null && d.heapBeforePercent !== undefined)
                                ? ' (' + d.heapBeforePercent + '% → ' + d.heapAfterPercent + '%)' : '';
                            $('#sysActionResult').text(sysT.clearCachesDone + heap);
                            loadSystemStatus();
                        } else {
                            $('#sysActionResult').text((res && res.errors && res.errors[0]) ? res.errors[0].detail : 'error');
                        }
                    },
                    error: function () { $btn.prop('disabled', false); $('#sysActionResult').text('error'); }
                });
            });

            $(document).on('click', '#sysRecalcBtn', function () {
                const $btn = $(this);
                $btn.prop('disabled', true).text('…');
                $.ajax({
                    url: sysApiRoot, method: 'POST', data: { action: 'system', itemType: 'storage-scan' },
                    success: function () { loadSystemStatus(); },
                    error: function () { $btn.prop('disabled', false); }
                });
            });

            // Optimize reuses the existing async index/optimize + optimization-status endpoints.
            $(document).on('click', '.sys-optimize-btn', function () {
                const $btn = $(this);
                const p = $btn.data('parliament');
                if (optimizeRunning[p]) { return; }
                optimizeRunning[p] = true;
                const orig = $btn.html();
                $btn.prop('disabled', true).html('<span class="icon-arrows-cw"></span> …');
                $.ajax({
                    url: sysApiRoot, method: 'POST', data: { action: 'index', itemType: 'optimize', parliament: p },
                    success: function (res) {
                        if (res && res.meta && res.meta.requestStatus === 'success') {
                            setTimeout(function () { pollOptimize(p, $btn, orig); }, 2000);
                        } else {
                            optimizeRunning[p] = false;
                            $btn.prop('disabled', false).html(orig);
                            $('#sysActionResult').text((res && res.errors && res.errors[0]) ? res.errors[0].detail : 'error');
                        }
                    },
                    error: function (xhr) {
                        optimizeRunning[p] = false;
                        $btn.prop('disabled', false).html(orig);
                        $('#sysActionResult').text((xhr.responseJSON && xhr.responseJSON.errors) ? xhr.responseJSON.errors[0].detail : 'error');
                    }
                });
            });

            function pollOptimize(p, $btn, orig) {
                $.ajax({
                    url: sysApiRoot, method: 'POST', data: { action: 'index', itemType: 'optimization-status', parliament: p },
                    success: function (res) {
                        const status = (res && res.data) ? res.data.status : null;
                        if (status === 'running') {
                            setTimeout(function () { pollOptimize(p, $btn, orig); }, 3000);
                        } else {
                            optimizeRunning[p] = false;
                            $btn.prop('disabled', false).html(orig);
                            $('#sysActionResult').text((res && res.data && res.data.statusDetails) || '');
                            loadSystemStatus();
                        }
                    },
                    error: function () { optimizeRunning[p] = false; $btn.prop('disabled', false).html(orig); }
                });
            }

            $('#sysRefreshBtn').on('click', loadSystemStatus);

            function startPolling() {
                if (sysPollTimer) { return; }
                sysPollTimer = setInterval(function () {
                    const pane = document.getElementById('status');
                    if (pane && pane.classList.contains('active') && document.visibilityState === 'visible') {
                        loadSystemStatus();
                    }
                }, 20000);
            }

            $(function () { loadSystemStatus(); startPolling(); });
        })();
    </script>
