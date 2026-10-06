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

if ($useGameClock && !$isFinal) {
    $html .= "<form action='?view=addresult&amp;game=" . $gameId . "' method='post' data-ajax='false'>\n";
    $html .= ScorekeeperClockControls($timerState, "", intval($result['homescore']) === 0 && intval($result['visitorscore']) === 0);
    $html .= "</form>\n";
}

$html .= "<form action='?view=addresult&amp;game=" . $gameId . "' method='post' data-ajax='false'>\n";

$html .= "<label for='home'>" . utf8entities($result['hometeamname']) . ":</label>";

$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";
$html .= "<input type='number' inputmode='numeric' id='home' name='home' value='" . intval($result['homescore']) . "' min='0' maxlength='4' size='5'/>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
if (!$isFinal) {
    $html .= "<input type='submit' form='scoretaps' name='homeplus' data-role='button' data-icon='plus' value='+1'/>";
}
$html .= "</div>";
$html .= "<div class='ui-block-c'>\n";
if (!$isFinal) {
    $html .= "<input type='submit' form='scoretaps' name='homeminus' data-role='button' data-icon='minus' value='-1'/>";
}
$html .= "</div>";
$html .= "</div>";

$html .= "<label for='away'>" . utf8entities($result['visitorteamname']) . ":</label>";
$html .= "<div class='ui-grid-b'>";
$html .= "<div class='ui-block-a'>\n";
$html .= "<input type='number' inputmode='numeric' id='away' name='away' value='" . intval($result['visitorscore']) . "' min='0' maxlength='4' size='5'/>";
$html .= "</div>";
$html .= "<div class='ui-block-b'>\n";
if (!$isFinal) {
    $html .= "<input type='submit' form='scoretaps' name='awayplus' data-role='button' data-icon='plus' value='+1'/>";
}
$html .= "</div>";
$html .= "<div class='ui-block-c'>\n";
if (!$isFinal) {
    $html .= "<input type='submit' form='scoretaps' name='awayminus' data-role='button' data-icon='minus' value='-1'/>";
}
$html .= "</div>";
$html .= "</div>";

$html .= $info;

if ($saveSucceeded) {
    $html .= "<input type='submit' name='save'  data-ajax='false' value='" . _("Save again") . "'/>";
    $html .= "<a href='?view=addplayerlists&game=" . $gameId . "&team=" . $game_result['hometeam'] . "' data-role='button' data-ajax='false'>" . _("Set rosters") . "</a>";
} else {
    $html .= "<div class='action-row action-row--stacked action-row--spaced'>\n";
    $html .= "<input type='submit' name='update' data-ajax='false' value='" . _("Game ongoing, update scores") . "'/>";
    $html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save as final result") . "'/>";
    $html .= "</div>\n";
}
$html .= "<a class='back-resp-button' href='?view=respgames' data-role='button' data-ajax='false'>" . _("Back to game responsibilities") . "</a>";
$html .= "</form>";
// The tap buttons belong to this form through their form attribute, so Enter
// in a score field still submits the main form's update.
$html .= "<form id='scoretaps' action='?view=addresult&amp;game=" . $gameId . "' method='post' data-ajax='false'></form>\n";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
if ($useGameClock && !$isFinal) {
    echo ScorekeeperClockControlScript();
}
