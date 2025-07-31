<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');
?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2><?= L::manageMedia(); ?></h2>
                    <div class="card mb-3">
                        <div class="card-body"></div>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-media-tab" data-bs-toggle="tab" data-bs-target="#all-media" role="tab" aria-controls="all-media" aria-selected="true"><span class="icon-play"></span> <?= L::speeches(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white px-0 fade show active" id="all-media" role="tabpanel" aria-labelledby="all-media-tab">
                            <?php 
                                // Include the filter bar component with only the filter container
                                $showSearchBar = false;
                                $showParliamentFilter = false;
                                $showToggleButton = false;
                                $showFactionChart = false;
                                $showDateRange = true;
                                $showSearchSuggestions = true;
                                $showAdvancedFilters = true;
                                include_once(__DIR__ . '/../../../components/search.filterbar.php'); 
                            ?>
                            <div id="speechListContainer" class="col">
                                <div class="resultWrapper"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    #filterbar {
        margin-top: 0px !important;
        padding-top: 0px !important;
    }
    .searchContainer {
        display: none !important;
    }
</style>

<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/timeline.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/filterController.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/mediaResults.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    // Initialize filter controller for manage/media page
    const filterController = new FilterController({
        mode: 'url-driven',
        baseUrl: '/manage/media',
        onFilterChange: function(formData) {
            // Update URL and reload results using absolute path
            const newUrl = '/manage/media' + (formData ? '?' + formData : '');
            history.pushState(null, '', newUrl);
            mediaManager.loadResults(formData);
        }
    });
    
    // Initialize media results manager for table view
    const mediaManager = getMediaResultsManager('#speechListContainer', {
        mode: 'url-driven',
        view: 'table',
        baseUrl: '/manage/media'
    });
    
    // Load initial results from URL
    const urlParams = new URLSearchParams(window.location.search);
    const initialQuery = urlParams.toString();
    filterController.updateFromUrl();
    
    // Set up callback to handle filter bar visibility
    mediaManager.onLoaded(function(data) {
        // Filter bar visibility is handled by updateFilterBarVisibility in mediaManager
    });
    
    mediaManager.loadResults(initialQuery, false);
    
    // Handle browser back/forward
    window.onpopstate = function(event) {
        filterController.updateFromUrl();
        const urlParams = new URLSearchParams(window.location.search);
        mediaManager.loadResults(urlParams.toString(), false);
    };
    
    // Handle public switch toggle
    $(document).on('change', '.public-switch', function(e) {
        const $switch = $(this);
        const speechId = $switch.data('speech-id');
        const newPublicStatus = $switch.is(':checked'); // What the switch is now set to
        
        // If making public (switch is now ON), show confirmation dialog
        if (newPublicStatus) {
            // Create confirmation modal
            const confirmationHtml = `
                <div class="modal fade" id="publicConfirmModal" tabindex="-1" aria-labelledby="publicConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="publicConfirmModalLabel"><i class="icon-attention"></i> <?= L::makePublic() ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p><?= L::makeMediaPublicConfirm() ?></p>
                            </div>
                            <div class="modal-footer">
                                <div class="row w-100">
                                    <div class="col-7 ps-0">
                                        <button type="button" class="btn btn-primary w-100" id="confirmPublicBtn"><span class="icon-ok"></span> <?= L::makePublic() ?></button>
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
            $('#publicConfirmModal').remove();
            
            // Add modal to body
            $('body').append(confirmationHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('publicConfirmModal'));
            modal.show();
            
            // Handle confirm button
            $('#confirmPublicBtn').off('click').on('click', function() {
                modal.hide();
                updatePublicStatus(speechId, newPublicStatus, $switch);
            });
            
            // Handle modal close/cancel - revert switch back to OFF
            $('#publicConfirmModal').on('hidden.bs.modal', function() {
                if (!$(this).data('confirmed')) {
                    $switch.prop('checked', false);
                }
                $(this).remove();
            });
            
            // Mark as confirmed when confirm button is clicked
            $('#confirmPublicBtn').on('click', function() {
                $('#publicConfirmModal').data('confirmed', true);
            });
            
        } else {
            // Direct update for making item non-public (no confirmation needed)
            updatePublicStatus(speechId, newPublicStatus, $switch);
        }
    });
    
    // Function to update public status via API
    function updatePublicStatus(speechId, publicStatus, $switch) {
        // Disable switch during update
        $switch.prop('disabled', true);
        
        const requestData = {
            action: 'changeItem',
            itemType: 'media',
            id: speechId,
            MediaPublic: publicStatus
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
                    console.log('Public status updated successfully');
                } else {
                    // Error - revert switch
                    console.error('Failed to update public status:', response);
                    $switch.prop('checked', !publicStatus);
                    alert('Failed to update public status: ' + (response.errors ? response.errors[0].detail : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Error - revert switch
                console.error('AJAX error updating public status:', error);
                console.error('Response text:', xhr.responseText);
                $switch.prop('checked', !publicStatus);
                alert('Error updating public status. Please try again.');
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
include_once(__DIR__ . '/../../../footer.php');
}
?>