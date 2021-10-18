<?php 
$description = strip_tags($pageDescription);
$claimShortClean = strip_tags(L::claimShort);
$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
switch ($page) {
  case 'main':
    $title = L::brand.' | '.$claimShortClean;
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'website';
    break;
  case 'search': 
    if (count($_REQUEST) < 2 || (!$_REQUEST["q"] && !$_REQUEST["personID"])) {
      $title = L::brand.' | '.$claimShortClean;
    } else {
      $title = strip_tags($pageTitle).' | '.L::brand;
    }
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    break;
  case 'media':
    $title = strip_tags($pageTitle).' | '.L::brand;
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
      $image = $config["dir"]["root"].'/content/client/images/share-image.php?id='.$_REQUEST['id'].'&t='.$_REQUEST['t'].'&f='.$_REQUEST['f'].'&c='.$_REQUEST['c'];
    } else {
      $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    }
    $ogType = 'video';
    break;
  default:
    $title = strip_tags($pageTitle).' | '.L::brand;
    $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    $ogType = 'article';
    break;
}
?>
<title><?= $title ?></title>
<meta name="description" content="<?= $description ?>">
<meta property="og:title" content="<?= $title ?>" />
<meta property="og:url" content="<?= $url ?>" />
<meta property="og:type" content="<?= $ogType ?>" />
<meta property="og:image" content="<?= $image ?>" />
<meta property="og:description" content="<?= $description ?>" />

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@OpenParlTV">
<meta name="twitter:title" content="<?= $title ?>">
<meta name="twitter:description" content="<?= $description ?>">
<meta name="twitter:image" content="<?= $image ?>">