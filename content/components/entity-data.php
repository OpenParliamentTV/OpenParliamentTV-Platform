<?php defined('OPTV') or die(); ?>
<?php require_once(__DIR__ . '/../../modules/utilities/security.php'); ?>
<div class="alert alert-info" role="alert">
	<div class="mb-1"><?= L::messageOpenData(); ?>: </div>
	<a href="<?= $config["dir"]["root"] ?>/api"><?= $config["dir"]["root"] ?>/api</a>
</div>
<div class="input-group">
	<span class="input-group-text fixed-width">API URL</span>
	<input id="apiLink" class="form-control" type="text" value="<?= hAttr($apiResult["data"]["links"]["self"]) ?>">
	<a href="<?= hAttr($apiResult["data"]["links"]["self"]) ?>" class="btn btn-sm input-group-text"><span class="icon-right-open-big me-0"></span></a>
</div>
<?php // Optional export-format rows; pages opt in by setting $entityDataFormatLinks (label => URL) before including this component. ?>
<?php foreach (($entityDataFormatLinks ?? []) as $formatLabel => $formatURL) { ?>
<div class="input-group mt-2">
	<span class="input-group-text fixed-width"><?= h($formatLabel) ?></span>
	<input class="form-control" type="text" value="<?= hAttr($formatURL) ?>">
	<a href="<?= hAttr($formatURL) ?>" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big me-0"></span></a>
</div>
<?php } ?>
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
			echo '<tr><td>'.h($key).'</td><td>'.h($value).'</td></tr>';
		}
		?>
	</tbody>
</table>