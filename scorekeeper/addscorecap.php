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
$existingEvent = GameCapEvent($gameId, $capType);
$currentScore = max((int) $gameResult['homescore'], (int) $gameResult['visitorscore']);
$target = $existingEvent ? (int) $existingEvent['info'] : $currentScore + 1;

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
    $eventTime = isset($_POST['eventtime']) ? max(0, (int) $_POST['eventtime']) : $eventTime;

    if ($target <= $currentScore) {
        $error = _("The cap target must be greater than the current score.");
    } elseif ($target > 255) {
        $error = _("The cap target must be 255 or less.");
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
$html .= "<p>" . _("Cap time") . ": " . SecToMin($eventTime) . "</p>\n";
if ($error !== "") {
    $html .= "<p class='warning'>" . $error . "</p>\n";
}
$html .= "<label for='target'>" . _("Cap target") . "</label>\n";
$html .= "<input type='number' id='target' name='target' min='" . ($currentScore + 1) . "' max='255' value='" . $target . "' required='required'/>\n";
$html .= "<input type='hidden' name='eventtime' value='" . $eventTime . "'/>\n";
$html .= "<input type='submit' name='save' data-ajax='false' value='" . _("Save") . "'/>\n";
if ($existingEvent) {
    $html .= "<input type='submit' name='remove' data-ajax='false' value='" . _("Remove cap") . "'/>\n";
}
$html .= "<a class='back-score-button' href='?view=addscoresheet&amp;game=" . $gameId . "' data-role='button' data-ajax='false'>" . _("Back to scoresheet") . "</a>\n";
$html .= "</form>\n";
$html .= "</div><!-- /content -->\n\n";

echo $html;
