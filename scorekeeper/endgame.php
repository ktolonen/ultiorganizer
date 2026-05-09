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
$timerState = $useGameClock ? GameTimerState($gameId) : [
    "started" => false,
    "ongoing" => false,
    "paused" => false,
    "mm" => 0,
    "ss" => 0,
    "rss" => 0,
];
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
    $html .= "<span id='gametime' style='float: left; margin: 0.2em 1.1em 0.25em 0.5ex; padding: 0.15em 0.4em; border-radius: 0.35em; background: #e6eef2; line-height: 1.3; font-size: 1.8em;'>" . sprintf("%02d", $timerState['mm']) . ":" . sprintf("%02d", $timerState['ss']) . "</span>";
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
$html .= "</td></tr><tr><td>\n";
if (!count($goalRows)) {
    $html .= _("No scores entered");
} else {
    $prevgoal = 0;
    foreach ($goalRows as $goal) {
        if ((intval($game_result['halftime']) >= $prevgoal) && (intval($game_result['halftime']) < intval($goal['time']))) {
            $html .= "<tr class='gameplay-row gameplay-row--halftime'><td>";
            $html .= _("Halftime");
            $html .= "</td></tr>\n";
        }
        if (count($gameevents)) {
            foreach ($gameevents as $event) {
                if ((intval($event['time']) >= $prevgoal) && (intval($event['time']) < intval($goal['time']))) {
                    if ($event['type'] == "timeout") {
                        $gameevent = _("timeout");
                    } elseif ($event['type'] == "spirit_timeout") {
                        $gameevent = _("Spirit stoppage");
                    } elseif ($event['type'] == "turnover") {
                        $gameevent = _("turnover");
                    } elseif ($event['type'] == "offence") {
                        $gameevent = _("offence");
                    } else {
                        $gameevent = $event['type'];
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
}
$html .= "</td></tr>\n";
$html .= "</table>\n";
$html .= "</div><!-- /content -->\n\n";

echo $html;
?>
<script type="text/javascript">
<?php if ($showClock) { ?>
  (function() {
    var clock = document.getElementById('gametime');
    var pausedSuffix = <?php echo json_encode(" (" . _("Paused") . ")"); ?>;
    var minutes = <?php echo (int) $timerState['mm']; ?>;
    var seconds = <?php echo (int) $timerState['ss']; ?>;

    function renderClock(paused) {
      if (!clock) {
        return;
      }
      var text = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
      if (paused) {
        text += pausedSuffix;
      }
      clock.textContent = text;
    }

    renderClock(<?php echo $timerState['paused'] ? 'true' : 'false'; ?>);

<?php if ($timerState['ongoing'] && !$timerState['paused']) { ?>
    window.setInterval(function() {
      seconds++;
      if (seconds > 59) {
        minutes++;
        seconds = 0;
      }
      renderClock(false);
    }, 1000);
<?php } ?>
  })();
<?php } ?>
</script>
