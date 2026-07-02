<?php defined('OPTV') or die();
/**
 * Route-level authentication helper.
 *
 * Replaces the 6-line auth() boilerplate that used to be duplicated in every
 * page template. The dispatcher calls checkPageAuth() BEFORE the handler runs;
 * templates never check auth themselves.
 *
 * Access levels: routes declare an `access` level ('public'|'admin') — the AUTH
 * dimension, kept separate from the presentation `pageType` the handlers set on
 * the render data. 'public' maps onto auth()'s requestPage whitelist (success for
 * everyone); 'admin' maps onto a non-whitelisted token, so only admin users pass
 * (auth() returns success early for UserRole == 'admin'). Public-by-design routes
 * ('embed') and feeds skip this check entirely via the route's 'skipAuth' flag.
 */

require_once(__DIR__ . '/../utilities/auth.php');

/**
 * Check whether the current user may view a page at the given access level.
 *
 * @param string $access One of: 'public', 'admin'
 * @return true|array True when authorized; an error array (alertText/title) otherwise.
 */
function checkPageAuth(string $access)
{
    $userId = $_SESSION['userdata']['id'] ?? null;
    // Map the access level onto auth()'s requestPage tokens: a whitelisted token
    // ('default') for public pages, a non-whitelisted one for admin-only pages.
    $authToken = ($access === 'admin') ? 'admin' : 'default';
    $result = auth($userId, 'requestPage', $authToken);

    if (($result['meta']['requestStatus'] ?? null) === 'success') {
        return true;
    }

    return [
        'alertText' => $result['errors'][0]['detail'] ?? '',
        'title' => $result['errors'][0]['title'] ?? '',
    ];
}
