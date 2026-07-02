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
 *  - A curated list of AI-training / scraping bots is disallowed entirely.
 *    (Server-level 403 enforcement for the same list lives in .htaccess.)
 */

require_once(__DIR__ . '/config.php');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$baseUrl = rtrim($config["dir"]["root"], '/');

// AI / scraper user-agents to block. Keep in sync with the .htaccess 403 list.
$blockedBots = [
    'GPTBot',
    'OAI-SearchBot',
    'ChatGPT-User',
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

<?php foreach ($blockedBots as $bot): ?>
User-agent: <?= $bot ?>

<?php endforeach; ?>
Disallow: /

Sitemap: <?= $baseUrl ?>/sitemap.xml
