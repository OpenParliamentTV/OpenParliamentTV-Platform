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
                            <div class="status-visualization p-4">
                                <div class="row align-items-center justify-content-center position-relative">
                                    <div class="connecting-line"></div>
                                    <div class="col-4 text-center">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-git-squared small"></i>
                                                <h4 class="small mb-0">Data <br>Repository</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div>Last updated:</div>
											<div id="lastCommitDate" class="fw-bolder">-</div>
										</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-database small"></i>
                                                <h4 class="small mb-0">Platform <br>Database</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div>Last updated:</div>
											<div id="lastDBDate" class="fw-bolder">-</div>
										</div>
                                    </div>
                                    <div class="col-4 text-center">
                                        <div class="status-circle rounded-circle">
                                            <div class="circle-content position-absolute top-50 start-50 translate-middle text-center p-2">
                                                <i class="icon-search small"></i>
                                                <h4 class="small mb-0">Search <br>Index</h4>
                                            </div>
                                        </div>
                                        <div class="mt-3">
											<div>Last updated:</div>
											<div id="lastSearchDate" class="fw-bolder">-</div>
										</div>
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
							if ((!$_REQUEST["parliament"]) || (!array_key_exists($_REQUEST["parliament"],$config["parliament"]))) {

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
		width: calc(70%);
		height: 2px;
		background: var(--border-color);
		z-index: 1;
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
                url:"<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
                dataType:"json",
                data:{"a":"runCronUpdater"},
                method:"post",
                success: function(ret) {
                    $('#successRunCronDialog').modal('show');
                }
            })
        });

		$(".updateSearchIndex").on("click", function() {
			if ($(this).data("type") == "specific") {
				//TODO popup with parliaments and textarea for comma separated list of MediaIDs

				$.ajax({
					url: config["dir"]["root"] + "/server/ajaxServer.php",
					method:"POST",
					data: {"a":"searchIndexUpdate","type":"specific","parliament":"DE","mediaIDs":"DE-0190062070,DE-0190077128"},
					success: function(ret) {
						console.log(ret);
					}
				})

			} else if ($(this).data("type") == "all") {
				//TODO Popup with parliaments
				//TODO Waitingscreen until response (will take some time)
				$.ajax({
					url: config["dir"]["root"] + "/server/ajaxServer.php",
					method:"POST",
					data: {"a":"searchIndexUpdate","type":"all","parliament":"DE"},
					success: function(ret) {
						console.log(ret);
					}
				})
			}

		});

		$("#deleteSearchIndex").on("click", function() {
			//TODO Dialog with parliament and if index should be re-initialized (true) or nor (false)
			$.ajax({
				url: config["dir"]["root"] + "/server/ajaxServer.php",
				method:"POST",
				data: {"a":"searchIndexDelete","parliament":"DE","init":"true"},
				success: function(ret) {
					console.log(ret);
				}
			})
		})

		fetch('https://api.github.com/repos/OpenParliamentTV/OpenParliamentTV-Data-DE/commits')
			.then(response => response.json())
			.then(data => {
				const date = new Date(data[0].commit.author.date);
				document.getElementById('lastCommitDate').textContent = 
					date.toLocaleString('de');
			});

		fetch('<?= $config["dir"]["root"] ?>/api/v1/search/media?includeAll=true&sort=date-desc')
			.then(response => response.json())
			.then(data => {
				if (data.data && data.data.length > 0) {
					const date = new Date(data.data[0].attributes.lastChanged);
					document.getElementById('lastSearchDate').textContent = 
						date.toLocaleString('de');
				}
			});
	});
</script>
<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>