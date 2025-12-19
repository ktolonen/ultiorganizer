<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../';
}

$auth_redirect = '../index.php?view=mobile/index';
include_once $include_prefix . 'lib/auth.guard.php';
