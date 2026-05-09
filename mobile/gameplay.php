<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
$html = "";

$gameId = intval(iget("game"));
$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
$goals = GameGoals($gameId);
$gameevents = GameEvents($gameId);

mobilePageTop(_("Gameplay"));

$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";
$html .= "<b>" . utf8entities($game_result['hometeamname']);
$html .= " - ";
$html .= utf8entities($game_result['visitorteamname']);
$html .= " " . intval($game_result['homescore']) . " - " . intval($game_result['visitorscore']) . "</b>";
$html .= "</td></tr><tr><td>\n";
if (count($goals) <= 0) {
    $html .= _("No scores entered");
    $html .= "</td></tr><tr><td>\n";
    $html .=  "<a href='?view=mobile/addplayerlists&amp;game=" . $gameId . "&amp;team=" . $game_result['hometeam'] . "'>" . _("Fill in scoresheet") . "</a>";
} else {
    $html .= "<a href='?view=mobile/scoreboard&amp;game=$gameId&amp;team=" . $game_result['hometeam'] . "'>" . _("home team") . "</a> | ";
    $html .= "<a href='?view=mobile/scoreboard&amp;game=$gameId&amp;team=" . $game_result['visitorteam'] . "'>" . _("away team") . "</a>";

    $prevgoal = 0;
    foreach ($goals as $goal) {

        if ((intval($game_result['halftime']) >= $prevgoal) &&
            (intval($game_result['halftime']) < intval($goal['time']))
        ) {
            $html .= "</td></tr><tr><td>\n";
            $html .= _("Halftime");
        }

        if (intval($goal['ishomegoal']) == 1) {
            $style = "class='homefontcolor'";
        } else {
            $style = "class='guestfontcolor'";
        }

        $html .= "</td></tr><tr><td $style>\n";

        if (count($gameevents)) {
            foreach ($gameevents as $event) {
                if ((intval($event['time']) >= $prevgoal) &&
                    (intval($event['time']) < intval($goal['time']))
                ) {
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
                        $style = "class='homefontcolor'";
                    } else {
                        $team = utf8entities($game_result['visitorteamname']);
                        $style = "class='guestfontcolor'";
                    }

                    if (!$hideTimeOnScoresheet) {
                        $html .= SecToMin($event['time']) . " ";
                    }
                    $html .= $team . " " . $gameevent;
                    $html .= "</td></tr><tr><td  $style>\n";
                }
            }
        }

        if (!$hideTimeOnScoresheet) {
            $html .= SecToMin($goal['time']) . " ";
        }
        $html .= $goal['homescore'] . " - " . $goal['visitorscore'] . " ";
        if (intval($goal['iscallahan'])) {
            $html .= _("Callahan goal") . "&nbsp;";
        } else {
            $html .= utf8entities($goal['assistfirstname']) . " " . utf8entities($goal['assistlastname']) . " --> ";
        }
        $html .= utf8entities($goal['scorerfirstname']) . " " . utf8entities($goal['scorerlastname']) . "&nbsp;";

        $prevgoal = intval($goal['time']);
    }

    $html .= "</td></tr><tr><td>\n";
    $html .= _("Game official") . ": " . utf8entities($game_result['official']);
}
$html .= "</td></tr><tr><td>\n";
$html .= "<a href='?view=mobile/respgames'>" . _("Back to game responsibilities") . "</a>";
$html .= "</td></tr><tr><td>\n";
$html .=  "<a href='?view=gameplay&amp;game=" . $gameId . "'>" . _("Desktop gameplay") . "</a>";
$html .= "</td></tr>\n";
$html .= "</table>\n";

echo $html;

pageEnd();
