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
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::data; ?>-Import</h2>
		</div>
	</div>
	<div class="row mt-5">
		<div class="col-12">
			<?php
			include_once(__DIR__."/../../../../config.php");
			if ((!$_REQUEST["parliament"]) || (!array_key_exists($_REQUEST["parliament"],$config["parliament"]))) {

				echo '
					<form action="" method="post">
						<!--<input type="hidden" name="a" value="import">-->
						<select class="form-control mb-4" name="parliament">';
						  foreach($config["parliament"] as $k=>$v) {
							  echo '<option value="'.$k.'">'.$v["label"].'</option>';
						  }
				echo '
						</select>
						<div class="form-group">
							<label for="inputDir">Input Directory ('.count(array_diff(scandir(__DIR__.'/../../../../modules/import/input/'), array('..', '.'))).' Files)</label>
							<input type="text" class="form-control" id="inputDir"  name="inputDir" value="'.realpath(__DIR__.'/../../../../modules/import/input/').'/">
					 	</div>
						<div class="form-group">
							<label for="doneDir">Done Directory</label>
							<input type="text" class="form-control" id="doneDir" name="doneDir" value="'.realpath(__DIR__.'/../../../../modules/import/done/').'/">
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
	</div>
</main>
<?php
    include_once(__DIR__ . '/../../../footer.php');

}

?>