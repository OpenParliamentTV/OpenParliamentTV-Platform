<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
require_once 'i18n.class.php';
$i18n = new i18n('lang/lang_{LANGUAGE}.json', 'langcache/', 'de');
//$i18n->setForcedLang('en');
$i18n->init();
require_once("./server/functions.php");

$pageTitle = L::brand.' - Deutscher Bundestag';
$page = (isset($_REQUEST["a"]) && strlen($_REQUEST["a"]) > 2) ? $_REQUEST["a"] : "main";

switch ($_REQUEST["a"]) {
	case "search":
		require_once("./server/include.search.php");
		ob_start();
		include_once("./templates/pages/search/page.php");
		$content = ob_get_clean();
	break;
	case "play":
		require_once("./server/include.play.php");
		ob_start();
		include_once("./templates/pages/player/page.php");
		$content = ob_get_clean();
		$pageTitle = $speechTitleShort.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "about":
		ob_start();
		include_once("./templates/pages/about/page.php");
		$content = ob_get_clean();
		$pageTitle = L::about.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "datapolicy":
		ob_start();
		include_once("./templates/pages/datapolicy/page.php");
		$content = ob_get_clean();
		$pageTitle = L::dataPolicy.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "imprint":
		ob_start();
		include_once("./templates/pages/imprint/page.php");
		$content = ob_get_clean();
		$pageTitle = L::imprint.' | '.L::brand.' - Deutscher Bundestag';
	break;
	case "login":
		ob_start();
		include_once("./templates/pages/login/page.php");
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
		include_once("./templates/pages/admin/page.php");
		$content = ob_get_clean();
		$pageTitle = 'Administration | '.L::brand.' - Deutscher Bundestag';
	break;
	default;
		require_once("./server/include.search.php");
		ob_start();
		include_once("./templates/pages/search/page.php");
		$content = ob_get_clean();
	break;
}
ob_start();
include_once("./templates/structure.php");
echo ob_get_clean();
?>