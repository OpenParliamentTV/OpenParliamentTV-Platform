<?php defined('OPTV') or die(); ?>
<?php /* Core client scripts shared by all full-chrome pages. Loaded from the layout
         (not the footer) so per-instance custom footers can override branding without
         dropping platform JS. */ ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/criteriaChips.js?v=<?= $config["version"] ?>"></script>
<?php if (!empty($_SESSION["login"]) && !empty($config["allow"]["notifications"])): ?>
<?php include(__DIR__ . '/alert-create-modal.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/alertManager.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/notificationBell.js?v=<?= $config["version"] ?>"></script>
<?php endif; ?>
