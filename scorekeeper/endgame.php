<?php

include_once __DIR__ . '/auth.php';

$html = "";
$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
$useGameClock = !$hideTimeOnScoresheet && !scorekeeperHasManualNoGameClock($gameId);
$goalRows = GameGoals($gameId);
$gameevents = GameEvents($gameId);

/**
 * One game event as a table row. Shared by the per-goal pass and the trailing
 * pass, so an event recorded after the last goal is rendered the same way.
 */
$renderGameEventRow = function ($event) use ($game_result, $hideTimeOnScoresheet) {
    if ($event['type'] == "timeout") {
        $gameevent = _("timeout");
    } elseif ($event['type'] == "spirit_timeout") {
        $gameevent = _("Spirit stoppage");
    } elseif ($event['type'] == "turnover") {
        $gameevent = _("turnover");
    } elseif ($event['type'] == "offence") {
        $gameevent = _("offence");
    } elseif (GameIsCapEventType($event['type'])) {
        // This row prints the time in front, so the cap text omits its own.
        $gameevent = GameCapEventText($event, false);
    } else {
        $gameevent = $event['type'];
    }

    if (GameIsCapEventType($event['type'])) {
        $team = "";
        $rowClass = "gameplay-row gameplay-row--event";
    } elseif (intval($event['ishome']) > 0) {
        $team = utf8entities($game_result['hometeamname']);
        $rowClass = "gameplay-row gameplay-row--event gameplay-row--home";
    } else {
        $team = utf8entities($game_result['visitorteamname']);
        $rowClass = "gameplay-row gameplay-row--event gameplay-row--away";
    }

    $row = "<tr class='" . $rowClass . "'><td>\n";
    if (!$hideTimeOnScoresheet) {
        $row .= SecToMin($event['time']) . " ";
    }
    $row .= trim($team . " " . $gameevent);
    $row .= "</td></tr>\n";

    return $row;
};
$timerState = $useGameClock ? GameTimerState($gameId) : ScorekeeperTimerStateDefaults();
$showClock = $useGameClock && ($timerState['ongoing'] || $timerState['mm'] > 0 || $timerState['ss'] > 0);

$home = 0;
$away = 0;
if (count($goalRows)) {
    $lastscore = $goalRows[count($goalRows) - 1];
    $home = intval($lastscore['homescore']);
    $away = intval($lastscore['visitorscore']);
} else {
    $home = intval($game_result['homescore']);
    $away = intval($game_result['visitorscore']);
}

if (isset($_POST['confirm'])) {
    GameSetResult($gameId, $home, $away);
    header("location:?view=gameplay&game=" . $gameId);
    exit;
}

$html .= "<div data-role='header'>\n";
if ($showClock) {
    $html .= ScorekeeperClockHeader($timerState);
}
$html .= "<h1>" . _("End game") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<h3>" . _("Final result") . "</h3>";
$html .= "<p><strong>" . utf8entities($game_result['hometeamname']) . " " . $home . " - " . $away . " " . utf8entities($game_result['visitorteamname']) . "</strong></p>";
$html .= "<form action='?view=endgame&amp;game=" . $gameId . "' method='post' data-ajax='false'>\n";
$html .= "<input type='submit' name='confirm' data-ajax='false' value='" . _("Confirm result and end game") . "'/>";
$html .= "<a href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Cancel and return to scoresheet") . "</a>";
$html .= "</form>";

$html .= "<h3>" . _("Gameplay summary") . "</h3>";
$html .= "<table class='gameplay-table'>\n";
$html .= "<tr><td>\n";
$html .= "<b>" . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . " " . $home . " - " . $away . "</b>";
$html .= "</td></tr>\n";
$prevgoal = 0;
if (!count($goalRows)) {
    $html .= "<tr><td>" . _("No scores entered") . "</td></tr>\n";
} else {
    foreach ($goalRows as $goal) {
        if ((intval($game_result['halftime']) >= $prevgoal) && (intval($game_result['halftime']) < intval($goal['time']))) {
            $html .= "<tr class='gameplay-row gameplay-row--halftime'><td>";
            $html .= _("Halftime");
            $html .= "</td></tr>\n";
        }
        if (count($gameevents)) {
            foreach ($gameevents as $event) {
                if ((intval($event['time']) >= $prevgoal) && (intval($event['time']) < intval($goal['time']))) {
                    $html .= $renderGameEventRow($event);
                }
            }
        }

        if (intval($goal['ishomegoal']) == 1) {
            $rowClass = "gameplay-row gameplay-row--goal gameplay-row--home";
        } else {
            $rowClass = "gameplay-row gameplay-row--goal gameplay-row--away";
        }

        $html .= "<tr class='" . $rowClass . "'><td>\n";
        if (!$hideTimeOnScoresheet) {
            $html .= SecToMin($goal['time']) . " ";
        }
        $html .= $goal['homescore'] . " - " . $goal['visitorscore'] . " ";
        $goalText = GoalDisplayText($goal, $gameId);
        if ($goalText !== '') {
            $html .= utf8entities($goalText) . "&nbsp;";
        }
        $html .= "</td></tr>\n";

        $prevgoal = intval($goal['time']);
    }
}
// Game events after the last goal, or every event when no goal has been recorded.
foreach ($gameevents as $event) {
    if (intval($event['time']) >= $prevgoal) {
        $html .= $renderGameEventRow($event);
    }
}
$html .= "</table>\n";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
