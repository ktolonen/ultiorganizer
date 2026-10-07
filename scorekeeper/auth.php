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

if (!function_exists('ScorekeeperHandleClockPost')) {
    /**
     * Applies a posted game clock control (start, pause, resume, set, reset)
     * and redirects back to $view. Returns only when no control was posted.
     */
    function ScorekeeperHandleClockPost($gameId, $view)
    {
        $location = "location:?view=" . $view . "&game=" . (int) $gameId;
        if (isset($_POST['startgame'])) {
            unset($_SESSION['scorekeeper_no_game_clock'][$gameId]);
            GameTimeStart($gameId);
        } elseif (isset($_POST['pausegame'])) {
            GameTimePause($gameId);
        } elseif (isset($_POST['resumegame'])) {
            GameTimeResume($gameId);
        } elseif (isset($_POST['setgameclock'])) {
            $setmm = isset($_POST['settimemm']) ? intval($_POST['settimemm']) : 0;
            $setss = isset($_POST['settimess']) ? intval($_POST['settimess']) : 0;
            GameTimeSetElapsed($gameId, ($setmm * 60) + $setss);
        } elseif (isset($_POST['resetgameclock'])) {
            $result = GameResult($gameId);
            if (intval($result['homescore']) === 0 && intval($result['visitorscore']) === 0) {
                GameTimeReset($gameId);
            }
        } else {
            return;
        }
        header($location);
        exit;
    }
}

if (!function_exists('ScorekeeperClockSetTimeFields')) {
    /**
     * Minute and second selects with the button that sets a paused game clock,
     * posted to ScorekeeperHandleClockPost().
     */
    function ScorekeeperClockSetTimeFields($timerState)
    {
        $html = "<label for='settimemm' class='select'>" . _("Set game clock to") . " " . _("min") . ":" . _("sec") . "</label>";
        $html .= "<div class='ui-grid-b'>";
        $html .= "<div class='ui-block-a'>\n";
        $html .= "<select id='settimemm' name='settimemm' >";
        for ($i = 0; $i <= 180; $i++) {
            $selected = $i === (int) $timerState['mm'] ? " selected='selected'" : "";
            $html .= "<option value='" . $i . "'" . $selected . ">" . $i . "</option>";
        }
        $html .= "</select>";
        $html .= "</div>";
        $html .= "<div class='ui-block-b'>\n";
        $html .= "<select id='settimess' name='settimess' >";
        for ($i = 0; $i <= 59; $i++) {
            $selected = $i === (int) $timerState['ss'] ? " selected='selected'" : "";
            $html .= "<option value='" . $i . "'" . $selected . ">" . sprintf("%02d", $i) . "</option>";
        }
        $html .= "</select>";
        $html .= "</div>";
        $html .= "</div>";
        $html .= "<input type='submit' name='setgameclock' data-ajax='false' value='" . _("Set game clock") . "'/>";

        return $html;
    }
}

if (!function_exists('ScorekeeperClockControls')) {
    /**
     * Game clock status and control buttons posted to
     * ScorekeeperHandleClockPost(). $startExtra is markup placed next to the
     * start button; $canReset offers the reset, which the handler only honours
     * while the score is 0 - 0.
     */
    function ScorekeeperClockControls($timerState, $startExtra = "", $canReset = false)
    {
        $html = "<h3>" . _("Game clock") . "</h3>";
        if ($timerState['ongoing']) {
            $status = $timerState['paused'] ? _("Paused") : _("Running");
            $html .= "<p><strong>" . _("Status") . ":</strong> " . $status . "</p>";
        } else {
            $html .= "<p>" . _("Clock not running") . ".</p>";
        }
        if ($timerState['ongoing']) {
            if ($timerState['paused']) {
                $html .= "<input type='submit' name='resumegame' data-ajax='false' value='" . _("Resume game clock") . "'/>";
                $html .= ScorekeeperClockSetTimeFields($timerState);
            } else {
                $html .= "<input type='submit' id='pausegame' name='pausegame' data-ajax='false' value='" . _("Pause game clock") . "'/>";
            }
        } else {
            // A score alone marks the game started, but only a clock that has
            // run can be restarted.
            $clockHasRun = $timerState['elapsed'] > 0;
            $startLabel = $clockHasRun ? _("Restart game clock") : _("Start game clock");
            $restart = $clockHasRun ? " data-confirm-restart='1'" : "";
            $html .= "<div data-role='controlgroup' data-type='horizontal'>";
            $html .= "<input type='submit' id='startgame' name='startgame' data-ajax='false' value='" . $startLabel . "'" . $restart . "/>";
            $html .= $startExtra;
            $html .= "</div>";
        }
        if ($timerState['started'] && $canReset) {
            $html .= "<input type='submit' name='resetgameclock' data-ajax='false' value='" . _("Reset game clock") . "'/>";
        }

        return $html;
    }
}

if (!function_exists('ScorekeeperClockControlScript')) {
    /**
     * Confirmations for the pause and restart buttons rendered by
     * ScorekeeperClockControls().
     */
    function ScorekeeperClockControlScript()
    {
        return "<script type='text/javascript'>\n"
            . "  window.scorekeeperClockControls(" . json_encode([
                "pause" => _("Pause the game clock? Use this only for exceptional stoppages."),
                "restart" => _("Restart the game clock from 00:00?"),
            ]) . ");\n"
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
