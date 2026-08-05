<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
    <main id="content">
        <?php include_once(__DIR__ . '/components/player.php'); ?>
    </main>
    <?php
    // Only when the speech was reached through a result set — the same condition
    // that decides whether the header shows queue controls at all. A bare
    // /media/{id} has no meaningful queue, just "every speech ever".
    //
    // Deliberately outside #content: updateContents() replaces that element's
    // entire inner HTML on every result switch, and FrameTrail uses it as its
    // fullscreenTarget. Out here the panel survives navigation (it only
    // re-highlights) and stays out of the way in fullscreen.
    if ($isResult): ?>
    <section id="queuePanel" class="queuePanel collapsed" hidden></section>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/@frametrail/frametrail@v1.2.7/frametrail.min.js"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/queue-panel.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/quote-selector.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/media/client/quote-handler.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript">
        var autoplayResults = <?php if ($autoplayResults) { echo 'true'; } else { echo 'false'; } ?>;
    </script>