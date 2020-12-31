<?php include_once(__DIR__ . '/../../structure/header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Import WIP</h2>
		</div>
	</div>
	<div class="row mt-5">
		<div class="col-12">
			<?php
			require_once(__DIR__."/../../config.php");
			if ((!$_REQUEST["parliament"]) || (!array_key_exists($_REQUEST["parliament"],$config["parliament"]))) {

				echo '
					<form action="" method="post">
						<input type="hidden" name="a" value="import">
						<select class="form-control mb-4" name="parliament">';
						  foreach($config["parliament"] as $k=>$v) {
							  echo '<option value="'.$k.'">'.$v["label"].'</option>';
						  }
				echo '
						</select>
						<div class="form-group">
							<label for="inputDir">Input Directory ('.count(array_diff(scandir(__DIR__.'/input/'), array('..', '.'))).' Files)</label>
							<input type="text" class="form-control" id="inputDir"  name="inputDir" value="'.realpath(__DIR__.'/input/').'/">
					 	</div>
						<div class="form-group">
							<label for="doneDir">Done Directory</label>
							<input type="text" class="form-control" id="doneDir" name="doneDir" value="'.realpath(__DIR__.'/done/').'/">
					 	</div>
					 	<button type="submit" class="btn btn-outline-primary">Start import</button>
					</form>
				';

			} else {
				echo "<pre>";
				print_r($_REQUEST);
				echo "</pre>";

				require_once(__DIR__."/functions.json2sql.php");
				ob_implicit_flush(true);
				ob_end_flush();
				$status = importParliamentSpeechJSONtoSQL($_REQUEST["inputDir"],$_REQUEST["doneDir"],$_REQUEST["parliament"]);

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
<?php include_once(__DIR__ . '/../../structure/footer.php'); ?>