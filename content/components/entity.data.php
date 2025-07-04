<div class="alert alert-info" role="alert">
	<div class="mb-1"><?= L::messageOpenData(); ?>: </div>
	<a href="<?= $config["dir"]["root"] ?>/api"><?= $config["dir"]["root"] ?>/api</a>
</div>
<div class="input-group">
	<span class="input-group-text">API URL</span>
	<input id="apiLink" class="form-control" type="text" value="<?= $apiResult["data"]["links"]["self"] ?>">
	<a href="<?= $apiResult["data"]["links"]["self"] ?>" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
</div>
<hr>
<div><b><?= L::dataTable(); ?></b></div>
<table id="dataTable" class="table">
	<thead>
		<tr>
			<th><?= L::dataTableKey(); ?></th>
			<th><?= L::dataTableValue(); ?></th>
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