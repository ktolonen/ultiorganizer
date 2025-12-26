<?php

/**
 * Start a hardened PHP session with consistent cookie settings.
 */
function startSecureSession()
{
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	$cookie = session_get_cookie_params();
	$path = isset($cookie['path']) ? $cookie['path'] : '/';
	$domain = isset($cookie['domain']) ? $cookie['domain'] : '';
	$secure = isHttpsRequest();
	$httponly = true;
	$lifetime = 0;
	$samesite = 'Lax';

	if (PHP_VERSION_ID >= 70300) {
		session_set_cookie_params([
			'lifetime' => $lifetime,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => $httponly,
			'samesite' => $samesite
		]);
	} else {
		session_set_cookie_params($lifetime, $path . '; SameSite=' . $samesite, $domain, $secure, $httponly);
	}

	session_name('UO_SESSID');
	session_start();
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

	$_SESSION = array();

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
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
