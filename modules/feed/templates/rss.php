<?php
/**
 * RSS 2.0 template. Expects $meta and $items (see modules/feed/functions.php).
 */
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
echo '<?xml-stylesheet type="text/xsl" href="' . feedXml($meta["xslUrl"]) . '"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title><?= feedXml($meta["title"]) ?></title>
    <link><?= feedXml($meta["link"]) ?></link>
    <description><?= feedXml($meta["description"]) ?></description>
    <language><?= feedXml($meta["language"]) ?></language>
    <lastBuildDate><?= feedXml($meta["lastBuildDate"]) ?></lastBuildDate>
    <generator><?= feedXml($meta["brand"]) ?></generator>
    <atom:link href="<?= feedXml($meta["selfUrl"]) ?>" rel="self" type="application/rss+xml" />
    <image>
      <url><?= feedXml($meta["imageUrl"]) ?></url>
      <title><?= feedXml($meta["title"]) ?></title>
      <link><?= feedXml($meta["imageLink"]) ?></link>
    </image>
<?php foreach ($items as $item): ?>
    <item>
      <title><?= feedXml($item["title"]) ?></title>
      <link><?= feedXml($item["link"]) ?></link>
      <guid isPermaLink="true"><?= feedXml($item["guid"]) ?></guid>
      <pubDate><?= feedXml($item["pubDateRss"]) ?></pubDate>
<?php if (!empty($item["author"])): ?>
      <dc:creator><?= feedXml($item["author"]) ?></dc:creator>
<?php endif; ?>
<?php if (!empty($item["category"])): ?>
      <category><?= feedXml($item["category"]) ?></category>
<?php endif; ?>
<?php if (!empty($item["description"])): ?>
      <description><?= feedXml($item["description"]) ?></description>
<?php endif; ?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
