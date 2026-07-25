<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

if (!isset($include_prefix)) {
    $include_prefix = __DIR__ . '/../';
}

// Login-gated surfaces (admin, user, mobile, scorekeeper, spiritkeeper, result,
// and the cust/ member pages) mutate data and immediately re-read it, often over
// Post/Redirect/Get. A GET landing inside the persistent cache TTL would render
// pre-write state, so opt these requests out entirely. Public spectator pages do
// not include this guard and keep the cache. Required directly because some
// callers include this guard before lib/database.php.
require_once __DIR__ . '/persistent-cache.functions.php';
DisablePersistentCacheForRequest();

include_once $include_prefix . 'lib/session.functions.php';
include_once $include_prefix . 'lib/user.functions.php';

startSecureSession();
if (!isset($_SESSION['uid'])) {
    $_SESSION['uid'] = "anonymous";
}

if (!isLoggedIn()) {
    $redirect = isset($auth_redirect) ? $auth_redirect : ($include_prefix . "index.php?view=frontpage");
    header("location:" . $redirect);
    exit();
}
