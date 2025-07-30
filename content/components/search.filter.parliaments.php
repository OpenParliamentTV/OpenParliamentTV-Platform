<?php
session_start();
require_once(__DIR__."/../../../config.php");
require_once(__DIR__ . '/../../../modules/utilities/security.php');
applySecurityHeaders();


include_once(__DIR__ . '/../../../modules/utilities/auth.php');
require_once(__DIR__."/../../../modules/i18n/language.php");

$auth = auth($_SESSION["userdata"]["id"], "requestPage", "results");

if ($auth["meta"]["requestStatus"] != "success") {
    echo "Not authorized";
} else {

	// ELSE FINISHES AT END OF FILE
?>
<div class="row no-gutters">
	<div id="selectParliament" class="col-6 col-sm-auto">
		<select class="form-control form-control-sm" name="parliament">
			<option value="all" <?php if (!isset($_REQUEST['parliament'])) { echo 'selected'; } ?>><?= L::showAll(); ?> <?= L::parliaments(); ?></option>
			<?php
			foreach($config["parliament"] as $k=>$v) {
				$selectedString = '';
				if (isset($_REQUEST['parliament']) && hAttr($_REQUEST['parliament']) == $k) {
					$selectedString = ' selected';
				}
				
				//TODO: Remove once all parliaments should be listed
				if ($k == 'DE') {
					echo '<option value="'.hAttr($k).'"'.$selectedString.'>'.h($v["label"]).'</option>';
				}

				//echo '<option value="'.hAttr($k).'"'.$selectedString.'>'.h($v["label"]).'</option>';
			}
			?>
		</select>
	</div>
	<?php 
	if (isset($_REQUEST['parliament'])) {
	?>
	<div id="selectElectoralPeriod" class="col-2 col-sm-auto">
		<select class="form-control form-control-sm" name="electoralPeriod">
			<option value="all" <?php if (!isset($_REQUEST['electoralPeriod'])) { echo 'selected'; } ?>><?= L::showAll(); ?> <?= L::electoralPeriods(); ?></option>
			<?php
			$selectedString = '';
			if (isset($_REQUEST['electoralPeriod']) && hAttr($_REQUEST['electoralPeriod']) == '19') {
				$selectedString = ' selected';
			}
			echo '<option value="19"'.$selectedString.'>19. '. L::electoralPeriod() .'</option>';
			?>
		</select>
	</div>
	<?php 
	}
	if (isset($_REQUEST['parliament']) && isset($_REQUEST['electoralPeriod'])) {
	?>
	<div id="selectSession" class="col-4 col-sm-auto">
		<select class="form-control form-control-sm" name="sessionNumber">
			<option value="all" <?php if (!isset($_REQUEST['sessionNumber'])) { echo 'selected'; } ?>><?= L::showAll(); ?> <?= L::sessions(); ?></option>
			<?php
			for ($i=1; $i <= 239; $i++) { 
			 	$selectedString = '';
				if (isset($_REQUEST['sessionNumber']) && hAttr($_REQUEST['sessionNumber']) == $i) {
					$selectedString = ' selected';
				}
			 	echo '<option value="'.$i.'"'.$selectedString.'>'.$i.'. '. L::session() .'</option>';
			} 
			?>
		</select>
	</div>
	<?php 
	}
	?>
</div>
<?php 
}
?>