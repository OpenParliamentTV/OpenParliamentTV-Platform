<footer>
	<div class="row">
        <div class="col-12" style="font-size: 12px; text-align: center;">This website is powered by <a href="https://openparliament.tv/" target="_blank">OpenParliament TV</a></div>
	</div>
</footer>

<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js?v=<?= $config["version"] ?>"></script>
<?php if (!empty($_SESSION["login"]) && !empty($config["allow"]["notifications"])): ?>
<?php include(__DIR__ . '/components/alert-create-modal.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/alertManager.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/notificationBell.js?v=<?= $config["version"] ?>"></script>
<?php endif; ?>
