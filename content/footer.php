<footer>
	<div class="row">
		<div class="col-12">
			<div style="float: left; margin-right: 15px; padding: 15px 10px 0px 10px">
					<div><?php echo L::fundedBy; ?>:</div>
					<div class="partnerLogos">
						<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/miz-logo.png" style="height: 26px; margin-top: -4px;margin-right: 28px;">
						<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/dsee.svg" style="height: 43px; margin-top: -13px;margin-right: 34px;">
						<img style="height: 57px; margin-top: -19px;" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/bmbf-de.svg">
						
						<div class="clearfix"></div>
					</div>
				</div>
			<div style="float: left; margin-right: 15px; padding: 15px 10px 0px 10px">
				<div><?php echo L::supportedBy; ?>:</div>
				<div class="partnerLogos">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/abgeordnetenwatch-sw.png" style="height: 26px; margin-top: -4px;">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/correctiv.svg" style="height: 38px; margin-top: -8px; margin-bottom: 15px; margin-left: 10px; filter: opacity(0.8);">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/bbc-news-labs.svg" style="height: 33px; margin-top: -8px;  margin-left: 10px; filter: opacity(0.7);">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/cct.png" style="height: 37px; margin-top: -9px;filter: grayscale(1) invert(0) brightness(0) opacity(0.8);">
					<div class="clearfix"></div>
				</div>
			</div>
			<!--
			<div style="float: left; padding: 15px 10px 0px 10px">
				<div><?php echo L::mediaPartners; ?>:</div>
				<div class="partnerLogos">
					<div class="clearfix"></div>
				</div>
			</div>
			-->
		</div>
	</div>
	<hr>
	<div class="row">
		<div class="col-12" style="font-size: 12px; text-align: center;"><?php echo L::dataPolicyHintPart1; ?> <a href="https://matomo.org" target="_blank">Matomo</a><?php echo L::dataPolicyHintPart2; ?> <br><?php echo L::dataPolicyHintPart3; ?><a href="https://stats.openparliament.tv/index.php?module=CoreAdminHome&amp;action=optOut&amp;language=<?php echo (isset($lang)) ? $lang : 'de'; ?>"><?php echo L::dataPolicyHintPart4; ?></a>. <?php echo L::dataPolicyHintPart5; ?> <a href="<?= $config["dir"]["root"] ?>/datapolicy"><?php echo L::dataPolicyHintPart6; ?></a>.</div>
	</div>
</footer>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js"></script>