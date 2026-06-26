<?php
/**
 * XML Sitemap for OpenParliamentTV.
 *
 * Lists entity pages only: static pages, persons, organisations, terms,
 * legal documents (from the platform DB), plus sessions and electoral periods
 * from every configured parliament DB. Media/speeches are deliberately NOT
 * included (100k+ items would exceed the 50k-URL per-file limit); they stay
 * discoverable via internal links and carry their own VideoObject structured data.
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/modules/utilities/safemysql.class.php');
require_once(__DIR__ . '/api/v1/utilities.php'); // getApiDatabaseConnection()

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=86400'); // 24h

$baseUrl = rtrim($config["dir"]["root"], '/');

$db = getApiDatabaseConnection('platform');
if (!($db instanceof SafeMySQL)) {
    http_response_code(500);
    exit;
}

/** Emit a single <url> entry. */
function sitemapUrl($loc, $lastmod = null, $changefreq = 'monthly', $priority = '0.5')
{
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) {
        echo "    <lastmod>" . htmlspecialchars($lastmod) . "</lastmod>\n";
    }
    echo "    <changefreq>" . $changefreq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}

/** Normalise a DB timestamp/date to YYYY-MM-DD, or null. */
function sitemapDate($value)
{
    if (empty($value)) {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// ===== Static pages =====
sitemapUrl($baseUrl . '/', null, 'daily', '1.0');
sitemapUrl($baseUrl . '/about', null, 'monthly', '0.4');

// ===== Persons (platform DB) =====
$persons = $db->getAll(
    "SELECT PersonID, PersonLastChanged FROM ?n ORDER BY PersonID",
    $config["platform"]["sql"]["tbl"]["Person"]
);
foreach ($persons ?: [] as $row) {
    sitemapUrl($baseUrl . '/person/' . $row["PersonID"], sitemapDate($row["PersonLastChanged"]), 'monthly', '0.7');
}

// ===== Organisations (platform DB) =====
$orgs = $db->getAll(
    "SELECT OrganisationID, OrganisationLastChanged FROM ?n ORDER BY OrganisationID",
    $config["platform"]["sql"]["tbl"]["Organisation"]
);
foreach ($orgs ?: [] as $row) {
    sitemapUrl($baseUrl . '/organisation/' . $row["OrganisationID"], sitemapDate($row["OrganisationLastChanged"]), 'monthly', '0.6');
}

// ===== Terms (platform DB) =====
$terms = $db->getAll(
    "SELECT TermID, TermLastChanged FROM ?n ORDER BY TermID",
    $config["platform"]["sql"]["tbl"]["Term"]
);
foreach ($terms ?: [] as $row) {
    sitemapUrl($baseUrl . '/term/' . $row["TermID"], sitemapDate($row["TermLastChanged"]), 'monthly', '0.5');
}

// ===== Legal documents only (platform DB) =====
$docs = $db->getAll(
    "SELECT DocumentID, DocumentLastChanged FROM ?n WHERE DocumentType = 'legalDocument' ORDER BY DocumentID",
    $config["platform"]["sql"]["tbl"]["Document"]
);
foreach ($docs ?: [] as $row) {
    sitemapUrl($baseUrl . '/document/' . $row["DocumentID"], sitemapDate($row["DocumentLastChanged"]), 'monthly', '0.5');
}

// ===== Sessions + electoral periods (per parliament DB) =====
foreach ($config["parliament"] as $parliamentCode => $parliamentConfig) {
    $dbp = getApiDatabaseConnection('parliament', $parliamentCode);
    if (!($dbp instanceof SafeMySQL)) {
        continue; // invalid/unavailable parliament — skip
    }

    $electoralPeriods = $dbp->getAll(
        "SELECT ElectoralPeriodID FROM ?n ORDER BY ElectoralPeriodNumber",
        $parliamentConfig["sql"]["tbl"]["ElectoralPeriod"]
    );
    foreach ($electoralPeriods ?: [] as $row) {
        sitemapUrl($baseUrl . '/electoralPeriod/' . $row["ElectoralPeriodID"], null, 'monthly', '0.5');
    }

    $sessions = $dbp->getAll(
        "SELECT SessionID, SessionDateStart FROM ?n ORDER BY SessionID",
        $parliamentConfig["sql"]["tbl"]["Session"]
    );
    foreach ($sessions ?: [] as $row) {
        sitemapUrl($baseUrl . '/session/' . $row["SessionID"], sitemapDate($row["SessionDateStart"]), 'yearly', '0.5');
    }
}

echo '</urlset>' . "\n";
