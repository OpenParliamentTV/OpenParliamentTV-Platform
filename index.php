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
require_once (__DIR__."/api/v1/api.php");

switch ($page) {
	/*********************************
	* RESOURCES / DETAIL PAGES 
	*********************************/
	case "agendaItem":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Agenda Item';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/agendaItem/page.php");
		$content = ob_get_clean();
	break;
	case "document":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Document';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/document/page.php");
		$content = ob_get_clean();
	break;
	case "electoralPeriod":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Electoral Period';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/electoralPeriod/page.php");
		$content = ob_get_clean();
	break;
	case "embed":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>"media", 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = $speechTitleShort;
		$pageType = 'default';
		require_once(__DIR__."/modules/search/functions.php");
		require_once(__DIR__."/modules/media/functions.php");
		require_once(__DIR__."/modules/media/include.media.php");
		ob_start();
		include_once("./content/pages/embed/page.php");
		$content = ob_get_clean();
	break;
	case "media":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = $speechTitleShort;
		$pageType = 'default';
		require_once(__DIR__."/modules/search/functions.php");
		require_once(__DIR__."/modules/media/functions.php");
		require_once(__DIR__."/modules/media/include.media.php");
		ob_start();
		include_once("./content/pages/media/page.php");
		$content = ob_get_clean();
	break;
	case "organisation":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Organisation';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/organisation/page.php");
		$content = ob_get_clean();
	break;
	case "person":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Person';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/person/page.php");
		$content = ob_get_clean();
	break;
	case "session":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Session';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/session/page.php");
		$content = ob_get_clean();
	break;
	case "term":
		$apiResult = apiV1([
			"action"=>"getItem", 
			"itemType"=>$page, 
			"id"=>$_REQUEST["id"]]
		);
		$pageTitle = 'Detail Term';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/term/page.php");
		$content = ob_get_clean();
	break;
	case "user":
		$pageTitle = 'Detail User';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/user/page.php");
		$content = ob_get_clean();
	break;
	/*********************************
	* OTHER PAGES 
	**********************************/
	case "about":
		$pageTitle = L::about;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/about/page.php");
		$content = ob_get_clean();
	break;
	case "documentation":
		$pageTitle = 'Documentation';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/documentation/page.php");
		$content = ob_get_clean();
	break;
	case "documentation-api":
		$pageTitle = 'API';
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => 'Documentation',
				'path' => '/documentation'
			],
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/documentation/api/page.php");
		$content = ob_get_clean();
	break;
	case "datapolicy":
		$pageTitle = L::dataPolicy;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/datapolicy/page.php");
		$content = ob_get_clean();
	break;
	case "imprint":
		$pageTitle = L::imprint;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/imprint/page.php");
		$content = ob_get_clean();
	break;
	case "login":
		$pageTitle = L::login;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/login/page.php");
		$content = ob_get_clean();
	break;
	case "logout":
		$pageTitle = L::logout;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/logout/page.php");
		$content = ob_get_clean();
	break;
	case "register":
		$pageTitle = L::registerNewAccount;
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/register/page.php");
		$content = ob_get_clean();
	break;
	case "registerConfirm":
		$pageTitle = L::registerNewAccount; //TODO i18n
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/registerconfirm/page.php");
		$content = ob_get_clean();
	break;
	case "passwordReset":
		$pageTitle = L::registerNewAccount; //TODO i18n
		$pageType = 'default';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/passwordreset/page.php");
		$content = ob_get_clean();
	break;
	/*********************************
	* USER-SPECIFIC / ADMINISTRATION
	**********************************/
	case "manage":
		$pageTitle = L::dashboard;
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/manage/page.php");
		$content = ob_get_clean();
	break;
	case "manage-config":
		$pageTitle = L::platformSettings;
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/manage/config/page.php");
		$content = ob_get_clean();
	break;
	case "manage-conflicts":
		ob_start();
		if (isset($_REQUEST["id"])) {
			$pageTitle = 'Manage Conflict';
			$pageType = 'admin';
			$pageBreadcrumbs = [
				[
					'label' => L::dashboard,
					'path' => '/manage'
				],
				[
					'label' => L::conflicts,
					'path' => '/manage/conflicts'
				],
				[
					'label' => '<span class="icon-pencil"></span>'
				]
			];
			include_once("./content/pages/manage/conflicts/conflict/page.php");
			$content = ob_get_clean();
		} else {
			$pageTitle = L::conflicts;
			$pageType = 'admin';
			$pageBreadcrumbs = [
				[
					'label' => L::dashboard,
					'path' => '/manage'
				],
				[
					'label' => $pageTitle
				]
			];
			include_once("./content/pages/manage/conflicts/page.php");
			$content = ob_get_clean();
		}
	break;
	case "manage-data":
		$pageTitle = L::data;
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => $pageTitle
			]
		];
		ob_start();
		include_once("./content/pages/manage/data/page.php");
		$content = ob_get_clean();
	break;
	case "manage-data-document":
		$pageTitle = 'Manage Document';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => L::data,
				'path' => '/manage/data'
			],
			[
				'label' => '<span class="icon-pencil"></span>'
			]
		];
		ob_start();
		include_once("./content/pages/manage/data/document/page.php");
		$content = ob_get_clean();
	break;
	case "manage-data-media":
		$pageTitle = 'Manage Media';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => L::data,
				'path' => '/manage/data'
			],
			[
				'label' => '<span class="icon-pencil"></span>'
			]
		];
		ob_start();
		if (isset($_REQUEST["id"]) && $_REQUEST["id"] == 'new') {
			$pageBreadcrumbs[count($pageBreadcrumbs) - 1]['label'] = '<span class="icon-plus"></span>';
			include_once("./content/pages/manage/data/media/new.php");
		} else {
			include_once("./content/pages/manage/data/media/page.php");
		}
		$content = ob_get_clean();
	break;
	case "manage-data-organisation":
		$pageTitle = 'Manage Organisation';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => L::data,
				'path' => '/manage/data'
			],
			[
				'label' => '<span class="icon-pencil"></span>'
			]
		];
		ob_start();
		include_once("./content/pages/manage/data/organisation/page.php");
		$content = ob_get_clean();
	break;
	case "manage-data-person":
		$pageTitle = 'Manage Person';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => L::data,
				'path' => '/manage/data'
			],
			[
				'label' => '<span class="icon-pencil"></span>'
			]
		];
		ob_start();
		include_once("./content/pages/manage/data/person/page.php");
		$content = ob_get_clean();
	break;
	case "manage-data-term":
		$pageTitle = 'Manage Term';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => L::data,
				'path' => '/manage/data'
			],
			[
				'label' => '<span class="icon-pencil"></span>'
			]
		];
		ob_start();
		include_once("./content/pages/manage/data/term/page.php");
		$content = ob_get_clean();
	break;
	case "manage-import":
		$pageTitle = L::data.'-Import';
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => $pageTitle,
			]
		];
		ob_start();
		include_once("./content/pages/manage/import/page.php");
		$content = ob_get_clean();
	break;
	case "manage-notifications":
		$pageTitle = L::notifications;
		$pageType = 'admin';
		$pageBreadcrumbs = [
			[
				'label' => L::dashboard,
				'path' => '/manage'
			],
			[
				'label' => $pageTitle,
			]
		];
		ob_start();
		include_once("./content/pages/manage/notifications/page.php");
		$content = ob_get_clean();
	break;
	case "manage-users":
		ob_start();
		if (isset($_REQUEST["id"])) {
			$pageTitle = 'Manage Detail User';
			$pageType = 'admin';
			$pageBreadcrumbs = [
				[
					'label' => L::dashboard,
					'path' => '/manage'
				],
				[
					'label' => L::users,
					'path' => '/manage'
				],
				[
					'label' => '<span class="icon-pencil"></span>',
				]
			];
			include_once("./content/pages/manage/users/user/page.php");
			$content = ob_get_clean();
		} else {
			$pageTitle = L::users;
			$pageType = 'admin';
			$pageBreadcrumbs = [
				[
					'label' => L::dashboard,
					'path' => '/manage'
				],
				[
					'label' => $pageTitle,
				]
			];
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
    <script type="text/javascript">
        const config = {
            "dir": {
                "root": "<?=$config["dir"]["root"]?>"
            }
        }

        //TODO: Move API to root $config and add it to JS Object
    </script>
</head>
<body class='<?= (($_SESSION["login"]) ? "login" : "") ?>'>
<?= $content ?>
</body>
</html>