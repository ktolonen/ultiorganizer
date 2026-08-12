<?php

include_once __DIR__ . '/auth.php';

$html = "";
$error = "";
$gameId = scorekeeperRequestGameId();
$_SESSION['game'] = $gameId;

$capType = iget("cap");
if (!GameIsCapEventType($capType)) {
    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

$gameResult = GameResult($gameId);
$seasoninfo = SeasonInfo(GameSeason($gameId));
$hideTimeOnScoresheet = !empty($seasoninfo['hide_time_on_scoresheet']);
$existingEvent = GameCapEvent($gameId, $capType);
$currentScore = max((int) $gameResult['homescore'], (int) $gameResult['visitorscore']);
$target = $existingEvent ? (int) $existingEvent['info'] : $currentScore + 1;

// Correcting a recorded cap must stay possible after the score has reached the
// target, which is the normal end state of a capped game. Only a brand new cap
// has to name a target the teams can still play to.
$minTarget = $existingEvent ? 1 : $currentScore + 1;

if ($existingEvent) {
    $eventTime = (int) $existingEvent['time'];
} elseif (isset($_GET['time'])) {
    $eventTime = max(0, (int) $_GET['time']);
} else {
    $timerState = GameTimerState($gameId);
    $eventTime = ((int) $timerState['mm'] * 60) + (int) $timerState['rss'];
}

if (isset($_POST['save'])) {
    $target = isset($_POST['target']) ? (int) $_POST['target'] : 0;
    if (!$hideTimeOnScoresheet) {
        $timemm = !empty($_POST['timemm']) ? intval($_POST['timemm']) : 0;
        $timess = !empty($_POST['timess']) ? intval($_POST['timess']) : 0;
        $eventTime = TimeToSec($timemm . "." . $timess);
    }

    if ($target > 255) {
        $error = _("The new point cap must be 255 or less.");
    } elseif ($target < 1 || (!$existingEvent && $target <= $currentScore)) {
        $error = _("The new point cap must be greater than the current score.");
    } else {
        GameSetCapEvent($gameId, $capType, $eventTime, $target);
        header("location:?view=addscoresheet&game=" . $gameId);
        exit;
    }
}

if (isset($_POST['remove'])) {
    GameRemoveCapEvent($gameId, $capType);
    header("location:?view=addscoresheet&game=" . $gameId);
    exit;
}

$capName = GameCapEventName($capType);

$html .= "<div data-role='header'>\n";
$html .= "<h1>" . $capName . ": " . utf8entities($gameResult['hometeamname']) . " - " . utf8entities($gameResult['visitorteamname']) . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";

$html .= "<div data-role='content'>\n";
$html .= "<form action='?view=addscorecap&amp;game=" . $gameId . "&amp;cap=" . $capType . "' method='post' data-ajax='false'>\n";
$html .= "<p>" . _("Current score") . ": " . (int) $gameResult['homescore'] . " - " . (int) $gameResult['visitorscore'] . "</p>\n";
if ($error !== "") {
    $html .= "<p class='warning'>" . $error . "</p>\n";
}
if (!$hideTimeOnScoresheet) {
    $capmm = intval($eventTime / 60);
    $capss = intval($eventTime % 60);
    $capss = intval(round($capss / 5) * 5);
    if ($capss === 60) {
        $capmm++;
        $capss = 0;
    }

    $html .= "<label for='timemm' class='select'>" . sprintf(_("%s called at"), $capName) . " " . _("min") . ":" . _("sec") . "</label>";
    $html .= "<div class='ui-grid-b'>";
    $html .= "<div class='ui-block-a'>\n";
    $html .= "<select id='timemm' name='timemm' >";
    for ($i = 0; $i <= 180; $i++) {
        $selected = $i === $capmm ? " selected='selected'" : "";
        $html .= "<option value='" . $i . "'" . $selected . ">" . $i . "</option>";
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "<div class='ui-block-b'>\n";
    $html .= "<select id='timess' name='timess' >";
    for ($i = 0; $i <= 55; $i = $i + 5) {
        $selected = $i === $capss ? " selected='selected'" : "";
        $html .= "<option value='" . $i . "'" . $selected . ">" . $i . "</option>";
    }
    $html .= "</select>";
    $html .= "</div>";
    $html .= "</div>";
}
$html .= "<label for='target'>" . _("New point cap") . "</label>\n";
$html .= "<input type='number' id='target' name='target' min='" . $minTarget . "' max='255' value='" . $target . "' required='required'/>\n";
$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>\n";
if ($existingEvent) {
    $html .= "<input type='submit' name='remove' formnovalidate='formnovalidate' data-ajax='false' value='" . _("Remove cap") . "'/>\n";
}
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>\n";
$html .= "</form>\n";
$html .= "</div><!-- /content -->\n\n";

echo $html;
