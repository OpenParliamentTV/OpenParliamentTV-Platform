<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta charset="utf-8">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="Expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

<?php include_once(__DIR__ . '/components/metadata.php'); ?>

<!-- Start Icons -->
<link rel="apple-touch-icon" sizes="180x180" href="<?= $config["dir"]["root"] ?>/content/client/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $config["dir"]["root"] ?>/content/client/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $config["dir"]["root"] ?>/content/client/images/favicon-16x16.png">
<!--<link rel="manifest" href="<?= $config["dir"]["root"] ?>/content/client/images/site.webmanifest">-->
<link rel="mask-icon" href="<?= $config["dir"]["root"] ?>/content/client/images/safari-pinned-tab.svg" color="#5bbad5">
<link rel="shortcut icon" href="<?= $config["dir"]["root"] ?>/content/client/images/favicon.ico">
<meta name="msapplication-TileColor" content="#00aba9">
<meta name="msapplication-config" content="<?= $config["dir"]["root"] ?>/content/client/images/browserconfig.xml">
<meta name="theme-color" content="#ffffff">
<!-- End Icons -->

<?php
if ($page != 'media') {
?>

	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/bootstrap.min.css" media="all" />
    <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/Chart.min.css" media="all" />
    <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/frametrail-webfont.css" media="all" />
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/style.css?ts=<?= time() ?>" media="all" />

<?php
} else {
?>
	<link rel="stylesheet" type="text/css" href="<?= $config["dir"]["root"] ?>/content/client/FrameTrail.min.css">
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/bootstrap.min.css" media="all" />
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/Chart.min.css" media="all" />
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/style.css?ts=<?= time() ?>" media="all" />
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/media/client/player.css?ts=<?= time() ?>" media="all" />
  <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/media/client/shareQuote.css?ts=<?= time() ?>" media="all" />
<?php
}
?>

<?php
if ($pageType == 'admin' || $pageType == 'entity') {
?>
	<link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/bootstrap-table.min.css" />
<?php
}
?>

<script type="text/javascript">
  // Set vh var based on actual height (mobile browsers)
  let vh = window.innerHeight * 0.01;
  document.documentElement.style.setProperty('--vh', `${vh}px`);
</script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/jquery-3.4.1.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/jquery.form.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/Chart.min.js"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/bootstrap.bundle.min.js"></script>

<?php
if ($pageType == 'admin' || $pageType == 'entity') {
?>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/bootstrap-table.min.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/bootstrap-table-de-DE.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/bootstrap-table-export.min.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/tableExport.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/bootstrap-table-multi-toggle.min.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/shim.min.js"></script>
  <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/xlsx.full.min.js"></script>
<?php
}
?>

<?php
if (isset($personDataFromRequest)) {
  // If set personDataFromRequest contains labels for personID values so we can display names
?>
  <script type="text/javascript">
    var personDataFromRequest = JSON.parse('<?php echo json_encode($personDataFromRequest); ?>');
  </script>
<?php
}
?>

<!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//stats.openparliament.tv/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '1']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->