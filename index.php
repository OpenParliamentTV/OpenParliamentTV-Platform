<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();
require_once 'i18n.class.php';
$i18n = new i18n('lang/lang_{LANGUAGE}.json', 'langcache/', 'de');
//$i18n->setForcedLang('en');
$i18n->init();
//require_once("./server/functions.php");

$pageTitle = L::brand.' - Deutscher Bundestag';
$page = (isset($_REQUEST["a"]) && strlen($_REQUEST["a"]) > 2) ? $_REQUEST["a"] : "main";

switch ($_REQUEST["a"]) {
	case "play":
		require_once(__DIR__."/modules/search/functions.php");
		require_once(__DIR__."/modules/player/functions.php");
		require_once(__DIR__."/modules/player/include.player.php");
		ob_start();
		include_once("./modules/player/page.php");
		$content = ob_get_clean();
		$pageTitle = $speechTitleShort.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "about":
		ob_start();
		include_once("./modules/about/page.php");
		$content = ob_get_clean();
		$pageTitle = L::about.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "datapolicy":
		ob_start();
		include_once("./modules/datapolicy/page.php");
		$content = ob_get_clean();
		$pageTitle = L::dataPolicy.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "imprint":
		ob_start();
		include_once("./modules/imprint/page.php");
		$content = ob_get_clean();
		$pageTitle = L::imprint.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "login":
		ob_start();
		include_once("./modules/login/page.php");
		$content = ob_get_clean();
		$pageTitle = L::login.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "register":
		ob_start();
		include_once("./templates/pages/register/page.php");
		$content = ob_get_clean();
		$pageTitle = L::registerNewAccount.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "admin":
		ob_start();
		include_once("./modules/admin/page.php");
		$content = ob_get_clean();
		$pageTitle = 'Administration | '.L::brand.' - Deutscher Bundestag';
	break;
	case "search":
	default:
		require_once("./modules/search/include.search.php");
		ob_start();
		include_once("./modules/search/page.php");
		$content = ob_get_clean();
	break;
}
?>
<?php
$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
$acceptLang = ['de', 'en'];
$lang = in_array($lang, $acceptLang) ? $lang : 'de';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="ltr">
<head>
	<?php require_once('structure/head.php'); ?>
</head>
<body<?= (($_SESSION["login"]) ? " class='login'" : "") ?>>
<?= $content ?>
</body>
</html>