<?php
//error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
error_reporting(0);
session_start();
require_once(__DIR__.'/../i18n.class.php');
$i18n = new i18n(__DIR__.'/../lang/lang_{LANGUAGE}.json', __DIR__.'/../langcache/', 'de');
$i18n->init();
$userLang = $i18n->getUserLangs();
$acceptLang = ['de', 'en'];
$langIntersection = array_values(array_intersect($userLang, $acceptLang));
$lang = (count($langIntersection) > 0) ? $langIntersection[0] : 'de';
// just used inside JS const
$langJSONString = file_get_contents(__DIR__.'/../lang/lang_'.$lang.'.json');

$color_scheme = isset($_COOKIE["color_scheme"]) ? $_COOKIE["color_scheme"] : false;
if ($color_scheme === false) $color_scheme = 'light';

$pageTitle = 'API '.L::documentation;
$pageDescription = L::messageOpenData;
$pageType = 'default';
$pageBreadcrumbs = [
	[
		'label' => $pageTitle
	]
];

require_once(__DIR__.'/../config.php');
require_once (__DIR__.'/v1/api.php');
?>
<!DOCTYPE html>
<html lang="<?= $lang; ?>" dir="ltr">
<head>
	<?php require_once(__DIR__.'/../content/head.php'); ?>
    <script type="text/javascript">
        const config = {
            "dir": {
                "root": "<?=$config["dir"]["root"]?>"
            }
        }

        const localizedLabels = <?= $langJSONString ?>;
    </script>
</head>
<body class='<?= $color_scheme."mode" ?> <?= (($_SESSION["login"]) ? "login" : "") ?>'>
	<div class="mainLoadingIndicator">
		<div class="workingSpinner" style="position: fixed; top: 50%;"></div>
	</div>
	<?php include_once(__DIR__ . '/../content/pages/api/page_'.$lang.'.php'); ?>
</body>
</html>