<?php
/**
 * Back-compat alias for the quote variant of the unified meta-image endpoint.
 *
 * Quote share URLs (e.g. ?id=…&t=…&f=…&c=…) cached by social platforms / crawlers
 * still point here, so we keep the path alive and forward to meta-image.php with
 * the quote type forced. New callers should use meta-image.php directly.
 */
$_REQUEST['type'] = 'quote';
require __DIR__ . '/meta-image.php';
