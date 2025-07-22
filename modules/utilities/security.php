<?php

/**
 * Security utility functions for user-provided content
 */

function h($text, $doubleEncode = false) {
    if ($text === null) return '';
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
}

function hAttr($text) {
    if ($text === null) return '';
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Safely renders HTML content while removing dangerous attributes and scripts
 * Allows basic HTML tags like <a>, <strong>, <em> but removes onclick, script, etc.
 * Automatically decodes HTML entities first (like the original html_entity_decode() usage)
 * @param string $html The HTML content to sanitize (may contain encoded entities)
 * @return string Safe HTML content
 */
function safeHtml($html) {
    if (empty($html)) {
        return '';
    }
    
    // First decode HTML entities (like the original html_entity_decode() usage)
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Allow basic safe tags and attributes
    $allowed_tags = '<a><strong><b><em><i><u><br><p>';
    
    // Strip tags, keeping only allowed ones
    $html = strip_tags($html, $allowed_tags);
    
    // Remove dangerous attributes using regex
    $html = preg_replace('/\s*(on\w+|javascript:|vbscript:|data:)\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html);
    
    // Ensure href attributes in links are safe and preserve useful attributes
    $html = preg_replace_callback('/<a\s+([^>]*)>/i', function($matches) {
        $attrs = $matches[1];
        $safeAttrs = [];
        
        // Extract and validate href
        if (preg_match('/href\s*=\s*["\']([^"\']*)["\']/', $attrs, $hrefMatches)) {
            $href = $hrefMatches[1];
            // Only allow http, https, and relative URLs
            if (preg_match('/^(https?:\/\/|\/|[a-zA-Z0-9])/', $href)) {
                $safeAttrs[] = 'href="' . hAttr($href) . '"';
            }
        }
        
        // Preserve safe attributes: target, rel, title, class
        if (preg_match('/target\s*=\s*["\']([^"\']*)["\']/', $attrs, $targetMatches)) {
            $target = $targetMatches[1];
            // Allow common safe target values
            if (in_array($target, ['_blank', '_self', '_parent', '_top'])) {
                $safeAttrs[] = 'target="' . hAttr($target) . '"';
            }
        }
        
        if (preg_match('/rel\s*=\s*["\']([^"\']*)["\']/', $attrs, $relMatches)) {
            $rel = $relMatches[1];
            // Allow safe rel values (nofollow, noopener, noreferrer, etc.)
            if (preg_match('/^[a-zA-Z0-9\s]+$/', $rel) && !preg_match('/javascript|script|on/i', $rel)) {
                $safeAttrs[] = 'rel="' . hAttr($rel) . '"';
            }
        }
        
        if (preg_match('/title\s*=\s*["\']([^"\']*)["\']/', $attrs, $titleMatches)) {
            $title = $titleMatches[1];
            // Allow title attribute for accessibility
            $safeAttrs[] = 'title="' . hAttr($title) . '"';
        }
        
        if (preg_match('/class\s*=\s*["\']([^"\']*)["\']/', $attrs, $classMatches)) {
            $class = $classMatches[1];
            // Allow CSS classes (alphanumeric, hyphens, underscores, spaces)
            if (preg_match('/^[a-zA-Z0-9\s_-]+$/', $class)) {
                $safeAttrs[] = 'class="' . hAttr($class) . '"';
            }
        }
        
        return '<a' . (empty($safeAttrs) ? '' : ' ' . implode(' ', $safeAttrs)) . '>';
    }, $html);
    
    return $html;
}

/**
 * Apply Content Security Policy and security headers
 * Call this at the top of any page/component that needs CSP
 */
function applySecurityHeaders() {
    global $config;
    
    if (headers_sent()) {
        return; // Headers already sent, can't modify
    }
    
    // Use existing config mode detection
    $isDev = ($config["mode"] ?? 'production') === 'dev';
    
    // Get current domain for self-embedding
    $currentDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $currentScheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $currentOrigin = $currentScheme . '://' . $currentDomain;
    
    // Check if we're on a local/non-production domain
    $isLocalDomain = in_array($currentDomain, ['localhost', 'optv.local', '127.0.0.1']) || 
                     strpos($currentDomain, '.local') !== false;
    
    // Base CSP directives - identical for dev/production except for local domain compatibility
    $cspDirectives = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' stats.openparliament.tv", // Added 'unsafe-eval' for FrameTrail.js
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https:", // Third-party images/thumbnails
        "font-src 'self'",
        "connect-src 'self' https://*.openparliament.tv",
        "frame-src 'self' stats.openparliament.tv", // Matomo + self-embedding
        "media-src 'self' https:", // Third-party videos
        "object-src 'none'", // Block plugins
        "base-uri 'self'", // Prevent base tag injection
        "form-action 'self'", // Forms only to same origin
        "frame-ancestors 'self' " . $currentOrigin // Allow self-embedding + current domain
    ];
    
    // Only exception: Skip external services when on local domains (they won't work anyway)
    if ($isLocalDomain) {
        $cspDirectives[1] = "script-src 'self' 'unsafe-inline' 'unsafe-eval'"; // Remove stats.openparliament.tv but keep unsafe-eval
        $cspDirectives[5] = "connect-src 'self'"; // Remove *.openparliament.tv
        $cspDirectives[6] = "frame-src 'self'"; // Remove stats.openparliament.tv
    }
    
    header("Content-Security-Policy: " . implode("; ", $cspDirectives));
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN"); // Allow self-embedding instead of DENY
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

