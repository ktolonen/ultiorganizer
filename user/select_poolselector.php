<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/search.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
$title = _("Choose pools to show");
$html = "";
$selectorType = isset($_GET['selectortype']) ? $_GET['selectortype'] : '';

if (!empty($_GET['user'])) {
    $target = "view=user/userinfo&amp;user=" . urlencode($_GET['user']);
} else {
    $target = "view=user/userinfo";
}


$html .= "<h2>" . $title . "</h2>";
if ($selectorType == 'currentseason') {
    $html .= "<h3>" . _("Current event") . " (" . utf8entities(CurrentSeasonName()) . ")</h3>\n";
    $html .= "<form method='post' action='?" . $target . "'>\n";
    $html .= "<div><input type='hidden' name='selectortype' value='currentseason'/>\n";
    $html .= "<input class='button' type='submit' name='selectpoolselector' value='" . _("Select") . "'/></div>\n";
    $html .= "</form>\n";
} elseif ($selectorType == 'team') {
    $html .= "<h3>" . _("Team pools") . "</h3>";
    $html .= SearchTeam($target, ['selectortype' => 'team'], ['selectpoolselector' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($selectorType == 'season') {
    $html .= "<h3>" . _("Event") . "</h3>";
    $html .= SearchSeason($target, ['selectortype' => 'season'], ['selectpoolselector' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($selectorType == 'series') {
    $html .= "<h3>" . _("Division") . "</h3>\n";
    $html .= SearchSeries('view=user/userinfo', ['selectortype' => 'series'], ['selectpoolselector' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($selectorType == 'pool') {
    $html .= "<h3>" . _("Division") . "</h3>";
    $html .= SearchPool($target, ['selectortype' => 'pool'], ['selectpoolselector' => _("Select"), 'cancel' => _("Cancel")]);
} else {
    $html .= "<p class='warning'>" . _("No pool selector type was chosen.") . "</p>";
}

showPage($title, $html);
