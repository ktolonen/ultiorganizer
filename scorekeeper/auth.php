<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../';
}

$auth_redirect = '../scorekeeper/index.php?view=login';
include_once $include_prefix . 'lib/auth.guard.php';
