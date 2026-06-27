<?php defined('OPTV') or die();
require_once(__DIR__ . '/../../modules/utilities/security.php');

if (!isset($page)) {
    $page = ''; // Initialize $page if not set
}
$root = rtrim($config["dir"]["root"], '/');
$description = strip_tags($pageDescription);
$claimShortClean = strip_tags(L::claimShort());
$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$urlWithoutParams = strtok($url, '?');

// Preserve the lang parameter in the canonical URL so it matches the hreflang
// entries below (subdomains are parliament instances, ?lang=xx is the language switch).
$canonicalLangSuffix = '';
if (!empty($_REQUEST['lang'])) {
    $canonicalLangSuffix = '?lang=' . urlencode($_REQUEST['lang']);
}

// Robots directive: keep search-result permutations and non-content pages out of the index.
$robotsMeta = null;
$noIndexPages = ['login', 'register', 'registerConfirm', 'password-reset', 'logout', 'embed-entity', 'notifications'];
$isAdminPage = (strpos($page, 'manage') === 0) || $page === 'statistics';
if ($page === 'search') {
    $robotsMeta = 'noindex, follow'; // discover links, don't index the query page
} elseif ($isAdminPage || in_array($page, $noIndexPages, true)) {
    $robotsMeta = 'noindex, nofollow';
}

// Meta images always render in the configured default language (no per-language
// variants), so no lang parameter is forwarded.
$metaImageBase = $root.'/content/client/images/meta-image.php';

// Short content-version hash for crawler/CDN cache-busting when data changes.
$metaImageVersion = function(array $parts) {
    return substr(md5(implode('|', $parts)), 0, 8);
};

switch ($page) {
  case 'main':
    $title = L::brand().' | '.$claimShortClean;
    $image = $root.'/content/client/images/thumbnail.png';
    $ogType = 'website';
    $canonicalUrl = $urlWithoutParams . $canonicalLangSuffix;
    break;
  case 'search':
    if (count($_REQUEST) < 2 || (!$_REQUEST["q"] && !$_REQUEST["personID"])) {
      $title = L::brand().' | '.$claimShortClean;
    } else {
      $title = strip_tags($pageTitle).' | '.L::brand();
    }
    // Reuse the facet labels the search handler already resolved (so the image
    // endpoint doesn't have to re-resolve them): compose "Label · Label · "q"".
    $searchLabelParts = [];
    foreach ([
      $personDataFromRequest ?? null, $organisationDataFromRequest ?? null,
      $documentDataFromRequest ?? null, $termDataFromRequest ?? null,
    ] as $facet) {
      if (is_array($facet)) {
        foreach ($facet as $facetNode) {
          if (!empty($facetNode['attributes']['label'])) { $searchLabelParts[] = $facetNode['attributes']['label']; }
        }
      }
    }
    if (!empty($_REQUEST['q'])) { $searchLabelParts[] = '“'.$_REQUEST['q'].'”'; }
    // Pass the composed label plus the filter ids (used for the result count).
    $searchParams = ['type' => 'search', 'label' => implode('  ·  ', $searchLabelParts)];
    foreach (['q', 'personID', 'organisationID', 'termID', 'documentID'] as $sp) {
      if (!empty($_REQUEST[$sp])) { $searchParams[$sp] = $_REQUEST[$sp]; }
    }
    $image = $metaImageBase.'?'.http_build_query($searchParams);
    $ogType = 'website';
    $canonicalUrl = $url;
    break;
  case 'media':
    $title = strip_tags($pageTitle).' | '.L::brand();
    if (isset($_REQUEST['t']) && isset($_REQUEST['f'])) {
      $image = $metaImageBase.'?type=quote&id='.urlencode($_REQUEST['id']).'&t='.urlencode($_REQUEST['t']).'&f='.urlencode($_REQUEST['f']).'&c='.urlencode($_REQUEST['c'] ?? 'l');
    } else {
      $image = $metaImageBase.'?type=media&id='.urlencode($_REQUEST['id'] ?? '');
    }
    $ogType = 'video.other';
    $canonicalUrl = $urlWithoutParams . $canonicalLangSuffix;
    break;
  case 'person':
  case 'organisation':
  case 'term':
  case 'document':
    $title = strip_tags($pageTitle).' | '.L::brand();
    $entityId = $apiResult['data']['id'] ?? ($_REQUEST['id'] ?? '');
    $v = $metaImageVersion([
      $apiResult['data']['attributes']['label'] ?? '',
      $apiResult['data']['attributes']['thumbnailURI'] ?? '',
    ]);
    $image = $metaImageBase.'?type='.$page.'&id='.urlencode($entityId).'&v='.$v;
    $ogType = 'article';
    $canonicalUrl = $urlWithoutParams . $canonicalLangSuffix;
    break;
  case 'session':
  case 'electoralPeriod':
  case 'agendaItem':
    // Date/structural types: no label/thumbnail, so version off the page title.
    $title = strip_tags($pageTitle).' | '.L::brand();
    $entityId = $apiResult['data']['id'] ?? ($_REQUEST['id'] ?? '');
    $v = $metaImageVersion([strip_tags($pageTitle)]);
    $image = $metaImageBase.'?type='.$page.'&id='.urlencode($entityId).'&v='.$v;
    $ogType = 'article';
    $canonicalUrl = $urlWithoutParams . $canonicalLangSuffix;
    break;
  default:
    $title = strip_tags($pageTitle).' | '.L::brand();
    $image = $root.'/content/client/images/thumbnail.png';
    $ogType = 'article';
    $canonicalUrl = $urlWithoutParams . $canonicalLangSuffix;
    break;
}
?>
<title><?= h($title) ?></title>
<meta name="description" content="<?= hAttr($description) ?>">
<?php if ($robotsMeta): ?>
<meta name="robots" content="<?= hAttr($robotsMeta) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= hAttr($title) ?>" />
<meta property="og:url" content="<?= hAttr($canonicalUrl) ?>" />
<meta property="og:type" content="<?= hAttr($ogType) ?>" />
<meta property="og:image" content="<?= hAttr($image) ?>" />
<meta property="og:description" content="<?= hAttr($description) ?>" />
<meta property="og:site_name" content="<?= hAttr(L::brand()) ?>" />
<meta property="og:locale" content="<?= hAttr(LanguageManager::getInstance()->getCurrentLang()) ?>" />
<?php if ($page === 'media' && isset($speech) && !empty($speech["attributes"]["videoFileURI"])): ?>
<meta property="og:video" content="<?= hAttr($speech["attributes"]["videoFileURI"]) ?>" />
<meta property="og:video:type" content="video/mp4" />
<?php if (!empty($speech["attributes"]["duration"])): ?>
<meta property="og:video:duration" content="<?= hAttr(intval($speech["attributes"]["duration"])) ?>" />
<?php endif; ?>
<?php endif; ?>

<meta name="twitter:card" content="<?= ($page === 'media') ? 'player' : 'summary_large_image' ?>">
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
<?php
// ================================================================
// JSON-LD structured data
// ================================================================
// $speech / $mainSpeaker / $apiResult are in scope here because head.php
// include_once()s this file and shares the Plates render data set in the
// route handlers (modules/routing/handlers.php).

$jsonLd = null;

switch ($page) {

  // ----- VideoObject (media / player pages) -----
  case 'media':
    if (isset($speech)) {
        $videoJsonLd = [
            "@context" => "https://schema.org",
            "@type" => "VideoObject",
            "name" => strip_tags($pageTitle),
            "description" => $description,
            "uploadDate" => $speech["attributes"]["dateStart"] ?? null,
            "contentUrl" => $speech["attributes"]["videoFileURI"] ?? null,
            "url" => $canonicalUrl,
        ];

        // Thumbnail (required by Google for VideoObject)
        if (!empty($speech["attributes"]["thumbnailURI"])) {
            $videoJsonLd["thumbnailUrl"] = $speech["attributes"]["thumbnailURI"];
        } else {
            $videoJsonLd["thumbnailUrl"] = $root . '/content/client/images/thumbnail.png';
        }

        if (!empty($speech["attributes"]["duration"])) {
            $videoJsonLd["duration"] = "PT" . intval($speech["attributes"]["duration"]) . "S";
        }

        // Transcript — the key SEO property (clean raw HTML from include.media.php)
        if (!empty($textContentsHTMLRaw)) {
            $videoJsonLd["transcript"] = mb_substr(strip_tags($textContentsHTMLRaw), 0, 100000);
        }

        if (isset($mainSpeaker) && $mainSpeaker) {
            $videoJsonLd["actor"] = [
                "@type" => "Person",
                "name" => $mainSpeaker['attributes']['label'],
                "url" => $root . '/person/' . $mainSpeaker["id"],
            ];
        }

        $videoJsonLd["publisher"] = [
            "@type" => "Organization",
            "name" => L::brand(),
            "url" => $root,
        ];

        if (isset($speech["relationships"]["agendaItem"]["data"]["attributes"]["title"])) {
            $videoJsonLd["about"] = $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"];
        }

        if (!empty($speech["attributes"]["parliamentLabel"])) {
            $videoJsonLd["sourceOrganization"] = [
                "@type" => "GovernmentOrganization",
                "name" => $speech["attributes"]["parliamentLabel"],
            ];
        }

        $jsonLd = $videoJsonLd;
    }
    break;

  // ----- Person -----
  case 'person':
    if (isset($apiResult["data"])) {
        $d = $apiResult["data"];
        $personJsonLd = [
            "@context" => "https://schema.org",
            "@type" => "Person",
            "name" => $d["attributes"]["label"],
            "url" => $canonicalUrl,
        ];
        if (!empty($d["attributes"]["firstName"])) {
            $personJsonLd["givenName"] = $d["attributes"]["firstName"];
        }
        if (!empty($d["attributes"]["lastName"])) {
            $personJsonLd["familyName"] = $d["attributes"]["lastName"];
        }
        if (!empty($d["attributes"]["thumbnailURI"])) {
            $personJsonLd["image"] = $d["attributes"]["thumbnailURI"];
        }
        if (!empty($d["attributes"]["abstract"]) && $d["attributes"]["abstract"] !== "undefined") {
            $personJsonLd["description"] = $d["attributes"]["abstract"];
        }
        $sameAs = optvEntitySameAs($d);
        if (!empty($sameAs)) {
            $personJsonLd["sameAs"] = (count($sameAs) === 1) ? $sameAs[0] : $sameAs;
        }
        if (isset($d["relationships"]["faction"]["data"]["attributes"]["label"])) {
            $personJsonLd["affiliation"] = [
                "@type" => "Organization",
                "name" => $d["relationships"]["faction"]["data"]["attributes"]["label"],
            ];
        }
        $jsonLd = $personJsonLd;
    }
    break;

  // ----- Organisation -----
  case 'organisation':
    if (isset($apiResult["data"])) {
        $d = $apiResult["data"];
        $orgJsonLd = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => $d["attributes"]["label"],
            "url" => $canonicalUrl,
        ];
        if (isset($d["attributes"]["labelAlternative"][0])) {
            $orgJsonLd["alternateName"] = $d["attributes"]["labelAlternative"][0];
        }
        if (!empty($d["attributes"]["thumbnailURI"])) {
            $orgJsonLd["image"] = $d["attributes"]["thumbnailURI"];
        }
        if (!empty($d["attributes"]["abstract"]) && $d["attributes"]["abstract"] !== "undefined") {
            $orgJsonLd["description"] = $d["attributes"]["abstract"];
        }
        $sameAs = optvEntitySameAs($d);
        if (!empty($sameAs)) {
            $orgJsonLd["sameAs"] = (count($sameAs) === 1) ? $sameAs[0] : $sameAs;
        }
        $jsonLd = $orgJsonLd;
    }
    break;

  // ----- Term -----
  case 'term':
    if (isset($apiResult["data"])) {
        $d = $apiResult["data"];
        $termJsonLd = [
            "@context" => "https://schema.org",
            "@type" => "DefinedTerm",
            "name" => $d["attributes"]["label"],
            "url" => $canonicalUrl,
        ];
        if (isset($d["attributes"]["labelAlternative"][0])) {
            $termJsonLd["alternateName"] = $d["attributes"]["labelAlternative"][0];
        }
        if (!empty($d["attributes"]["abstract"]) && $d["attributes"]["abstract"] !== "undefined") {
            $termJsonLd["description"] = $d["attributes"]["abstract"];
        }
        $sameAs = optvEntitySameAs($d);
        if (!empty($sameAs)) {
            $termJsonLd["sameAs"] = (count($sameAs) === 1) ? $sameAs[0] : $sameAs;
        }
        $jsonLd = $termJsonLd;
    }
    break;

  // ----- Document (legal / official documents) -----
  case 'document':
    if (isset($apiResult["data"])) {
        $d = $apiResult["data"];
        $docJsonLd = [
            "@context" => "https://schema.org",
            "@type" => "Legislation",
            "name" => $d["attributes"]["label"],
            "url" => $canonicalUrl,
        ];
        if (isset($d["attributes"]["labelAlternative"][0])) {
            $docJsonLd["alternateName"] = $d["attributes"]["labelAlternative"][0];
        }
        if (!empty($d["attributes"]["abstract"]) && $d["attributes"]["abstract"] !== "undefined") {
            $docJsonLd["description"] = $d["attributes"]["abstract"];
        }
        if (!empty($d["attributes"]["additionalInformation"]["subType"])) {
            $docJsonLd["legislationType"] = $d["attributes"]["additionalInformation"]["subType"];
        }
        if (!empty($d["attributes"]["additionalInformation"]["creator"][0])) {
            $docJsonLd["legislationPassedBy"] = [
                "@type" => "Organization",
                "name" => $d["attributes"]["additionalInformation"]["creator"][0],
            ];
        }
        if (!empty($d["attributes"]["sourceURI"])) {
            $docJsonLd["sameAs"] = $d["attributes"]["sourceURI"];
        }
        $jsonLd = $docJsonLd;
    }
    break;
}

if ($jsonLd !== null) {
    $jsonLd = array_filter($jsonLd, function ($v) { return $v !== null && $v !== ''; });
    echo "\n<script type=\"application/ld+json\">\n";
    echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}

/**
 * Collect external "same as" URLs (Wikidata, Wikipedia, official website) for an
 * entity data node. Entity IDs are Wikidata Q-numbers.
 */
function optvEntitySameAs(array $d): array
{
    $sameAs = [];
    if (!empty($d["id"]) && preg_match('/^Q\d+$/', $d["id"])) {
        $sameAs[] = "https://www.wikidata.org/wiki/" . $d["id"];
    }
    if (!empty($d["attributes"]["additionalInformation"]["wikipedia"]["url"])) {
        $sameAs[] = $d["attributes"]["additionalInformation"]["wikipedia"]["url"];
    }
    if (!empty($d["attributes"]["websiteURI"])) {
        $sameAs[] = $d["attributes"]["websiteURI"];
    }
    return $sameAs;
}
