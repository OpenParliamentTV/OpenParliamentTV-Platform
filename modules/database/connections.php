<?php
/**
 * Database connection factory.
 *
 * Plain returns and exceptions (D2): optvDbConnection() hands back a SafeMySQL
 * or throws OptvDbException. The JSON:API envelope for a failed connection is
 * applied by getApiDatabaseConnection() in api/v1/utilities.php, which is a
 * catch-and-translate wrapper over this.
 *
 * Every connection the platform opens goes through here, so the credentials are
 * read from $config in exactly one place.
 */

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../utilities/safemysql.class.php");

/**
 * @param string $scope 'platform', or anything else together with $parliament
 *                      for a parliament database. (The loose second branch is
 *                      the pre-extraction behaviour and is kept deliberately —
 *                      callers pass 'parliament', but nothing enforced it.)
 * @param string|null $parliament Parliament key, e.g. "DE".
 * @return SafeMySQL
 * @throws OptvDbException
 */
function optvDbConnection($scope = 'platform', $parliament = null)
{
    global $config;

    if ($scope === 'platform') {
        try {
            return new SafeMySQL([
                'host' => $config["platform"]["sql"]["access"]["host"],
                'user' => $config["platform"]["sql"]["access"]["user"],
                'pass' => $config["platform"]["sql"]["access"]["passwd"],
                'db'   => $config["platform"]["sql"]["db"]
            ]);
        } catch (Exception $e) {
            throw new OptvDbException("Platform database unavailable.", "unavailable", "platform", $e);
        }
    }

    if ($parliament) {
        if (!isset($config["parliament"][$parliament])) {
            throw new OptvDbException("No such parliament: {$parliament}.", "unknown-parliament", "parliament");
        }

        try {
            return new SafeMySQL([
                'host' => $config["parliament"][$parliament]["sql"]["access"]["host"],
                'user' => $config["parliament"][$parliament]["sql"]["access"]["user"],
                'pass' => $config["parliament"][$parliament]["sql"]["access"]["passwd"],
                'db'   => $config["parliament"][$parliament]["sql"]["db"]
            ]);
        } catch (Exception $e) {
            throw new OptvDbException("Parliament database unavailable.", "unavailable", "parliament", $e);
        }
    }

    throw new OptvDbException("A parliament database needs a parliament.", "missing-parliament", "parliament");
}
