<?php
//error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
error_reporting(0);
session_start();

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../modules/utilities/language.php');

// initialize language
$lang = LanguageManager::getInstance()->getCurrentLang();
$langJSONString = LanguageManager::getInstance()->getLangJSONString();

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