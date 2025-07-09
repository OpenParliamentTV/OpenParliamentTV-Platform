<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$userId = $_SESSION['userdata']['id'] ?? null;
$auth = auth($userId, "requestPage", $pageType);

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
					<h2><?= L::manageImport(); ?></h2>
					<div class="card mb-3">
						<div class="card-body"></div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="parliaments-tab" data-bs-toggle="tab" data-bs-target="#parliaments" role="tab" aria-controls="parliaments" aria-selected="true"><span class="icon-bank"></span> <?= L::parliaments(); ?></a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="external-tab" data-bs-toggle="tab" data-bs-target="#external" role="tab" aria-controls="external" aria-selected="false"><span class="icon-tags"></span> <?= L::entities(); ?></a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="settings" aria-selected="false"><span class="icon-cog"></span> <?= L::settings(); ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="parliaments" role="tabpanel" aria-labelledby="parliaments-tab">
                            <div class="status-visualization p-4 position-relative">
                                <div class="row align-items-start justify-content-center position-relative">
                                    <div class="connecting-line"></div>
                                    <div class="col-4 text-center status-item status-item-repository-remote">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-git-squared small"></i>
                                                <h4 class="small mb-0">Data <br>Repository</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div><?= L::lastChanged(); ?>:</div>
											<div id="lastCommitDate" class="fw-bolder">-</div>
											<div class="small"><?= L::sessions(); ?>: <span id="repoRemoteSessions">-</span></div>
											<div class="small mt-1 d-none">Location: <span id="repoLocation">-</span></div>
										</div>
                                    </div>
                                    <div class="col-4 text-center status-item status-item-repository-local">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-git-squared small"></i>
                                                <h4 class="small mb-0">Local <br>Repository</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3 bg-white" style="z-index: 3;position: relative;">
											<div><?= L::lastChanged(); ?>:</div>
											<div id="repoLocalUpdate" class="fw-bolder">-</div>
											<div class="small"><?= L::sessions(); ?>: <span id="repoLocalSessions">-</span></div>
										</div>
                                    </div>
									<div class="col-4 text-center status-item status-item-database">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-database small"></i>
                                                <h4 class="small mb-0">Platform <br>Database</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div><?= L::lastChanged(); ?>:</div>
											<div id="lastDBDate" class="fw-bolder">-</div>
											<div class="small"><?= L::sessions(); ?>: <span id="dbSessions">-</span></div>
										</div>
                                    </div>
                                </div>
                            </div>
                            <hr>
							<div class="row" id="data-import-progress-section">
								<div class="col-12">
									<div class="d-flex justify-content-between">
										<div class="fw-bolder"><?= L::dataImport(); ?> (DE)</div>
										<div id="data-import-items-text" class="small">Idle</div>
									</div>
									<div class="progress my-1" role="progressbar" aria-label="Data Import Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										<div id="data-import-progress-bar" class="progress-bar" style="width: 0%"></div>
									</div>
                                    <div id="data-import-status-text" class="small text-muted mb-2">Status: Idle</div>
                                    <div id="data-import-current-file-text" class="small text-muted mb-2"><?= L::files(); ?>: N/A</div>
									<button type="button" id="btn-trigger-data-import" class="btn btn-outline-primary btn-sm me-1"><span class="icon-cw"></span> <?= L::triggerManualUpdate(); ?></button>
                                    <div id="data-import-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
								</div>
							</div>
							<hr>
							<div class="row" id="search-index-progress-section-DE" data-parliament-code="DE">
								<div class="col-12">
									<div class="d-flex justify-content-between">
										<div class="fw-bolder"><?= L::searchIndex(); ?> (DE)</div> 
										<div id="search-index-DE-items-text" class="small">Idle</div>
									</div>
									<div class="progress my-1" role="progressbar" aria-label="Search Index DE Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										<div id="search-index-DE-progress-bar" class="progress-bar" style="width: 0%"></div>
									</div>
                                    <div id="search-index-DE-status-text" class="small text-muted mb-2">Status: Idle</div>
									<button type="button" id="btn-trigger-search-index-refresh-DE" class="btn btn-outline-primary btn-sm me-1" data-parliament-code="DE"><span class="icon-arrows-cw"></span> <?= L::refreshFullIndex(); ?> (DE)</button>
									<button type="button" id="btn-trigger-search-index-delete-DE" class="btn btn-danger btn-sm me-1" data-parliament-code="DE"><span class="icon-trash"></span> <?= L::deleteIndex(); ?> (DE)</button>
                                    <div id="search-index-DE-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
								</div>
							</div>
							<hr>
							<div class="row" id="enhanced-index-progress-section-DE" data-parliament-code="DE">
								<div class="col-12">
									<div class="d-flex justify-content-between">
										<div class="fw-bolder">Enhanced Index (DE) <small class="text-muted">(auto-synced)</small></div> 
										<div id="enhanced-index-DE-items-text" class="small">Idle</div>
									</div>
									<div class="progress my-1" role="progressbar" aria-label="Enhanced Index DE Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										<div id="enhanced-index-DE-progress-bar" class="progress-bar" style="width: 0%"></div>
									</div>
                                    <div id="enhanced-index-DE-status-text" class="small text-muted mb-2">Status: Idle</div>
									<button type="button" id="btn-trigger-enhanced-index-rebuild-DE" class="btn btn-outline-primary btn-sm me-1" data-parliament-code="DE"><span class="icon-arrows-cw"></span> Rebuild Enhanced Index (DE)</button>
                                    <div id="enhanced-index-DE-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
								</div>
							</div>
                        </div>
						<div class="tab-pane bg-white fade p-3" id="external" role="tabpanel" aria-labelledby="external-tab">
							<div id="ads-progress-container">
								<!-- ADS Progress Bars will be dynamically inserted here -->
							</div>
                        </div>
						<div class="tab-pane bg-white fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
							[SETTINGS]
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>

<style>
	.status-circle {
		width: 100px;
		height: 100px;
		margin: 0 auto;
		position: relative;
		transition: all 0.3s ease;
		border: 2px solid var(--border-color);
		background-color: var(--secondary-bg-color);
		z-index: 2;
		color: var(--primary-fg-color);
	}
	.connecting-line {
		position: absolute;
		top: 51px;
		left: 50%;
		transform: translateX(-50%);
		width: calc(100% * 2 / 3);
		height: 2px;
		background: var(--border-color);
		z-index: 1;
	}
	.status-circle i {
		font-size: 1.25rem;
		margin-bottom: 4px;
	}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const API_BASE_URL = '<?= $config["dir"]["root"]; ?>/api/v1'; 
    const POLLING_INTERVAL = 5000; // 5 seconds

    const appState = {
        repo: {
            remoteSessions: 0,
            localSessions: 0,
            isOutOfSync: function() {
                return this.remoteSessions > this.localSessions;
            },
            getOutOfSyncCount: function() {
                return this.isOutOfSync() ? this.remoteSessions - this.localSessions : 0;
            }
        },
        importStatus: {},
        entityCounts: {}
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        try {
            // Use a specific locale and options for consistent formatting
            return new Date(dateString).toLocaleString('de-DE', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        } catch (e) {
            console.error("Error formatting date:", dateString, e);
            return '-';
        }
    };

    // --- Generic Helper Functions ---
    function getApiUrl(action, itemType, params = {}) {
        const url = `${API_BASE_URL}/?action=${action}&itemType=${itemType}`;
        const queryParams = new URLSearchParams(params).toString();
        return queryParams ? `${url}&${queryParams}` : url;
    }

    async function apiCall(url, method = 'GET', body = null) {
        try {
            const options = { method };
            if (body && (method === 'POST' || method === 'PUT')) {
                options.headers = { 'Content-Type': 'application/json' };
                options.body = JSON.stringify(body);
            }
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { errors: [{ detail: `HTTP error ${response.status} - ${response.statusText}` }] };
                }
                console.error('API call failed:', url, errorData);
                return { success: false, data: null, errors: errorData.errors || [{detail: 'Unknown API error'}] };
            }
            const data = await response.json();
            return { 
                success: true, 
                data: data.data !== undefined ? data.data : data, 
                meta: data.meta, 
                errors: data.errors 
            };
        } catch (error) {
            console.error('Network or other error during API call:', url, error);
            return { success: false, data: null, errors: [{ detail: error.message || 'Network error' }] };
        }
    }

    function updateElementText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function updateProgressBar(barId, percentage, currentStatus) {
        const bar = document.getElementById(barId);
        if (bar) {
            percentage = parseFloat(percentage) || 0;
            bar.style.width = percentage + '%';
            bar.textContent = '';

            // Clear all contextual classes first
            bar.classList.remove('bg-success', 'bg-primary', 'bg-danger', 'progress-bar-animated', 'progress-bar-striped');

            const errorStates = ['error', 'error_shutdown', 'error_critical', 'error_all_items_failed', 'partially_completed_with_errors', 'error_final'];
            if (errorStates.includes(currentStatus)) {
                bar.classList.add('bg-danger');
            } else if (currentStatus === 'running') {
                bar.classList.add('bg-primary', 'progress-bar-animated', 'progress-bar-striped');
            } else if (percentage >= 100) { // Completed or idle but 100%
                bar.classList.add('bg-success');
            } else if (percentage > 0) { // Idle with some progress, or any other non-error/non-running state with progress
                bar.classList.add('bg-primary');
            }
            // If currentStatus is idle and percentage is 0, no specific color class is added here (default progress bar appearance)
        }
    }
    
    function setButtonText(buttonId, content) {
        const btn = document.getElementById(buttonId);
        if (!btn) return;

        // If content is HTML, just set it and we're done.
        // This is for restoring buttons with icons.
        if (typeof content === 'string' && content.trim().startsWith('<')) {
            btn.innerHTML = content;
            return;
        }

        const icon = btn.querySelector('i, span[class^="icon-"]');
        if (icon) {
            // This button has an icon. We want to preserve it and update the text.
            // Remove existing text nodes to avoid duplicates.
            const nodesToRemove = [];
            btn.childNodes.forEach(child => {
                if (child.nodeType === Node.TEXT_NODE) {
                    nodesToRemove.push(child);
                }
            });
            nodesToRemove.forEach(node => btn.removeChild(node));
            
            // Add new text node.
            btn.appendChild(document.createTextNode(' ' + content));
        } else {
            // No icon, just set the text content.
            btn.textContent = content;
        }
    }

    function toggleButton(buttonId, disabledState, content = null) {
        const btn = document.getElementById(buttonId);
        if (btn) {
            btn.disabled = disabledState;
            if (content) {
                setButtonText(buttonId, content);
            }
        }
    }

    function showError(displayId, messages) {
        const el = document.getElementById(displayId);
        if (el) {
            el.innerHTML = ''; 
            let messageContent = null;
            if (Array.isArray(messages) && messages.length > 0) {
                const ul = document.createElement('ul');
                ul.className = 'list-unstyled mb-0';
                messages.forEach(err => {
                    const li = document.createElement('li');
                    li.textContent = err.detail || err.title || 'An unspecified error occurred.';
                    ul.appendChild(li);
                });
                messageContent = ul;
            } else if (typeof messages === 'string' && messages.trim().length > 0) {
                 messageContent = document.createTextNode(messages);
            } else if (messages && typeof messages.detail === 'string') { // Handle single error object
                messageContent = document.createTextNode(messages.detail);
            }

            if (messageContent) {
                el.appendChild(messageContent);
                el.classList.remove('d-none');
            } else {
                 el.classList.add('d-none'); 
            }
        }
    }

    function clearError(displayId) {
        showError(displayId, null); // Call showError with null to hide it
    }

    // --- Data Import Specific Functions ---
    const dataImportElems = {
        section: 'data-import-progress-section',
        progressBar: 'data-import-progress-bar',
        statusText: 'data-import-status-text',
        itemsText: 'data-import-items-text',
        currentFileText: 'data-import-current-file-text',
        errorDisplay: 'data-import-error-display',
        triggerButton: 'btn-trigger-data-import',
        originalButtonText: `<span class="icon-cw"></span> <?= L::triggerManualUpdate(); ?>` 
    };

    async function fetchDataImportStatus() {
        const url = getApiUrl('import', 'status');
        const result = await apiCall(url);
        if (result.success && result.data) {
            appState.importStatus = result.data;
            updateDataImportUI();
        } else {
            console.warn("Failed to fetch data import status:", result.errors);
            appState.importStatus = { status: 'error', statusDetails: 'Status fetch failed', percentage: 0, errors: result.errors || [{detail: 'Connection error while fetching status.'}] };
            updateDataImportUI();
        }
    }

    function updateDataImportUI() {
        const { status, statusDetails = 'N/A', totalFiles = 0, processedFiles = 0, currentFile = 'N/A', lastSuccessfullyProcessedFile = null, lastActivityTime = null, errors = [] } = appState.importStatus;
        
        const percentage = totalFiles > 0 ? (processedFiles / totalFiles) * 100 : 0;
        let finalStatusDetails = statusDetails;
        let finalItemsText = `<?= L::files(); ?>: ${processedFiles} / ${totalFiles}`;
        const isNotRunning = status !== 'running';
        const lastRunDate = formatDate(lastActivityTime);

        if (isNotRunning && appState.repo.isOutOfSync()) {
            const diff = appState.repo.getOutOfSyncCount();
            const plural = diff > 1 ? 's' : '';
            finalStatusDetails = `Repository is out of sync. ${diff} new session file${plural} available.`;
            finalItemsText = `Pending Sync: ${diff} session${plural}`;
            const fileToShow = lastSuccessfullyProcessedFile || 'N/A';
            updateElementText(dataImportElems.currentFileText, `<?= L::lastRun(); ?>: ${lastRunDate} | <?= L::lastUpdated(); ?>: ${fileToShow}`);
        } else if (isNotRunning) {
            // Handles idle, completed, error, etc.
            const fileToShow = lastSuccessfullyProcessedFile || 'N/A';
            updateElementText(dataImportElems.currentFileText, `<?= L::lastRun(); ?>: ${lastRunDate} | <?= L::lastFile(); ?>: ${fileToShow}`);
        } else { // status === 'running'
            updateElementText(dataImportElems.currentFileText, `Current: ${currentFile || 'N/A'}`);
        }

        updateProgressBar(dataImportElems.progressBar, percentage, status);
        updateElementText(dataImportElems.statusText, `Status: ${finalStatusDetails}`);
        updateElementText(dataImportElems.itemsText, finalItemsText);

        if (status === 'running') {
            toggleButton(dataImportElems.triggerButton, true, `<?= L::running(); ?>...`);
            
            // Also disable main index buttons during data import since it will auto-trigger
            const mainIndexElems = getSearchIndexElements('DE');
            toggleButton(mainIndexElems.refreshButton, true, 'Data import running...');
            toggleButton(mainIndexElems.deleteButton, true, 'Data import running...');
            
            // Also disable enhanced index button during data import since it will auto-trigger
            const enhancedElems = getEnhancedIndexElements('DE');
            toggleButton(enhancedElems.rebuildButton, true, 'Data import running...');
            
            clearError(dataImportElems.errorDisplay);
        } else {
            toggleButton(dataImportElems.triggerButton, false, dataImportElems.originalButtonText);
            
            // Re-enable other buttons after data import completes
            // Check current state of other processes to determine correct button states
            const mainIndexElems = getSearchIndexElements('DE');
            const enhancedElems = getEnhancedIndexElements('DE');
            
            // Check current states
            const mainIndexStatus = window.mainIndexStatus?.['DE'];
            const mainIndexRunning = mainIndexStatus?.isRunning || false;
            
            const enhancedState = window.enhancedIndexState?.['DE'];
            const enhancedIsRunning = enhancedState?.isRunning || false;
            
            // Set button states based on current process states
            if (mainIndexRunning) {
                // Main index is running
                toggleButton(mainIndexElems.refreshButton, true, 'Main index running...');
                toggleButton(mainIndexElems.deleteButton, true, 'Main index running...');
                toggleButton(enhancedElems.rebuildButton, true, 'Main index running...');
            } else if (enhancedIsRunning) {
                // Enhanced index is running
                toggleButton(mainIndexElems.refreshButton, true, 'Enhanced index running...');
                toggleButton(mainIndexElems.deleteButton, true, 'Enhanced index running...');
                toggleButton(enhancedElems.rebuildButton, true, 'Enhanced index running...');
            } else {
                // No other processes running - enable all buttons
                toggleButton(mainIndexElems.refreshButton, false, mainIndexElems.originalRefreshBtnText);
                toggleButton(mainIndexElems.deleteButton, false, mainIndexElems.originalDeleteBtnText);
                toggleButton(enhancedElems.rebuildButton, false, enhancedElems.originalRebuildBtnText);
            }
            
            // Trigger status updates to ensure UI is synchronized
            setTimeout(() => {
                fetchSearchIndexStatus('DE');
                fetchEnhancedIndexStatus('DE');
            }, 1000);
            
            const errorStates = ['error', 'error_shutdown', 'error_critical', 'error_all_items_failed', 'partially_completed_with_errors', 'error_final'];
            if (errorStates.includes(status) && !appState.repo.isOutOfSync()) {
                const errorMessages = errors && errors.length > 0 ? errors : (statusDetails ? [{ detail: statusDetails }] : [{detail: 'An unknown error occurred during data import.'}]);
                showError(dataImportElems.errorDisplay, errorMessages);
            } else { // completed, idle, or out-of-sync
                clearError(dataImportElems.errorDisplay);
            }
        }
    }

    async function triggerDataImport() {
        toggleButton(dataImportElems.triggerButton, true, 'Starting...');
        clearError(dataImportElems.errorDisplay);
        const url = getApiUrl('import', 'run');
        const result = await apiCall(url, 'POST'); 

        if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && typeof result.data.message === 'string') {
            updateElementText(dataImportElems.statusText, `Status: ${result.data.message || 'Import triggered, waiting for progress...'}`);
            setTimeout(fetchDataImportStatus, 1000); 
        } else {
            showError(dataImportElems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: 'Failed to trigger data import.'}]);
            toggleButton(dataImportElems.triggerButton, false, dataImportElems.originalButtonText); 
        }
    }

    // --- Search Index Specific Functions (Refactored for Reusability) ---
    function getSearchIndexElements(parliamentCode) {
        return {
            progressBar: `search-index-${parliamentCode}-progress-bar`,
            statusText: `search-index-${parliamentCode}-status-text`,
            itemsText: `search-index-${parliamentCode}-items-text`,
            errorDisplay: `search-index-${parliamentCode}-error-display`,
            refreshButton: `btn-trigger-search-index-refresh-${parliamentCode}`,
            deleteButton: `btn-trigger-search-index-delete-${parliamentCode}`,
            originalRefreshBtnText: `<span class="icon-arrows-cw"></span> <?= L::refreshFullIndex(); ?> (${parliamentCode})`,
            originalDeleteBtnText: `<span class="icon-trash"></span> <?= L::deleteIndex(); ?> (${parliamentCode})`
        };
    }

    function updateSearchIndexUI(parliamentCode, statusData) {
        const elems = getSearchIndexElements(parliamentCode);
        const {
            status,
            statusDetails = 'N/A',
            totalDbMediaItems = 0,
            processedMediaItems = 0,
            errors = []
        } = statusData;

        const percentage = totalDbMediaItems > 0 ? (processedMediaItems / totalDbMediaItems) * 100 : 0;

        updateProgressBar(elems.progressBar, percentage, status);
        updateElementText(elems.statusText, `Status: ${statusDetails}`);
        updateElementText(elems.itemsText, `<?= L::speeches(); ?>: ${processedMediaItems} / ${totalDbMediaItems}`);

        // Track main index status globally for enhanced index coordination
        window.mainIndexStatus = window.mainIndexStatus || {};
        const wasRunning = window.mainIndexStatus[parliamentCode]?.isRunning || false;
        const isRunning = status === 'running';
        window.mainIndexStatus[parliamentCode] = { isRunning: isRunning };
        
        // Check if data import is running - if so, don't update any buttons
        const dataImportStatus = appState.importStatus?.status;
        const dataImportRunning = dataImportStatus === 'running';
        
        if (dataImportRunning) {
            // Skip all button updates during data import
            // Data import has full control over button states
            return;
        }
        
        // Check enhanced index state to avoid conflicts
        const enhancedState = window.enhancedIndexState?.[parliamentCode];
        const enhancedIsRunning = enhancedState?.isRunning || false;
        
        if (status === 'running') {
            toggleButton(elems.refreshButton, true, `<?= L::running(); ?>...`);
            toggleButton(elems.deleteButton, true, `<?= L::running(); ?>...`);
            
            // Also disable enhanced index button when main index is running
            // This is the only time main index polling touches the enhanced index button
            const enhancedElems = getEnhancedIndexElements(parliamentCode);
            toggleButton(enhancedElems.rebuildButton, true, 'Main index running...');
            
            // Update enhanced index state to prevent it from changing the button
            window.enhancedIndexState = window.enhancedIndexState || {};
            window.enhancedIndexState[parliamentCode] = { isRunning: false };
            
            clearError(elems.errorDisplay);
        } else {
            // Main index is not running
            // Only enable main index buttons if enhanced index is not running
            if (!enhancedIsRunning) {
                toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
                toggleButton(elems.deleteButton, false, elems.originalDeleteBtnText);
            } else {
                toggleButton(elems.refreshButton, true, 'Enhanced index running...');
                toggleButton(elems.deleteButton, true, 'Enhanced index running...');
            }
            
            // Don't touch the enhanced index button from main index polling
            // Let the enhanced index polling have full control over its own button
            // This prevents race conditions between the two polling intervals
            
            // Force enhanced index status check when main index completes
            // This ensures enhanced index button state is updated if enhanced indexing is running
            if (wasRunning && !isRunning) {
                setTimeout(() => fetchEnhancedIndexStatus(parliamentCode), 1000);
            }
            
            if (status === 'error') {
                const errorMessages = errors && errors.length > 0 ? errors : (statusDetails ? [{ detail: statusDetails }] : [{detail: 'An unknown error occurred.'}]);
                showError(elems.errorDisplay, errorMessages);
            } else { // completed, idle
                clearError(elems.errorDisplay);
                if (status === 'deleted') { // Special case for after delete
                    updateElementText(elems.itemsText, 'Index Deleted');
                }
            }
        }
    }

    async function fetchSearchIndexStatus(parliamentCode) {
        const url = getApiUrl('index', 'status', { parliament: parliamentCode });
        const result = await apiCall(url);
        if (result.success && result.data) {
            updateSearchIndexUI(parliamentCode, result.data);
        } else {
            console.warn(`Failed to fetch search index status for ${parliamentCode}:`, result.errors);
            updateSearchIndexUI(parliamentCode, { status: 'error', statusDetails: 'Status fetch failed', errors: result.errors || [{detail: 'Connection error while fetching status.'}] });
        }
    }

    async function triggerSearchIndexRefresh(parliamentCode) {
        const elems = getSearchIndexElements(parliamentCode);
        toggleButton(elems.refreshButton, true, 'Starting Refresh...');
        toggleButton(elems.deleteButton, true); // Disable delete during refresh
        clearError(elems.errorDisplay);
        const url = getApiUrl('index', 'full-update', { parliament: parliamentCode });
        const result = await apiCall(url, 'POST');

        if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && typeof result.data.message === 'string') {
            updateElementText(elems.statusText, `Status: ${result.data.message || 'Search index refresh triggered.'}`);
            setTimeout(() => fetchSearchIndexStatus(parliamentCode), 1000);
        } else {
            showError(elems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: 'Failed to trigger search index refresh.'}]);
            toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
            toggleButton(elems.deleteButton, false, elems.originalDeleteBtnText);
        }
    }

    async function triggerSearchIndexDelete(parliamentCode) {
        if (!confirm(`Are you sure you want to delete the search index for parliament ${parliamentCode}? This action cannot be undone.`)) {
            return;
        }
        const elems = getSearchIndexElements(parliamentCode);
        toggleButton(elems.deleteButton, true, 'Deleting...');
        toggleButton(elems.refreshButton, true); // Disable refresh during delete
        clearError(elems.errorDisplay);
        const url = getApiUrl('index', 'delete', { parliament: parliamentCode, init: true }); 
        const result = await apiCall(url, 'POST');

        if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && result.data.deleted === true) {
            updateElementText(elems.statusText, `Status: ${result.data.message || 'Search index deleted successfully.'}`);
            updateProgressBar(elems.progressBar, 0, 'idle'); // Reset progress bar to idle and 0%
            updateSearchIndexUI(parliamentCode, {status: 'deleted', statusDetails: result.data.message || 'Index deleted'});
            setTimeout(() => fetchSearchIndexStatus(parliamentCode), 1000); 
        } else {
            showError(elems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: 'Failed to delete search index.'}]);
            toggleButton(elems.deleteButton, false, elems.originalDeleteBtnText);
            toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
        }
    }

    function initializeSearchIndexSections() {
        const sections = document.querySelectorAll('[id^="search-index-progress-section-"]');
        sections.forEach(section => {
            const parliamentCode = section.dataset.parliamentCode;
            if (!parliamentCode) {
                console.warn('Search index section found without data-parliament-code:', section.id);
                return;
            }
            
            const elems = getSearchIndexElements(parliamentCode);

            const refreshBtn = document.getElementById(elems.refreshButton);
            if (refreshBtn) refreshBtn.addEventListener('click', () => triggerSearchIndexRefresh(parliamentCode));
            
            const deleteBtn = document.getElementById(elems.deleteButton);
            if (deleteBtn) deleteBtn.addEventListener('click', () => triggerSearchIndexDelete(parliamentCode));

            fetchSearchIndexStatus(parliamentCode);
            setInterval(() => fetchSearchIndexStatus(parliamentCode), POLLING_INTERVAL);
            
            // Enhanced indexing setup
            setupEnhancedIndexUI(parliamentCode);
        });
    }

    // --- Localized Labels ---
    // Using L:: functions directly in PHP instead of JS variables
    
    // --- Enhanced Indexing Functions ---
    
    function getEnhancedIndexElements(parliamentCode) {
        return {
            progressBar: `enhanced-index-${parliamentCode}-progress-bar`,
            statusText: `enhanced-index-${parliamentCode}-status-text`,
            itemsText: `enhanced-index-${parliamentCode}-items-text`,
            errorDisplay: `enhanced-index-${parliamentCode}-error-display`,
            rebuildButton: `btn-trigger-enhanced-index-rebuild-${parliamentCode}`,
            originalRebuildBtnText: `<span class="icon-arrows-cw"></span> Rebuild Enhanced Index (${parliamentCode})`
        };
    }
    
    function updateEnhancedIndexUI(parliamentCode, statusData) {
        const elems = getEnhancedIndexElements(parliamentCode);
        const {
            status,
            statusDetails = 'N/A',
            totalDbMediaItems = 0,
            processedMediaItems = 0,
            words_indexed = 0,
            statistics_updated = 0,
            performance = {},
            errors = []
        } = statusData;

        const percentage = totalDbMediaItems > 0 ? (processedMediaItems / totalDbMediaItems) * 100 : 0;

        updateProgressBar(elems.progressBar, percentage, status);
        updateElementText(elems.statusText, `Status: ${statusDetails}`);
        
        // Update items text with enhanced indexing metrics
        let itemsText = `${processedMediaItems}/${totalDbMediaItems}`;
        if (words_indexed > 0) {
            itemsText += ` | Words: ${words_indexed.toLocaleString()}`;
        }
        if (statistics_updated > 0) {
            itemsText += ` | Stats: ${statistics_updated.toLocaleString()}`;
        }
        if (performance.avg_docs_per_second > 0) {
            itemsText += ` | ${performance.avg_docs_per_second}/s`;
        }
        updateElementText(elems.itemsText, itemsText);

        // Handle button states
        const isActive = ['running', 'processing', 'processing_batch', 'starting', 'initializing'].includes(status);
        
        // Update enhanced index state first
        window.enhancedIndexState = window.enhancedIndexState || {};
        const previousState = window.enhancedIndexState[parliamentCode]?.isRunning || false;
        window.enhancedIndexState[parliamentCode] = { isRunning: isActive };
        
        // Check if data import is running - if so, don't update any buttons
        const dataImportStatus = appState.importStatus?.status;
        const dataImportRunning = dataImportStatus === 'running';
        
        if (dataImportRunning) {
            // Skip all button updates during data import
            // Data import has full control over button states
            return;
        }
        
        // Check if main index is running by checking the global state
        // If main index is running, completely skip enhanced index button updates
        const mainIndexStatus = window.mainIndexStatus || {};
        const mainIndexRunning = mainIndexStatus[parliamentCode]?.isRunning || false;
        
        // Only update enhanced index button if main index is not running
        if (!mainIndexRunning) {
            // Always update button to match current state (don't check for changes)
            if (isActive) {
                toggleButton(elems.rebuildButton, true, 'Running...');
            } else {
                toggleButton(elems.rebuildButton, false, elems.originalRebuildBtnText);
            }
        }
        // If main index is running, completely skip button updates
        // The main index status update has full control over the enhanced index button

        // Show errors if any
        if (errors && errors.length > 0) {
            showError(elems.errorDisplay, errors);
        } else {
            clearError(elems.errorDisplay);
        }
    }
    
    async function fetchEnhancedIndexStatus(parliamentCode) {
        // Don't fetch enhanced index status while data import is running
        const dataImportStatus = appState.importStatus?.status;
        const dataImportRunning = dataImportStatus === 'running';
        
        if (dataImportRunning) {
            // Skip enhanced index status update while data import is running
            return;
        }
        
        // Don't fetch enhanced index status while main index is running
        const mainIndexStatus = window.mainIndexStatus || {};
        const mainIndexRunning = mainIndexStatus[parliamentCode]?.isRunning || false;
        
        if (mainIndexRunning) {
            // Skip enhanced index status update while main index is running
            return;
        }
        
        const url = getApiUrl('index', 'enhanced-status', { parliament: parliamentCode });
        const result = await apiCall(url);
        
        if (result.success && result.data) {
            updateEnhancedIndexUI(parliamentCode, result.data);
        } else {
            console.error('Failed to fetch enhanced index status:', result.errors);
        }
    }
    
    async function triggerEnhancedIndexRebuild(parliamentCode) {
        if (!confirm(`Are you sure you want to rebuild the enhanced index for parliament ${parliamentCode}? This will process all documents and may take a long time. Setup will be handled automatically if needed.`)) {
            return;
        }
        
        const elems = getEnhancedIndexElements(parliamentCode);
        toggleButton(elems.rebuildButton, true, 'Starting Rebuild...');
        clearError(elems.errorDisplay);
        
        const url = getApiUrl('index', 'enhanced-update', { parliament: parliamentCode });
        const result = await apiCall(url, 'POST');

        if (result.success && result.meta && result.meta.requestStatus === 'success') {
            updateElementText(elems.statusText, `Status: ${result.data.message || 'Enhanced index rebuild started.'}`);
            setTimeout(() => fetchEnhancedIndexStatus(parliamentCode), 1000);
        } else {
            showError(elems.errorDisplay, result.errors || [{detail: 'Failed to start enhanced index rebuild.'}]);
            toggleButton(elems.rebuildButton, false, elems.originalRebuildBtnText);
        }
    }
    
    function setupEnhancedIndexUI(parliamentCode) {
        const elems = getEnhancedIndexElements(parliamentCode);
        
        const rebuildBtn = document.getElementById(elems.rebuildButton);
        if (rebuildBtn) rebuildBtn.addEventListener('click', () => triggerEnhancedIndexRebuild(parliamentCode));

        // Start enhanced index polling with a delay to avoid race conditions with main index polling
        setTimeout(() => {
            fetchEnhancedIndexStatus(parliamentCode);
            setInterval(() => fetchEnhancedIndexStatus(parliamentCode), POLLING_INTERVAL);
        }, 2500); // 2.5 second delay to offset from main index polling
    }

    // --- Additional Data Services (ADS) Specific Functions ---
    const adsElems = {
        progressContainer: 'ads-progress-container',
        triggerButtonClass: '.ads-trigger-btn'
    };
    
    const entityTypeDetails = {
        'person': { label: '<?= L::personPlural(); ?>', icon: 'icon-type-person', parent: 'person' },
        'memberOfParliament': { label: '<?= L::personPlural(); ?> > memberOfParliament', icon: 'icon-type-person', parent: 'person' },
        'organisation': { label: '<?= L::organisations(); ?>', icon: 'icon-type-organisation', parent: 'organisation' },
        'legalDocument': { label: '<?= L::documents(); ?> > legalDocument', icon: 'icon-type-document', parent: 'document' },
        'officialDocument': { label: '<?= L::documents(); ?> > officialDocument', icon: 'icon-type-document', parent: 'document' },
        'term': { label: '<?= L::terms(); ?>', icon: 'icon-type-term', parent: 'term' }
    };

    function createAdsSectionHTML(entityType, details) {
        return `
            <div class="row mb-3" id="ads-${entityType}-progress-section" data-entity-type="${entityType}">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <div class="fw-bolder"><span class="${details.icon}"></span> ${details.label}</div>
                        <div id="ads-${entityType}-items-text" class="small">Idle</div>
                    </div>
                    <div class="progress my-1" role="progressbar" aria-label="ADS ${details.label} Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div id="ads-${entityType}-progress-bar" class="progress-bar" style="width: 0%"></div>
                    </div>
                    <div id="ads-${entityType}-status-text" class="small text-muted mb-2">Status: Idle</div>
                    <div id="ads-${entityType}-last-run-text" class="small text-muted mb-2"><?= L::lastRun(); ?>: N/A</div>
                    <button type="button" id="btn-trigger-ads-${entityType}" class="btn btn-outline-primary btn-sm ads-trigger-btn" data-entity-type="${entityType}"><span class="icon-cw"></span> <?= L::refreshData(); ?></button>
                    <div id="ads-${entityType}-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
                </div>
            </div>
            <hr>`;
    }

    function initializeAdsSections() {
        const container = document.getElementById(adsElems.progressContainer);
        if (!container) return;

        let html = '';
        for (const type in entityTypeDetails) {
            html += createAdsSectionHTML(type, entityTypeDetails[type]);
        }
        container.innerHTML = html;

        // Add event listeners after creation
        document.querySelectorAll(adsElems.triggerButtonClass).forEach(btn => {
            btn.addEventListener('click', function() {
                triggerAdsUpdate(this.dataset.entityType);
            });
        });
    }

    function getAdsButtonOriginalText(entityType) {
        return `<span class="icon-cw"></span> <?= L::refreshData(); ?>`;
    }

    async function fetchAdsStatus() {
        const url = getApiUrl('externalData', 'status');
        const result = await apiCall(url);
        if (result.success && result.data) {
            updateAdsUI(result.data);
        } else {
            console.warn("Failed to fetch ADS status:", result.errors);
            // Create a default error state for all types
            const errorStatus = { 
                globalStatus: 'error', 
                activeType: null,
                types: {}
            };
            for (const type in entityTypeDetails) {
                errorStatus.types[type] = { 
                    status: 'error', 
                    statusDetails: 'Status fetch failed', 
                    errors: result.errors || [{detail: 'Connection error while fetching status.'}] 
                };
            }
            updateAdsUI(errorStatus);
        }
    }

    function updateAdsUI(statusData) {
        const { globalStatus, activeType, types } = statusData;

        const allButtons = document.querySelectorAll(adsElems.triggerButtonClass);

        if (globalStatus === 'running') {
            allButtons.forEach(btn => {
                const entityType = btn.dataset.entityType;
                toggleButton(btn.id, true, getAdsButtonOriginalText(entityType)); 
            });
            if (activeType) {
                const activeButton = document.getElementById(`btn-trigger-ads-${activeType}`);
                if (activeButton) {
                    setButtonText(activeButton.id, `<?= L::running(); ?>...`);
                }
            }
        } else { // idle, completed, error, etc.
            allButtons.forEach(btn => {
                const entityType = btn.dataset.entityType;
                toggleButton(btn.id, false, getAdsButtonOriginalText(entityType));
            });
        }

        for (const entityType in entityTypeDetails) { // Iterate over all known types to ensure UI is drawn
            if (entityTypeDetails.hasOwnProperty(entityType)) {
                const typeStatus = types[entityType] || {}; // Use received status or empty object
                const {
                    status,
                    statusDetails = 'N/A',
                    processedItems = 0,
                    errors = [],
                    endTime = null
                } = typeStatus;
                
                let totalItems = 0;
                const typeInfo = entityTypeDetails[entityType];
                
                if (entityType === typeInfo.parent && appState.entityCounts[entityType]) {
                    // This is a "total" entry like 'person', 'organisation', or 'term'
                    totalItems = appState.entityCounts[entityType].total;
                } else if (appState.entityCounts[typeInfo.parent] && appState.entityCounts[typeInfo.parent].subtypes && appState.entityCounts[typeInfo.parent].subtypes[entityType] !== undefined) {
                    // This is a subtype entry like 'memberOfParliament'
                    totalItems = appState.entityCounts[typeInfo.parent].subtypes[entityType];
                }
                
                // Fallback to progress file's total if available
                if (typeStatus.totalItems > 0) {
                    totalItems = typeStatus.totalItems;
                }

                const section = document.getElementById(`ads-${entityType}-progress-section`);
                if (!section) continue;

                const progressBarId = `ads-${entityType}-progress-bar`;
                const itemsTextId = `ads-${entityType}-items-text`;
                const statusTextId = `ads-${entityType}-status-text`;
                const lastRunTextId = `ads-${entityType}-last-run-text`;
                const errorDisplayId = `ads-${entityType}-error-display`;

                const percentage = totalItems > 0 ? (processedItems / totalItems) * 100 : (status === 'completed_successfully' ? 100 : 0);
                updateProgressBar(progressBarId, percentage, status || 'idle');

                updateElementText(itemsTextId, `<?= L::items(); ?>: ${processedItems} / ${totalItems}`);
                updateElementText(statusTextId, `Status: ${statusDetails || 'Idle'}`);
                updateElementText(lastRunTextId, `<?= L::lastRun(); ?>: ${formatDate(endTime)}`);
                
                const errorStates = ['error', 'error_shutdown', 'error_critical', 'error_all_items_failed', 'partially_completed_with_errors', 'error_final'];
                if (errorStates.includes(status) && errors && errors.length > 0) {
                    showError(errorDisplayId, errors.map(e => ({ detail: e.message || 'Error message not found.' })));
                } else {
                    clearError(errorDisplayId);
                }
            }
        }
    }
    
    async function triggerAdsUpdate(entityType) { 
        document.querySelectorAll(adsElems.triggerButtonClass).forEach(btn => {
            toggleButton(btn.id, true);
        });
        const clickedButton = document.getElementById(`btn-trigger-ads-${entityType}`);
        if(clickedButton) setButtonText(clickedButton.id, `<?= L::running(); ?>...`);

        const url = getApiUrl('externalData', 'full-update', { type: entityType });
        const result = await apiCall(url, 'POST');

        if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && typeof result.data.message === 'string') {
            // The UI will update on the next poll, but we can give some immediate feedback
            const statusText = document.getElementById(`ads-${entityType}-status-text`);
            if(statusText) statusText.textContent = `Status: ${result.data.message}`;
            setTimeout(fetchAdsStatus, 1000);
        } else {
            const errorDisplayId = `ads-${entityType}-error-display`;
            showError(errorDisplayId, result.errors || (result.data ? result.data.message : null) || [{detail: `Failed to trigger ADS update for ${entityType}.`}]);
            // Re-enable buttons on failure to trigger
            document.querySelectorAll(adsElems.triggerButtonClass).forEach(btn => {
                toggleButton(btn.id, false, getAdsButtonOriginalText(btn.dataset.entityType));
            });
        }
    }

    // --- Overall Status Display Functions ---
    function fetchOverallStatus() {
        const url = `${API_BASE_URL}/status/all`;
        
        apiCall(url).then(result => {
            if (result.success && result.data && result.data.parliaments && result.data.parliaments.length > 0) {
                const parliamentData = result.data.parliaments[0]; // Assuming first parliament for now

					// Update Repository Info
					if (parliamentData.repository) {
                    updateElementText('lastCommitDate', formatDate(parliamentData.repository.remote?.lastUpdated));
                    updateElementText('repoLocation', parliamentData.repository.location);
                    updateElementText('repoRemoteSessions', parliamentData.repository.remote?.numberOfSessions);
                    updateElementText('repoLocalUpdate', formatDate(parliamentData.repository.local?.lastUpdated));
                    updateElementText('repoLocalSessions', parliamentData.repository.local?.numberOfSessions);
                    
                    appState.repo.remoteSessions = parseInt(parliamentData.repository.remote?.numberOfSessions, 10) || 0;
                    appState.repo.localSessions = parseInt(parliamentData.repository.local?.numberOfSessions, 10) || 0;
                    
                    updateDataImportUI();

					} else {
                    ['lastCommitDate', 'repoLocation', 'repoRemoteSessions', 'repoLocalUpdate', 'repoLocalSessions'].forEach(id => updateElementText(id, 'N/A'));
					}

					// Update Database Info
					if (parliamentData.database) {
                    updateElementText('lastDBDate', formatDate(parliamentData.database.lastUpdated));
                    updateElementText('dbSessions', parliamentData.database.numberOfSessions);
					} else {
                    ['lastDBDate', 'dbSessions'].forEach(id => updateElementText(id, 'N/A'));
					}

				} else {
                console.error("Error fetching status data or data is not in expected format:", result.errors || "Unknown error or no parliament data");
					const idsToReset = ['lastCommitDate', 'repoLocation', 'repoRemoteSessions', 'repoLocalUpdate', 'repoLocalSessions', 'lastDBDate', 'dbSessions'];
                idsToReset.forEach(id => updateElementText(id, 'Error'));
            }
        });
    }

    async function fetchEntityCounts() {
        const url = getApiUrl('statistics', 'entity-counts');
        const result = await apiCall(url);
        if (result.success && result.data) {
            appState.entityCounts = result.data;
            // Trigger a UI update with the new counts.
            // The next poll of fetchAdsStatus will merge this with live data.
            updateAdsUI({ globalStatus: 'idle', activeType: null, types: {} });
        } else {
            console.error("Failed to fetch entity counts:", result.errors);
        }
    }

    // --- Initialization ---
    function initPage() {
        // Initialize state tracking for coordination between main and enhanced index
        window.enhancedIndexState = {};
        window.mainIndexStatus = {};
        
        // Data Import
        const triggerDataImportBtn = document.getElementById(dataImportElems.triggerButton);
        if (triggerDataImportBtn) {
            triggerDataImportBtn.addEventListener('click', triggerDataImport);
            setButtonText(dataImportElems.triggerButton, dataImportElems.originalButtonText);
        }
        fetchDataImportStatus(); 
        setInterval(fetchDataImportStatus, POLLING_INTERVAL);

        // Search Index Sections 
        initializeSearchIndexSections();
        
        // Additional Data Services (ADS)
        initializeAdsSections();
        fetchEntityCounts();
        fetchAdsStatus();
        setInterval(fetchAdsStatus, POLLING_INTERVAL);

        // Fetch overall status for repository/DB info
        fetchOverallStatus();
        setInterval(fetchOverallStatus, POLLING_INTERVAL);
    }

    initPage();
	});
</script>

<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>