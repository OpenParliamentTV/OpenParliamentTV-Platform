<?php
/**
 * Core bootstrap — shared by every entry point (web, API, CLI, binary).
 *
 * Loads configuration, the Composer autoloader, the cross-cutting utilities and
 * the security helper functions, and applies the per-mode error reporting policy.
 *
 * It deliberately produces **no output of any kind**: no session, no headers, no
 * echo. That makes it safe to require from binary endpoints (image proxies)
 * where a single stray byte would corrupt the response.
 *
 * Layers on top of this file:
 *   bootstrap.php      web pages — adds session, security headers, i18n, view globals
 *   bootstrap-cli.php  CLI scripts — adds the CLI-only guard
 *
 * Requiring `config.php` also defines the OPTV constant, so any entry point that
 * boots through here satisfies the `defined('OPTV') or die()` template guard.
 */

require_once(__DIR__ . "/config.php");        // defines OPTV, $config, $acceptLang
require_once(__DIR__ . "/vendor/autoload.php");

if ($config["mode"] == "dev") {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ERROR);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cross-cutting helpers. utilities/functions.php pulls in paths.php (optvVarPath),
// so runtime state locations are resolvable everywhere after this point.
require_once(__DIR__ . "/modules/utilities/functions.php");

// Escaping helpers (h/hAttr/safeHtml) and applySecurityHeaders(). Loading the
// file emits nothing — headers are only sent when a caller invokes the function.
require_once(__DIR__ . "/modules/utilities/security.php");
