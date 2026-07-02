<?php
/**
 * Lightweight service-health checks used to surface outages to users.
 *
 * Currently reports OpenSearch reachability, which drives the global header
 * banner (content/header.php). The result is cached in a short-TTL file so a
 * full page load never pings the cluster more than once per TTL window, and the
 * ping uses a short connect/timeout so a crashed or unreachable cluster cannot
 * stall page rendering.
 */

require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../config.php');

/**
 * Aggregate service health for the current request.
 *
 * @return array{searchAvailable: bool}
 */
function optvServiceHealth() {
    $cacheFile = __DIR__ . '/../../api/v1/cache/serviceHealth.json';
    $cacheTtl  = 30; // seconds — banner can lag a recovery by at most this long

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached["searchAvailable"])) {
            return $cached;
        }
    }

    $health = ["searchAvailable" => optvCheckSearchAvailable()];

    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    @file_put_contents($cacheFile, json_encode($health));

    return $health;
}

/**
 * Ping OpenSearch with short timeouts. Returns false on connection refused,
 * timeout, auth/SSL failure, or client-init failure — i.e. any state where the
 * search index cannot serve requests.
 *
 * @return bool
 */
function optvCheckSearchAvailable() {
    global $config;

    try {
        $clientBuilder = OpenSearch\ClientBuilder::create();

        if (!empty($config["OpenSearch"]["hosts"])) {
            $clientBuilder->setHosts($config["OpenSearch"]["hosts"]);
        }
        if (!empty($config["OpenSearch"]["BasicAuthentication"]["user"]) && isset($config["OpenSearch"]["BasicAuthentication"]["passwd"])) {
            $clientBuilder->setBasicAuthentication($config["OpenSearch"]["BasicAuthentication"]["user"], $config["OpenSearch"]["BasicAuthentication"]["passwd"]);
        }
        if (!empty($config["OpenSearch"]["SSL"]["pem"])) {
            $clientBuilder->setSSLVerification($config["OpenSearch"]["SSL"]["pem"]);
        }
        // Short timeouts so a down/unreachable cluster can't stall page render.
        $clientBuilder->setConnectionParams(['client' => ['timeout' => 2, 'connect_timeout' => 2]]);

        $client = $clientBuilder->build();

        // ping() issues a HEAD / and returns false (not throws) on transport
        // errors; wrap anyway in case client construction throws.
        return $client->ping() === true;
    } catch (Throwable $e) {
        error_log("optvCheckSearchAvailable: " . $e->getMessage());
        return false;
    }
}
