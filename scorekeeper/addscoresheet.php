<?php
include_once __DIR__ . '/auth.php';

function ScorekeeperPlayedPlayerIdMap($players)
{
    $playerIds = [];
    foreach ($players as $player) {
        $playerIds[(int) $player['player_id']] = true;
    }

    return $playerIds;
}

/**
 * Option markup for the assist and scorer dropdowns, swapped in client side
 * when the scoring team changes.
 */
function ScorekeeperPlayerOptions($players, $withCallahan)
{
    $options = "<option value='0'>-</option>";
    foreach ($players as $player) {
        $options .= "<option value='" . utf8entities($player['player_id']) . "'>#" . $player['num']
            . " " . utf8entities($player['firstname'] . " " . $player['lastname']) . "</option>";
    }
    if ($withCallahan) {
        $options .= "<option value='xx'>XX " . _("Callahan goal") . "</option>";
    }

    return $options;
}

function ScorekeeperRosterPlayerId($playerId, $playedPlayerIds)
{
    $playerId = (int) $playerId;
    if ($playerId > 0 && !empty($playedPlayerIds[$playerId])) {
        return $playerId;
    }

    return 0;
}

$html = "";
$errors = "";
$gameId = scorekeeperRequestGameId();
$_SESSION['game'] = $gameId;

$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
if (!isset($_SESSION['scorekeeper_no_game_clock'])) {
    $_SESSION['scorekeeper_no_game_clock'] = [];
}

if (!$hideTimeOnScoresheet && isset($_POST['nogameclock'])) {
    $_SESSION['scorekeeper_no_game_clock'][$gameId] = 1;
    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

if (!$hideTimeOnScoresheet && isset($_POST['usegameclock'])) {
    unset($_SESSION['scorekeeper_no_game_clock'][$gameId]);
    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

$manualNoGameClock = !$hideTimeOnScoresheet && scorekeeperHasManualNoGameClock($gameId);
$useGameClock = !$hideTimeOnScoresheet && !$manualNoGameClock;

if ($useGameClock) {
    if (isset($_POST['startgame'])) {
        unset($_SESSION['scorekeeper_no_game_clock'][$gameId]);
        GameTimeStart($gameId);
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
    if (isset($_POST['pausegame'])) {
        GameTimePause($gameId);
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
    if (isset($_POST['resumegame'])) {
        GameTimeResume($gameId);
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
    if (isset($_POST['setgameclock'])) {
        $setmm = isset($_POST['settimemm']) ? intval($_POST['settimemm']) : 0;
        $setss = isset($_POST['settimess']) ? intval($_POST['settimess']) : 0;
        GameTimeSetElapsed($gameId, ($setmm * 60) + $setss);
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
    if (isset($_POST['resetgameclock'])) {
        $result = GameResult($gameId);
        if (intval($result['homescore']) === 0 && intval($result['visitorscore']) === 0) {
            GameTimeReset($gameId);
        }
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
}

$game_result = GameResult($gameId);
$homePlayers = GamePlayers($gameId, $game_result['hometeam']);
$awayPlayers = GamePlayers($gameId, $game_result['visitorteam']);
$scores = GameGoals($gameId);
$lastscore = count($scores) ? $scores[count($scores) - 1] : null;
$timerState = $useGameClock ? GameTimerState($gameId) : ScorekeeperTimerStateDefaults();
$showClock = $useGameClock && ($timerState['ongoing'] || $timerState['mm'] > 0 || $timerState['ss'] > 0);
$capEventTime = $useGameClock
    ? ((int) $timerState['mm'] * 60) + (int) $timerState['rss']
    : (int) ($lastscore['time'] ?? 0);
$capEvents = GameCapEvents($gameId);
$halfCapEvent = $capEvents['half_cap'] ?? null;
$timeCapEvent = $capEvents['time_cap'] ?? null;

$uo_goal = [
    "game" => $gameId,
    "num" => 0,
    "assist" => 0,
    "scorer" => 0,
    "time" => "",
    "homescore" => 0,
    "visitorscore" => 0,
    "ishomegoal" => 0,
    "iscallahan" => 0,
];
$timemm = "";
$timess = "";
$settimemm = "";
$settimess = "";
$pass = "";
$goal = "";
$team = "";

if (isset($_POST['add']) || isset($_POST['forceadd'])) {
    if ($useGameClock && !$timerState['ongoing']) {
        $errors .= "<p class='warning'>" . _("Start the game clock before adding goals.") . "</p>\n";
    } else {
        $prevtime = 0;
        $timemm = "0";
        $timess = "0";

        if ($lastscore) {
            $prevtime = $lastscore['time'];
            $uo_goal['num'] = $lastscore['num'] + 1;
            $uo_goal['homescore'] = $lastscore['homescore'];
            $uo_goal['visitorscore'] = $lastscore['visitorscore'];
        }

        if (!empty($_POST['team'])) {
            $team = $_POST['team'];
        }
        if (!empty($_POST['pass'])) {
            $uo_goal['assist'] = $_POST['pass'];
            $pass = $_POST['pass'];
        }
        if (!empty($_POST['goal'])) {
            $uo_goal['scorer'] = $_POST['goal'];
            $goal = $_POST['goal'];
        }

        if ($hideTimeOnScoresheet) {
            $uo_goal['time'] = $prevtime + 1;
        } else {
            if (isset($_POST['timemm']) && $_POST['timemm'] !== '') {
                $timemm = intval($_POST['timemm']);
            }
            if (isset($_POST['timess']) && $_POST['timess'] !== '') {
                $timess = intval($_POST['timess']);
            }

            $uo_goal['time'] = TimeToSec($timemm . "." . $timess);

            if ($uo_goal['time'] <= $prevtime) {
                $errors .= "<p class='warning'>" . _("Time cannot be the same as or earlier than the previous point.") . "</p>\n";
            }
        }

        if (strcasecmp($uo_goal['assist'], 'xx') == 0 || strcasecmp($uo_goal['assist'], 'x') == 0) {
            $uo_goal['iscallahan'] = 1;
            $uo_goal['assist'] = -1;
        }

        if (strcasecmp($uo_goal['scorer'], 'xx') == 0 || strcasecmp($uo_goal['scorer'], 'x') == 0) {
            $uo_goal['iscallahan'] = 1;
            $uo_goal['scorer'] = -1;
        }

        $scoringPlayers = null;
        if ($team == 'H') {
            $scoringPlayers = $homePlayers;
        } elseif ($team == 'A') {
            $scoringPlayers = $awayPlayers;
        }

        if ($scoringPlayers !== null) {
            $playedPlayerIds = ScorekeeperPlayedPlayerIdMap($scoringPlayers);
            if ($uo_goal['assist'] !== -1) {
                $uo_goal['assist'] = ScorekeeperRosterPlayerId($uo_goal['assist'], $playedPlayerIds);
            }
            if ($uo_goal['scorer'] !== -1) {
                $uo_goal['scorer'] = ScorekeeperRosterPlayerId($uo_goal['scorer'], $playedPlayerIds);
            }
        }

        if (!empty($team) && $team == 'H') {
            $uo_goal['homescore']++;
            $uo_goal['ishomegoal'] = 1;

            if ($uo_goal['assist'] == 0 && !$uo_goal['iscallahan']) {
                $errors .= "<p class='warning'>" . _("Assisting player not on the roster") . "!</p>\n";
            }

            if ($uo_goal['scorer'] == 0) {
                $errors .= "<p class='warning'>" . _("Scoring player not on the roster.") . "</p>\n";
            }
        } elseif (!empty($team) && $team == 'A') {
            $uo_goal['visitorscore']++;
            $uo_goal['ishomegoal'] = 0;

            if ($uo_goal['assist'] == 0 && !$uo_goal['iscallahan']) {
                $errors .= "<p class='warning'>" . _("Assisting player not on the roster") . "!</p>\n";
            }

            if ($uo_goal['scorer'] == 0) {
                $errors .= "<p class='warning'>" . _("Scoring player not on the roster.") . "</p>\n";
            }
        }

        if (($uo_goal['assist'] != -1 || $uo_goal['scorer'] != -1) && $uo_goal['assist'] == $uo_goal['scorer']) {
            $errors .= "<p class='warning'>" . _("Scorer and assist are the same player!") . "</p>\n";
        }
        if (empty($team)) {
            $errors .= "<p class='warning'>" . _("Select the team that scored.") . "</p>\n";
        }

        if (empty($errors) || isset($_POST['forceadd'])) {
            GameAddScoreEntry($uo_goal);
            $result = GameResult($gameId);
            if (($uo_goal['homescore'] + $uo_goal['visitorscore']) > ($result['homescore'] + $result['visitorscore'])) {
                GameUpdateResult($gameId, $uo_goal['homescore'], $uo_goal['visitorscore']);
            }
            header("location:?view=addscoresheet&game=" . $gameId);
            exit;
        }
    }
}

if (isset($_POST['save']) && !$useGameClock) {
    $home = 0;
    $away = 0;
    if ($lastscore) {
        $home = $lastscore['homescore'];
        $away = $lastscore['visitorscore'];
    }
    GameSetResult($gameId, $home, $away);
    header("location:?view=gameplay&game=" . $gameId);
    exit;
}

if (!$hideTimeOnScoresheet) {
    if (isset($_POST['timemm']) && $_POST['timemm'] !== '') {
        $timemm = intval($_POST['timemm']);
    } elseif ($useGameClock && $timerState['ongoing']) {
        $timemm = $timerState['mm'];
    } elseif ($lastscore) {
        $time = explode(".", SecToMin($lastscore['time']));
        $timemm = $time[0];
    }

    if (isset($_POST['timess']) && $_POST['timess'] !== '') {
        $timess = intval($_POST['timess']);
    } elseif ($useGameClock && $timerState['ongoing']) {
        $timess = $timerState['rss'];
    } elseif ($lastscore) {
        $time = explode(".", SecToMin($lastscore['time']));
        $timess = $time[1];
    }
}

$showGoalForm = !$useGameClock || $timerState['ongoing'];
$canShowTimedActions = !$hideTimeOnScoresheet && (!$useGameClock || $timerState['started']);
$settimemm = $useGameClock ? $timerState['mm'] : 0;
$settimess = $useGameClock ? $timerState['ss'] : 0;

$html .= "<div data-role='header'>\n";
if ($showClock) {
    $html .= ScorekeeperClockHeader($timerState);
}
$html .= "<h1>" . _("Scoresheet") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addscoresheet&amp;game=" . $gameId . "' method='post' data-ajax='false'>\n";

if ($lastscore) {
    $html .= "#" . ($lastscore['num'] + 1) . " " . _("Score") . ": " . $lastscore['homescore'] . " - " . $lastscore['visitorscore'] . " ";
    if (!$hideTimeOnScoresheet) {
        $html .= "[" . SecToMin($lastscore['time']) . "] ";
    }
    $goalText = GoalDisplayText($lastscore, $gameId, true);
    if ($goalText !== '') {
        $html .= utf8entities($goalText);
    }
    $html .= " <a href='?view=deletescore&amp;game=" . $gameId . "' data-ajax='false'>" . _("Delete goal") . "</a>";
} else {
    $html .= _("Score") . ": 0 - 0";
}

if ($useGameClock) {
    $html .= "<h3>" . _("Game clock") . "</h3>";
    if ($timerState['ongoing']) {
        $status = $timerState['paused'] ? _("Paused") : _("Running");
        $html .= "<p><strong>" . _("Status") . ":</strong> " . $status . "</p>";
    } else {
        $html .= "<p>" . _("Clock not running") . ".</p>";
    }
    if ($timerState['ongoing']) {
        if ($timerState['paused']) {
            $html .= "<input type='submit' name='resumegame' data-ajax='false' value='" . _("Resume game clock") . "'/>";
            $html .= "<label for='settimemm' class='select'>" . _("Set game clock to") . " " . _("min") . ":" . _("sec") . "</label>";
            $html .= "<div class='ui-grid-b'>";
            $html .= "<div class='ui-block-a'>\n";
            $html .= "<select id='settimemm' name='settimemm' >";
            for ($i = 0; $i <= 180; $i++) {
                if ((string) $i === (string) $settimemm) {
                    $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
                } else {
                    $html .= "<option value='" . $i . "'>" . $i . "</option>";
                }
            }
            $html .= "</select>";
            $html .= "</div>";
            $html .= "<div class='ui-block-b'>\n";
            $html .= "<select id='settimess' name='settimess' >";
            for ($i = 0; $i <= 59; $i++) {
                if ((string) $i === (string) $settimess) {
                    $html .= "<option value='" . $i . "' selected='selected'>" . sprintf("%02d", $i) . "</option>";
                } else {
                    $html .= "<option value='" . $i . "'>" . sprintf("%02d", $i) . "</option>";
                }
            }
            $html .= "</select>";
            $html .= "</div>";
            $html .= "</div>";
            $html .= "<input type='submit' name='setgameclock' data-ajax='false' value='" . _("Set game clock") . "'/>";
        } else {
            $html .= "<input type='submit' id='pausegame' name='pausegame' data-ajax='false' value='" . _("Pause game clock") . "'/>";
        }
    } else {
        $startLabel = $timerState['started'] ? _("Restart game clock") : _("Start game clock");
        $html .= "<div data-role='controlgroup' data-type='horizontal'>";
        $html .= "<input type='submit' id='startgame' name='startgame' data-ajax='false' value='" . $startLabel . "'/>";
        $html .= "<input type='submit' name='nogameclock' data-ajax='false' value='" . _("No game clock") . "'/>";
        $html .= "</div>";
    }
    if ($timerState['started'] && $lastscore === null) {
        $html .= "<input type='submit' name='resetgameclock' data-ajax='false' value='" . _("Reset game clock") . "'/>";
    }
    if ($timerState['started'] || GameHasStarted($game_result)) {
        $html .= "<a href='?view=endgame&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("End game") . "</a>";
    }
} elseif ($manualNoGameClock) {
    $html .= "<h3>" . _("Game clock") . "</h3>";
    $html .= "<p><strong>" . _("Status") . ":</strong> " . _("Manual time entry") . "</p>";
    $html .= "<input type='submit' name='usegameclock' data-ajax='false' value='" . _("Use game clock") . "'/>";
}

if (!$showGoalForm && $useGameClock) {
    $message = $timerState['started'] ? _("Restart the game clock to continue timed scoring.") : _("Start the game clock before adding goals.");
    $html .= "<p class='warning'>" . $message . "</p>";
}

// An empty roster leaves the assist and scorer dropdowns empty, which is a
// common surprise for scorekeepers who did not check the players in.
$rosters = [
    [$game_result['hometeam'], $game_result['hometeamname'], $homePlayers],
    [$game_result['visitorteam'], $game_result['visitorteamname'], $awayPlayers],
];
foreach ($rosters as $roster) {
    if (count($roster[2]) > 0) {
        continue;
    }
    $html .= "<p class='warning'>" . sprintf(_("No players given for team %s."), utf8entities($roster[1]))
        . " <a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $roster[0] . "' data-ajax='false'>" . _("Enter the players for this game.") . "</a></p>\n";
}

if ($errors && !$showGoalForm) {
    $html .= $errors;
}

if ($showGoalForm) {
    $vgoal = "";
    $hgoal = "";
    if ($team == 'H') {
        $hgoal = "checked='checked'";
    } elseif ($team == 'A') {
        $vgoal = "checked='checked'";
    }

    $html .= "<h3>" . _("New goal") . "</h3>";
    $html .= "<div id='radiot' name='radiot'>";
    $html .= "<fieldset data-role='controlgroup' id='teamselection'>";
    $html .= "<div class='control-option'>";
    $html .= "<input type='radio' name='team' id='hteam' value='H' $hgoal />";
    $html .= "<label for='hteam'>" . utf8entities($game_result['hometeamname']) . "</label>";
    $html .= "</div>";
    $html .= "<div class='control-option'>";
    $html .= "<input type='radio' name='team' id='ateam' value='A' $vgoal  />";
    $html .= "<label for='ateam'>" . utf8entities($game_result['visitorteamname']) . "</label>";
    $html .= "</div>";
    $html .= "</fieldset>";
    $html .= "</div>";

    $played_players = [];
    if ($team == 'H') {
        $played_players = $homePlayers;
    } elseif ($team == 'A') {
        $played_players = $awayPlayers;
    }

    // Hidden until the scoring team is picked, because the player lists are
    // empty until then and an empty dropdown confuses new scorekeepers. The
    // hint and the toggling are applied client side, so the page still works
    // without JavaScript.
    $html .= "<p id='teamfirsthint' class='goal-players-hint'>" . _("Select the team that scored.") . "</p>";
    $html .= "<div id='goalplayers'>";

    // A Callahan is picked in the assist dropdown but stored as assist -1, so
    // the marker has to be mapped back onto its option or the re-render below
    // silently drops it.
    $assistValue = ($uo_goal['iscallahan'] && (int) $uo_goal['assist'] === -1)
        ? "xx"
        : (string) $uo_goal['assist'];
    $scorerValue = (string) $uo_goal['scorer'];

    $html .= "<label for='pass' class='select'>" . _("Assist") . "</label>";
    $html .= "<select id='pass' name='pass' >";
    $html .= "<option value='0'" . ($assistValue === "0" ? " selected='selected'" : "") . ">-</option>";
    foreach ($played_players as $player) {
        $selected = ((string) $player['player_id'] === $assistValue) ? " selected='selected'" : "";
        $html .= "<option value='" . utf8entities($player['player_id']) . "'$selected>#" . $player['num'] . " " . utf8entities($player['firstname'] . " " . $player['lastname']) . "</option>";
    }
    $html .= "<option value='xx'" . ($assistValue === "xx" ? " selected='selected'" : "") . ">XX " . _("Callahan goal") . "</option>";
    $html .= "</select>";

    $html .= "<label for='goal' class='select'>" . _("Scorer") . "</label>";
    $html .= "<select id='goal' name='goal' >";
    $html .= "<option value='0'" . ($scorerValue === "0" ? " selected='selected'" : "") . ">-</option>";
    foreach ($played_players as $player) {
        $selected = ((string) $player['player_id'] === $scorerValue) ? " selected='selected'" : "";
        $html .= "<option value='" . utf8entities($player['player_id']) . "'$selected>#" . $player['num'] . " " . utf8entities($player['firstname'] . " " . $player['lastname']) . "</option>";
    }
    $html .= "</select>";
    $html .= "</div>";

    if (!$hideTimeOnScoresheet) {
        $html .= "<label for='timemm' class='select'>" . _("Goal time") . " " . _("min") . ":" . _("sec") . "</label>";
        $html .= "<div class='ui-grid-b'>";
        $html .= "<div class='ui-block-a'>\n";
        $html .= "<select id='timemm' name='timemm' >";
        for ($i = 0; $i <= 180; $i++) {
            if ((string) $i === (string) $timemm) {
                $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
            } else {
                $html .= "<option value='" . $i . "'>" . $i . "</option>";
            }
        }
        $html .= "</select>";
        $html .= "</div>";
        $html .= "<div class='ui-block-b'>\n";
        $html .= "<select id='timess' name='timess' >";
        for ($i = 0; $i <= 55; $i = $i + 5) {
            if ((string) $i === (string) $timess) {
                $html .= "<option value='" . $i . "' selected='selected'>" . $i . "</option>";
            } else {
                $html .= "<option value='" . $i . "'>" . $i . "</option>";
            }
        }
        $html .= "</select>";
        $html .= "</div>";
        $html .= "</div>";
    }

    if (empty($errors)) {
        $html .= "<input type='submit' name='add' data-ajax='false' value='" . _("Save goal") . "'/>";
    } else {
        $html .= $errors;
        $html .= _("Correct the errors or save the goal with errors.");
        $html .= "<input class='button' type='submit' name='forceadd' value='" . _("Save goal with errors") . "'/>";
        $html .= "<input class='button' type='submit' name='cancel' value='" . _("Cancel") . "'/>";
    }
}

$html .= "<h3>" . _("Additional game data") . "</h3>";
$html .= "<div class='action-row action-row--even'>\n";
if ($canShowTimedActions) {
    $html .= "<a href='?view=addtimeouts&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Timeouts") . "</a>";
    if (intval($seasoninfo['spiritmode']) > 0) {
        $html .= "<a href='?view=addspirittimeouts&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Spirit stoppages") . "</a>";
    }
    $html .= "<a href='?view=addhalftime&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Halftime") . "</a>";
    $html .= "<a class='score-cap-button' href='?view=addscorecap&amp;game=" . $gameId . "&amp;cap=half_cap&amp;time=" . $capEventTime . "' data-role='button' data-ajax='false'>" . _("Halftime cap") . "</a>";
    $html .= "<a class='score-cap-button' href='?view=addscorecap&amp;game=" . $gameId . "&amp;cap=time_cap&amp;time=" . $capEventTime . "' data-role='button' data-ajax='false'>" . _("Time cap") . "</a>";
}
$html .= "<a href='?view=addfirstoffence&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("First offence") . "</a>";
$html .= "<a href='?view=addofficial&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Scorekeeper(s)") . "</a>";
$html .= "<a href='?view=addcomment&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Game note") . "</a>";
$html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Roster") . "</a>";
$html .= "</div>\n";
if ($halfCapEvent || $timeCapEvent) {
    $html .= "<ul>\n";
    foreach ([$halfCapEvent, $timeCapEvent] as $capEvent) {
        if (!$capEvent) {
            continue;
        }
        $html .= "<li>" . GameCapEventText($capEvent, !$hideTimeOnScoresheet) . "</li>\n";
    }
    $html .= "</ul>\n";
}

if (!$useGameClock) {
    $html .= "<h3>" . _("Game has ended") . "</h3>";
    if ($lastscore) {
        $home = $lastscore['homescore'];
        $away = $lastscore['visitorscore'];
        $html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save final result") . " $home - $away'/>";
        $html .= "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Roster") . "</a>";
    }
}

$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
?>
<script type="text/javascript">
  var homeAssistList = <?php echo json_encode(ScorekeeperPlayerOptions($homePlayers, true)); ?>;

  var awayAssistList = <?php echo json_encode(ScorekeeperPlayerOptions($awayPlayers, true)); ?>;

  var homeScorerList = <?php echo json_encode(ScorekeeperPlayerOptions($homePlayers, false)); ?>;

  var awayScorerList = <?php echo json_encode(ScorekeeperPlayerOptions($awayPlayers, false)); ?>;

  function showPlayerSelects(visible) {
    var players = document.getElementById('goalplayers');
    var hint = document.getElementById('teamfirsthint');
    if (players) {
      players.style.display = visible ? '' : 'none';
    }
    if (hint) {
      hint.style.display = visible ? 'none' : '';
    }
  }

  function swapTeamLists(teamValue) {
    var passSelect = document.getElementById('pass');
    var goalSelect = document.getElementById('goal');
    if (!passSelect || !goalSelect) {
      return;
    }
    if (teamValue === "H") {
      passSelect.innerHTML = homeAssistList;
      goalSelect.innerHTML = homeScorerList;
    } else {
      passSelect.innerHTML = awayAssistList;
      goalSelect.innerHTML = awayScorerList;
    }
    showPlayerSelects(true);
  }

  function setGoalTimeFromClock() {
    var minuteSelect = document.getElementById('timemm');
    var secondSelect = document.getElementById('timess');
    if (!minuteSelect || !secondSelect || !window.scorekeeperClock || !window.scorekeeperClock.isActive()) {
      return;
    }

    var rounded = window.scorekeeperClock.roundedTime();
    minuteSelect.value = String(rounded.mm);
    secondSelect.value = String(rounded.ss);
  }

  var teamRadios = document.querySelectorAll('input[name="team"]');
  teamRadios.forEach(function(radio) {
    radio.addEventListener('change', function() {
      swapTeamLists(this.value);
      setGoalTimeFromClock();
    });
  });

  var checkedTeam = document.querySelector('input[name="team"]:checked');
  if (checkedTeam) {
    // Only reveal them. A team is already selected here on the error
    // re-render, and PHP has rendered that team's players with the submitted
    // assist and scorer marked; rebuilding the lists would drop that choice.
    showPlayerSelects(true);
  } else {
    showPlayerSelects(false);
  }

  // The cap pages stamp the time carried in the link, so refresh it from the
  // live clock at click time instead of the value rendered with the page.
  var capButtons = document.querySelectorAll('.score-cap-button');
  capButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      if (!window.scorekeeperClock || !window.scorekeeperClock.isActive()) {
        return;
      }

      var rounded = window.scorekeeperClock.roundedTime();
      this.href = this.href.replace(/([?&])time=\d+/, '$1time=' + ((rounded.mm * 60) + rounded.ss));
    });
  });

  var pauseButton = document.getElementById('pausegame');
  if (pauseButton) {
    pauseButton.addEventListener('click', function(event) {
      if (!confirm(<?php echo json_encode(_("Pause the game clock? Use this only for exceptional stoppages.")); ?>)) {
        event.preventDefault();
      }
    });
  }

  var startButton = document.getElementById('startgame');
  if (startButton && startButton.value !== <?php echo json_encode(_("Start game clock")); ?>) {
    startButton.addEventListener('click', function(event) {
      if (!confirm(<?php echo json_encode(_("Restart the game clock from 00:00?")); ?>)) {
        event.preventDefault();
      }
    });
  }
</script>
