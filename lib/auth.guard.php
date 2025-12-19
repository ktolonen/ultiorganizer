<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../';
}

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
