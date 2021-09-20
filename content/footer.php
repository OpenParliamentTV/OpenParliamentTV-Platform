<footer>
	<div class="row">
		<div class="col-12 col-lg-8 col-xl-8">
			<div style="float: left; margin-right: 15px; padding: 15px 10px 0px 10px">
				<div><?php echo L::fundedBy; ?>:</div>
				<div class="partnerLogos">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/miz-logo.png" style="height: 26px; margin-top: -4px;">
					<div class="clearfix"></div>
				</div>
			</div>
			<div style="float: left; margin-right: 15px; padding: 15px 10px 0px 10px">
				<div><?php echo L::supportedBy; ?>:</div>
				<div class="partnerLogos">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/abgeordnetenwatch-sw.png" style="height: 26px; margin-top: -4px;">
					<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/correctiv.svg" style="height: 40px; margin-top: -8px; margin-bottom: 2px; margin-left: 10px;">
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