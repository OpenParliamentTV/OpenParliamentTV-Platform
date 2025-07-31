<?php
require_once(__DIR__ . '/../../modules/utilities/security.php');
require_once(__DIR__ . '/../../modules/i18n/language.php');
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../modules/search/include.search.php');
?>

<div class="row justify-content-center">
	<div id="introHint" class="col-11 col-md-10 col-lg-8 col-xl-6">
		<img src="content/client/images/arrow.png" class="bigArrow d-none d-md-inline">
		<div class="introHintText mt-0 mt-md-2">
			<h1 class="mb-3 introClaim"><?= L::claimShort(); ?></h1>
			<div><b><span class="countup"><?= $indexCount ?></span> <?= L::speeches(); ?></b> <?= L::inTheGermanBundestag(); ?></div>
			<ul>
				<li><?= L::featureBullet1(); ?></li>
				<li><?= L::featureBullet2(); ?></li>
				<li><?= L::fromTime(); ?> 2013 <?= L::until(); ?> <?= L::today(); ?></li>
				<li><?= L::moreParliamentsSoon(); ?> <br class="d-inline d-sm-none"/>(<a href='https://openparliament.tv'> <u><?= L::moreInfo(); ?></u> </a>)</li>
			</ul>
			<!--
			<div class="text-center alert mt-3 px-1 py-0 alert-info" style="font-size: 14px;"><span class="icon-attention me-1"></span><a href="<?= $config["dir"]["root"] ?>/announcements" style="color: inherit; text-decoration: underline;"><?= L::messageAnnouncementCurrentState(); ?></a></div>
			-->
		</div>
	</div>
</div>
<div class="row justify-content-center">
	<div class="examplesContainer mt-3 mb-5 col-11 col-md-10 col-lg-8 col-xl-6"><?= L::examples(); ?>: <br>
		<a href='<?= $config["dir"]["root"] ?>/search?q=Mietpreisbremse'>Mietpreisbremse</a><a href='<?= $config["dir"]["root"] ?>/search?q=Rente'>Rente</a><a href='<?= $config["dir"]["root"] ?>/search?q=Brexit'>Brexit</a><a href='<?= $config["dir"]["root"] ?>/search?q=Pariser%20Abkommen'>Pariser Abkommen</a><a href='<?= $config["dir"]["root"] ?>/search?q=NetzDG'>NetzDG</a><a href='<?= $config["dir"]["root"] ?>/search?q=BAMF'>BAMF</a><a href='<?= $config["dir"]["root"] ?>/search?q=Klimawandel'>Klimawandel</a><a href='<?= $config["dir"]["root"] ?>/search?q=Lobbyregister'>Lobbyregister</a><a href='<?= $config["dir"]["root"] ?>/search?q=Pflegeversicherung'>Pflegeversicherung</a><a href='<?= $config["dir"]["root"] ?>/search?q=Datenschutz-Grundverordnung'>Datenschutz-Grundverordnung</a><a href='<?= $config["dir"]["root"] ?>/search?q=Katze%20Sack'>Katze im Sack</a><a href='<?= $config["dir"]["root"] ?>/search?q=Hase%20Igel'>Hase und Igel</a><a href='<?= $config["dir"]["root"] ?>/search?q=%22das%20ist%20die%20Wahrheit%22'>"das ist die Wahrheit"</a><a href='<?= $config["dir"]["root"] ?>/search?q=Tropfen%20hei%C3%9Fen%20Stein'>Tropfen auf den heißen Stein</a>
	</div>
</div>