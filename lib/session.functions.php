<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

/**
 * Resolve the session cookie name for this installation.
 *
 * Installations sharing a domain must not share a session cookie name, or a login
 * on one instance is picked up by the other. `UO_SESSION_NAME` is therefore
 * optional: when it is undefined, the name is derived from the installation
 * directory and is already unique, the same way `DBMaintenanceRuntimeDir()`
 * derives its default path. Setting the constant is for installations that want a
 * specific name, and each one on a domain must then keep its own.
 *
 * @return string
 */
function sessionCookieName()
{
    $default = 'UO_SESSID_' . substr(md5(dirname(__DIR__)), 0, 12);

    if (!defined('UO_SESSION_NAME')) {
        return $default;
    }

    $configured = (string) constant('UO_SESSION_NAME');

    // session_name() only raises a warning for invalid values and then silently
    // falls back to PHPSESSID, so reject anything PHP would not accept.
    if ($configured === '' || ctype_digit($configured) || preg_match('/[^A-Za-z0-9_-]/', $configured)) {
        return $default;
    }

    return $configured;
}

/**
 * Build a stable fingerprint identifying this installation.
 *
 * Session payloads are stored under the session id alone, so two installations
 * sharing a `session.save_path` can read each other's session data even when their
 * cookie names differ. Two genuinely separate installations differ in at least one
 * of these values.
 *
 * @return string
 */
function sessionInstanceFingerprint()
{
    $parts = [
        defined('DB_HOST') ? (string) DB_HOST : '',
        defined('DB_DATABASE') ? (string) DB_DATABASE : '',
        defined('BASEURL') ? (string) BASEURL : '',
        sessionCookieName(),
    ];

    return substr(hash('sha256', implode('|', $parts)), 0, 32);
}

/**
 * Reject session data created by another installation.
 *
 * Sessions without a fingerprint are adopted rather than discarded, so deploying
 * this check does not log out everyone who is currently signed in.
 *
 * @return void
 */
function enforceSessionInstanceBinding()
{
    $fingerprint = sessionInstanceFingerprint();
    $stored = isset($_SESSION['__uo_instance']) ? $_SESSION['__uo_instance'] : null;

    if ($stored === null) {
        $_SESSION['__uo_instance'] = $fingerprint;
        return;
    }

    if (is_string($stored) && hash_equals($stored, $fingerprint)) {
        return;
    }

    // Abort instead of clearing and writing, so the installation that owns this
    // session keeps its stored data.
    session_abort();
    session_id(session_create_id());
    session_start();
    $_SESSION['__uo_instance'] = $fingerprint;
}

/**
 * Start a hardened PHP session with consistent cookie settings.
 */
function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookie = session_get_cookie_params();
    $path = $cookie['path'];
    $domain = $cookie['domain'];
    $secure = isHttpsRequest();
    $httponly = true;
    $lifetime = 0;
    $samesite = 'Lax';

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite,
    ]);

    // Reject attacker-supplied session ids that no session has ever used.
    ini_set('session.use_strict_mode', '1');

    session_name(sessionCookieName());
    session_start();
    enforceSessionInstanceBinding();
}

/**
 * Regenerate the session identifier to avoid session fixation.
 */
function regenerateSessionId()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Destroy the current session and expire its cookie.
 */
function destroySessionCompletely()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly'],
        );
    }

    session_destroy();
}

/**
 * Detect whether the current request is over HTTPS.
 */
function isHttpsRequest()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return true;
    }

    if (defined('BASEURL')) {
        $scheme = parse_url(BASEURL, PHP_URL_SCHEME);
        return ($scheme === 'https');
    }

    return false;
}
