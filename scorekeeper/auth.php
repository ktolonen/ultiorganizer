<?php
if (!isset($include_prefix)) {
	$include_prefix = __DIR__ . '/../';
}

$auth_redirect = '../scorekeeper/index.php?view=login';
include_once $include_prefix . 'lib/auth.guard.php';

if (!function_exists('scorekeeperHasManualNoGameClock')) {
	function scorekeeperHasManualNoGameClock($gameId)
	{
		return !empty($_SESSION['scorekeeper_no_game_clock'][(string) $gameId]);
	}
}
