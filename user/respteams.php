<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/series.functions.php';

$title = _("Team responsibilities");
$html = "";

if (isset($_GET['season'])) {
	$season = $_GET['season'];
} else {
	$season = CurrentSeason();
}
$seasoninfo = SeasonInfo($season);

$html .= "<h1>" . _("Team responsibilities") . "</h1>";
$html .= "<table width='500px'>";
$html .= "<tr>";
$html .= "<th>" . _("Team") . "</th>";
$html .= "<th>" . _("Division") . "</th>";
$html .= "<th>" . _("Roster") . "</th>";
$html .= "<th>" . _("Team profile") . "</th>";
if (!intval($seasoninfo['isnationalteams'])) {
	$html .= "<th>" . _("Club profile") . "</th>";
}
$html .= "</tr>";

	if (isset($_SESSION['userproperties']['userrole']['teamadmin'])) {
		foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $team => $param) {
			$teaminfo = TeamInfo($team);
			if (!$teaminfo || !isset($teaminfo['season'])) {
				continue;
			}
			if ($teaminfo['season'] == $seasoninfo['season_id']) {

			$html .= "<tr>";
			$html .= "<td>" . utf8entities($teaminfo['name']) . "</td>";
			$html .= "<td>" . utf8entities($teaminfo['seriesname']) . "</td>";

			$html .= "<td><a href='?view=user/teamplayers&amp;team=$team'>" . _("Players") . "</a></td>";
			$html .= "<td><a href='?view=user/teamprofile&amp;team=$team'>" . _("Team card") . "</a></td>";
			if (!intval($seasoninfo['isnationalteams'])) {
				$html .= "<td><a href='?view=user/clubprofile&amp;team=$team'>" . _("Club card") . "</a></td>";
			}
			$html .= "</tr>";
		}
	}
}
$html .= "</table>";

showPage($title, $html);
