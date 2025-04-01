<div class="alert alert-info" role="alert">
	<div class="mb-1"><?php echo L::messageOpenData; ?>: </div>
	<a href="<?= $config["dir"]["root"] ?>/api"><?= $config["dir"]["root"] ?>/api</a>
</div>
<div class="input-group">
	<span class="input-group-text">API URL</span>
	<input id="apiLink" class="form-control" type="text" value="<?= $apiResult["data"]["links"]["self"] ?>">
	<a href="<?= $apiResult["data"]["links"]["self"] ?>" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?php echo L::showResult; ?></span></a>
</div>
<hr>
<div><b><?php echo L::dataTable; ?></b></div>
<table id="dataTable" class="table">
	<thead>
		<tr>
			<th><?php echo L::dataTableKey; ?></th>
			<th><?php echo L::dataTableValue; ?></th>
		</tr>
	</thead>
	<tbody>
		<?php 
		foreach ($flatDataArray as $key => $value) {
			echo '<tr><td>'.$key.'</td><td>'.$value.'</td></tr>';
		}
		?>
	</tbody>
</table>