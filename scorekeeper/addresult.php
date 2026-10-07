<?php

include_once __DIR__ . '/auth.php';

$html = "";
$info = "";
$game_result = [];
$saveSucceeded = false;

$gameId = scorekeeperRequestGameId();
$_SESSION['game'] = $gameId;

$seasoninfo = SeasonInfo(GameSeason($gameId));
$useGameClock = empty($seasoninfo['hide_time_on_scoresheet']);
if ($useGameClock) {
    ScorekeeperHandleClockPost($gameId, 'addresult');
}

// Each tap saves at once, applied to the stored score so a stale page on
// another device cannot overwrite newer points. A final result is left alone;
// reopening it takes the explicit update button.
$scoreTaps = ['homeplus' => [1, 0], 'homeminus' => [-1, 0], 'awayplus' => [0, 1], 'awayminus' => [0, -1]];
foreach ($scoreTaps as $tap => $delta) {
    if (isset($_POST[$tap])) {
        $stored = GameResult($gameId);
        if (GameHasStarted($stored) && !$stored['isongoing']) {
            header("location:?view=addresult&game=" . $gameId);
            exit;
        }
        $home = max(0, intval($stored['homescore']) + $delta[0]);
        $away = max(0, intval($stored['visitorscore']) + $delta[1]);
        if ($home !== intval($stored['homescore']) || $away !== intval($stored['visitorscore'])) {
            GameUpdateResult($gameId, $home, $away, false);
        }
        header("location:?view=addresult&game=" . $gameId);
        exit;
    }
}

if (isset($_POST['save'])) {
    $home = intval($_POST['home']);
    $away = intval($_POST['away']);
    if (!IsValidGameScore($home) || !IsValidGameScore($away)) {
        $info = "<p class='warning'>" . _("Points must be between 0 and 1000.") . "</p>";
    }
    if (empty($info)) {
        $ok = GameSetResult($gameId, $home, $away);
        if ($ok) {
            $game_result = GameResult($gameId);
            $saveSucceeded = true;
            $info = "<p>" . sprintf(_("Game result %s - %s saved!"), $home, $away) . "</p>";
        } else {
            $info = "<p class='warning'>" . _("Error: Could not save result.") . "</p>";
        }
    }
} elseif (isset($_POST['update'])) {
    $home = intval($_POST['home']);
    $away = intval($_POST['away']);
    if (!IsValidGameScore($home) || !IsValidGameScore($away)) {
        $info = "<p class='warning'>" . _("Points must be between 0 and 1000.") . "</p>";
    }
    if (empty($info)) {
        if (GameUpdateResult($gameId, $home, $away)) {
            $info = "<p>" . sprintf(_("Game result %s - %s updated!"), $home, $away) . "</p>";
        } else {
            $info = "<p class='warning'>" . _("Error: Could not save result.") . "</p>";
        }
    }
}

$result = GameResult($gameId);
$timerState = $useGameClock ? GameTimerState($gameId) : ScorekeeperTimerStateDefaults();
$showClock = $useGameClock && ($timerState['ongoing'] || $timerState['mm'] > 0 || $timerState['ss'] > 0);
$isFinal = GameHasStarted($result) && !$result['isongoing'];

$html .= "<div data-role='header'>\n";
if ($showClock) {
    $html .= ScorekeeperClockHeader($timerState);
}
$html .= "<h1>" . _("Result") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$action = "?view=addresult&amp;game=" . $gameId;
$homeScore = intval($result['homescore']);
$awayScore = intval($result['visitorscore']);

if ($useGameClock && !$isFinal) {
    $html .= "<form action='" . $action . "' method='post' data-ajax='false'>\n";
    $html .= "<div class='sk-clock-bar'>";
    if ($timerState['ongoing']) {
        $html .= "<span>" . ($timerState['paused'] ? _("Paused") : _("Running")) . "</span>";
        if ($timerState['paused']) {
            $html .= "<input type='submit' name='resumegame' data-ajax='false' value='" . _("Resume game clock") . "'/>";
        } else {
            $html .= "<input type='submit' id='pausegame' class='button-secondary' name='pausegame' data-ajax='false' value='" . _("Pause game clock") . "'/>";
        }
    } else {
        $html .= "<span>" . _("Clock not running") . "</span>";
        $startLabel = $timerState['started'] ? _("Restart game clock") : _("Start game clock");
        $restart = $timerState['started'] ? " data-confirm-restart='1'" : "";
        $html .= "<input type='submit' id='startgame' name='startgame' data-ajax='false' value='" . $startLabel . "'" . $restart . "/>";
    }
    $html .= "</div>\n";
    if ($timerState['ongoing'] && $timerState['paused']) {
        $html .= "<details class='sk-fold'><summary>" . _("Set game clock") . "</summary>\n";
        $html .= ScorekeeperClockSetTimeFields($timerState);
        $html .= "</details>\n";
    }
    if ($timerState['started'] && $homeScore === 0 && $awayScore === 0) {
        $html .= "<input type='submit' class='button-secondary' name='resetgameclock' data-ajax='false' value='" . _("Reset game clock") . "'/>";
    }
    $html .= "</form>\n";
}

$html .= "<form action='" . $action . "' method='post' data-ajax='false'>\n";
$html .= "<div class='sk-score-cards'>";
$teams = [
    'home' => [$result['hometeamname'], $homeScore],
    'away' => [$result['visitorteamname'], $awayScore],
];
foreach ($teams as $side => $team) {
    $html .= "<div class='sk-score-card sk-score-card--" . $side . "'>";
    $html .= "<div class='sk-score-team'>" . utf8entities($team[0]) . "</div>";
    $html .= "<div class='sk-score-value'>" . $team[1] . "</div>";
    if (!$isFinal) {
        $html .= "<input type='submit' class='sk-score-plus' name='" . $side . "plus' data-ajax='false' value='+1'/>";
        $html .= "<input type='submit' class='button-secondary' name='" . $side . "minus' data-ajax='false' value='-1'/>";
    }
    $html .= "</div>";
}
$html .= "</div>\n";
$html .= "</form>\n";

$html .= $info;

if (!$isFinal) {
    $html .= "<form action='" . $action . "' method='post' data-ajax='false'>\n";
    $html .= "<input type='hidden' name='home' value='" . $homeScore . "'/>";
    $html .= "<input type='hidden' name='away' value='" . $awayScore . "'/>";
    $html .= "<input type='submit' class='sk-score-final' name='save' data-ajax='false' value='" . _("Save as final result") . "'/>";
    $html .= "</form>\n";
} elseif ($saveSucceeded) {
    $html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Set rosters") . "</a>";
}

// Typed scores are a correction path, so they stay folded away until needed.
$html .= "<details class='sk-fold sk-score-edit'" . ($isFinal ? " open" : "") . ">";
$html .= "<summary>" . _("Edit") . "</summary>\n";
$html .= "<form action='" . $action . "' method='post' data-ajax='false'>\n";
$html .= "<div class='sk-score-edit-fields'>";
$html .= "<label>" . utf8entities($result['hometeamname'])
    . "<input type='number' inputmode='numeric' name='home' value='" . $homeScore . "' min='0' maxlength='4' size='5'/></label>";
$html .= "<label>" . utf8entities($result['visitorteamname'])
    . "<input type='number' inputmode='numeric' name='away' value='" . $awayScore . "' min='0' maxlength='4' size='5'/></label>";
$html .= "</div>";
$html .= "<input type='submit' name='update' data-ajax='false' value='" . _("Game ongoing, update scores") . "'/>";
if ($isFinal) {
    $html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save as final result") . "'/>";
}
$html .= "</form>\n";
$html .= "</details>\n";

$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
if ($useGameClock && !$isFinal) {
    echo ScorekeeperClockControlScript();
}
