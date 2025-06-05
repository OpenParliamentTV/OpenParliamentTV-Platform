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
					<h2><?= L::manageImport; ?></h2>
					<div class="card mb-3">
						<div class="card-body">
							
                        </div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="parliaments-tab" data-bs-toggle="tab" data-bs-target="#parliaments" role="tab" aria-controls="parliaments" aria-selected="true"><span class="icon-bank"></span> Parliaments</a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="external-tab" data-bs-toggle="tab" data-bs-target="#external" role="tab" aria-controls="external" aria-selected="false"><span class="icon-tags"></span> Entity Data</a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="settings" aria-selected="false"><span class="icon-cog"></span> Settings</a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="old-tab" data-bs-toggle="tab" data-bs-target="#old" role="tab" aria-controls="old" aria-selected="false">OLD THINGS TO REUSE</a>
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
											<div>Last updated:</div>
											<div id="lastCommitDate" class="fw-bolder">-</div>
											<div class="small">Sessions: <span id="repoRemoteSessions">-</span></div>
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
											<div>Last updated:</div>
											<div id="repoLocalUpdate" class="fw-bolder">-</div>
											<div class="small">Sessions: <span id="repoLocalSessions">-</span></div>
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
											<div>Last updated:</div>
											<div id="lastDBDate" class="fw-bolder">-</div>
											<div class="small">Sessions: <span id="dbSessions">-</span></div>
										</div>
                                    </div>
                                </div>
                            </div>
                            <hr>
							<div class="row" id="data-import-progress-section">
								<div class="col-12">
									<div class="d-flex justify-content-between">
										<div class="fw-bolder">Data Import</div>
										<div id="data-import-items-text" class="small">Idle</div>
									</div>
									<div class="progress my-1" role="progressbar" aria-label="Data Import Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										<div id="data-import-progress-bar" class="progress-bar" style="width: 0%"></div>
									</div>
                                    <div id="data-import-status-text" class="small text-muted mb-2">Status: Idle</div>
                                    <div id="data-import-current-file-text" class="small text-muted mb-2">File: N/A</div>
									<button type="button" id="btn-trigger-data-import" class="btn btn-outline-primary rounded-pill btn-sm me-1">Start Full Data Import</button>
                                    <div id="data-import-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
								</div>
							</div>
							<hr>
							<div class="row" id="search-index-progress-section-DE" data-parliament-code="DE">
								<div class="col-12">
									<div class="d-flex justify-content-between">
										<div class="fw-bolder">Search Index (DE)</div> 
										<div id="search-index-DE-items-text" class="small">Idle</div>
									</div>
									<div class="progress my-1" role="progressbar" aria-label="Search Index DE Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
										<div id="search-index-DE-progress-bar" class="progress-bar" style="width: 0%"></div>
									</div>
                                    <div id="search-index-DE-status-text" class="small text-muted mb-2">Status: Idle</div>
									<button type="button" id="btn-trigger-search-index-refresh-DE" class="btn btn-outline-primary rounded-pill btn-sm me-1" data-parliament-code="DE">Refresh Full Index (DE)</button>
									<button type="button" id="btn-trigger-search-index-delete-DE" class="btn btn-outline-danger rounded-pill btn-sm me-1" data-parliament-code="DE">Delete Index (DE)</button>
                                    <div id="search-index-DE-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
								</div>
							</div>
                        </div>
						<div class="tab-pane bg-white fade p-3" id="external" role="tabpanel" aria-labelledby="external-tab">
                            <!-- Main ADS Progress Display -->
                            <div class="row mb-3" id="ads-overall-progress-section">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <div class="fw-bolder">Overall Additional Data Service Status</div>
                                        <div id="ads-overall-items-text" class="small">Idle</div>
                                    </div>
                                    <div class="progress my-1" role="progressbar" aria-label="Overall ADS Progress" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        <div id="ads-overall-progress-bar" class="progress-bar" style="width: 0%"></div>
                                    </div>
                                    <div id="ads-overall-status-text" class="small text-muted mb-2">Status: Idle</div>
                                    <div id="ads-overall-active-type-text" class="small text-muted mb-2">Current Task: N/A</div>
                                    <div id="ads-overall-error-display" class="alert alert-danger mt-2 p-2 small d-none"></div>
                                </div>
                            </div>
                            <hr class="mb-4">

                            <!-- Entity Specific Triggers -->
							<div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-person"></span> People</div>
										<button type="button" id="btn-trigger-ads-person" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="person">Refresh Data for People</button>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-person"></span> Members of Parliament</div>
										<button type="button" id="btn-trigger-ads-memberOfParliament" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="memberOfParliament">Refresh Data for MoPs</button>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-organisation"></span> Organisations</div>
										<button type="button" id="btn-trigger-ads-organisation" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="organisation">Refresh Data for Organisations</button>
									</div>
								</div>
							</div>
							<hr>
                            <div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-document"></span> Legal Documents</div>
										<button type="button" id="btn-trigger-ads-legalDocument" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="legalDocument">Refresh Data for Legal Documents</button>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-document"></span> Official Documents</div>
										<button type="button" id="btn-trigger-ads-officialDocument" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="officialDocument">Refresh Data for Official Documents</button>
									</div>
								</div>
							</div>
							<hr>
							<div class="row">
								<div class="col-12">
									<div class="d-flex justify-content-between align-items-center">
										<div class="fw-bolder"><span class="icon-type-term"></span> Terms</div>
										<button type="button" id="btn-trigger-ads-term" class="btn btn-outline-primary rounded-pill btn-sm ads-trigger-btn" data-entity-type="term">Refresh Data for Terms</button>
									</div>
								</div>
							</div>
                        </div>
						<div class="tab-pane bg-white fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
							[SETTINGS]
                        </div>
						<div class="tab-pane bg-white fade" id="old" role="tabpanel" aria-labelledby="old-tab">
							[OLD THINGS TO REUSE]
							<div class="row">
                                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                                    <span class="d-block p-4 bg-white text-center btn rounded-pill updateSearchIndex" href="" data-type="specific">Update specific Medias</span>
                                </div>
                                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                                    <span class="d-block p-4 bg-white text-center btn rounded-pill updateSearchIndex" href="" data-type="all">Update whole Searchindex</span>
                                </div>
                                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                                    <span class="d-block p-4 bg-white text-center btn rounded-pill" id="deleteSearchIndex" href="">Delete whole Searchindex</span>
                                </div>
                            </div>
							<hr>
							<?php
							include_once(__DIR__."/../../../../config.php");
							if ((!isset($_REQUEST["parliament"])) || (!array_key_exists($_REQUEST["parliament"],$config["parliament"]))) {

								echo '
									<form action="" method="post">
										<!--<input type="hidden" name="a" value="import">-->
										<select class="form-select mb-4" name="parliament">';
										foreach($config["parliament"] as $k=>$v) {
											echo '<option value="'.$k.'">'.$v["label"].'</option>';
										}
								echo '
										</select>
										<div class="form-group">
											<label for="inputDir">Input Directory ('.count(array_diff(scandir(__DIR__.'/../../../../data/input/'), array('..', '.'))).' Files)</label>
											<input type="text" class="form-control" id="inputDir"  name="inputDir" value="'.realpath(__DIR__.'/../../../../data/input/').'/">
										</div>
										<div class="form-group">
											<label for="doneDir">Done Directory</label>
											<input type="text" class="form-control" id="doneDir" name="doneDir" value="'.realpath(__DIR__.'/../../../../data/done/').'/">
										</div>
										<button type="submit" class="btn btn-outline-primary rounded-pill">Start import</button>
									</form>
								';

							} else {
								echo "<pre>";
								print_r($_REQUEST);
								echo "</pre>";

								/*require_once(__DIR__."/../../../../modules/import/functions.json2sql.php");
								ob_implicit_flush(true);
								ob_end_flush();
								$status = importParliamentSpeechJSONtoSQL($_REQUEST["inputDir"],$_REQUEST["doneDir"],$_REQUEST["parliament"]);
								*/
								include_once(__DIR__."/../../../../modules/import/functions.import.php");
								ob_implicit_flush(true);
								ob_end_flush();
								$meta["preserveFiles"] = true;
								$meta["inputDir"] = $_REQUEST["inputDir"];
								$meta["doneDir"] = $_REQUEST["doneDir"];
								$status = importParliamentMedia("jsonfiles",$_REQUEST["parliament"],$meta);
								//$status = importParliamentMedia($_REQUEST["inputDir"],$_REQUEST["doneDir"],$_REQUEST["parliament"]);

								if (is_array($status)) {
									echo "<pre>";
									print_r($status);
									echo "</pre>";
								}


							}

							?>
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>

<div class="modal fade" id="successRunCronDialog" tabindex="-1" role="dialog" aria-labelledby="successRunCronDialogLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successRunCronDialogLabel">Run cronUpdater</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                The cronUpdater should run in background now
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary rounded-pill" data-bs-dismiss="modal">Okay</button>
            </div>
        </div>
    </div>
</div>

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

            const errorStates = ['error', 'error_shutdown', 'error_critical', 'error_all_items_failed', 'partially_completed_with_errors'];
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
    
    function setButtonText(buttonId, textContent) {
        const btn = document.getElementById(buttonId);
        if (btn) {
            const icon = btn.querySelector('i, span[class^="icon-"]');
            if (icon) {
                let currentTextNode = null;
                for (let i = 0; i < btn.childNodes.length; i++) {
                    if (btn.childNodes[i].nodeType === Node.TEXT_NODE && btn.childNodes[i].textContent.trim() !== '') {
                        currentTextNode = btn.childNodes[i];
                        break;
                    }
                }
                if (currentTextNode) {
                    currentTextNode.textContent = ' ' + textContent; // Add space after icon
                } else {
                    btn.appendChild(document.createTextNode(' ' + textContent));
                }
            } else {
                btn.textContent = textContent;
            }
        }
    }

    function toggleButton(buttonId, disabledState, textContent = null) {
        const btn = document.getElementById(buttonId);
        if (btn) {
            btn.disabled = disabledState;
            if (textContent) {
                setButtonText(buttonId, textContent);
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
        originalButtonText: 'Start Full Data Import' 
    };

    async function fetchDataImportStatus() {
        const url = getApiUrl('import', 'status');
        const result = await apiCall(url);
        if (result.success && result.data) {
            updateDataImportUI(result.data);
        } else {
            console.warn("Failed to fetch data import status:", result.errors);
            updateDataImportUI({ status: 'error', statusDetails: 'Status fetch failed', percentage: 0, errors: result.errors || [{detail: 'Connection error while fetching status.'}] });
        }
    }

    function updateDataImportUI(statusData) {
        const { status, percentage = 0, statusDetails = 'N/A', totalFiles = 0, processedFiles = 0, currentFile = 'N/A', errors = [] } = statusData;
        
        updateProgressBar(dataImportElems.progressBar, percentage, status);
        updateElementText(dataImportElems.statusText, `Status: ${statusDetails}`);
        updateElementText(dataImportElems.itemsText, `Files: ${processedFiles} / ${totalFiles}`);
        updateElementText(dataImportElems.currentFileText, `Current: ${currentFile || 'N/A'}`);

        if (status === 'running') {
            toggleButton(dataImportElems.triggerButton, true, 'Importing...');
            clearError(dataImportElems.errorDisplay);
        } else {
            toggleButton(dataImportElems.triggerButton, false, dataImportElems.originalButtonText);
            if (status === 'error') {
                const errorMessages = errors && errors.length > 0 ? errors : (statusDetails ? [{ detail: statusDetails }] : [{detail: 'An unknown error occurred during data import.'}]);
                showError(dataImportElems.errorDisplay, errorMessages);
            } else { // completed, idle, or other non-running, non-error status
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

    // --- Search Index Specific Functions (Dynamic based on HTML) ---
    function initializeSearchIndexSections() {
        const sections = document.querySelectorAll('[id^="search-index-progress-section-"]');
        sections.forEach(section => {
            const parliamentCode = section.dataset.parliamentCode;
            if (!parliamentCode) {
                console.warn('Search index section found without data-parliament-code:', section.id);
                return;
            }

            const elems = {
                progressBar: `search-index-${parliamentCode}-progress-bar`,
                statusText: `search-index-${parliamentCode}-status-text`,
                itemsText: `search-index-${parliamentCode}-items-text`,
                errorDisplay: `search-index-${parliamentCode}-error-display`,
                refreshButton: `btn-trigger-search-index-refresh-${parliamentCode}`,
                deleteButton: `btn-trigger-search-index-delete-${parliamentCode}`,
                parliamentCode: parliamentCode,
                originalRefreshBtnText: `Refresh Full Index (${parliamentCode})`,
                originalDeleteBtnText: `Delete Index (${parliamentCode})`
            };

            async function fetchStatus() {
                const url = getApiUrl('index', 'status', { parliament: elems.parliamentCode });
                const result = await apiCall(url);
                if (result.success && result.data) {
                    updateUI(result.data);
                } else {
                    console.warn(`Failed to fetch search index status for ${elems.parliamentCode}:`, result.errors);
                    updateUI({ status: 'error', statusDetails: 'Status fetch failed', percentage: 0, errors: result.errors || [{detail: 'Connection error while fetching status.'}] });
                }
            }

            function updateUI(statusData) {
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
                updateElementText(elems.itemsText, `Speeches: ${processedMediaItems} / ${totalDbMediaItems}`);

                if (status === 'running') {
                    toggleButton(elems.refreshButton, true, 'Refreshing...');
                    toggleButton(elems.deleteButton, true, 'Processing...');
                    clearError(elems.errorDisplay);
                } else {
                    toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
                    toggleButton(elems.deleteButton, false, elems.originalDeleteBtnText);
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

            async function triggerRefresh() {
                toggleButton(elems.refreshButton, true, 'Starting Refresh...');
                toggleButton(elems.deleteButton, true); // Disable delete during refresh
                clearError(elems.errorDisplay);
                const url = getApiUrl('index', 'full-update', { parliament: elems.parliamentCode });
                const result = await apiCall(url, 'POST');

                if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && typeof result.data.message === 'string') {
                    updateElementText(elems.statusText, `Status: ${result.data.message || 'Search index refresh triggered.'}`);
                    setTimeout(fetchStatus, 1000);
                } else {
                    showError(elems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: 'Failed to trigger search index refresh.'}]);
                    toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
                    toggleButton(elems.deleteButton, false); // Re-enable delete on failure
                }
            }
        
            async function triggerDelete() {
                if (!confirm(`Are you sure you want to delete the search index for parliament ${elems.parliamentCode}? This action cannot be undone.`)) {
                    return;
                }
                toggleButton(elems.deleteButton, true, 'Deleting...');
                toggleButton(elems.refreshButton, true); // Disable refresh during delete
                clearError(elems.errorDisplay);
                const url = getApiUrl('index', 'delete', { parliament: elems.parliamentCode, init: true }); 
                const result = await apiCall(url, 'POST');

                if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && result.data.deleted === true) {
                    updateElementText(elems.statusText, `Status: ${result.data.message || 'Search index deleted successfully.'}`);
                    updateProgressBar(elems.progressBar, 0, 'idle'); // Reset progress bar to idle and 0%
                    updateUI({status: 'deleted', percentage: 0, statusDetails: result.data.message || 'Index deleted'});// Update UI to reflect deletion
                    setTimeout(fetchStatus, 1000); 
                } else {
                    showError(elems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: 'Failed to delete search index.'}]);
                    toggleButton(elems.deleteButton, false, elems.originalDeleteBtnText);
                    toggleButton(elems.refreshButton, false, elems.originalRefreshBtnText);
                }
            }

            const refreshBtn = document.getElementById(elems.refreshButton);
            if (refreshBtn) refreshBtn.addEventListener('click', triggerRefresh);
            
            const deleteBtn = document.getElementById(elems.deleteButton);
            if (deleteBtn) deleteBtn.addEventListener('click', triggerDelete);

            fetchStatus();
            setInterval(fetchStatus, POLLING_INTERVAL);
        });
    }

    // --- Additional Data Services (ADS) Specific Functions ---
    const adsElems = {
        progressBar: 'ads-overall-progress-bar',
        statusText: 'ads-overall-status-text',
        itemsText: 'ads-overall-items-text',
        activeTypeText: 'ads-overall-active-type-text',
        errorDisplay: 'ads-overall-error-display',
        triggerButtons: document.querySelectorAll('.ads-trigger-btn')
    };

    function getAdsButtonOriginalText(btn) {
        let entityTypeDisplay = btn.dataset.entityType;
        if (entityTypeDisplay === 'memberOfParliament') entityTypeDisplay = 'MoPs';
        else if (entityTypeDisplay) entityTypeDisplay = entityTypeDisplay.charAt(0).toUpperCase() + entityTypeDisplay.slice(1) + ('s' !== entityTypeDisplay.slice(-1) ? 's' : '');
        else entityTypeDisplay = 'All Entities';
        return `Refresh Data for ${entityTypeDisplay}`;
    }

    async function fetchAdsStatus() {
        const url = getApiUrl('externalData', 'status');
        const result = await apiCall(url);
        if (result.success && result.data) {
            updateAdsUI(result.data);
        } else {
            console.warn("Failed to fetch ADS status:", result.errors);
            updateAdsUI({ status: 'error', statusDetails: 'Status fetch failed', percentage: 0, errors: result.errors || [{detail: 'Connection error while fetching status.'}] });
        }
    }

    function updateAdsUI(statusData) {
        const { status, percentage = 0, statusDetails = 'N/A', entityType = 'all', currentItem = 'N/A', totalItems = 0, processedItems = 0, errors = [] } = statusData;

        updateProgressBar(adsElems.progressBar, percentage, status);
        updateElementText(adsElems.statusText, `Status: ${statusDetails}`);
        updateElementText(adsElems.activeTypeText, `Current Task: ${entityType || 'Overall'} - ${currentItem || 'N/A'}`);
        updateElementText(adsElems.itemsText, `Items: ${processedItems} / ${totalItems}`);
        
        if (status === 'running') {
            adsElems.triggerButtons.forEach(btn => {
                toggleButton(btn.id, true, `${btn.dataset.entityType} (Running)`);
            });
            clearError(adsElems.errorDisplay);
        } else {
            adsElems.triggerButtons.forEach(btn => {
                toggleButton(btn.id, false, getAdsButtonOriginalText(btn));
            });
            if (status === 'error') {
                const errorMessages = errors && errors.length > 0 ? errors : (statusDetails ? [{ detail: statusDetails }] : [{detail: 'An unknown error occurred.'}]);
                showError(adsElems.errorDisplay, errorMessages);
            } else { // completed, idle
                clearError(adsElems.errorDisplay);
            }
        }
    }
    
    async function triggerAdsUpdate(entityType = 'all') { 
        adsElems.triggerButtons.forEach(btn => {
            toggleButton(btn.id, true, `${btn.dataset.entityType} (Starting)`);
        });
        clearError(adsElems.errorDisplay);
        const params = entityType && entityType.toLowerCase() !== 'all' ? { type: entityType } : {};
        const url = getApiUrl('externalData', 'full-update', params);
        const result = await apiCall(url, 'POST');

        if (result.success && result.meta && result.meta.requestStatus === 'success' && result.data && typeof result.data.message === 'string') {
            updateElementText(adsElems.statusText, `Status: ${result.data.message || `ADS update for ${entityType} triggered.`}`);
            setTimeout(fetchAdsStatus, 1000);
        } else {
            showError(adsElems.errorDisplay, result.errors || (result.data ? result.data.message : null) || [{detail: `Failed to trigger ADS update for ${entityType}.`}]);
            adsElems.triggerButtons.forEach(btn => {
                toggleButton(btn.id, false, getAdsButtonOriginalText(btn));
            });
        }
    }

    // --- Overall Status Display Functions ---
    function fetchOverallStatus() {
        const url = `${API_BASE_URL}/status/all`;
        
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

    // --- Initialization ---
    function initPage() {
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
        adsElems.triggerButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                triggerAdsUpdate(this.dataset.entityType);
            });
            setButtonText(btn.id, getAdsButtonOriginalText(btn));
        });
        fetchAdsStatus();
        setInterval(fetchAdsStatus, POLLING_INTERVAL);

        // Fetch overall status for repository/DB info
        fetchOverallStatus();
    }

    initPage();
});
</script>

<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>