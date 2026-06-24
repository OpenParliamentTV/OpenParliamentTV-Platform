<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');
    include_once (__DIR__."/../../../../api/v1/api.php");

    require_once(__DIR__ . '/../../../../api/v1/modules/systemMessage.php');
    $systemMessagesResp = systemMessageList([]);
    $systemMessages = ($systemMessagesResp["meta"]["requestStatus"] === "success") ? $systemMessagesResp["data"] : [];
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="people" aria-selected="true"><span class="icon-cog"></span> <?= L::settings(); ?></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="settings-filterablefactions-tab" data-bs-toggle="tab" data-bs-target="#settings-filterablefactions" role="tab" aria-controls="filterablefactions" aria-selected="true"><span class="icon-filter"></span> <?= L::filterable() ?></a>
                        </li>
                        <?php if (!empty($config["allow"]["notifications"])): ?>
                        <li class="nav-item">
                            <a class="nav-link" id="settings-systemmessages-tab" data-bs-toggle="tab" data-bs-target="#settings-systemmessages" role="tab" aria-controls="systemmessages" aria-selected="false"><span class="icon-megaphone"></span> <?= L::systemMessages(); ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="settings" role="tabpanel" aria-labelledby="settings-tab">
							[CONTENT]
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
                                    <p class="small text-muted">Run alert matching over the most recent media of a parliament to generate notifications for testing (no full import needed).</p>
                                    <div class="d-flex align-items-end gap-2 flex-wrap">
                                        <div>
                                            <label class="form-label small mb-0" for="runMatchParliament">Parliament</label>
                                            <input type="text" class="form-control form-control-sm" id="runMatchParliament" value="DE" style="width:90px;">
                                        </div>
                                        <div>
                                            <label class="form-label small mb-0" for="runMatchLast">Last N</label>
                                            <input type="number" class="form-control form-control-sm" id="runMatchLast" value="50" min="1" max="500" style="width:90px;">
                                        </div>
                                        <button type="button" id="runMatchBtn" class="btn btn-sm"><?= L::notificationRunMatch(); ?></button>
                                        <span id="runMatchResult" class="small text-muted"></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <h5>New broadcast</h5>
                                    <p class="small text-muted">Sends an in-app notification to every targeted active user. Optionally also queues an email.</p>
                                    <div class="mb-2">
                                        <label class="form-label small mb-0" for="bcTitle">Title</label>
                                        <input type="text" class="form-control form-control-sm" id="bcTitle" maxlength="500">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-0" for="bcBody">Body</label>
                                        <textarea class="form-control form-control-sm" id="bcBody" rows="3"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-0" for="bcLink">Link (optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="bcLink">
                                    </div>
                                    <div class="d-flex align-items-end gap-3 flex-wrap mb-2">
                                        <div>
                                            <label class="form-label small mb-0" for="bcTarget">Target</label>
                                            <select class="form-select form-select-sm" id="bcTarget" style="width:160px;">
                                                <option value="">All users</option>
                                                <option value="admin">Admins only</option>
                                                <option value="user">Regular users</option>
                                            </select>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bcSendEmail">
                                            <label class="form-check-label small" for="bcSendEmail">Also send email</label>
                                        </div>
                                        <button type="button" id="bcSend" class="btn btn-sm">Send broadcast</button>
                                        <span id="bcResult" class="small text-muted"></span>
                                    </div>
                                </div>
                                <hr>
                                <div class="">
                                    <h5>Recent system messages</h5>
                                    <?php if (empty($systemMessages)): ?>
                                        <div class="text-muted small">None yet.</div>
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
                                                    <td class="small text-muted"><?= h(substr((string)$a["created"], 0, 16)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
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


    </script>
<?php

    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>