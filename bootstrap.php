<?php
/**
 * Web bootstrap — for entry points that render a page or an HTML fragment.
 *
 * Adds to bootstrap-core.php: the session, the security/CSP headers, i18n
 * initialisation, and the ambient view globals that templates and the Plates
 * engine read ($lang, $langJSONString, $color_scheme, $isMobile).
 *
 * Binary endpoints (image proxies) must NOT use this file — they require
 * bootstrap-core.php directly, so nothing can emit a header or a stray byte
 * ahead of the image data.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/bootstrap-core.php");

applySecurityHeaders();

require_once(__DIR__ . "/modules/i18n/language.php");

// Ambient view globals. Declared global so the values are visible at file scope
// in every entry point, whether it requires this file at top level or not.
global $lang, $langJSONString, $color_scheme, $isMobile;

$lang = LanguageManager::getInstance()->getCurrentLang();
$langJSONString = LanguageManager::getInstance()->getLangJSONString();

$color_scheme = isset($_COOKIE["color_scheme"]) ? $_COOKIE["color_scheme"] : false;
if ($color_scheme === false) $color_scheme = 'light';

$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool) preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent);
