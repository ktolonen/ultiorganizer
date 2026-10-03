<?php

if (!isset($include_prefix)) {
    $include_prefix = __DIR__ . '/../';
}

$auth_redirect = '../scorekeeper/index.php?view=login';
$auth_allow_anonymous = 'ScorekeeperSessionHasAnonymousAccess';
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

// Pages read the game and team from the body, the URL or the session in
// different orders, so every id the request carries is checked. An anonymous
// session admitted by a scorekeeping link sees only the games that link
// covers, and a team must play in the game. A game or team left in the session
// by an earlier page is dropped instead, so a link that has expired or a
// different game does not lock the session out of its own pages.
if (!isLoggedIn() && isset($_SESSION['game']) && ScorekeeperGrantTokenId(intval($_SESSION['game'])) === 0) {
    unset($_SESSION['game'], $_SESSION['team']);
}
$requestGameIds = array_unique(array_filter([
    intval($_POST['game'] ?? 0),
    intval($_GET['game'] ?? 0),
    scorekeeperRequestGameId(),
]));
$requestTeamIds = array_unique(array_filter([
    intval($_POST['team'] ?? 0),
    intval($_GET['team'] ?? 0),
]));
$requestRefused = count($requestGameIds) > 1;
if (!isLoggedIn()) {
    foreach ($requestGameIds as $requestGameId) {
        if (ScorekeeperGrantTokenId($requestGameId) === 0) {
            $requestRefused = true;
        }
    }
}
$requestGame = count($requestGameIds) === 1 ? GameResult(reset($requestGameIds)) : null;
$requestGameTeams = is_array($requestGame) ? [(int) $requestGame['hometeam'], (int) $requestGame['visitorteam']] : [];
if (array_diff($requestTeamIds, $requestGameTeams) !== []) {
    $requestRefused = true;
}
if (isset($_SESSION['team']) && !in_array(intval($_SESSION['team']), $requestGameTeams, true)) {
    unset($_SESSION['team']);
}
if ($requestRefused) {
    header("location:" . (isLoggedIn() ? '../scorekeeper/index.php?view=respgames' : $auth_redirect));
    exit();
}
