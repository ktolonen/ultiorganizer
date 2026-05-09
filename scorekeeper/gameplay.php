<?php

include_once __DIR__ . '/auth.php';
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
$goals = GameGoals($gameId);
$gameevents = GameEvents($gameId);

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Gameplay") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";

$html .= "<table class='gameplay-table'>\n";
$html .= "<tr><td>\n";
$html .= "<b>" . utf8entities($game_result['hometeamname']);
$html .= " - ";
$html .= utf8entities($game_result['visitorteamname']);
$html .= " " . intval($game_result['homescore']) . " - " . intval($game_result['visitorscore']) . "</b>";
$html .= "</td></tr><tr><td>\n";
if (count($goals) <= 0) {
    $html .= _("No scores entered");
    $html .= "</td></tr><tr><td>\n";
    $html .=  "<a href='?view=addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "'>" . _("Fill in scoresheet") . "</a>";
} else {
    $prevgoal = 0;
    foreach ($goals as $goal) {

        if ((intval($game_result['halftime']) >= $prevgoal) &&
            (intval($game_result['halftime']) < intval($goal['time']))
        ) {
            $html .= "<tr class='gameplay-row gameplay-row--halftime'><td>";
            $html .= _("Halftime");
            $html .= "</td></tr>\n";
        }
        if (count($gameevents)) {
            foreach ($gameevents as $event) {
                if ((intval($event['time']) >= $prevgoal) &&
                    (intval($event['time']) < intval($goal['time']))
                ) {
                    $gameevent = '';
                    if ($event['type'] == "timeout") {
                        $gameevent = _("timeout");
                    } elseif ($event['type'] == "spirit_timeout") {
                        $gameevent = _("Spirit stoppage");
                    } elseif ($event['type'] == "turnover") {
                        $gameevent = _("turnover");
                    } elseif ($event['type'] == "offence") {
                        $gameevent = _("offence");
                    }

                    if (intval($event['ishome']) > 0) {
                        $team = utf8entities($game_result['hometeamname']);
                        $rowClass = "gameplay-row gameplay-row--event gameplay-row--home";
                    } else {
                        $team = utf8entities($game_result['visitorteamname']);
                        $rowClass = "gameplay-row gameplay-row--event gameplay-row--away";
                    }

                    $html .= "<tr class='" . $rowClass . "'><td>\n";
                    if (!$hideTimeOnScoresheet) {
                        $html .= SecToMin($event['time']) . " ";
                    }
                    $html .= $team . " " . $gameevent;
                    $html .= "</td></tr>\n";
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

    $html .= "</td></tr><tr><td>\n";
    $html .= _("Game official") . ": " . utf8entities($game_result['official']);
}
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "<div class='action-row action-row--half'>\n";
$html .= "<a href='?view=scoreboard&amp;game=$gameId&amp;team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['hometeamname']) . " " . _("Scoreboard") . "</a>";
$html .= "<a href='?view=scoreboard&amp;game=$gameId&amp;team=" . $game_result['visitorteam'] . "' data-role='button' data-ajax='false'>" . utf8entities($game_result['visitorteamname']) . " " . _("Scoreboard") . "</a>";
$html .= "</div>\n";
$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
