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

if (!function_exists('scorekeeperRequestGameId')) {
    function scorekeeperRequestGameId()
    {
        if (isset($_POST['game'])) {
            return intval($_POST['game']);
        }
        if (isset($_GET['game'])) {
            return intval($_GET['game']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            return 0;
        }
        if (isset($_SESSION['game'])) {
            return intval($_SESSION['game']);
        }

        return 0;
    }
}

if (!function_exists('scorekeeperRequestTeamId')) {
    function scorekeeperRequestTeamId()
    {
        if (isset($_POST['team'])) {
            return intval($_POST['team']);
        }
        if (isset($_GET['team'])) {
            return intval($_GET['team']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            return 0;
        }
        if (isset($_SESSION['team'])) {
            return intval($_SESSION['team']);
        }

        return 0;
    }
}
