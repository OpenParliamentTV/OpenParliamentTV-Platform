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
					<div class="clearfix"></div>
				</div>
			</div>
			<div style="float: left; padding: 15px 10px 0px 10px">
				<div><?php echo L::mediaPartners; ?>:</div>
				<div class="partnerLogos">
					<!--<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/zeit-online.png" style="height: 21px; margin-top: 0px;">-->
					<div class="clearfix"></div>
				</div>
			</div>
		</div>
		<!--
		<hr class="d-block d-lg-none" style="width: 100%">
		<div class="col-12 col-lg-4 col-xl-4">
			<div class="d-flex align-content-end">
				<img class="ml-auto align-self-center" src="<?= $config["dir"]["root"] ?>/content/client/images/logos/okfde.svg" style="height: 100px; margin-top: 0px;">
				<div class="align-self-center" style="font-size: 11px; max-width: 260px;">OpenParliament.TV ist ein gemeinnütziges Projekt des Open Knowledge Foundation Deutschland e.V.</div>
			</div>
		</div>
		-->
	</div>
	<hr>
	<div class="row">
		<div class="col-12" style="font-size: 11px; text-align: center;"><?php echo L::dataPolicyHintPart1; ?> <a href="https://matomo.org" target="_blank">Matomo</a><?php echo L::dataPolicyHintPart2; ?> <br><?php echo L::dataPolicyHintPart3; ?><a href="https://stats.openparliament.tv/index.php?module=CoreAdminHome&amp;action=optOut&amp;language=de"><?php echo L::dataPolicyHintPart4; ?></a>. <?php echo L::dataPolicyHintPart5; ?> <a href="<?= $config["dir"]["root"] ?>/datapolicy"><?php echo L::dataPolicyHintPart6; ?></a>.</div>
	</div>
</footer>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js"></script>