<?php

include_once __DIR__ . '/auth.php';
$html = "";

$gameId = scorekeeperRequestGameId();
$_SESSION['game'] = $gameId;

$game_result = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
$useGameClock = !$hideTimeOnScoresheet && !scorekeeperHasManualNoGameClock($gameId);
$timerState = $useGameClock ? GameTimerState($gameId) : ScorekeeperTimerStateDefaults();
$showClock = $useGameClock && ($timerState['ongoing'] || $timerState['mm'] > 0 || $timerState['ss'] > 0);
$timemm = "";
$timess = "";

if (isset($_POST['save'])) {
    $timemm = "0";
    $timess = "0";

    if (!empty($_POST['timemm'])) {
        $timemm = intval($_POST['timemm']);
    }
    if (!empty($_POST['timess'])) {
        $timess = intval($_POST['timess']);
    }
    $htime = TimeToSec($timemm . "." . $timess);
    GameSetHalftime($gameId, $htime);

    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

if (!empty($game_result['halftime'])) {
    $time = explode(".", SecToMin($game_result['halftime']));
    $timemm = $time[0];
    $timess = $time[1];
} elseif ($useGameClock && $timerState['started']) {
    $timemm = $timerState['mm'];
    $timess = $timerState['rss'];
}

$html .= "<div data-role='header'>\n";
if ($showClock) {
    $html .= ScorekeeperClockHeader($timerState);
}
$html .= "<h1>" . _("Halftime ends") . ": " . utf8entities($game_result['hometeamname']) . " - " . utf8entities($game_result['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addhalftime&amp;game=" . $gameId . "' method='post' data-ajax='false'>\n";
$html .= "<label for='timemm' class='select'>" . _("Halftime ends at") . " " . _("min") . ":" . _("sec") . "</label>";
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

$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>";
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
if ($showClock) {
    echo ScorekeeperClockScript($timerState);
}
