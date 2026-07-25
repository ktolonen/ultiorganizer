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

if (!function_exists('ScorekeeperTimerStateDefaults')) {
    /**
     * Timer state shape used when the game clock is not in play, matching the
     * keys returned by GameTimerState().
     */
    function ScorekeeperTimerStateDefaults()
    {
        return [
            "started" => false,
            "ongoing" => false,
            "paused" => false,
            "elapsed" => 0,
            "mm" => 0,
            "ss" => 0,
            "rss" => 0,
        ];
    }
}

if (!function_exists('ScorekeeperClockHeader')) {
    /**
     * Live game clock element for the page header. Rendered server side so the
     * clock is readable before script/scorekeeper.js takes over.
     */
    function ScorekeeperClockHeader($timerState)
    {
        return "<span id='gametime' class='sk-gameclock'>"
            . sprintf("%02d", $timerState['mm']) . ":" . sprintf("%02d", $timerState['ss'])
            . "</span>";
    }
}

if (!function_exists('ScorekeeperClockScript')) {
    /**
     * Hands the server-side timer state to the shared clock in
     * script/scorekeeper.js. Every scorekeeper page that shows the clock uses
     * this so the drift-free timing rules live in one place.
     */
    function ScorekeeperClockScript($timerState)
    {
        $options = [
            "elapsed" => (int) $timerState['elapsed'],
            "ongoing" => (bool) $timerState['ongoing'],
            "paused" => (bool) $timerState['paused'],
            "pausedSuffix" => " (" . _("Paused") . ")",
        ];

        return "<script type='text/javascript'>\n"
            . "  window.scorekeeperClock.init(" . json_encode($options) . ");\n"
            . "</script>\n";
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
