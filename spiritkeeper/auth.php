<?php

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit();
}

if (!isset($include_prefix)) {
    $include_prefix = __DIR__ . '/../';
}

function spiritkeeperRedirectToIndex($view)
{
    $location = 'index.php?view=' . urlencode($view);
    if (!empty($_SERVER['QUERY_STRING'])) {
        $location .= '&' . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $location);
    exit();
}

function spiritkeeperHasValidToken()
{
    if (!function_exists('SpiritkeeperGetToken') || !function_exists('SpiritTeamIdByToken')) {
        return false;
    }

    $token = SpiritkeeperGetToken();
    return $token !== '' && (int) SpiritTeamIdByToken($token) > 0;
}

function spiritkeeperRequireAuth($file, $view, $mode = 'login')
{
    if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath($file)) {
        spiritkeeperRedirectToIndex($view);
    }

    if ($mode === 'public') {
        return;
    }

    if ($mode === 'either' && spiritkeeperHasValidToken()) {
        return;
    }

    if ($mode === 'token') {
        if (spiritkeeperHasValidToken()) {
            return;
        }
        spiritkeeperRedirectToIndex('login');
    }

    $auth_redirect = '../spiritkeeper/index.php?view=login';
    include_once $GLOBALS['include_prefix'] . 'lib/auth.guard.php';
}
