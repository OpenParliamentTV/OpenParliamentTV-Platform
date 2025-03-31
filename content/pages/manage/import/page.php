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
					<h2><?php echo L::manageImport; ?></h2>
					<div class="card mb-3">
						<div class="card-body">
    
                        </div>
					</div>
					<ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" role="tab" aria-controls="status" aria-selected="true"><span class="icon-arrows-cw"></span> Status</a>
                        </li>
						<li class="nav-item">
                            <a class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" role="tab" aria-controls="people" aria-selected="false"><span class="icon-cog"></span> <?php echo L::settings; ?></a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="status" role="tabpanel" aria-labelledby="status-tab">
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
										<button type="submit" class="btn btn-outline-primary">Start import</button>
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
						<div class="tab-pane bg-white fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
							[SETTINGS]
                        </div>
                    </div>
				</div>
			</div>
		</div>
	</div>
</main>
<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>