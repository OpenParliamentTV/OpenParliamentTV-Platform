<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();
require_once('i18n.class.php');
$i18n = new i18n('lang/lang_{LANGUAGE}.json', 'langcache/', 'de');
$i18n->setForcedLang('de');
$i18n->init();
//require_once("./server/functions.php");

$pageTitle = L::brand;
$page = (isset($_REQUEST["a"]) && strlen($_REQUEST["a"]) > 2) ? $_REQUEST["a"] : "main";

require_once('config.php');

switch ($page) {
	/*********************************
	* RESOURCES / DETAIL PAGES 
	*********************************/
	case "document":
		$pageTitle = 'Detail Document';
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/detail-document/page.php");
		$content = ob_get_clean();
	break;
	case "embed":
		$pageTitle = $speechTitleShort;
		$pageType = 'default';
		require_once(__DIR__."/modules/search/functions.php");
		require_once(__DIR__."/modules/media/functions.php");
		require_once(__DIR__."/modules/media/include.media.php");
		ob_start();
		include_once("./content/pages/detail-embed/page.php");
		$content = ob_get_clean();
	break;
	case "media":
		$pageTitle = $speechTitleShort;
		$pageType = 'default';
		require_once(__DIR__."/modules/search/functions.php");
		require_once(__DIR__."/modules/media/functions.php");
		require_once(__DIR__."/modules/media/include.media.php");
		ob_start();
		include_once("./content/pages/detail-media/page.php");
		$content = ob_get_clean();
	break;
	case "person":
		$pageTitle = 'Detail Person';
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/detail-person/page.php");
		$content = ob_get_clean();
	break;
	case "user":
		$pageTitle = 'Detail User';
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/detail-user/page.php");
		$content = ob_get_clean();
	break;
	/*********************************
	* OTHER PAGES 
	**********************************/
	case "about":
		$pageTitle = L::about;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/about/page.php");
		$content = ob_get_clean();
	break;
	case "api":
		$pageTitle = 'API';
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/api/page.php");
		$content = ob_get_clean();
	break;
	case "datapolicy":
		$pageTitle = L::dataPolicy;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/datapolicy/page.php");
		$content = ob_get_clean();
	break;
	case "imprint":
		$pageTitle = L::imprint;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/imprint/page.php");
		$content = ob_get_clean();
	break;
	case "login":
		$pageTitle = L::login;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/login/page.php");
		$content = ob_get_clean();
	break;
	case "logout":
		$pageTitle = L::logout;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/logout/page.php");
		$content = ob_get_clean();
	break;
	case "register":
		$pageTitle = L::registerNewAccount;
		$pageType = 'default';
		ob_start();
		include_once("./content/pages/register/page.php");
		$content = ob_get_clean();
	break;
	/*********************************
	* USER-SPECIFIC / ADMINISTRATION
	**********************************/
	case "manage":
		$pageTitle = L::dashboard;
		$pageType = 'admin';
		ob_start();
		include_once("./content/pages/manage/page.php");
		$content = ob_get_clean();
	break;
	case "manage-config":
		$pageTitle = 'Configuration';
		$pageType = 'admin';
		ob_start();
		include_once("./content/pages/manage/config/page.php");
		$content = ob_get_clean();
	break;
	case "manage-conflicts":
		ob_start();
		if (isset($_REQUEST["id"])) {
			$pageTitle = 'Manage Conflict';
			$pageType = 'admin';
			include_once("./content/pages/manage/detail-conflict/page.php");
			$content = ob_get_clean();
		} else {
			$pageTitle = L::conflicts;
			$pageType = 'admin';
			include_once("./content/pages/manage/conflicts/page.php");
			$content = ob_get_clean();
		}
	break;
	case "manage-data":
		$pageTitle = L::data;
		$pageType = 'admin';
		ob_start();
		include_once("./content/pages/manage/data/page.php");
		$content = ob_get_clean();
	break;
	case "manage-data-document":
		$pageTitle = 'Manage Document';
		$pageType = 'admin';
		ob_start();
		if (isset($_REQUEST["id"]) && $_REQUEST["id"] == 'new') {
			include_once("./content/pages/manage/data/document/new.php");
		} else {
			include_once("./content/pages/manage/data/document/page.php");
		}
		$content = ob_get_clean();
	break;
	case "manage-data-media":
		$pageTitle = 'Manage Media';
		$pageType = 'admin';
		ob_start();
		if (isset($_REQUEST["id"]) && $_REQUEST["id"] == 'new') {
			include_once("./content/pages/manage/data/media/new.php");
		} else {
			include_once("./content/pages/manage/data/media/page.php");
		}
		$content = ob_get_clean();
	break;
	case "manage-data-person":
		$pageTitle = 'Manage Person';
		$pageType = 'admin';
		ob_start();
		if (isset($_REQUEST["id"]) && $_REQUEST["id"] == 'new') {
			include_once("./content/pages/manage/data/person/new.php");
		} else {
			include_once("./content/pages/manage/data/person/page.php");
		}
		$content = ob_get_clean();
	break;
	case "manage-import":
		$pageTitle = 'Data Import';
		$pageType = 'admin';
		ob_start();
		include_once("./content/pages/manage/import/page.php");
		$content = ob_get_clean();
	break;
	case "manage-notifications":
		$pageTitle = L::notifications;
		$pageType = 'admin';
		ob_start();
		include_once("./content/pages/manage/notifications/page.php");
		$content = ob_get_clean();
	break;
	case "manage-users":
		ob_start();
		if (isset($_REQUEST["id"])) {
			$pageTitle = 'Manage Detail User';
			$pageType = 'admin';
			include_once("./content/pages/manage/users/user/page.php");
			$content = ob_get_clean();
		} else {
			$pageTitle = L::users;
			$pageType = 'admin';
			include_once("./content/pages/manage/users/page.php");
			$content = ob_get_clean();
		}
	break;
	/*
	case "admin":
		$pageTitle = 'Administration';
		$pageType = 'admin';
		ob_start();
		include_once("./modules/admin/page.php");
		$content = ob_get_clean();
	break;
	case "import":
		$pageTitle = 'Administration'.' - Import json';
		$pageType = 'admin';
		ob_start();
		include_once("./modules/importtasks/page.php");
		$content = ob_get_clean();
	break;
	*/
	case "search":
		$pageTitle = 'Search';
		$pageType = 'default';
		require_once("./modules/search/include.search.php");
		ob_start();
		include_once("./content/pages/search/page.php");
		$content = ob_get_clean();
	break;
	case "main":
		$pageTitle = L::brand;
		$pageType = 'default';
		require_once("./modules/search/include.search.php");
		ob_start();
		include_once("./content/pages/search/page.php");
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
	<?php require_once('content/head.php'); ?>
</head>
<body class='darkmode <?= (($_SESSION["login"]) ? "login" : "") ?>'>
<?= $content ?>
</body>
</html>