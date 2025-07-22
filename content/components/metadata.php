<?php 
require_once(__DIR__ . '/../../modules/utilities/security.php');

if (!isset($page)) {
    $page = ''; // Initialize $page if not set
}
$description = strip_tags($pageDescription);
$claimShortClean = strip_tags(L::claimShort());
$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$urlWithoutParams = strtok($url, '?');
switch ($page) {
  case 'main':
    $title = L::brand().' | '.$claimShortClean;
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'website';
    $canonicalUrl = $urlWithoutParams;
    break;
  case 'search': 
    if (count($_REQUEST) < 2 || (!$_REQUEST["q"] && !$_REQUEST["personID"])) {
      $title = L::brand().' | '.$claimShortClean;
    } else {
      $title = strip_tags($pageTitle).' | '.L::brand();
    }
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'website';
    $canonicalUrl = $url;
    break;
  case 'media':
    $title = strip_tags($pageTitle).' | '.L::brand();
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
      $image = $config["dir"]["root"].'/content/client/images/share-image.php?id='.$_REQUEST['id'].'&t='.$_REQUEST['t'].'&f='.$_REQUEST['f'].'&c='.$_REQUEST['c'];
    } else {
      $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    }
    $ogType = 'video';
    $canonicalUrl = $urlWithoutParams;
    break;
  default:
    $title = strip_tags($pageTitle).' | '.L::brand();
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'article';
    $canonicalUrl = $urlWithoutParams;
    break;
}
?>
<title><?= h($title) ?></title>
<meta name="description" content="<?= hAttr($description) ?>">
<meta property="og:title" content="<?= hAttr($title) ?>" />
<meta property="og:url" content="<?= hAttr($canonicalUrl) ?>" />
<meta property="og:type" content="<?= hAttr($ogType) ?>" />
<meta property="og:image" content="<?= hAttr($image) ?>" />
<meta property="og:description" content="<?= hAttr($description) ?>" />

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@OpenParlTV">
<meta name="twitter:title" content="<?= hAttr($title) ?>">
<meta name="twitter:description" content="<?= hAttr($description) ?>">
<meta name="twitter:image" content="<?= hAttr($image) ?>">

<link rel="canonical" href="<?= hAttr($canonicalUrl) ?>">
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
<link rel='alternate' hreflang='<?= hAttr($thisLang["short"]) ?>' href='<?= hAttr($alternateUrl) ?>' />
<?php } ?>
