<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();
require_once('i18n.class.php');
$i18n = new i18n('lang/lang_{LANGUAGE}.json', 'langcache/', 'de');
$i18n->setForcedLang('de');
$i18n->init();

$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
$acceptLang = ['de', 'en'];
$lang = in_array($lang, $acceptLang) ? $lang : 'de';

$color_scheme = isset($_COOKIE["color_scheme"]) ? $_COOKIE["color_scheme"] : false;
if ($color_scheme === false) $color_scheme = 'light';

$useragent=$_SERVER['HTTP_USER_AGENT'];
$isMobile = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)));


$paramStr = "";
$allowedParams = array_intersect_key($_REQUEST,array_flip(array("q","name","person","party","partyID","faction","factionID","organisation","organisationID","electoralPeriod","dateFrom","dateTo","gender","degree","aw_uuid","speakerID","sessionNumber","documentID","playresults", "page", "sort")));
$paramCount = 1;
foreach ($allowedParams as $k=>$v) {
    if ($paramCount == 1) {
		$paramPrefix = "?";
	} else {
		$paramPrefix = "&";
	}
    if (is_array($v)) {
        foreach ($v as $i) {
            $paramStr .= $paramPrefix.$k."[]=".$i;
        }
    } else {
        $paramStr .= $paramPrefix.$k."=".$_REQUEST[$k];
    }
    $paramCount++;
}

$isResult = (strlen($paramStr) > 2) ? true : false;

$pageTitle = '';
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
		$pageTitle = '<span class="icon-list-numbered"></span>'.$apiResult["data"]["attributes"]["title"];
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-doc-text"></span>'.$apiResult["data"]["attributes"]["label"];
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-check"></span>'.$apiResult["data"]["attributes"]["parliamentLabel"].' – '.$apiResult["data"]["attributes"]["number"].'. Electoral Period';
		$pageType = 'entity';
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
		$pageTitle = '';
		$pageType = 'entity';
		ob_start();
		include_once("./content/pages/embed/page.php");
		$content = ob_get_clean();
	break;
	case "media":
		// API Result is included via include.media.php
		$pageTitle = '';
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-bank"></span>'.$apiResult["data"]["attributes"]["labelAlternative"];
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-torso"></span>'.$apiResult["data"]["attributes"]["label"];
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-group"></span>'.$apiResult["data"]["attributes"]["parliamentLabel"].' – Session '.$apiResult["data"]["attributes"]["number"];
		$pageType = 'entity';
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
		$pageTitle = '<span class="icon-tag-1"></span>'.$apiResult["data"]["attributes"]["label"];
		$pageType = 'entity';
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
		$pageType = 'entity';
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
		$pageTitle = 'Search';
		$pageType = 'default';
		require_once("./modules/search/include.search.php");
		ob_start();
		include_once("./content/pages/search/page.php");
		$content = ob_get_clean();
	break;
}
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
<body class='<?= $color_scheme."mode" ?> <?= (($_SESSION["login"]) ? "login" : "") ?>'>
	<div class="mainLoadingIndicator">
		<div class="workingSpinner" style="position: fixed; top: 50%;"></div>
	</div>
	<?= $content ?>
</body>
</html>