<?php
/**
 * robots.txt for OpenParliamentTV (served via a rewrite so the Sitemap URL and
 * host are derived from config across the different parliament subdomains).
 *
 * Policy:
 *  - Search engines (Googlebot, Bingbot, Applebot, …) may crawl everything
 *    except admin/auth/utility paths.
 *  - /api/ is intentionally crawlable (the sanctioned machine-readable path);
 *    raw JSON is kept out of the index via an X-Robots-Tag: noindex header.
 *  - A curated list of AI-training / scraping crawlers is disallowed from the
 *    HTML pages and pointed to /api and /llms.txt instead. The list covers
 *    crawlers only: agents that fetch a page on a user's behalf (ChatGPT-User,
 *    Claude-User, Perplexity-User, …) are not blocked.
 *    (Server-level 403 enforcement for the same list lives in .htaccess.)
 *  - The leading comment block points automated clients to the API. Parsers
 *    ignore it; LLM agents and people reading the file see it.
 */

require_once(__DIR__ . '/bootstrap-core.php');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$baseUrl = rtrim($config["dir"]["root"], '/');

// AI / scraper crawler user-agents to block. Keep in sync with the .htaccess 403 list.
$blockedBots = [
    'GPTBot',
    'OAI-SearchBot',
    'ClaudeBot',
    'anthropic-ai',
    'Claude-Web',
    'Google-Extended',
    'Applebot-Extended',
    'PerplexityBot',
    'CCBot',
    'cohere-ai',
    'Bytespider',
    'Amazonbot',
    'Meta-ExternalAgent',
    'FacebookBot',
    'Diffbot',
    'ImagesiftBot',
    'Omgilibot',
    'YouBot',
    'DataForSeoBot',
    'PetalBot',
];
?>
# Open Parliament TV publishes all of its data as open data.
# Crawlers, scrapers and AI agents: please use the API instead of the HTML pages.
#
#   API documentation:  <?= $baseUrl ?>/api
#   OpenAPI spec:       <?= $baseUrl ?>/api/openapi.yaml
#   Overview for LLMs:  <?= $baseUrl ?>/llms.txt
#   Raw data (bulk):    https://github.com/OpenParliamentTV/
#
# Data license: ODC Open Database License (ODbL) v1.0

User-agent: *
Allow: /
Disallow: /manage/
Disallow: /login
Disallow: /register
Disallow: /registerConfirm
Disallow: /password-reset
Disallow: /logout
Disallow: /embed/
Disallow: /notifications
Disallow: /playlist/

<?php foreach ($blockedBots as $bot): ?>
User-agent: <?= $bot ?>

<?php endforeach; ?>
Allow: /api
Allow: /llms.txt
Disallow: /

Sitemap: <?= $baseUrl ?>/sitemap.xml
