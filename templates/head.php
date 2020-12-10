<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta charset="utf-8">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="Expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= $pageTitle ?></title>
<link rel="icon" type="image/png" href="favicon.png" />

<?php
if ($page != 'play') {
?>

	<link type="text/css" rel="stylesheet" href="client/css/bootstrap.min.css" media="all" />
    <link type="text/css" rel="stylesheet" href="client/css/Chart.min.css" media="all" />
    <link type="text/css" rel="stylesheet" href="client/css/frametrail-webfont.css" media="all" />
	<link type="text/css" rel="stylesheet" href="client/css/style.css" media="all" />

<?php
} else {
?>
	<link rel="stylesheet" type="text/css" href="client/FrameTrail.min.css">
    <link type="text/css" rel="stylesheet" href="client/css/bootstrap.min.css" media="all" />
    <link type="text/css" rel="stylesheet" href="client/css/Chart.min.css" media="all" />
	<link type="text/css" rel="stylesheet" href="client/css/style.css" media="all" />
	<link type="text/css" rel="stylesheet" href="client/css/player.css" media="all" />
<?php
}
?>

<!-- Matomo -->
<script type="text/javascript">
  var _paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//stats.openparliament.tv/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '1']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->