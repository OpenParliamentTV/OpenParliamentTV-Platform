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
							<button type="button" id="runCronUpdater" class="btn btn-outline-success rounded-pill btn-sm me-1">Run cronUpdater</button>
                        </div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" role="tab" aria-controls="status" aria-selected="true"><span class="icon-arrows-cw"></span> Status</a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="people" aria-selected="false"><span class="icon-cog"></span> <?= L::settings; ?></a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="old-tab" data-bs-toggle="tab" data-bs-target="#old" role="tab" aria-controls="old" aria-selected="false">OLD THINGS TO REUSE</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="status" role="tabpanel" aria-labelledby="status-tab">
                            <div class="status-visualization p-4 position-relative">
                                <div class="row align-items-start justify-content-center position-relative">
                                    <div class="connecting-line"></div>
                                    <div class="col-4 text-center status-item status-item-dummy-1">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-bank small"></i>
                                                <h4 class="small mb-0">Parliament <br>Sources</h4>
                                            </div>
                                        </div>
                                    </div>
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
                                </div>

                                <div class="row align-items-start justify-content-start position-relative flex-row-reverse mt-5">
                                    <div class="connecting-line connecting-line-short"></div>
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
											<div class="small mt-1">Last Speech: <span id="dbLastSpeechDate">-</span></div>
											<div class="small">Sessions: <span id="dbSessions">-</span></div>
											<div class="small">Speeches: <span id="dbSpeeches">-</span></div>
										</div>
                                    </div>
                                    <div class="col-4 text-center status-item status-item-search">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-search small"></i>
                                                <h4 class="small mb-0">Search <br>Index</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div>Last updated:</div>
											<div id="lastSearchDate" class="fw-bolder">-</div>
											<div class="small mt-1">Last Speech: <span id="searchLastSpeechDate">-</span></div>
											<div class="small">Speeches: <span id="searchSpeeches">-</span></div>
										</div>
                                    </div>
                                </div>
                                
                                <div class="connecting-line-vertical-snake"></div>

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
	.connecting-line-short {
		width: calc(100% / 3);
		left: calc(100% * 2 / 3);
		transform: translateX(-50%);
		position: absolute;
		top: 51px;
		height: 2px;
		background: var(--border-color);
		z-index: 1;
	}
	.connecting-line-vertical-snake {
        position: absolute;
        width: 2px;
        background-color: var(--border-color);
        z-index: 1;
        right: calc(100% / 6 + 6px);
        top: 124px;
        height: 150px; 
    }
	.status-circle i {
		font-size: 1.25rem;
		margin-bottom: 4px;
	}
</style>

<script type="text/javascript">
	
	$(function() {
        
		$("#runCronUpdater").on("click", function() {
            $.ajax({
                url: "<?= $config["dir"]["root"] ?>/api/v1/import/run",
                dataType: "json",
                data: {
                    "action": "import",
                    "itemType": "run"
                },
                method: "post",
                success: function(response) {
                    if (response.meta.requestStatus === "success") {
                        $('#successRunCronDialog').modal('show');
                    } else {
                        // Handle error case
                        let errorMessage = "Failed to start CronUpdater";
                        if (response.errors && response.errors.length > 0) {
                            errorMessage = response.errors[0].detail || response.errors[0].title;
                        }
                        alert(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Error: " + error);
                }
            });
        });

		$(".updateSearchIndex").on("click", function() {
			const parliament = "DE";

			if ($(this).data("type") == "specific") {
				const mediaIDs = "DE-0190062070,DE-0190077128";

				$.ajax({
					url: "<?= $config["dir"]["root"] ?>/api/v1/index/update",
					method:"POST",
					dataType: "json",
					data: {
                        "parliament": parliament,
                        "mediaIDs": mediaIDs,
                        "initIndex": false
                    },
					success: function(ret) {
						console.log("Update specific Medias response:", ret);
                        alert( (ret.meta && ret.meta.requestStatus === "success") ? "Specific medias update triggered: " + (ret.data ? ret.data.updated + " updated." : "") : "Error: " + (ret.errors ? ret.errors[0].detail : "Unknown error") );
					},
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Update specific Medias error:", textStatus, errorThrown, jqXHR.responseText);
                        alert("AJAX Error updating specific medias: " + textStatus);
                    }
				})

			} else if ($(this).data("type") == "all") {
				$.ajax({
					url: "<?= $config["dir"]["root"] ?>/api/v1/index/full-update",
					method:"POST",
					dataType: "json",
					data: {
                        "parliament": parliament
                    },
					success: function(ret) {
						console.log("Update whole Searchindex response:", ret);
                        alert( (ret.meta && ret.meta.requestStatus === "success") ? (ret.data ? ret.data.message : "Full search index update process started.") : "Error: " + (ret.errors ? ret.errors[0].detail : "Unknown error") );
					},
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Update whole Searchindex error:", textStatus, errorThrown, jqXHR.responseText);
                        alert("AJAX Error updating whole search index: " + textStatus);
                    }
				})
			}

		});

		$("#deleteSearchIndex").on("click", function() {
			const parliament = "DE";
            const initAfterDelete = true;

			$.ajax({
				url: "<?= $config["dir"]["root"] ?>/api/v1/index/delete",
				method:"POST",
				dataType: "json",
				data: {
                    "parliament": parliament,
                    "init": initAfterDelete
                },
				success: function(ret) {
					console.log("Delete whole Searchindex response:", ret);
                    alert( (ret.meta && ret.meta.requestStatus === "success") ? "Search index deletion triggered." : "Error: " + (ret.errors ? ret.errors[0].detail : "Unknown error") );
				},
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("Delete whole Searchindex error:", textStatus, errorThrown, jqXHR.responseText);
                    alert("AJAX Error deleting search index: " + textStatus);
                }
			})
		})

		fetch('<?= $config["dir"]["root"] ?>/api/v1/status/all')
			.then(response => response.json())
			.then(data => {
				if (data.meta && data.meta.requestStatus === "success" && data.data && data.data.parliaments && data.data.parliaments.length > 0) {
					const parliamentData = data.data.parliaments[0]; // Assuming first parliament

					// Helper to format date or return placeholder
					const formatDate = (dateString) => {
						if (!dateString) return '-';
						try {
							return new Date(dateString).toLocaleString('de');
						} catch (e) {
							return '-';
						}
					};
					
					const el = (id) => document.getElementById(id);
					const setText = (id, text) => {
						if (el(id)) el(id).textContent = text || '-';
					};

					// Update Repository Info
					if (parliamentData.repository) {
						setText('lastCommitDate', formatDate(parliamentData.repository.remote?.lastUpdated));
						setText('repoLocation', parliamentData.repository.location);
						setText('repoRemoteSessions', parliamentData.repository.remote?.numberOfSessions);
						setText('repoLocalUpdate', formatDate(parliamentData.repository.local?.lastUpdated));
						setText('repoLocalSessions', parliamentData.repository.local?.numberOfSessions);
					} else {
						setText('lastCommitDate', 'N/A');
						setText('repoLocation', 'N/A');
						setText('repoRemoteSessions', 'N/A');
						setText('repoLocalUpdate', 'N/A');
						setText('repoLocalSessions', 'N/A');
					}

					// Update Database Info
					if (parliamentData.database) {
						setText('lastDBDate', formatDate(parliamentData.database.lastUpdated));
						setText('dbLastSpeechDate', formatDate(parliamentData.database.lastSpeechDate));
						setText('dbSessions', parliamentData.database.numberOfSessions);
						setText('dbSpeeches', parliamentData.database.numberOfSpeeches);
					} else {
						setText('lastDBDate', 'N/A');
						setText('dbLastSpeechDate', 'N/A');
						setText('dbSessions', 'N/A');
						setText('dbSpeeches', 'N/A');
					}

					// Update Search Index Info
					if (parliamentData.index) {
						setText('lastSearchDate', formatDate(parliamentData.index.lastUpdated));
						setText('searchLastSpeechDate', formatDate(parliamentData.index.lastSpeechDate));
						setText('searchSpeeches', parliamentData.index.numberOfSpeeches);
					} else {
						setText('lastSearchDate', 'N/A');
						setText('searchLastSpeechDate', 'N/A');
						setText('searchSpeeches', 'N/A');
					}

				} else {
					console.error("Error fetching status data or data is not in expected format:", data.errors || "Unknown error or no parliament data");
					const idsToReset = ['lastCommitDate', 'repoLocation', 'repoRemoteSessions', 'repoLocalUpdate', 'repoLocalSessions', 'lastDBDate', 'dbLastSpeechDate', 'dbSessions', 'dbSpeeches', 'lastSearchDate', 'searchLastSpeechDate', 'searchSpeeches'];
					idsToReset.forEach(id => setText(id, 'Error'));
				}
			})
			.catch(error => {
				console.error("Failed to fetch status:", error);
				const idsToReset = ['lastCommitDate', 'repoLocation', 'repoRemoteSessions', 'repoLocalUpdate', 'repoLocalSessions', 'lastDBDate', 'dbLastSpeechDate', 'dbSessions', 'dbSpeeches', 'lastSearchDate', 'searchLastSpeechDate', 'searchSpeeches'];
				idsToReset.forEach(id => document.getElementById(id) ? document.getElementById(id).textContent = 'Error' : null);
			});
	});
</script>
<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>