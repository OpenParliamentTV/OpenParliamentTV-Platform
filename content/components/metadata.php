<?php 
$title = L::brand.' | '.strip_tags($pageTitle);
$description = 'Description Lorem Ipsum';
$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
switch ($page) {
  case 'main':
    $ogType = 'website';
    break;
  case 'media':
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
      $image = $config["dir"]["root"].'/content/client/images/share-image.php?id='.$_REQUEST['id'].'&t='.$_REQUEST['t'].'&f='.$_REQUEST['f'].'&c='.$_REQUEST['c'];
    } else {
      $image = $config["dir"]["root"].'/content/client/images/thumbnail.png';
    }
    $ogType = 'video';
    break;
  
  default:
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