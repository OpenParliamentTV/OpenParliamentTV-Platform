<?php
/**
 * Atom 1.0 template. Expects $meta and $items (see modules/feed/functions.php).
 */
echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="<?= feedXml($meta["language"]) ?>">
  <title><?= feedXml($meta["title"]) ?></title>
  <subtitle><?= feedXml($meta["description"]) ?></subtitle>
  <id><?= feedXml($meta["selfUrl"]) ?></id>
  <link href="<?= feedXml($meta["selfUrl"]) ?>" rel="self" type="application/atom+xml" />
  <link href="<?= feedXml($meta["link"]) ?>" rel="alternate" type="text/html" />
  <updated><?= feedXml($meta["lastBuildAtom"]) ?></updated>
  <generator><?= feedXml($meta["brand"]) ?></generator>
  <logo><?= feedXml($meta["imageUrl"]) ?></logo>
<?php foreach ($items as $item): ?>
  <entry>
    <title><?= feedXml($item["title"]) ?></title>
    <link href="<?= feedXml($item["link"]) ?>" rel="alternate" type="text/html" />
    <id><?= feedXml($item["guid"]) ?></id>
    <updated><?= feedXml($item["pubDateAtom"]) ?></updated>
<?php if (!empty($item["author"])): ?>
    <author><name><?= feedXml($item["author"]) ?></name></author>
<?php endif; ?>
<?php if (!empty($item["category"])): ?>
    <category term="<?= feedXml($item["category"]) ?>" />
<?php endif; ?>
<?php if (!empty($item["description"])): ?>
    <summary><?= feedXml($item["description"]) ?></summary>
<?php endif; ?>
  </entry>
<?php endforeach; ?>
</feed>
