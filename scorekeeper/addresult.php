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
        if ($home === 0 && $away === 0 && empty($stored['timer_start'])) {
            // Back to 0 - 0 with no clock started undoes an accidental tap, so
            // the game returns to not started instead of staying ongoing.
            if (GameHasStarted($stored)) {
                GameClearResult($gameId);
            }
        } elseif ($home !== intval($stored['homescore']) || $away !== intval($stored['visitorscore'])) {
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
        // A score alone marks the game started, but only a clock that has run
        // can be restarted.
        $clockHasRun = $timerState['elapsed'] > 0;
        $startLabel = $clockHasRun ? _("Restart game clock") : _("Start game clock");
        $restart = $clockHasRun ? " data-confirm-restart='1'" : "";
        $html .= "<input type='submit' id='startgame' name='startgame' data-ajax='false' value='" . $startLabel . "'" . $restart . "/>";
    }
    $html .= "</div>\n";
    if ($timerState['ongoing'] && $timerState['paused']) {
        $html .= "<details class='sk-fold'><summary>" . _("Set game clock") . "</summary>\n";
        $html .= ScorekeeperClockSetTimeFields($timerState);
        $html .= "</details>\n";
    }
    if ($timerState['elapsed'] > 0 && $homeScore === 0 && $awayScore === 0) {
        $html .= "<input type='submit' class='button-secondary' name='resetgameclock' data-ajax='false' value='" . _("Reset game clock") . "'/>";
    }
    $html .= "</form>\n";
}

// One form serves both uses: a live game taps +1/-1, which save at once
// through the scoretaps form and mark the game ongoing, and a known result is
// typed into the score fields and saved as final.
$html .= "<form id='scoreform' action='" . $action . "' method='post' data-ajax='false'>\n";
if ($isFinal) {
    $html .= "<p class='sk-result-status'>" . _("Final result") . ": " . $homeScore . " - " . $awayScore . "</p>";
} elseif (GameHasStarted($result)) {
    $html .= "<p class='sk-result-status sk-result-status--ongoing'>" . _("Game ongoing") . ": " . $homeScore . " - " . $awayScore . "</p>";
}
$html .= "<div class='sk-score-cards'>";
$teams = [
    'home' => [$result['hometeamname'], $homeScore],
    'away' => [$result['visitorteamname'], $awayScore],
];
foreach ($teams as $side => $team) {
    $html .= "<div class='sk-score-card sk-score-card--" . $side . "'>";
    $html .= "<label class='sk-score-team' for='" . $side . "'>" . utf8entities($team[0]) . "</label>";
    $html .= "<input type='number' inputmode='numeric' class='sk-score-value' id='" . $side . "' name='" . $side . "' value='" . $team[1] . "' min='0' maxlength='4' size='5'/>";
    if (!$isFinal) {
        $html .= "<input type='submit' form='scoretaps' class='sk-score-plus' name='" . $side . "plus' data-ajax='false' value='+1'/>";
        $html .= "<input type='submit' form='scoretaps' class='button-secondary' name='" . $side . "minus' data-ajax='false' value='-1'/>";
    }
    $html .= "</div>";
}
$html .= "</div>\n";
$html .= $info;
$html .= "<div class='sk-result-actions'>";
$html .= "<input type='submit' id='savefinal' name='save' data-ajax='false' value='" . _("Save final result") . "'/>";
// Taps already keep an ongoing game's score, so updating is only needed to
// reopen a final result.
if ($isFinal) {
    $html .= "<input type='submit' class='button-secondary' name='update' data-ajax='false' value='" . _("Game ongoing, update scores") . "'/>";
}
$html .= "</div>\n";
if ($saveSucceeded) {
    $html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Set rosters") . "</a>";
}
$html .= "</form>\n";
$html .= "<form id='scoretaps' action='" . $action . "' method='post' data-ajax='false'></form>\n";

$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
if ($useGameClock && !$isFinal) {
    echo ScorekeeperClockControlScript();
}
?>
<script type="text/javascript">
  (function () {
    var save = document.getElementById("savefinal");
    if (!save) {
      return;
    }
    save.addEventListener("click", function (event) {
      var home = document.getElementById("home").value;
      var away = document.getElementById("away").value;
      if (!window.confirm(<?php echo json_encode(_("Save final result")); ?> + " " + home + " - " + away + "?")) {
        event.preventDefault();
      }
    });
  })();
</script>
