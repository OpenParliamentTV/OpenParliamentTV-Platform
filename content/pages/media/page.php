<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
    <main id="content">
        <?php include_once(__DIR__ . '/content.player.php'); ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/@frametrail/frametrail@v1.2.5/frametrail.min.js"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/quote-selector.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/quote-handler.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript">
        var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;
    </script>