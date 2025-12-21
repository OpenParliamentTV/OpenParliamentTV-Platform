<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(include_custom(realpath(__DIR__ . '/../../../header.php'),false));
    include_once (__DIR__."/../../../../api/v1/api.php");
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


    </script>
<?php

    include_once (include_custom(realpath(__DIR__ . '/../../../footer.php'),false));

}
?>