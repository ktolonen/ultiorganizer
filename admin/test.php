<?php
include_once __DIR__ . '/auth.php';

// Minimal bootstrap so this page can be called both directly and via index.php.
require_once dirname(__DIR__) . '/lib/database.php';
require_once $include_prefix . 'lib/user.functions.php';

startSecureSession();

if (!isSuperAdmin()) {
	Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

phpinfo();
