<?php
include_once $include_prefix . 'lib/search.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
$title = _("Choose pools to show");
$html = "";

if (!empty($_GET['user'])) {
	$target = "view=user/userinfo&amp;user=" . urlencode($_GET['user']);
} else {
	$target = "view=user/userinfo";
}


$html .= "<h2>" . $title . "</h2>";
if ($_GET['selectortype'] == 'currentseason') {
	$html .= "<h3>" . _("Current event") . " (" . utf8entities(CurrentSeasonName()) . ")</h3>\n";
	$html .= "<form method='post' action='?" . $target . "'>\n";
	$html .= "<div><input type='hidden' name='selectortype' value='currentseason'/>\n";
	$html .= "<input class='button' type='submit' name='selectpoolselector' value='" . _("Select") . "'/></div>\n";
	$html .= "</form>\n";
} elseif ($_GET['selectortype'] == 'team') {
	$html .= "<h3>" . _("Team pools") . "</h3>";
	$html .= SearchTeam($target, array('selectortype' => 'team'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'season') {
	$html .= "<h3>" . _("Event") . "</h3>";
	$html .= SearchSeason($target, array('selectortype' => 'season'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'series') {
	$html .= "<h3>" . _("Division") . "</h3>\n";
	$html .= SearchSeries('view=user/userinfo', array('selectortype' => 'series'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'pool') {
	$html .= "<h3>" . _("Division") . "</h3>";
	$html .= SearchPool($target, array('selectortype' => 'pool'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
}

showPage($title, $html);
