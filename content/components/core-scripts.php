<?php defined('OPTV') or die(); ?>
<?php /* Core client scripts shared by all full-chrome pages. Loaded from the layout
         (not the footer) so per-instance custom footers can override branding without
         dropping platform JS. */ ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/generic.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/media-item.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/criteria-chips.js?v=<?= $config["version"] ?>"></script>
<?php if (!empty($_SESSION["login"]) && !empty($config["allow"]["notifications"])): ?>
<?php include(__DIR__ . '/alert-create-modal.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/alert-manager.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/notification-bell.js?v=<?= $config["version"] ?>"></script>
<?php endif; ?>
<?php if (!empty($_SESSION["login"]) && !empty($config["allow"]["playlists"])): ?>
<?php include(__DIR__ . '/playlist-add-modal.php'); ?>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/playlist-manager.js?v=<?= $config["version"] ?>"></script>
<?php endif; ?>
