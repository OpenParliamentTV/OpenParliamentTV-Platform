<?php 
if (!isset($page)) {
    $page = ''; // Initialize $page if not set
}
$description = strip_tags($pageDescription);
$claimShortClean = strip_tags(L::claimShort);
$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$urlWithoutParams = strtok($url, '?');
switch ($page) {
  case 'main':
    $title = L::brand.' | '.$claimShortClean;
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'website';
    $canonicalUrl = $urlWithoutParams;
    break;
  case 'search': 
    if (count($_REQUEST) < 2 || (!$_REQUEST["q"] && !$_REQUEST["personID"])) {
      $title = L::brand.' | '.$claimShortClean;
    } else {
      $title = strip_tags($pageTitle).' | '.L::brand;
    }
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'website';
    $canonicalUrl = $url;
    break;
  case 'media':
    $title = strip_tags($pageTitle).' | '.L::brand;
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
      $image = $config["dir"]["root"].'/content/client/images/share-image.php?id='.$_REQUEST['id'].'&t='.$_REQUEST['t'].'&f='.$_REQUEST['f'].'&c='.$_REQUEST['c'];
    } else {
      $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    }
    $ogType = 'video';
    $canonicalUrl = $urlWithoutParams;
    break;
  default:
    $title = strip_tags($pageTitle).' | '.L::brand;
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'article';
    $canonicalUrl = $urlWithoutParams;
    break;
}
?>
<title><?= $title ?></title>
<meta name="description" content="<?= $description ?>">
<meta property="og:title" content="<?= $title ?>" />
<meta property="og:url" content="<?= $canonicalUrl ?>" />
<meta property="og:type" content="<?= $ogType ?>" />
<meta property="og:image" content="<?= $image ?>" />
<meta property="og:description" content="<?= $description ?>" />

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@OpenParlTV">
<meta name="twitter:title" content="<?= $title ?>">
<meta name="twitter:description" content="<?= $description ?>">
<meta name="twitter:image" content="<?= $image ?>">

<link rel="canonical" href="<?= $canonicalUrl ?>">
<?php
global $acceptLang;
$queryString = parse_url($url, PHP_URL_QUERY);
$params = [];
if ($queryString) {
  parse_str($queryString, $params);
}
unset($params['lang']); // Remove existing lang parameter if present

foreach ($acceptLang as $thisLang) {
  $params['lang'] = $thisLang["short"];
  $alternateUrl = $urlWithoutParams . '?' . http_build_query($params);
  ?>
<link rel='alternate' hreflang='<?= $thisLang["short"] ?>' href='<?= $alternateUrl ?>' />
<?php } ?>
