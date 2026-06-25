<?php defined('OPTV') or die();
/**
 * Route-level authentication helper.
 *
 * Replaces the 6-line auth() boilerplate that used to be duplicated in every
 * page template. The dispatcher calls checkPageAuth() BEFORE the handler runs;
 * templates never check auth themselves.
 *
 * Note on page types: auth()'s requestPage whitelist only contains
 * 'default', 'results' and 'entity' (public). 'admin' is intentionally absent —
 * only admin users return success early (UserRole == 'admin'), so admin pages
 * stay protected. Public-by-design page types ('embed') and the feed endpoint
 * skip this check entirely via the route's 'skipAuth' flag.
 */

require_once(__DIR__ . '/../utilities/auth.php');

/**
 * Check whether the current user may view a page of the given type.
 *
 * @param string $pageType One of: 'default', 'results', 'entity', 'admin'
 * @return true|array True when authorized; an error array (alertText/title) otherwise.
 */
function checkPageAuth(string $pageType)
{
    $userId = $_SESSION['userdata']['id'] ?? null;
    $result = auth($userId, 'requestPage', $pageType);

    if (($result['meta']['requestStatus'] ?? null) === 'success') {
        return true;
    }

    return [
        'alertText' => $result['errors'][0]['detail'] ?? '',
        'title' => $result['errors'][0]['title'] ?? '',
    ];
}
